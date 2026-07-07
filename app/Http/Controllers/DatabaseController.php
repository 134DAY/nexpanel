<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\MysqlService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseController extends Controller
{
    public function __construct(private readonly MysqlService $mysql) {}

    public function index()
    {
        $available = $this->mysql->available();

        return view('databases.index', [
            'databases' => $available ? $this->mysql->databases() : [],
            'users'     => $available ? $this->mysql->users() : [],
            'totalSize' => $available ? $this->mysql->totalSizeHuman() : '—',
            'available' => $available,
            'connError' => $available ? null : $this->mysql->error(),
            'isMock'    => false,
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->mysql->available()) {
            return back()->with('error', 'Cannot connect to MySQL.');
        }

        try {
            // A "name" field means create-database; "username" means create-user.
            if ($request->filled('username')) {
                $data = $request->validate([
                    'username' => 'required|string|max:32',
                    'password' => 'required|string|max:255',
                ]);
                $this->mysql->createUser($data['username'], $data['password']);
                ActivityLogger::log('database.user.create', "Created MySQL user {$data['username']}");

                return back()->with('success', "User '{$data['username']}' created.");
            }

            $data = $request->validate([
                'name'    => 'required|string|max:64',
                'charset' => 'nullable|string|max:16',
            ]);
            $this->mysql->createDatabase($data['name'], $data['charset'] ?? 'utf8mb4');
            ActivityLogger::log('database.create', "Created database {$data['name']}");

            return back()->with('success', "Database '{$data['name']}' created.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
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
