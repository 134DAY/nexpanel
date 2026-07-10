<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    /** Never read more than this from the tail of a log file. */
    private const TAIL_BYTES = 256 * 1024;

    /** Operation log rows per page. */
    private const PER_PAGE = 20;

    public function index()
    {
        return view('logs.index', [
            'levels'  => ['info', 'warning', 'danger'],
            'runPath' => storage_path('logs/laravel.log'),
        ]);
    }

    // ------------------------------------------------------------ operations

    public function operations(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $level  = (string) $request->query('level', '');
        $page   = max(1, (int) $request->query('page', 1));

        $query = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select('activity_logs.*', 'users.name as user_name', 'users.email as user_email');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('activity_logs.action', 'like', "%{$search}%")
                  ->orWhere('activity_logs.details', 'like', "%{$search}%")
                  ->orWhere('activity_logs.ip', 'like', "%{$search}%");
            });
        }
        if (in_array($level, ['info', 'warning', 'danger'], true)) {
            $query->where('activity_logs.level', $level);
        }

        $total = (clone $query)->count();
        $rows  = $query->orderByDesc('activity_logs.id')
            ->forPage($page, self::PER_PAGE)
            ->get();

        return response()->json([
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => self::PER_PAGE,
            'last_page' => max(1, (int) ceil($total / self::PER_PAGE)),
        ]);
    }

    public function clearOperations(): JsonResponse
    {
        $deleted = DB::table('activity_logs')->count();
        DB::table('activity_logs')->delete();

        // Leave a trace of the wipe itself — an empty audit trail should still
        // say who emptied it.
        ActivityLogger::warning('logs.clear', "Cleared {$deleted} operation log entries");

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    // ------------------------------------------------------------- run logs

    public function run(): JsonResponse
    {
        $path = storage_path('logs/laravel.log');

        return response()->json([
            'path'     => $path,
            'exists'   => is_file($path),
            'size'     => is_file($path) ? filesize($path) : 0,
            'content'  => $this->tail($path),
            'modified' => is_file($path) ? date('Y-m-d H:i:s', filemtime($path)) : null,
        ]);
    }

    public function clearRun(): JsonResponse
    {
        $path = storage_path('logs/laravel.log');
        if (is_file($path)) {
            file_put_contents($path, '');
        }
        ActivityLogger::warning('logs.clear', 'Cleared run log (laravel.log)');

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------ cron logs

    public function cronList(): JsonResponse
    {
        $names = $this->jobNamesByKey();
        $files = [];
        foreach (glob($this->cronDir() . '/*.log') ?: [] as $file) {
            $key = basename($file, '.log');
            $files[] = [
                'key'      => $key,
                'name'     => $names[$key] ?? $key,
                'size'     => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        usort($files, fn($a, $b) => strcmp($b['modified'], $a['modified']));

        return response()->json(['files' => $files]);
    }

    public function cronShow(string $key): JsonResponse
    {
        $path = $this->cronPath($key);
        if ($path === null || ! is_file($path)) {
            return response()->json(['error' => 'Log not found'], 404);
        }

        return response()->json([
            'key'      => $key,
            'name'     => $this->cronJobName($key),
            'content'  => $this->tail($path),
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
        ]);
    }

    public function clearCron(string $key): JsonResponse
    {
        $path = $this->cronPath($key);
        if ($path === null || ! is_file($path)) {
            return response()->json(['error' => 'Log not found'], 404);
        }
        file_put_contents($path, '');
        ActivityLogger::warning('logs.clear', "Cleared cron log: {$this->cronJobName($key)}");

        return response()->json(['ok' => true]);
    }

    // --------------------------------------------------------------- helpers

    private function cronDir(): string
    {
        return CronController::cronLogDir();
    }

    /**
     * Resolve a log key to a path. Keys are md5 hashes written by
     * CronController, so anything else is a traversal attempt.
     */
    private function cronPath(string $key): ?string
    {
        if (! preg_match('/^[0-9a-f]{32}$/', $key)) {
            return null;
        }

        return $this->cronDir() . '/' . $key . '.log';
    }

    /**
     * [log key => job name] for the jobs still in the crontab. Reads the
     * crontab once — callers must not do this per log file.
     */
    private function jobNamesByKey(): array
    {
        return array_flip(CronController::jobLogKeys());
    }

    /** A log key's display name, falling back to the key for deleted jobs. */
    private function cronJobName(string $key): string
    {
        return $this->jobNamesByKey()[$key] ?? $key;
    }

    /** Last TAIL_BYTES of a file, trimmed to whole lines. */
    private function tail(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }
        $size = filesize($path);
        if ($size === 0) {
            return '';
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $truncated = $size > self::TAIL_BYTES;
        if ($truncated) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
            fgets($handle); // drop the partial first line
        }
        $content = stream_get_contents($handle);
        fclose($handle);

        return $truncated ? "… (showing last " . number_format(self::TAIL_BYTES / 1024) . " KB)\n\n" . $content : $content;
    }
}
