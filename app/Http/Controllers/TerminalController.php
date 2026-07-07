<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class TerminalController extends Controller
{
    private const MARKER = '___NEXPWD___';

    public function index()
    {
        $home = getenv('HOME') ?: '/';

        return view('terminal.index', [
            'user'   => trim(Process::run('whoami')->output()) ?: 'user',
            'host'   => trim(Process::run('hostname')->output()) ?: 'server',
            'cwd'    => $home,
            'isMock' => false,
        ]);
    }

    /**
     * Execute a command in a shell. The client sends the current working
     * directory back on each call so `cd` persists across commands.
     */
    public function exec(Request $request): JsonResponse
    {
        $request->validate([
            'command' => 'required|string|max:4000',
            'cwd'     => 'nullable|string',
        ]);

        $command = $request->input('command');
        $cwd     = $request->input('cwd') ?: (getenv('HOME') ?: '/');

        // cd runs in the main shell so pwd updates; stderr is merged into stdout.
        $script = <<<'BASH'
        cd "$NEX_CWD" 2>/dev/null || cd /
        eval "$NEX_CMD" 2>&1
        __rc=$?
        printf '\n%s%s\t%s\n' "___NEXPWD___" "$(pwd)" "$__rc"
        BASH;

        $result = Process::timeout(30)
            ->env(['NEX_CWD' => $cwd, 'NEX_CMD' => $command])
            ->run(['bash', '-c', $script]);

        $output = $result->output();
        $newCwd = $cwd;
        $exit   = $result->exitCode();

        $pos = strrpos($output, "\n" . self::MARKER);
        if ($pos !== false) {
            $meta   = substr($output, $pos + strlen("\n" . self::MARKER));
            $output = substr($output, 0, $pos);
            [$parsedCwd, $parsedRc] = array_pad(explode("\t", trim($meta), 2), 2, null);
            if ($parsedCwd) {
                $newCwd = $parsedCwd;
            }
            if ($parsedRc !== null && $parsedRc !== '') {
                $exit = (int) $parsedRc;
            }
        }

        ActivityLogger::log('terminal.exec', $command, $exit === 0 ? 'info' : 'warning');

        return response()->json([
            'output' => $output,
            'cwd'    => $newCwd,
            'exit'   => $exit,
        ]);
    }
}
