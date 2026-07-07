<?php

namespace App\Services\AI;

use App\Services\ActivityLogger;
use App\Services\AI\Actions\SafetyGuard;
use App\Services\MysqlService;
use App\Services\NginxService;
use Illuminate\Support\Facades\Process;

/**
 * Runs AI-proposed actions after the user confirms them.
 *
 * Two execution paths (hybrid):
 *   - "tools": high-level operations dispatched to the panel's own vetted
 *     services (website/database/service/cron). Safe — no raw shell.
 *   - "shell": a fallback raw command, gated by SafetyGuard and run through a
 *     privileged, denylisted wrapper (/usr/local/bin/nexpanel-run).
 *
 * Every action is assessed for risk and must be confirmed by the user in the
 * chat UI before run() is called; every run is written to the audit log.
 */
class AIExecutor
{
    /** Tools the AI may propose, with their default risk level. */
    public const TOOLS = [
        'create_website'  => 'caution',
        'delete_website'  => 'dangerous',
        'toggle_website'  => 'caution',
        'create_database' => 'caution',
        'drop_database'   => 'dangerous',
        'create_db_user'  => 'caution',
        'service'         => 'caution',
        'create_cron'     => 'caution',
        'shell'           => 'caution',
    ];

    /** Assess an action's risk before it runs (drives the confirm card). */
    public static function assess(string $tool, array $args): array
    {
        if ($tool === 'shell') {
            $s = SafetyGuard::assess($args['command'] ?? '');

            return ['level' => $s['level'], 'allowed' => $s['allowed'], 'message' => $s['message']];
        }

        if (! isset(self::TOOLS[$tool])) {
            return ['level' => 'blocked', 'allowed' => false, 'message' => "Unknown action: {$tool}"];
        }

        $level = self::TOOLS[$tool];
        // Stopping/disabling a service is destructive.
        if ($tool === 'service' && in_array($args['action'] ?? '', ['stop', 'disable'], true)) {
            $level = 'dangerous';
        }

        return ['level' => $level, 'allowed' => true, 'message' => self::describe($tool, $args)];
    }

    /** A short human description of what the action will do. */
    public static function describe(string $tool, array $args): string
    {
        return match ($tool) {
            'create_website'  => "Create Nginx vhost for {$args['domain']}" . (($args['ssl'] ?? false) ? ' with SSL' : ''),
            'delete_website'  => "Delete Nginx vhost {$args['domain']}",
            'toggle_website'  => "Enable/disable vhost {$args['domain']}",
            'create_database' => "Create MySQL database {$args['name']}",
            'drop_database'   => "Drop MySQL database {$args['name']}",
            'create_db_user'  => "Create MySQL user {$args['username']}",
            'service'         => ucfirst($args['action'] ?? '?') . " service {$args['name']}",
            'create_cron'     => "Add cron job: {$args['command']} ({$args['schedule']})",
            'shell'           => "Run: {$args['command']}",
            default           => $tool,
        };
    }

    /** Execute the action. Returns ['ok' => bool, 'output' => string]. */
    public static function run(string $tool, array $args): array
    {
        $assessment = self::assess($tool, $args);
        if (! $assessment['allowed']) {
            return ['ok' => false, 'output' => $assessment['message']];
        }

        try {
            $result = self::dispatch($tool, $args);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'output' => $e->getMessage()];
        }

        ActivityLogger::log(
            'ai.execute',
            self::describe($tool, $args) . ($result['ok'] ? ' — ok' : ' — FAILED: ' . $result['output']),
            $result['ok'] ? ($assessment['level'] === 'dangerous' ? 'warning' : 'info') : 'danger'
        );

