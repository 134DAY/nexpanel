<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class ServerMetricsService
{
    public function getAll(): array
    {
        return [
            'cpu'      => $this->getCpuUsage(),
            'ram'      => $this->getMemoryUsage(),
            'disk'     => $this->getDiskUsage(),
            'uptime'   => $this->getUptime(),
            'hostname' => trim(Process::run('hostname')->output()),
            'os'       => trim(Process::run('lsb_release -ds 2>/dev/null || cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d \'"\' | head -1')->output()) ?: 'Linux',
            'services' => $this->getServiceStatuses(),
        ];
    }

    public function getCpuUsage(): float
    {
        $stat1 = $this->readProcStat();
        usleep(500000);
        $stat2 = $this->readProcStat();
        $diff = [];
        foreach ($stat1 as $k => $v) { $diff[$k] = $stat2[$k] - $v; }
        $total = array_sum($diff);
        $idle  = $diff['idle'] + ($diff['iowait'] ?? 0);
        return $total === 0 ? 0.0 : round((($total - $idle) / $total) * 100, 1);
    }

    public function getMemoryUsage(): array
    {
        $lines = explode("\n", Process::run('cat /proc/meminfo')->output());
        $mem = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) $mem[$m[1]] = (int) $m[2];
        }
        $total = $mem['MemTotal'] ?? 0;
        $available = $mem['MemAvailable'] ?? 0;
        $used = $total - $available;
        return [
            'total' => round($total / 1024), 'used' => round($used / 1024),
            'free' => round($available / 1024),
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    public function getDiskUsage(string $path = '/'): array
    {
        $parts = preg_split('/\s+/', trim(Process::run("df -B1 {$path} | tail -1")->output()));
        return [
            'total' => round(((int)($parts[1] ?? 0)) / (1024**3), 1),
            'used' => round(((int)($parts[2] ?? 0)) / (1024**3), 1),
            'percent' => (float) trim($parts[4] ?? '0%', '%'),
        ];
    }

    public function getUptime(): string
    {
        $seconds = (int) explode(' ', trim(Process::run('cat /proc/uptime')->output()))[0];
        $d = floor($seconds / 86400); $h = floor(($seconds % 86400) / 3600); $m = floor(($seconds % 3600) / 60);
        if ($d > 0) return "{$d}d {$h}h {$m}m";
        if ($h > 0) return "{$h}h {$m}m";
        return "{$m}m";
    }

    public function getServiceStatuses(): array
    {
        return [
            'nginx' => $this->checkService('nginx'),
            'mysql' => $this->checkService('mysql') ?: $this->checkService('mariadb'),
            'phpfpm' => $this->checkPhpFpm(),
        ];
    }

    private function readProcStat(): array
    {
        $line = explode(' ', trim(file('/proc/stat')[0]));
        $parts = array_values(array_filter(array_slice($line, 1), fn($v) => $v !== ''));
        return ['user'=>(int)($parts[0]??0),'nice'=>(int)($parts[1]??0),'system'=>(int)($parts[2]??0),'idle'=>(int)($parts[3]??0),'iowait'=>(int)($parts[4]??0),'irq'=>(int)($parts[5]??0),'softirq'=>(int)($parts[6]??0)];
    }

    private function checkService(string $s): string
    {
        return trim(Process::run("systemctl is-active {$s} 2>/dev/null")->output()) === 'active' ? 'active' : 'inactive';
    }

    private function checkPhpFpm(): string
    {
        foreach (['php8.3-fpm','php8.2-fpm','php8.1-fpm','php-fpm'] as $n) {
            if (trim(Process::run("systemctl is-active {$n} 2>/dev/null")->output()) === 'active') return 'active';
        }
        return 'inactive';
    }
}
