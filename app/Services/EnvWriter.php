<?php

namespace App\Services;

/**
 * Rewrites single keys in the project's .env file, so settings the panel
 * changes at runtime (like the MySQL admin password) survive a restart.
 */
class EnvWriter
{
    public static function set(string $key, string $value): void
    {
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            throw new \InvalidArgumentException('Invalid env key.');
        }

        $path = base_path('.env');
        if (! is_writable($path)) {
            throw new \RuntimeException('.env is not writable by the web user.');
        }

        $line = $key . '=' . self::quote($value);
        $contents = (string) file_get_contents($path);
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        // Callback, not a replacement string: `$` and `\` in a password must
        // never be read as backreferences.
        $contents = preg_match($pattern, $contents)
            ? preg_replace_callback($pattern, fn() => $line, $contents, 1)
            : rtrim($contents, "\r\n") . PHP_EOL . $line . PHP_EOL;

        file_put_contents($path, $contents, LOCK_EX);
    }

    /** Quote anything that is not a bare, safe token. */
    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.\/-]+$/', $value)) {
            return $value;
        }

        return '"' . addcslashes($value, '"\\$') . '"';
    }
}