        return $result;
    }

    private static function dispatch(string $tool, array $args): array
    {
        switch ($tool) {
            case 'create_website':
                $nginx = new NginxService();
                if (! $nginx->available()) {
                    return ['ok' => false, 'output' => 'Nginx is not installed.'];
                }
                $domain  = $args['domain'];
                $docRoot = $args['document_root'] ?? "/var/www/{$domain}/public";
                $msg = $nginx->createSite($domain, $docRoot, $args['php'] ?? '8.3', (bool) ($args['ssl'] ?? false));
                self::shellRoot("mkdir -p " . escapeshellarg($docRoot) . " && chown -R www-data:www-data " . escapeshellarg(dirname($docRoot)));

                return ['ok' => true, 'output' => $msg];

            case 'delete_website':
                (new NginxService())->deleteSite($args['domain']);

                return ['ok' => true, 'output' => "Deleted vhost {$args['domain']}."];

            case 'toggle_website':
                $en = (new NginxService())->toggleSite($args['domain']);

                return ['ok' => true, 'output' => "{$args['domain']} is now " . ($en ? 'enabled' : 'disabled') . '.'];

            case 'create_database':
                (new MysqlService())->createDatabase($args['name'], $args['charset'] ?? 'utf8mb4');

                return ['ok' => true, 'output' => "Database {$args['name']} created."];

            case 'drop_database':
                (new MysqlService())->dropDatabase($args['name']);

                return ['ok' => true, 'output' => "Database {$args['name']} dropped."];

            case 'create_db_user':
                (new MysqlService())->createUser($args['username'], $args['password'] ?? '', $args['host'] ?? 'localhost');

                return ['ok' => true, 'output' => "MySQL user {$args['username']} created."];

            case 'service':
                $unit = self::resolveUnit($args['name']);
                $action = $args['action'] ?? 'restart';
                if (! in_array($action, ['start', 'stop', 'restart', 'reload', 'enable', 'disable'], true)) {
                    return ['ok' => false, 'output' => "Invalid service action: {$action}"];
                }
                $r = Process::timeout(30)->run([...self::sudo(), 'systemctl', $action, $unit]);

                return ['ok' => $r->successful(), 'output' => $r->successful()
                    ? ucfirst($action) . " {$unit} ok."
                    : trim($r->errorOutput() ?: $r->output())];

            case 'create_cron':
                $line = trim($args['schedule']) . ' ' . trim($args['command']);
                $cur = Process::run(['bash', '-c', 'crontab -l 2>/dev/null'])->output();
                Process::input(rtrim($cur, "\n") . "\n" . $line . "\n")->run(['crontab', '-']);

                return ['ok' => true, 'output' => "Cron job added: {$line}"];

            case 'shell':
                return self::shellRoot($args['command'] ?? '');

            default:
                return ['ok' => false, 'output' => "Unknown action: {$tool}"];
        }
    }

    /** Run a raw command as root via the denylisted privileged wrapper. */
    private static function shellRoot(string $command): array
    {
        $command = trim($command);
        if ($command === '') {
            return ['ok' => false, 'output' => 'Empty command.'];
        }
        $safety = SafetyGuard::assess($command);
        if (! $safety['allowed']) {
            return ['ok' => false, 'output' => $safety['message']];
        }

        $runner = '/usr/local/bin/nexpanel-run';
        if (! is_file($runner)) {
            // Fallback for environments without the privileged wrapper.
            $r = Process::timeout(120)->run(['bash', '-c', $command]);
        } else {
            $r = Process::timeout(120)->input($command)->run([...self::sudo(), $runner]);
        }

        return [
            'ok'     => $r->successful(),
            'output' => trim($r->output() . "\n" . $r->errorOutput()) ?: '(no output)',
        ];
    }

    private static function sudo(): array
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return [];
        }

        return ['sudo', '-n'];
    }

    private static function resolveUnit(string $name): string
    {
        return match ($name) {
            'nginx' => 'nginx',
            'mysql', 'mariadb' => 'mysql',
            'php-fpm', 'php', 'fpm' => trim(Process::run("bash -c \"systemctl list-unit-files --type=service --no-legend | grep -oE 'php[0-9.]+-fpm' | head -1\"")->output()) ?: 'php8.3-fpm',
            'redis' => 'redis-server',
            default => preg_replace('/[^a-zA-Z0-9._-]/', '', $name),
        };
    }
}
