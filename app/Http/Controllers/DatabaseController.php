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
        $request->validate(['file' => 'required|file|mimes:sql,txt|max:51200']);
        if (! $this->mysql->available()) {
            return back()->with('error', 'Cannot connect to MySQL.');
        }
        try {
            $this->mysql->importSql($name, $request->file('file')->getRealPath());
            ActivityLogger::log('database.import', "Imported SQL into {$name}");

            return back()->with('success', "SQL imported into '{$name}'.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
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
