<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Self-update from GitHub, driven from the UI instead of the shell.
 *
 * - check()  compares the local checkout with origin/main and returns the
 *            pending changelog (read-only, safe to poll).
 * - run()    launches update.sh detached via the privileged runner so the
 *            php-fpm reload inside the script cannot kill the update midway.
 * - status() tails the run log so the UI can show live progress.
 */
class UpdateController extends Controller
{
    private const SENTINEL = '___NEXPANEL_UPDATE_DONE___';

    private function logPath(): string
    {
        return storage_path('logs/update-run.log');
    }

    /**
     * Run git in the app dir, immune to "dubious ownership" (repo owned by a
     * different uid) and with a writable HOME so config lookups never fail.
     */
    private function git(string $args): \Illuminate\Contracts\Process\ProcessResult
    {
        $dir = base_path();

        return Process::path($dir)
            ->env(['HOME' => storage_path('app'), 'GIT_TERMINAL_PROMPT' => '0'])
            ->timeout(25)
            ->run('git -c safe.directory=' . escapeshellarg($dir) . ' ' . $args);
    }

    /** Compare HEAD with origin/main and list the pending commits. */
    public function check(): JsonResponse
    {
        // Refresh remote refs at most once a minute to keep the request cheap
        // while still noticing a fresh push within ~a minute.
        if (! Cache::has('update_fetched')) {
            $this->git('fetch --quiet origin');
            Cache::put('update_fetched', true, now()->addSeconds(60));
        }

        $current  = trim($this->git('rev-parse --short HEAD')->output());
        $latest   = trim($this->git('rev-parse --short origin/main')->output());
        $behind   = (int) trim($this->git('rev-list --count HEAD..origin/main')->output());
        $fetchErr = trim($this->git('rev-parse --abbrev-ref origin/HEAD')->errorOutput());

        $commits = [];
        if ($behind > 0) {
            $raw = $this->git('log --pretty=format:%h%x1f%s HEAD..origin/main -n 30')->output();
            foreach (explode("\n", trim($raw)) as $line) {
                if ($line === '') {
                    continue;
                }
                [$hash, $subject] = array_pad(explode("\x1f", $line, 2), 2, '');
                $commits[] = ['hash' => $hash, 'subject' => $subject];
            }
        }

        return response()->json([
            'updateAvailable' => $behind > 0,
            'current'         => $current ?: 'unknown',
            'latest'          => $latest ?: 'unknown',
            'behind'          => $behind,
            'commits'         => $commits,
            'gitError'        => $current === '' ? $fetchErr : null,
        ]);
    }

    /** Kick off update.sh in the background. Returns immediately. */
    public function run(): JsonResponse
    {
        $dir = base_path();
        $log = $this->logPath();

        // Already running? Don't start a second one.
        if ($this->isRunning()) {
            return response()->json(['ok' => false, 'message' => 'An update is already running.'], 409);
        }

        @file_put_contents($log, "==> Starting NexPanel update…\n");

        // A small script (world-readable, in writable storage) that the root
        // runner executes; keeps quoting sane and captures everything to the log.
        $script = storage_path('app/nexpanel-update.sh');
        file_put_contents($script, implode("\n", [
            '#!/bin/bash',
            'LOG="' . $log . '"',
            'cd "' . $dir . '" || { echo "cd failed" >> "$LOG"; exit 1; }',
            'bash update.sh >> "$LOG" 2>&1',
            'echo "' . self::SENTINEL . ' exit=$?" >> "$LOG"',
            '',
        ]));
        @chmod($script, 0755);

        // systemd-run (no --wait) → detached transient unit that survives the
        // php-fpm reload update.sh performs. nexpanel-run gives us root.
        $cmd = 'systemd-run --collect /bin/bash ' . escapeshellarg($script);
        $res = Process::timeout(20)->input($cmd)->run(['sudo', '-n', '/usr/local/bin/nexpanel-run']);

        ActivityLogger::log('update.run', 'Triggered panel update from the UI');

        if (! $res->successful()) {
            $err = trim($res->errorOutput() ?: $res->output()) ?: 'Failed to launch updater.';
            @file_put_contents($log, "\n[error] {$err}\n", FILE_APPEND);

            return response()->json(['ok' => false, 'message' => $err], 500);
        }

        return response()->json(['ok' => true, 'message' => 'Update started.']);
    }

    /** Return the current run log and whether it has finished. */
    public function status(): JsonResponse
    {
        $log = $this->logPath();
        $content = is_file($log) ? (string) file_get_contents($log) : '';
        $done = str_contains($content, self::SENTINEL);

        $exit = null;
        if ($done && preg_match('/' . self::SENTINEL . ' exit=(\d+)/', $content, $m)) {
            $exit = (int) $m[1];
            $content = trim(preg_replace('/' . self::SENTINEL . '.*/', '', $content));
        }

        return response()->json([
            'running'  => $content !== '' && ! $done,
            'done'     => $done,
            'success'  => $done && $exit === 0,
            'exitCode' => $exit,
            'log'      => $content,
        ]);
    }

    private function isRunning(): bool
    {
        $log = $this->logPath();
        if (! is_file($log)) {
            return false;
        }
        $content = (string) file_get_contents($log);

        // "Running" = the log has content but no completion sentinel yet, and it
        // was touched recently (guards against a stale log from a crashed run).
        return $content !== ''
            && ! str_contains($content, self::SENTINEL)
            && (time() - filemtime($log)) < 600;
    }
}
