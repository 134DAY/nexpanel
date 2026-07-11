<?php

namespace App\Console\Commands;

use App\Models\NotificationSetting;
use App\Services\NotificationService;
use App\Services\ServerMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Watches server resources and events, firing notifications when a
 * configured threshold is crossed (scope 1.3.2.2 + 1.3.3.2).
 *
 * Runs every minute from the scheduler. Each alert type keeps a small
 * state machine in the notification_settings table so we alert once on
 * breach, re-alert only after a cooldown, and send a single "resolved"
 * message when the condition clears — never a flood.
 */
class MonitorServer extends Command
{
    protected $signature = 'nexpanel:monitor';

    protected $description = 'Check server thresholds and fire alerts to the configured channels';

    public function handle(ServerMetricsService $metrics): int
    {
        if (NotificationSetting::get('monitor_enabled') !== '1') {
            $this->info('Monitoring is disabled — nothing to do.');

            return self::SUCCESS;
        }

        $cooldown = (int) (NotificationSetting::get('monitor_cooldown', 30)) ?: 30;
        $m = $metrics->getAll();

        // --- Resource thresholds -------------------------------------------
        $cpuLimit  = (float) NotificationSetting::get('monitor_cpu', 90);
        $ramLimit  = (float) NotificationSetting::get('monitor_ram', 90);
        $diskLimit = (float) NotificationSetting::get('monitor_disk', 90);

        $this->evaluate('cpu', $m['cpu'] >= $cpuLimit, $cooldown,
            'High CPU usage', "CPU is at {$m['cpu']}% (threshold {$cpuLimit}%).", 'warning');

        $this->evaluate('ram', $m['ram']['percent'] >= $ramLimit, $cooldown,
            'High RAM usage', "RAM is at {$m['ram']['percent']}% (threshold {$ramLimit}%).", 'warning');

        // Disk over threshold doubles as the "disk almost full" alert.
        $this->evaluate('disk', $m['disk']['percent'] >= $diskLimit, $cooldown,
            'Disk almost full', "Disk is at {$m['disk']['percent']}% ({$m['disk']['used']}/{$m['disk']['total']} GB, threshold {$diskLimit}%).", 'danger');

        // --- Service down ---------------------------------------------------
        if (NotificationSetting::get('monitor_services_enabled') === '1') {
            $names = ['nginx' => 'Nginx', 'mysql' => 'MySQL/MariaDB', 'phpfpm' => 'PHP-FPM'];
            foreach ($m['services'] as $svc => $state) {
                $this->evaluate("svc_{$svc}", $state !== 'active', $cooldown,
                    "Service down: {$names[$svc]}", ($names[$svc] ?? $svc) . ' is not running on ' . $m['hostname'] . '.', 'danger');
            }
        }

        // --- SSL expiry -----------------------------------------------------
        if (NotificationSetting::get('monitor_ssl_enabled') === '1') {
            $this->checkSsl($cooldown);
        }

        // --- Cron failures --------------------------------------------------
        if (NotificationSetting::get('monitor_cron_enabled') === '1') {
            $this->checkCronFailures($cooldown);
        }

        $this->info('Monitor run complete.');

        return self::SUCCESS;
    }

    /**
     * One-breach / cooldown / recovery state machine for a boolean condition.
     */
    private function evaluate(string $key, bool $breach, int $cooldownMin, string $title, string $message, string $level): void
    {
        $stateKey = "monitor_state_{$key}";
        $lastKey  = "monitor_last_{$key}";
        $active   = NotificationSetting::get($stateKey) === '1';

        if ($breach) {
            $lastTs = (int) NotificationSetting::get($lastKey, 0);
            $due    = (time() - $lastTs) >= $cooldownMin * 60;
            if (! $active || $due) {
                NotificationService::send("⚠️ {$title}", $message, $level);
                NotificationSetting::put($stateKey, '1');
                NotificationSetting::put($lastKey, (string) time());
                $this->warn("ALERT [{$key}]: {$message}");
            }

            return;
        }

        // Condition cleared — send a single resolved message, then reset.
        if ($active) {
            NotificationService::send("✅ Resolved: {$title}", "{$title} is back to normal.", 'info');
            NotificationSetting::put($stateKey, '0');
            NotificationSetting::put($lastKey, '0');
            $this->info("RESOLVED [{$key}]");
        }
    }

    /** Alert when any Let's Encrypt certificate is within N days of expiry. */
    private function checkSsl(int $cooldownMin): void
    {
        if (trim(Process::run('command -v certbot')->output()) === '') {
            return;
        }
        $sudo = (function_exists('posix_geteuid') && posix_geteuid() === 0) ? [] : ['sudo', '-n'];
        $res = Process::timeout(20)->run([...$sudo, 'certbot', 'certificates']);
        if (! $res->successful()) {
            return;
        }

        $days = (int) NotificationSetting::get('monitor_ssl_days', 14) ?: 14;
        $expiring = [];
        foreach (preg_split('/Certificate Name:\s*/', $res->output()) as $block) {
            if (! preg_match('/^([^\s]+)/', trim($block), $nameM)) {
                continue;
            }
            if (preg_match('/VALID:\s*(\d+)\s*day/i', $block, $d) && (int) $d[1] <= $days) {
                $expiring[] = "{$nameM[1]} ({$d[1]} วัน)";
            } elseif (preg_match('/(INVALID|EXPIRED)/i', $block)) {
                $expiring[] = "{$nameM[1]} (หมดอายุแล้ว)";
            }
        }

        $this->evaluate('ssl', ! empty($expiring), max($cooldownMin, 720), // ≥12h between SSL nags
            'SSL ใกล้หมดอายุ', "ใบรับรองที่ต้องต่ออายุ:\n• " . implode("\n• ", $expiring), 'warning');
    }

    /** Alert on cron jobs that failed since the last check. */
    private function checkCronFailures(int $cooldownMin): void
    {
        $sinceTs = (int) NotificationSetting::get('monitor_last_cron_check', 0);
        $since   = $sinceTs > 0 ? date('Y-m-d H:i:s', $sinceTs) : date('Y-m-d H:i:s', time() - 3600);

        $fails = DB::table('activity_logs')
            ->where('level', 'danger')
            ->where('action', 'like', 'cron%')
            ->where('created_at', '>', $since)
            ->orderByDesc('created_at')
            ->limit(10)
            ->pluck('details')
            ->all();

        NotificationSetting::put('monitor_last_cron_check', (string) time());

        if (empty($fails)) {
            return;
        }

        // Throttle by cooldown so a burst of failures sends one summary.
        $lastTs = (int) NotificationSetting::get('monitor_last_cron_alert', 0);
        if ((time() - $lastTs) < $cooldownMin * 60) {
            return;
        }

        $count = count($fails);
        NotificationService::send(
            "⚠️ Cron job ล้มเหลว ({$count})",
            "ตารางงานที่ทำงานผิดพลาดล่าสุด:\n• " . implode("\n• ", $fails),
            'danger'
        );
        NotificationSetting::put('monitor_last_cron_alert', (string) time());
        $this->warn("ALERT [cron]: {$count} failure(s)");
    }
}
