<?php

namespace App\Services\AI\Actions;

/**
 * Safety layer that checks AI-suggested actions before execution.
 * Classifies commands by danger level and requires confirmation for risky operations.
 */
class SafetyGuard
{
    /**
     * Danger levels
     */
    const SAFE = 'safe';           // Read-only operations
    const CAUTION = 'caution';     // Modifying operations (restart, config change)
    const DANGEROUS = 'dangerous'; // Destructive operations (delete, drop, rm)
    const BLOCKED = 'blocked';     // Never allowed (rm -rf /, drop all)

    /**
     * Patterns for blocked commands — NEVER allowed
     */
    private static array $blockedPatterns = [
        'rm -rf /',
        'rm -rf /*',
        'mkfs',
        'dd if=',
        '> /dev/sda',
        'chmod -R 777 /',
        ':(){:|:&};:',     // fork bomb
        'DROP DATABASE',
        'DROP ALL',
        'TRUNCATE',
        'DELETE FROM .* WHERE 1',
        'shutdown -h now',
        'init 0',
        'halt',
    ];

    /**
     * Patterns for dangerous commands — require explicit confirmation
     */
    private static array $dangerousPatterns = [
        'rm -rf',
        'rm -r',
        'rmdir',
        'DROP TABLE',
        'DROP DATABASE',
        'DELETE FROM',
        'systemctl stop',
        'systemctl disable',
        'service .* stop',
        'nginx -s stop',
        'kill -9',
        'killall',
        'pkill',
        'userdel',
        'deluser',
        'iptables -F',
        'ufw disable',
        'certbot delete',
    ];

    /**
     * Patterns for cautionary commands — show warning
     */
    private static array $cautionPatterns = [
        'systemctl restart',
        'service .* restart',
        'nginx -s reload',
        'mysql -e',
        'mysqldump',
        'chmod',
        'chown',
        'apt install',
        'apt remove',
        'pip install',
        'composer install',
        'npm install',
        'crontab -e',
        'certbot',
    ];

    /**
     * Check a command string and return its danger level + details
     */
    public static function assess(string $command): array
    {
        $command = trim($command);

        // Check blocked first
        foreach (self::$blockedPatterns as $pattern) {
            if (self::matches($command, $pattern)) {
                return [
                    'level' => self::BLOCKED,
                    'allowed' => false,
                    'message' => '🚫 This command is blocked for safety reasons.',
                    'detail' => "The command matches a blocked pattern: {$pattern}",
                    'command' => $command,
                ];
            }
        }

        // Check dangerous
        foreach (self::$dangerousPatterns as $pattern) {
            if (self::matches($command, $pattern)) {
                return [
                    'level' => self::DANGEROUS,
                    'allowed' => true,
                    'requires_confirm' => true,
                    'message' => '⚠️ This is a destructive command. Are you sure?',
                    'detail' => "This command can permanently delete data or stop critical services.",
                    'command' => $command,
                ];
            }
        }

        // Check caution
        foreach (self::$cautionPatterns as $pattern) {
            if (self::matches($command, $pattern)) {
                return [
                    'level' => self::CAUTION,
                    'allowed' => true,
                    'requires_confirm' => true,
                    'message' => '⚡ This command will modify your system. Please confirm.',
                    'detail' => "This will change system configuration or restart a service.",
                    'command' => $command,
                ];
            }
        }

        // Safe by default (read-only)
        return [
            'level' => self::SAFE,
            'allowed' => true,
            'requires_confirm' => false,
            'message' => '✅ This is a safe, read-only command.',
            'command' => $command,
        ];
    }

    /**
     * Check multiple commands at once
     */
    public static function assessMultiple(array $commands): array
    {
        $results = [];
        $highestLevel = self::SAFE;

        foreach ($commands as $cmd) {
            $result = self::assess($cmd);
            $results[] = $result;

            // Track highest danger level
            $levelOrder = [self::SAFE => 0, self::CAUTION => 1, self::DANGEROUS => 2, self::BLOCKED => 3];
            if ($levelOrder[$result['level']] > $levelOrder[$highestLevel]) {
                $highestLevel = $result['level'];
            }
        }

        return [
            'overall_level' => $highestLevel,
            'overall_allowed' => $highestLevel !== self::BLOCKED,
            'requires_confirm' => $highestLevel !== self::SAFE,
            'commands' => $results,
        ];
    }

    /**
     * Match command against pattern (case-insensitive, supports regex-like patterns)
     */
    private static function matches(string $command, string $pattern): bool
    {
        // Try regex first
        $regex = '/' . str_replace('/', '\/', $pattern) . '/i';
        if (@preg_match($regex, $command)) {
            return true;
        }

        // Fallback to simple string contains
        return stripos($command, $pattern) !== false;
    }

    /**
     * Get a human-readable summary of danger level
     */
    public static function getLevelInfo(string $level): array
    {
        return match ($level) {
            self::SAFE => [
                'label' => 'Safe',
                'color' => 'emerald',
                'icon' => 'check-circle',
                'description' => 'Read-only operation, no risk',
            ],
            self::CAUTION => [
                'label' => 'Caution',
                'color' => 'amber',
                'icon' => 'exclamation-triangle',
                'description' => 'Will modify system configuration',
            ],
            self::DANGEROUS => [
                'label' => 'Dangerous',
                'color' => 'red',
                'icon' => 'x-circle',
                'description' => 'Can permanently delete data',
            ],
            self::BLOCKED => [
                'label' => 'Blocked',
                'color' => 'red',
                'icon' => 'ban',
                'description' => 'This operation is never allowed',
            ],
            default => [
                'label' => 'Unknown',
                'color' => 'slate',
                'icon' => 'question-mark-circle',
                'description' => 'Unknown danger level',
            ],
        };
    }
}
