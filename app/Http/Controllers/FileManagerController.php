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
            return response()->json(['error' => 'Permission denied writing file'], 403);
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
            return response()->json(['error' => 'Permission denied'], 403);
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
        if (! $ok) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        ActivityLogger::log('file.delete', "Deleted {$path}");

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string', 'file' => 'required|file']);
        $dir = $this->normalize($request->input('path'));

        if (! is_dir($dir) || ! is_writable($dir)) {
            return response()->json(['error' => 'Destination not writable'], 403);
        }
        $file = $request->file('file');
        $name = basename($file->getClientOriginalName());
        try {
            $file->move($dir, $name);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
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

    // ---------------------------------------------------------------- helpers

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
