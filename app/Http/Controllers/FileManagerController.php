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

    /** Content search budget — a search must never hang the panel. */
    private const SEARCH_MAX_FILES     = 5000;
    private const SEARCH_MAX_FILE_SIZE = 1024 * 1024; // 1 MB
    private const SEARCH_MAX_RESULTS   = 200;
    private const SEARCH_MAX_SECONDS   = 10;

    /** Directories skipped by default during a content search. */
    private const SEARCH_SKIP_DIRS = ['.git', 'node_modules', 'vendor', '.svn'];

    /** Never delete these, trash or not. */
    private const PROTECTED_PATHS = ['', '/', '/root', '/home', '/etc', '/var', '/usr', '/bin'];

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
            'root'        => $this->defaultRoot(),
            'breadcrumbs' => $this->breadcrumbs($path),
            'disk'        => $this->diskUsage($path),
            'error'       => $error,
            'trashCount'  => count($this->listTrash()),
            'isMock'      => false,
        ]);
    }

    /** Directory listing as JSON, so tabs can navigate without a page reload. */
    public function list(Request $request): JsonResponse
    {
        $path = $this->normalize($request->query('path', $this->defaultRoot()));

        if (! is_dir($path)) {
            return response()->json(['error' => "Directory not found: {$path}"], 404);
        }
        if (! is_readable($path)) {
            return response()->json(['error' => "Permission denied reading: {$path}"], 403);
        }

        return response()->json([
            'path'        => $path,
            'parent'      => $path === '/' ? null : dirname($path),
            'breadcrumbs' => $this->breadcrumbs($path),
            'files'       => $this->listDirectory($path),
            'disk'        => $this->diskUsage($path),
        ]);
    }

    /**
     * Grep-like search for text inside files under a directory. Bounded by file
     * count, file size, result count and wall-clock time.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path'    => 'required|string',
            'query'   => 'required|string|min:2|max:200',
            'include' => 'nullable|string|max:100',
            'skip'    => 'nullable|boolean',
        ]);

        $root = $this->normalize($data['path']);
        if (! is_dir($root)) {
            return response()->json(['error' => 'Directory not found'], 404);
        }

        $needle  = $data['query'];
        $include = trim((string) ($data['include'] ?? ''));
        $skipHeavy = $request->boolean('skip', true);

        $deadline = microtime(true) + self::SEARCH_MAX_SECONDS;
        $results = [];
        $scanned = 0;
        $truncated = false;

        foreach ($this->walk($root, $skipHeavy) as $file) {
            if (microtime(true) > $deadline || $scanned >= self::SEARCH_MAX_FILES) {
                $truncated = true;
                break;
            }
            if ($include !== '' && ! fnmatch($include, $file->getFilename())) {
                continue;
            }
            $size = $file->getSize();
            if ($size === false || $size > self::SEARCH_MAX_FILE_SIZE || $size === 0) {
                continue;
            }
            $scanned++;

            $content = @file_get_contents($file->getPathname());
            if ($content === false || str_contains($content, "\0") || ! str_contains($content, $needle)) {
                continue;
            }

            foreach (explode("\n", $content) as $i => $line) {
                if (! str_contains($line, $needle)) {
                    continue;
                }
                if (count($results) >= self::SEARCH_MAX_RESULTS) {
                    $truncated = true;
                    break 2;
                }
                $results[] = [
                    'path' => $file->getPathname(),
                    'name' => $file->getFilename(),
                    'line' => $i + 1,
                    'text' => mb_substr(trim($line), 0, 200),
                ];
            }
        }

        ActivityLogger::log('file.search', "Searched \"{$needle}\" under {$root} — " . count($results) . ' hit(s)');

        return response()->json([
            'results'   => $results,
            'scanned'   => $scanned,
            'truncated' => $truncated,
        ]);
    }

    /** Files under $root, optionally pruning heavy dependency directories. */
    private function walk(string $root, bool $skipHeavy): \Generator
    {
        $dir = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS);

        $filtered = new \RecursiveCallbackFilterIterator($dir, function (\SplFileInfo $current) use ($skipHeavy) {
            if ($current->isDir()) {
                return ! $skipHeavy || ! in_array($current->getFilename(), self::SEARCH_SKIP_DIRS, true);
            }

            return true;
        });

        $it = new \RecursiveIteratorIterator($filtered, \RecursiveIteratorIterator::LEAVES_ONLY);
        $it->setMaxDepth(20);

        foreach ($it as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->isReadable()) {
                yield $file;
            }
        }
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
        $request->validate(['path' => 'required|string', 'permanent' => 'nullable|boolean']);
        $path = $this->normalize($request->input('path'));

        if (! file_exists($path)) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ($this->isProtected($path)) {
            return response()->json(['error' => 'Refusing to delete a protected system path'], 403);
        }

        if (! $request->boolean('permanent')) {
            try {
                $this->moveToTrash($path);
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 403);
            }
            ActivityLogger::log('file.trash', "Moved {$path} to the recycle bin");

            return response()->json(['ok' => true, 'trashed' => true]);
        }

        $ok = is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        if (! $ok && file_exists($path)) {
            $ok = $this->runRoot('rm -rf ' . escapeshellarg($path));
        }
        if (! $ok && file_exists($path)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        ActivityLogger::log('file.delete', "Permanently deleted {$path}", 'warning');

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------ recycle bin

    public function trash(): JsonResponse
    {
        return response()->json(['items' => $this->listTrash()]);
    }

    public function trashRestore(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|string']);
        try {
            $meta = $this->trashMeta($data['id']);
            $target = $meta['original_path'];

            if (file_exists($target)) {
                return response()->json(['error' => basename($target) . ' already exists at the original location.'], 409);
            }
            if (! is_dir(dirname($target))) {
                return response()->json(['error' => 'The original directory no longer exists: ' . dirname($target)], 409);
            }

            $stored = $this->trashDir() . '/' . $data['id'];
            if (! @rename($stored, $target) && ! $this->runRoot('mv ' . escapeshellarg($stored) . ' ' . escapeshellarg($target))) {
                return response()->json(['error' => 'Permission denied restoring to ' . $target], 403);
            }
            @unlink($this->trashDir() . '/' . $data['id'] . '.json');
            ActivityLogger::log('file.trash.restore', "Restored {$target} from the recycle bin");

            return response()->json(['ok' => true, 'items' => $this->listTrash()]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function trashPurge(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|string']);
        try {
            $this->assertTrashId($data['id']);
            $stored = $this->trashDir() . '/' . $data['id'];
            if (is_dir($stored)) {
                $this->rrmdir($stored);
            } else {
                @unlink($stored);
            }
            if (file_exists($stored)) {
                $this->runRoot('rm -rf ' . escapeshellarg($stored));
            }
            @unlink($this->trashDir() . '/' . $data['id'] . '.json');
            ActivityLogger::log('file.trash.purge', "Permanently deleted {$data['id']} from the recycle bin", 'danger');

            return response()->json(['ok' => true, 'items' => $this->listTrash()]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /** Move a path into the trash directory, recording where it came from. */
    private function moveToTrash(string $path): void
    {
        $id = bin2hex(random_bytes(8));
        $stored = $this->trashDir() . '/' . $id;

        if (! @rename($path, $stored)) {
            // Different filesystem, or not ours to move — try as root, then copy.
            if (! $this->runRoot('mv ' . escapeshellarg($path) . ' ' . escapeshellarg($stored))) {
                if (is_dir($path)) {
                    $this->recursiveCopy($path, $stored);
                    $this->rrmdir($path);
                } elseif (! @copy($path, $stored) || ! @unlink($path)) {
                    throw new \RuntimeException('Permission denied moving to the recycle bin.');
                }
            }
        }
        if (! file_exists($stored)) {
            throw new \RuntimeException('Permission denied moving to the recycle bin.');
        }

        file_put_contents($this->trashDir() . '/' . $id . '.json', json_encode([
            'id'            => $id,
            'name'          => basename($path),
            'original_path' => $path,
            'type'          => is_dir($stored) ? 'directory' : 'file',
            'deleted_at'    => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT));
    }

    private function listTrash(): array
    {
        $out = [];
        foreach (glob($this->trashDir() . '/*.json') ?: [] as $metaFile) {
            $meta = json_decode((string) @file_get_contents($metaFile), true);
            if (! is_array($meta) || empty($meta['id']) || ! file_exists($this->trashDir() . '/' . $meta['id'])) {
                continue;
            }
            $stored = $this->trashDir() . '/' . $meta['id'];
            $meta['size'] = is_dir($stored)
                ? $this->humanSize($this->dirSize($stored))
                : $this->humanSize((int) @filesize($stored));
            $out[] = $meta;
        }
        usort($out, fn($a, $b) => strcmp($b['deleted_at'] ?? '', $a['deleted_at'] ?? ''));

        return $out;
    }

    private function trashMeta(string $id): array
    {
        $this->assertTrashId($id);
        $file = $this->trashDir() . '/' . $id . '.json';
        $meta = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($meta) || empty($meta['original_path'])) {
            throw new \RuntimeException('Recycle bin entry not found.');
        }

        return $meta;
    }

    private function trashDir(): string
    {
        $dir = storage_path('app/trash');
        @mkdir($dir, 0755, true);

        return $dir;
    }

    private function assertTrashId(string $id): void
    {
        if (! preg_match('/^[a-f0-9]{16}$/', $id)) {
            throw new \InvalidArgumentException('Invalid recycle bin entry.');
        }
    }

    private function isProtected(string $path): bool
    {
        $trimmed = rtrim($path, '/');
        if (in_array($trimmed, self::PROTECTED_PATHS, true)) {
            return true;
        }
        // Any filesystem root (Linux "/" or a Windows drive root like "C:\") —
        // a root is its own parent. Never let one of these be deleted.
        $real = realpath($path);
        if ($real !== false && dirname($real) === $real) {
            return true;
        }
        // Windows drive root written with forward slashes, e.g. "C:".
        if (preg_match('/^[A-Za-z]:$/', $trimmed)) {
            return true;
        }

        return false;
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
