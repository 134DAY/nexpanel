<?php

namespace App\Http\Controllers;

use App\Models\DbCredential;
use App\Services\ActivityLogger;
use App\Services\MysqlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseController extends Controller
{
    public function __construct(private readonly MysqlService $mysql) {}

    public function index()
    {
        $available = $this->mysql->available();
        $databases = $available ? $this->mysql->databases() : [];

        // Attach the stored per-database user + password (aaPanel-style).
        $creds = DbCredential::pluck('password', 'db_name');
        $users = DbCredential::pluck('username', 'db_name');
        $databases = array_map(function ($db) use ($creds, $users) {
            $db['username'] = $users[$db['name']] ?? null;
            $db['password'] = isset($creds[$db['name']]) ? $creds[$db['name']] : null;

            return $db;
        }, $databases);

        return view('databases.index', [
            'databases'  => $databases,
            'users'      => $available ? $this->mysql->users() : [],
            'totalSize'  => $available ? $this->mysql->totalSizeHuman() : '—',
            'available'  => $available,
            'connError'  => $available ? null : $this->mysql->error(),
            'phpmyadmin' => is_dir('/usr/share/phpmyadmin'),
            'isMock'     => false,
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->mysql->available()) {
            return back()->with('error', 'Cannot connect to MySQL.');
        }

        try {
            // Standalone user (Users tab).
            if ($request->filled('username') && ! $request->filled('name')) {
                $data = $request->validate([
                    'username' => 'required|string|max:32',
                    'password' => 'required|string|max:255',
                ]);
                $this->mysql->createUser($data['username'], $data['password']);
                ActivityLogger::log('database.user.create', "Created MySQL user {$data['username']}");

                return back()->with('success', "User '{$data['username']}' created.");
            }

            // Database + paired user (aaPanel-style).
            $data = $request->validate([
                'name'     => 'required|string|max:64',
                'charset'  => 'nullable|string|max:16',
                'username' => 'nullable|string|max:32',
                'password' => 'nullable|string|max:255',
            ]);
            $user = ($data['username'] ?? null) ?: $data['name'];
            $password = ($data['password'] ?? null) ?: Str::random(16);

            $this->mysql->createDatabaseWithUser($data['name'], $user, $password, $data['charset'] ?? 'utf8mb4');
            DbCredential::updateOrCreate(['db_name' => $data['name']], ['username' => $user, 'password' => $password]);
            ActivityLogger::log('database.create', "Created database {$data['name']} + user {$user}");

            return back()->with('success', "Database '{$data['name']}' created with user '{$user}' (password: {$password}).");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function changePassword(Request $request, string $name)
    {
        $data = $request->validate(['password' => 'required|string|min:4|max:255']);
        $cred = DbCredential::where('db_name', $name)->first();
        if (! $cred) {
            return back()->with('error', "No stored user for database '{$name}'.");
        }
        try {
            $this->mysql->changeUserPassword($cred->username, $data['password']);
            $cred->update(['password' => $data['password']]);
            ActivityLogger::log('database.user.password', "Changed password for {$cred->username}");

            return back()->with('success', "Password updated for user '{$cred->username}'.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function import(Request $request, string $name)
    {
        $request->validate(['file' => 'required|file|mimes:sql,txt,gz,zip,tgz,gzip|max:512000']);
        if (! $this->mysql->available()) {
            return back()->with('error', 'Cannot connect to MySQL.');
        }
        try {
            $file = $request->file('file');
            $this->mysql->importSql($name, $file->getRealPath(), $file->getClientOriginalName());
            ActivityLogger::log('database.import', "Imported {$file->getClientOriginalName()} into {$name}");

            return back()->with('success', "Imported into '{$name}'.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    // -------- backup management (aaPanel-style) --------

    public function backups(Request $request, string $name): JsonResponse
    {
        return response()->json(['backups' => $this->mysql->listBackups($name)]);
    }

    public function createBackup(Request $request, string $name): JsonResponse
    {
        if (! $this->mysql->available()) {
            return response()->json(['error' => 'MySQL not available'], 400);
        }
        try {
            $file = $this->mysql->createBackup($name);
            ActivityLogger::log('database.backup', "Created backup of {$name}: " . basename($file));

            return response()->json(['ok' => true, 'backups' => $this->mysql->listBackups($name)]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function restoreBackup(Request $request, string $name): JsonResponse
    {
        $data = $request->validate(['file' => 'required|string']);
        try {
            $this->mysql->restoreBackup($name, $data['file']);
            ActivityLogger::log('database.restore', "Restored {$name} from {$data['file']}", 'warning');

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function deleteBackup(Request $request, string $name): JsonResponse
    {
        $data = $request->validate(['file' => 'required|string']);
        $this->mysql->deleteBackup($name, $data['file']);
        ActivityLogger::log('database.backup.delete', "Deleted backup {$data['file']} of {$name}");

        return response()->json(['ok' => true, 'backups' => $this->mysql->listBackups($name)]);
    }

    public function downloadBackup(Request $request, string $name): BinaryFileResponse
    {
        $path = $this->mysql->backupPath($name, (string) $request->query('file'));
        abort_unless($path, 404, 'Backup not found');

        return response()->download($path);
    }

    public function permission(Request $request, string $name): JsonResponse
    {
        $cred = DbCredential::where('db_name', $name)->first();
        if (! $cred) {
            return response()->json(['error' => 'No paired user for this database.'], 404);
        }

        return response()->json([
            'username' => $cred->username,
            'grants'   => $this->mysql->userGrants($cred->username),
            'databases' => array_column($this->mysql->databases(), 'name'),
        ]);
    }

    public function grant(Request $request, string $name): JsonResponse
    {
        $data = $request->validate([
            'db'     => 'required|string|max:64',
            'action' => 'required|in:grant,revoke',
        ]);
        $cred = DbCredential::where('db_name', $name)->first();
        if (! $cred) {
            return response()->json(['error' => 'No paired user.'], 404);
        }
        try {
            $data['action'] === 'grant'
                ? $this->mysql->grantUserOnDb($cred->username, $data['db'])
                : $this->mysql->revokeUserOnDb($cred->username, $data['db']);
            ActivityLogger::log('database.grant', "{$data['action']} {$cred->username} on {$data['db']}", 'warning');

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Auto-login to phpMyAdmin (aaPanel-style). Writes a one-time signon token,
     * then redirects into phpMyAdmin logged in as the right user: the db's own
     * paired user when $name is given, otherwise the admin user.
     */
    public function pmaLogin(Request $request, ?string $name = null)
    {
        if ($name) {
            $cred = DbCredential::where('db_name', $name)->first();
            if (! $cred) {
                return redirect()->route('databases.index')->with('error', "No stored user for '{$name}'. Use the admin phpMyAdmin button.");
            }
            $user = $cred->username;
            $pass = $cred->password;
        } else {
            $user = (string) config('nexpanel.db_admin.user', 'root');
            $pass = (string) config('nexpanel.db_admin.password', '');
        }

        $token = bin2hex(random_bytes(16));
        $dir = '/var/lib/phpmyadmin/signon';
        @mkdir($dir, 0700, true);
        @file_put_contents("{$dir}/{$token}.json", json_encode(['user' => $user, 'pass' => $pass, 't' => time()]));
        @chmod("{$dir}/{$token}.json", 0600);

        $url = '/pma-signon/signon.php?token=' . $token;
        if ($name) {
            $url .= '&db=' . rawurlencode($name);
        }

        return redirect($url);
    }

    public function backup(Request $request, string $name): BinaryFileResponse
    {
        abort_unless($this->mysql->available(), 400, 'MySQL not available');
        $file = $this->mysql->dumpToFile($name);
        ActivityLogger::log('database.backup', "Backed up database {$name}");

        return response()->download($file)->deleteFileAfterSend(true);
    }

    public function destroy(Request $request, string $name)
    {
        if (! $this->mysql->available()) {
            return back()->with('error', 'Cannot connect to MySQL.');
        }
        try {
            $this->mysql->dropDatabase($name);
            // Also drop the paired user + stored credential.
            if ($cred = DbCredential::where('db_name', $name)->first()) {
                try {
                    $this->mysql->dropUser($cred->username, 'localhost');
                    $this->mysql->dropUser($cred->username, '127.0.0.1');
                } catch (\Throwable $e) {
                    // ignore
                }
                $cred->delete();
            }
            ActivityLogger::log('database.drop', "Dropped database {$name}", 'warning');

            return back()->with('success', "Database '{$name}' dropped.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function dropUser(Request $request)
    {
        if (! $this->mysql->available()) {
            return back()->with('error', 'Cannot connect to MySQL.');
        }
        $data = $request->validate([
            'username' => 'required|string|max:32',
            'host'     => 'nullable|string|max:60',
        ]);
        try {
            $this->mysql->dropUser($data['username'], $data['host'] ?? 'localhost');
            ActivityLogger::log('database.user.drop', "Dropped MySQL user {$data['username']}", 'warning');

            return back()->with('success', "User '{$data['username']}' dropped.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
