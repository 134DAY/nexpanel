<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class CronController extends Controller
{
    public function index()
    {
        $available = $this->crontabAvailable();
        $jobs = $available ? $this->parseJobs() : [];

        $stats = [
            'total'  => count($jobs),
            'active' => count(array_filter($jobs, fn($j) => $j['status'] === 'active')),
            'paused' => count(array_filter($jobs, fn($j) => $j['status'] === 'paused')),
            'failed' => 0,
        ];

        return view('cron.index', [
            'jobs'      => $jobs,
            'stats'     => $stats,
            'available' => $available,
            'isMock'    => false,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'nullable|string|max:100',
            'command'  => 'required|string|max:1000',
            'schedule' => 'required|string|max:100',
        ]);
        if (! $this->crontabAvailable()) {
            return response()->json(['error' => 'crontab not available on this system'], 400);
        }
        if (! $this->validSchedule($request->input('schedule'))) {
            return response()->json(['error' => 'Invalid cron schedule expression'], 422);
        }

        $entries = $this->readEntries();
        $entries[] = [
            'kind'     => 'job',
            'name'     => trim((string) $request->input('name')),
            'schedule' => trim($request->input('schedule')),
            'command'  => trim($request->input('command')),
            'enabled'  => true,
        ];
        $this->writeEntries($entries);
        ActivityLogger::log('cron.create', "Added cron job: {$request->input('command')}");

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->mutateJob($id, function (array &$entries, int $jobIndex) {
            array_splice($entries, $jobIndex, 1);
        }, 'cron.delete', 'Deleted cron job');
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        return $this->mutateJob($id, function (array &$entries, int $jobIndex) {
            $entries[$jobIndex]['enabled'] = ! $entries[$jobIndex]['enabled'];
        }, 'cron.toggle', 'Toggled cron job');
    }

    public function run(Request $request, int $id): JsonResponse
    {
        $entries = $this->readEntries();
        $job = $this->findJob($entries, $id);
        if ($job === null) {
            return response()->json(['error' => 'Job not found'], 404);
        }
        $result = Process::timeout(30)->run(['bash', '-lc', $job['command']]);
        ActivityLogger::log('cron.run', "Ran cron job now: {$job['command']}");

        return response()->json([
            'ok'       => true,
            'exit'     => $result->exitCode(),
            'output'   => trim($result->output() . "\n" . $result->errorOutput()),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    private function mutateJob(int $id, callable $mutator, string $action, string $detail): JsonResponse
    {
        if (! $this->crontabAvailable()) {
            return response()->json(['error' => 'crontab not available'], 400);
        }
        $entries = $this->readEntries();
        $jobIndex = $this->findJobIndex($entries, $id);
        if ($jobIndex === null) {
            return response()->json(['error' => 'Job not found'], 404);
        }
        $mutator($entries, $jobIndex);
        $this->writeEntries($entries);
        ActivityLogger::log($action, $detail);

        return response()->json(['ok' => true]);
    }

    private function crontabAvailable(): bool
    {
        return trim(Process::run('command -v crontab')->output()) !== '';
    }

    /** Raw `crontab -l` output ('' when the user has no crontab). */
    private function rawCrontab(): string
    {
        $result = Process::run(['crontab', '-l']);
        // Exit 1 with "no crontab for user" is normal — treat as empty.
        return $result->successful() ? $result->output() : '';
    }

    /**
     * Parse the crontab into ordered entries. Each entry is either
     * ['kind' => 'raw', 'text' => string] for lines we don't manage, or
     * ['kind' => 'job', 'name', 'schedule', 'command', 'enabled'].
     */
    private function readEntries(): array
    {
        $entries = [];
        $pendingName = '';
        foreach (explode("\n", rtrim($this->rawCrontab(), "\n")) as $line) {
            if ($line === '' && $pendingName === '') {
                $entries[] = ['kind' => 'raw', 'text' => ''];
                continue;
            }
            // Name marker written by the panel.
            if (preg_match('/^#NEXPANEL_NAME:\s*(.*)$/', $line, $m)) {
                $pendingName = trim($m[1]);
                continue;
            }
            // Disabled job written by the panel: "#NEXPANEL_OFF: <cronline>".
            if (preg_match('/^#NEXPANEL_OFF:\s*(.+)$/', $line, $m)) {
                $parsed = $this->splitCronLine(trim($m[1]));
                if ($parsed) {
                    $entries[] = ['kind' => 'job', 'name' => $pendingName, 'schedule' => $parsed[0], 'command' => $parsed[1], 'enabled' => false];
                    $pendingName = '';
                    continue;
                }
            }
            // Plain comment we don't manage.
            if (str_starts_with(ltrim($line), '#')) {
                $entries[] = ['kind' => 'raw', 'text' => $line];
                $pendingName = '';
                continue;
            }
            // Active cron line.
            $parsed = $this->splitCronLine($line);
            if ($parsed) {
                $entries[] = ['kind' => 'job', 'name' => $pendingName, 'schedule' => $parsed[0], 'command' => $parsed[1], 'enabled' => true];
                $pendingName = '';
            } else {
                $entries[] = ['kind' => 'raw', 'text' => $line];
                $pendingName = '';
            }
        }

        return $entries;
    }

    private function writeEntries(array $entries): void
    {
        $lines = [];
        foreach ($entries as $e) {
            if ($e['kind'] === 'raw') {
                $lines[] = $e['text'];
                continue;
            }
            if ($e['name'] !== '') {
                $lines[] = '#NEXPANEL_NAME: ' . $e['name'];
            }
            $cronLine = $e['schedule'] . ' ' . $e['command'];
            $lines[] = $e['enabled'] ? $cronLine : '#NEXPANEL_OFF: ' . $cronLine;
        }
        $content = rtrim(implode("\n", $lines), "\n") . "\n";
        Process::input($content)->run(['crontab', '-']);
    }

    /** Build the job list the view renders, assigning stable-ish ids by order. */
    private function parseJobs(): array
    {
        $jobs = [];
        $id = 0;
        foreach ($this->readEntries() as $e) {
            if ($e['kind'] !== 'job') {
                continue;
            }
            $jobs[] = [
                'id'             => $id++,
                'name'           => $e['name'] !== '' ? $e['name'] : $this->deriveName($e['command']),
                'command'        => $e['command'],
                'schedule'       => $e['schedule'],
                'schedule_human' => $this->humanSchedule($e['schedule']),
                'status'         => $e['enabled'] ? 'active' : 'paused',
            ];
        }

        return $jobs;
    }

    private function findJobIndex(array $entries, int $id): ?int
    {
        $jobId = 0;
        foreach ($entries as $i => $e) {
            if ($e['kind'] !== 'job') {
                continue;
            }
            if ($jobId === $id) {
                return $i;
            }
            $jobId++;
        }

        return null;
    }

    private function findJob(array $entries, int $id): ?array
    {
        $i = $this->findJobIndex($entries, $id);

        return $i === null ? null : $entries[$i];
    }

    /** Split a cron line into [schedule, command] or null if not a cron line. */
    private function splitCronLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }
        // Special @-strings (@reboot, @daily, ...).
        if (preg_match('/^(@\w+)\s+(.+)$/', $line, $m)) {
            return [$m[1], trim($m[2])];
        }
        // Standard 5-field schedule followed by the command.
        if (preg_match('/^(\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+(.+)$/', $line, $m)) {
            return [preg_replace('/\s+/', ' ', $m[1]), trim($m[2])];
        }

        return null;
    }

    private function validSchedule(string $schedule): bool
    {
        $schedule = trim($schedule);
        if (preg_match('/^@(reboot|yearly|annually|monthly|weekly|daily|midnight|hourly)$/', $schedule)) {
            return true;
        }
        $fields = preg_split('/\s+/', $schedule);

        return count($fields) === 5;
    }

    private function deriveName(string $command): string
    {
        $first = strtok($command, ' ');
        $base = basename($first ?: 'job');

        return ucfirst($base) . ' task';
    }

    private function humanSchedule(string $schedule): string
    {
        $map = [
            '@reboot'  => 'At system startup',
            '@hourly'  => 'Every hour',
            '@daily'   => 'Every day at midnight',
            '@midnight' => 'Every day at midnight',
            '@weekly'  => 'Every week',
            '@monthly' => 'Every month',
            '@yearly'  => 'Every year',
            '@annually' => 'Every year',
        ];
        if (isset($map[$schedule])) {
            return $map[$schedule];
        }

        $f = preg_split('/\s+/', $schedule);
        if (count($f) !== 5) {
            return $schedule;
        }
        [$min, $hour, $dom, $mon, $dow] = $f;

        if ($schedule === '* * * * *')        return 'Every minute';
        if (preg_match('#^\*/(\d+) \* \* \* \*$#', $schedule, $m)) return "Every {$m[1]} minutes";
        if ($min !== '*' && $hour === '*' && $dom === '*' && $mon === '*' && $dow === '*') return "Every hour at minute {$min}";
        if (ctype_digit($min) && ctype_digit($hour) && $dom === '*' && $mon === '*' && $dow === '*') {
            return 'Every day at ' . sprintf('%02d:%02d', $hour, $min);
        }
        if (ctype_digit($min) && ctype_digit($hour) && $dom === '*' && $mon === '*' && ctype_digit($dow)) {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            return 'Every ' . ($days[(int) $dow % 7] ?? $dow) . ' at ' . sprintf('%02d:%02d', $hour, $min);
        }
        if (ctype_digit($min) && ctype_digit($hour) && ctype_digit($dom) && $mon === '*' && $dow === '*') {
            return "Monthly on day {$dom} at " . sprintf('%02d:%02d', $hour, $min);
        }

        return $schedule;
    }
}
