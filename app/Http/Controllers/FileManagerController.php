<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManagerController extends Controller
{
    /** Max size (bytes) a file may be to open in the text editor. */
    private const MAX_EDIT_SIZE = 2 * 1024 * 1024; // 2 MB

    public function index(Request $request)
    {
        $path = $this->normalize($request->query('path', $this->defaultRoot()));

        $error = null;
        $files = [];
        if (! is_dir($path)) {
            $error = "Directory not found: {$path}";
            $path = $this->defaultRoot();
        }
        if (! is_readable($path)) {
            $error = "Permission denied reading: {$path}";
        } else {
            $files = $this->listDirectory($path);
        }

        return view('files.index', [
            'files'       => $files,
            'path'        => $path,
            'breadcrumbs' => $this->breadcrumbs($path),
            'disk'        => $this->diskUsage($path),
            'error'       => $error,
            'isMock'      => false,
        ]);
    }

    /** Return the contents of a text file for the in-browser editor. */
    public function read(Request $request): JsonResponse
    {
        $path = $this->normalize($request->query('path', ''));

        if (! is_file($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }
        if (! is_readable($path)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        if (filesize($path) > self::MAX_EDIT_SIZE) {
            return response()->json(['error' => 'File too large to edit (max 2 MB)'], 413);
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return response()->json(['error' => 'Could not read file'], 500);
        }
        // Reject obviously binary content.
        if (str_contains($content, "\0")) {
            return response()->json(['error' => 'Binary file — cannot edit as text'], 415);
        }

        return response()->json(['content' => $content, 'path' => $path]);
    }

    public function save(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string', 'content' => 'present|string']);
        $path = $this->normalize($request->input('path'));

        if (is_dir($path)) {
            return response()->json(['error' => 'Path is a directory'], 400);
        }
        if (@file_put_contents($path, $request->input('content')) === false) {
            // Root fallback: write via base64 to dodge quoting.
            $cmd = 'echo ' . escapeshellarg(base64_encode($request->input('content'))) . ' | base64 -d > ' . escapeshellarg($path);
            if (! $this->runRoot($cmd)) {
                return response()->json(['error' => 'Permission denied writing file'], 403);
            }
        }
        ActivityLogger::log('file.save', "Edited file {$path}");

        return response()->json(['ok' => true]);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
            'name' => 'required|string|max:255',
            'type' => 'required|in:file,folder',
        ]);
        $name = basename(trim($request->input('name')));
        if ($name === '' || $name === '.' || $name === '..') {
            return response()->json(['error' => 'Invalid name'], 422);
        }
        $target = $this->normalize(rtrim($request->input('path'), '/') . '/' . $name);

        if (file_exists($target)) {
            return response()->json(['error' => 'Already exists'], 409);
        }

        $ok = $request->input('type') === 'folder'
            ? @mkdir($target, 0755)
            : @touch($target);

        if (! $ok) {
            $base = $request->input('type') === 'folder'
                ? 'mkdir -p ' . escapeshellarg($target)
                : 'touch ' . escapeshellarg($target);
            $ok = $this->runRoot($base . $this->chownWww($target));
        }
        if (! $ok) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        ActivityLogger::log('file.create', "Created {$request->input('type')} {$target}");

        return response()->json(['ok' => true]);
    }

    public function rename(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string', 'name' => 'required|string|max:255']);
        $path    = $this->normalize($request->input('path'));
        $newName = basename(trim($request->input('name')));
        if ($newName === '' || $newName === '.' || $newName === '..') {
            return response()->json(['error' => 'Invalid name'], 422);
        }
        $target = dirname($path) . '/' . $newName;

        if (! file_exists($path)) {
            return response()->json(['error' => 'Source not found'], 404);
        }
        if (file_exists($target)) {
            return response()->json(['error' => 'Target already exists'], 409);
        }
        if (! @rename($path, $target)) {
            if (! $this->runRoot('mv ' . escapeshellarg($path) . ' ' . escapeshellarg($target))) {
                return response()->json(['error' => 'Permission denied'], 403);
            }
        }
        ActivityLogger::log('file.rename', "Renamed {$path} → {$target}");

        return response()->json(['ok' => true]);
    }

    public function delete(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);
        $path = $this->normalize($request->input('path'));

        if (! file_exists($path)) {
            return response()->json(['error' => 'Not found'], 404);
        }
        // Guard against catastrophic deletes.
        if (in_array(rtrim($path, '/'), ['', '/', '/root', '/home', '/etc', '/var', '/usr', '/bin'], true)) {
            return response()->json(['error' => 'Refusing to delete a protected system path'], 403);
        }

        $ok = is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        if (! $ok && file_exists($path)) {
            $ok = $this->runRoot('rm -rf ' . escapeshellarg($path));
        }
        if (! $ok && file_exists($path)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        ActivityLogger::log('file.delete', "Deleted {$path}");

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string', 'file' => 'required|file']);
        $dir = $this->normalize($request->input('path'));

        if (! is_dir($dir)) {
            return response()->json(['error' => 'Destination is not a directory'], 400);
        }
        $file = $request->file('file');
        $name = basename($file->getClientOriginalName());
        $target = $dir . '/' . $name;

        if (is_writable($dir)) {
            try {
                $file->move($dir, $name);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
            }
        } else {
            // Root fallback: move the temp upload into a root-owned directory.
            $tmp = $file->getRealPath();
            if (! $this->runRoot('mv ' . escapeshellarg($tmp) . ' ' . escapeshellarg($target) . $this->chownWww($target))) {
                return response()->json(['error' => 'Destination not writable'], 403);
            }
        }
        ActivityLogger::log('file.upload', "Uploaded {$name} to {$dir}");

        return response()->json(['ok' => true]);
    }

    public function download(Request $request): BinaryFileResponse|StreamedResponse|JsonResponse
    {
        $path = $this->normalize($request->query('path', ''));
        if (! is_file($path) || ! is_readable($path)) {
            return response()->json(['error' => 'File not found or unreadable'], 404);
        }

        return response()->download($path);
    }

    public function chmod(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string', 'mode' => 'required|string|regex:/^[0-7]{3,4}$/']);
        $path = $this->normalize($request->input('path'));
        if (! file_exists($path)) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if (! @chmod($path, intval($request->input('mode'), 8))) {
            if (! $this->runRoot('chmod -R ' . escapeshellarg($request->input('mode')) . ' ' . escapeshellarg($path))) {
                return response()->json(['error' => 'Permission denied'], 403);
            }
        }
        ActivityLogger::log('file.chmod', "chmod {$request->input('mode')} {$path}");

        return response()->json(['ok' => true]);
    }

    /** Copy or move a file/dir into a destination directory. */
    public function transfer(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'required|string',
            'dest'   => 'required|string',
            'action' => 'required|in:copy,move',
        ]);
        $src = $this->normalize($request->input('source'));
        $destDir = $this->normalize($request->input('dest'));
        if (! file_exists($src)) {
            return response()->json(['error' => 'Source not found'], 404);
        }
        if (! is_dir($destDir)) {
            return response()->json(['error' => 'Destination is not a directory'], 400);
        }
        $target = $destDir . '/' . basename($src);
        if (realpath($src) === realpath($target)) {
            return response()->json(['error' => 'Source and destination are the same'], 400);
        }
        if (file_exists($target)) {
            return response()->json(['error' => basename($src) . ' already exists in destination'], 409);
        }

        $isMove = $request->input('action') === 'move';
        $ok = false;
        try {
            $ok = $isMove ? @rename($src, $target) : (function () use ($src, $target) {
                $this->recursiveCopy($src, $target);

                return true;
            })();
        } catch (\Throwable $e) {
            $ok = false;
        }
        if (! $ok) {
            $shell = ($isMove ? 'mv ' : 'cp -a ') . escapeshellarg($src) . ' ' . escapeshellarg($target) . $this->chownWww($target);
            $ok = $this->runRoot($shell);
        }
        if (! $ok) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        ActivityLogger::log('file.' . $request->input('action'), "{$request->input('action')} {$src} → {$target}");

        return response()->json(['ok' => true]);
    }

    /** Zip one or more entries into <name>.zip in the given directory. */
    public function compress(Request $request): JsonResponse
    {
        $request->validate([
            'path'  => 'required|string',
            'items' => 'required|array|min:1',
            'name'  => 'required|string|max:255',
        ]);
        $dir = $this->normalize($request->input('path'));
        $zipName = basename(trim($request->input('name')));
        if (! str_ends_with(strtolower($zipName), '.zip')) {
            $zipName .= '.zip';
        }
        $zipPath = $dir . '/' . $zipName;
        // Build the archive in a writable temp file, then place it (root if needed).
        $tmpZip = tempnam(sys_get_temp_dir(), 'nexzip') . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['error' => 'Cannot create archive'], 500);
        }
        foreach ($request->input('items') as $item) {
            $full = $this->normalize($dir . '/' . basename($item));
            if (is_file($full)) {
                $zip->addFile($full, basename($full));
            } elseif (is_dir($full)) {
                $this->addDirToZip($zip, $full, basename($full));
            }
        }
        $zip->close();

        if (! @rename($tmpZip, $zipPath)) {
            $moved = $this->runRoot('mv ' . escapeshellarg($tmpZip) . ' ' . escapeshellarg($zipPath) . $this->chownWww($zipPath));
            @unlink($tmpZip);
            if (! $moved) {
                return response()->json(['error' => 'Cannot write archive to destination'], 403);
            }
        }
        ActivityLogger::log('file.compress', "Compressed to {$zipPath}");

        return response()->json(['ok' => true]);
    }

    /** Extract a .zip / .tar.gz / .tgz archive into its directory. */
    public function extract(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);
        $path = $this->normalize($request->input('path'));
        if (! is_file($path)) {
            return response()->json(['error' => 'Archive not found'], 404);
        }
        $dir = dirname($path);
        $lower = strtolower($path);

        $isZip = str_ends_with($lower, '.zip');
        $isTar = str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tgz') || str_ends_with($lower, '.tar');
        if (! $isZip && ! $isTar) {
            return response()->json(['error' => 'Unsupported archive type'], 400);
        }

        $done = false;
        if (is_writable($dir)) {
            try {
                if ($isZip) {
                    $zip = new \ZipArchive();
                    if ($zip->open($path) === true) {
                        $zip->extractTo($dir);
                        $zip->close();
                        $done = true;
                    }
                } else {
                    (new \PharData($path))->extractTo($dir, null, true);
                    $done = true;
                }
            } catch (\Throwable $e) {
                $done = false;
            }
        }
        if (! $done) {
            $tarflag = str_ends_with($lower, '.tar') ? 'xf' : 'xzf';
            $extract = $isZip
                ? 'cd ' . escapeshellarg($dir) . ' && unzip -o ' . escapeshellarg($path)
                : 'tar ' . $tarflag . ' ' . escapeshellarg($path) . ' -C ' . escapeshellarg($dir);
            if (! $this->runRoot($extract . $this->chownWww($dir))) {
                return response()->json(['error' => 'Extract failed (permission?)'], 403);
            }
        }
        ActivityLogger::log('file.extract', "Extracted {$path}");

        return response()->json(['ok' => true]);
    }

    public function info(Request $request): JsonResponse
    {
        $path = $this->normalize($request->query('path', ''));
        if (! file_exists($path)) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $isDir = is_dir($path);

        return response()->json([
            'name'        => basename($path),
            'path'        => $path,
            'type'        => $isDir ? 'directory' : 'file',
            'size'        => $isDir ? $this->humanSize($this->dirSize($path)) : $this->humanSize((int) @filesize($path)),
            'permissions' => $this->permString($path),
            'mode'        => substr(sprintf('%o', @fileperms($path)), -3),
            'owner'       => $this->ownerName($path),
            'modified'    => date('Y-m-d H:i:s', (int) @filemtime($path)),
            'accessed'    => date('Y-m-d H:i:s', (int) @fileatime($path)),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /** sudo prefix — empty when already root, non-interactive otherwise. */
    private function sudo(): array
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return [];
        }

        return ['sudo', '-n'];
    }

    /** Run a shell command as root via the privileged wrapper (aaPanel-style). */
    private function runRoot(string $cmd): bool
    {
        $runner = '/usr/local/bin/nexpanel-run';
        if (! is_file($runner)) {
            return false;
        }
        $r = \Illuminate\Support\Facades\Process::timeout(120)->input($cmd)->run([...$this->sudo(), $runner]);

        return $r->successful();
    }

    /** chown to the web user, but only inside /var/www (don't touch /etc etc.). */
    private function chownWww(string $path): string
    {
        return str_starts_with($path, '/var/www/')
            ? ' && chown -R www-data:www-data ' . escapeshellarg($path)
            : '';
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        if (is_dir($src)) {
            if (! @mkdir($dst, 0755, true) && ! is_dir($dst)) {
                throw new \RuntimeException('Cannot create directory');
            }
            foreach (array_diff(scandir($src) ?: [], ['.', '..']) as $item) {
                $this->recursiveCopy($src . '/' . $item, $dst . '/' . $item);
            }
        } elseif (! @copy($src, $dst)) {
            throw new \RuntimeException('Permission denied copying ' . basename($src));
        }
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $base): void
    {
        $zip->addEmptyDir($base);
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $full = $dir . '/' . $item;
            $local = $base . '/' . $item;
            is_dir($full) ? $this->addDirToZip($zip, $full, $local) : $zip->addFile($full, $local);
        }
    }

    private function dirSize(string $dir): int
    {
        $size = 0;
        foreach (array_diff(@scandir($dir) ?: [], ['.', '..']) as $item) {
            $p = $dir . '/' . $item;
            $size += is_dir($p) ? $this->dirSize($p) : (int) @filesize($p);
        }

        return $size;
    }

    private function listDirectory(string $path): array
    {
        $entries = @scandir($path);
        if ($entries === false) {
            return [];
        }
        $dirs = [];
        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path === '/' ? '/' . $entry : $path . '/' . $entry;
            $isDir = is_dir($full);
            $row = [
                'name'        => $entry,
                'path'        => $full,
                'type'        => $isDir ? 'directory' : 'file',
                'size'        => $isDir ? '-' : $this->humanSize(@filesize($full) ?: 0),
                'permissions' => $this->permString($full),
                'owner'       => $this->ownerName($full),
                'modified'    => date('Y-m-d H:i', @filemtime($full) ?: time()),
            ];
            $isDir ? $dirs[] = $row : $files[] = $row;
        }
        usort($dirs, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return array_merge($dirs, $files);
    }

    private function breadcrumbs(string $path): array
    {
        $crumbs = [['name' => '/', 'path' => '/']];
        $build = '';
        foreach (array_filter(explode('/', $path)) as $part) {
            $build .= '/' . $part;
            $crumbs[] = ['name' => $part, 'path' => $build];
        }

        return $crumbs;
    }

    private function diskUsage(string $path): array
    {
        $total = @disk_total_space($path) ?: 0;
        $free  = @disk_free_space($path) ?: 0;
        $used  = $total - $free;

        return [
            'total_h' => $this->humanSize($total),
            'used_h'  => $this->humanSize($used),
            'free_h'  => $this->humanSize($free),
            'percent' => $total > 0 ? (int) round($used / $total * 100) : 0,
        ];
    }

    private function permString(string $path): string
    {
        $perms = @fileperms($path);
        if ($perms === false) {
            return '----------';
        }
        $info = match ($perms & 0xF000) {
            0xC000 => 's', 0xA000 => 'l', 0x8000 => '-', 0x6000 => 'b',
            0x4000 => 'd', 0x2000 => 'c', 0x1000 => 'p', default => 'u',
        };
        foreach ([0x0100, 0x0080, 0x0040, 0x0020, 0x0010, 0x0008, 0x0004, 0x0002, 0x0001] as $i => $bit) {
            $chars = ['r', 'w', 'x'];
            $info .= ($perms & $bit) ? $chars[$i % 3] : '-';
        }

        return $info;
    }

    private function ownerName(string $path): string
    {
        $uid = @fileowner($path);
        if ($uid === false) {
            return '?';
        }
        if (function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid($uid);
            if ($info && isset($info['name'])) {
                return $info['name'];
            }
        }

        return (string) $uid;
    }

    private function humanSize(int|float $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }

    private function rrmdir(string $dir): bool
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }

        return @rmdir($dir);
    }

    /** Collapse the path and strip null bytes. Returns an absolute path. */
    private function normalize(string $path): string
    {
        $path = str_replace("\0", '', $path);
        if ($path === '') {
            return $this->defaultRoot();
        }
        $real = realpath($path);
        if ($real !== false) {
            return $real;
        }
        // Path may not exist yet (create/rename target) — collapse manually.
        $abs = str_starts_with($path, '/') ? $path : '/' . $path;
        $parts = [];
        foreach (explode('/', $abs) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $seg;
        }

        return '/' . implode('/', $parts);
    }

    private function defaultRoot(): string
    {
        foreach (['/var/www', '/home', getenv('HOME') ?: ''] as $candidate) {
            if ($candidate && is_dir($candidate)) {
                return $candidate;
            }
        }

        return '/';
    }
}
