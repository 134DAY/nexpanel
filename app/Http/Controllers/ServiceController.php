<?php

namespace App\Http\Controllers;

use App\Services\AI\Actions\SafetyGuard;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class ServiceController extends Controller
{
    /** Short timeout so a misbehaving command can never freeze the panel. */
    private const STATUS_TIMEOUT = 5;
    private const ACTION_TIMEOUT = 20;

    private function getServices(): array
    {
        return [
            ['id' => 'nginx',      'name' => 'Nginx',       'description' => 'Web server & reverse proxy',           'icon' => 'globe',    'port' => 80],
            ['id' => 'mysql',      'name' => 'MySQL',       'description' => 'Database server',                       'icon' => 'database', 'port' => 3306],
            ['id' => 'php-fpm',    'name' => 'PHP-FPM',     'description' => 'PHP FastCGI Process Manager',           'icon' => 'code',     'port' => 9000],
            ['id' => 'redis',      'name' => 'Redis',       'description' => 'In-memory cache & session store',       'icon' => 'zap',      'port' => 6379],
            ['id' => 'supervisor', 'name' => 'Supervisor',  'description' => 'Process manager for queues & daemons',  'icon' => 'activity', 'port' => null],
            ['id' => 'ufw',        'name' => 'UFW Firewall', 'description' => 'Uncomplicated Firewall',               'icon' => 'shield',   'port' => null],
        ];
    }

    private function getServiceStatus(string $serviceId): array
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! $this->systemctlAvailable()) {
            return $this->getMockStatus($serviceId);
        }

        $unit = $this->resolveServiceName($serviceId);
        $status  = $this->ctl(['is-active', $unit]);
        $enabled = $this->ctl(['is-enabled', $unit]);

        // Unit not present at all → report as not installed (still fast).
        if ($status === '' && $enabled === '') {
            return ['status' => 'stopped', 'enabled' => false, 'uptime' => 'N/A', 'memory' => 'N/A', 'pid' => null, 'is_real' => true];
        }

        $running = $status === 'active';
        $uptime = 'N/A';
        $memory = 'N/A';
        $pid = null;

        if ($running) {
            $ts  = $this->ctl(['show', $unit, '--property=ActiveEnterTimestamp', '--value']);
            $pidRaw = $this->ctl(['show', $unit, '--property=MainPID', '--value']);
            $pid = ($pidRaw && $pidRaw !== '0') ? (int) $pidRaw : null;
            $uptime = $this->calculateUptime($ts);
            if ($pid) {
                $rss = trim((string) (Process::timeout(self::STATUS_TIMEOUT)->run(['ps', '-o', 'rss=', '-p', (string) $pid])->output()));
                $memory = $this->formatMemory((int) $rss);
            }
        }

        return [
            'status'  => $running ? 'running' : 'stopped',
            'enabled' => $enabled === 'enabled',
            'uptime'  => $uptime,
            'memory'  => $memory,
            'pid'     => $pid,
            'is_real' => true,
        ];
    }

    private function resolveServiceName(string $serviceId): string
    {
        return match ($serviceId) {
            'nginx' => 'nginx',
            'mysql' => 'mysql',
            'php-fpm' => $this->detectPhpFpmUnit(),
            'redis' => 'redis-server',
            'supervisor' => 'supervisor',
            'ufw' => 'ufw',
            default => $serviceId,
        };
    }

    public function index()
    {
        $services = [];
        foreach ($this->getServices() as $svc) {
            $services[] = array_merge($svc, $this->getServiceStatus($svc['id']));
        }

        return view('services.index', compact('services'));
    }

    public function action(Request $request)
    {
        $request->validate([
            'service' => 'required|string|in:nginx,mysql,php-fpm,redis,supervisor,ufw',
            'action'  => 'required|string|in:start,stop,restart,enable,disable',
        ]);

        $serviceId = $request->service;
        $action = $request->action;
        $unit = $this->resolveServiceName($serviceId);

        $command = "systemctl {$action} {$unit}";
        $safety = SafetyGuard::assess($command);
        if (! $safety['allowed']) {
            ActivityLogger::danger('service_blocked', "Blocked: {$command}");

            return response()->json(['success' => false, 'message' => $safety['message'], 'safety' => $safety], 403);
        }

        $logLevel = $safety['level'] === SafetyGuard::DANGEROUS ? 'danger'
            : ($safety['level'] === SafetyGuard::CAUTION ? 'warning' : 'info');
        ActivityLogger::log("service_{$action}", "Service: {$serviceId}, Command: {$command}", $logLevel);

        if (PHP_OS_FAMILY !== 'Linux' || ! $this->systemctlAvailable()) {
            return response()->json([
                'success'    => true,
                'message'    => '[Demo] ' . ucfirst($action) . " {$serviceId} (systemd not available here).",
                'new_status' => in_array($action, ['start', 'restart']) ? 'running' : 'stopped',
                'is_real'    => false,
            ]);
        }

        // Run with a hard timeout and non-interactive sudo so it can NEVER hang.
        $result = Process::timeout(self::ACTION_TIMEOUT)->run([...$this->sudo(), 'systemctl', $action, $unit]);

        if (! $result->successful()) {
            $err = trim($result->errorOutput() ?: $result->output());
            if (str_contains($err, 'password is required') || str_contains($err, 'a terminal is required')) {
                $err = 'Passwordless sudo is not configured for systemctl. See the deploy guide (sudoers NOPASSWD).';
            }
            ActivityLogger::warning('service_action_failed', "{$command}: {$err}");

            return response()->json(['success' => false, 'message' => $err ?: 'Command failed'], 500);
        }

        $newStatus = $this->ctl(['is-active', $unit]) === 'active' ? 'running' : 'stopped';

        NotificationService::send(
            'Service ' . ucfirst($action),
            ucfirst($action) . " on **{$serviceId}** completed — now {$newStatus}.",
            'info'
        );

        return response()->json([
            'success'    => true,
            'message'    => ucfirst($action) . " {$serviceId} completed.",
            'new_status' => $newStatus,
            'is_real'    => true,
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /** Run a read-only `systemctl` query, returning trimmed stdout ('' on error). */
    private function ctl(array $args): string
    {
        $result = Process::timeout(self::STATUS_TIMEOUT)->run(['systemctl', ...$args]);

        return trim($result->output());
    }

    /** sudo prefix — empty when already root, non-interactive otherwise. */
    private function sudo(): array
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return [];
        }

        return ['sudo', '-n'];
    }

    private function systemctlAvailable(): bool
    {
        static $available = null;
        if ($available === null) {
            $available = trim(Process::timeout(self::STATUS_TIMEOUT)->run('command -v systemctl')->output()) !== '';
        }

        return $available;
    }

    private function detectPhpFpmUnit(): string
    {
        static $unit = null;
        if ($unit !== null) {
            return $unit;
        }
        $list = Process::timeout(self::STATUS_TIMEOUT)->run('systemctl list-unit-files --type=service --no-legend')->output();
        if (preg_match('/(php[0-9.]+-fpm)\.service/', $list, $m)) {
            return $unit = $m[1];
        }

        return $unit = 'php8.2-fpm';
    }

    private function calculateUptime(string $timestamp): string
    {
        if (empty($timestamp)) {
            return 'N/A';
        }
        try {
            $diff = (new \DateTime($timestamp))->diff(new \DateTime());
            if ($diff->days > 0) return $diff->days . 'd ' . $diff->h . 'h';
            if ($diff->h > 0) return $diff->h . 'h ' . $diff->i . 'm';

            return $diff->i . 'm ' . $diff->s . 's';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function formatMemory(int $kb): string
    {
        if ($kb >= 1048576) return round($kb / 1048576, 1) . ' GB';
        if ($kb >= 1024) return round($kb / 1024, 1) . ' MB';

        return $kb > 0 ? $kb . ' KB' : 'N/A';
    }

    private function getMockStatus(string $serviceId): array
    {
        return ['status' => 'stopped', 'enabled' => false, 'uptime' => 'N/A', 'memory' => 'N/A', 'pid' => null, 'is_real' => false];
    }
}
