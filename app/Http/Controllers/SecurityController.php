<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\FirewallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    private function firewall(Request $request): FirewallService
    {
        return new FirewallService($request->getPort());
    }

    public function index(Request $request)
    {
        $fw = $this->firewall($request);

        return view('security.index', [
            'available'       => $fw->available(),
            'protectedPorts'  => $fw->protectedPorts(),
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $fw = $this->firewall($request);
        if (! $fw->available()) {
            return response()->json([
                'available' => false,
                'enabled'   => false,
                'rules'     => [],
                'protected' => $fw->protectedPorts(),
            ]);
        }

        return response()->json([
            'available' => true,
            'enabled'   => $fw->enabled(),
            'rules'     => $fw->rules(),
            'protected' => $fw->protectedPorts(),
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate(['enabled' => 'required|boolean']);
        $fw = $this->firewall($request);
        $on = $request->boolean('enabled');

        $result = $on ? $fw->enable() : $fw->disable();
        if (! $result['ok']) {
            ActivityLogger::danger('firewall.toggle.failed', $result['error'] ?: 'ufw command failed');

            return response()->json(['error' => $result['error'] ?: 'Failed to change firewall state'], 500);
        }

        ActivityLogger::warning('firewall.toggle', $on ? 'Firewall enabled' : 'Firewall disabled');

        return response()->json(['ok' => true, 'enabled' => $fw->enabled()]);
    }

    public function addPort(Request $request): JsonResponse
    {
        $request->validate([
            'port'   => ['required', 'regex:/^\d{1,5}(:\d{1,5})?$/'],
            'proto'  => 'required|in:tcp,udp,both',
            'action' => 'required|in:allow,deny,reject',
            'from'   => 'nullable|string|max:64',
        ]);

        $detail = "{$request->input('action')} port {$request->input('port')}/{$request->input('proto')}"
            . ($request->input('from') ? " from {$request->input('from')}" : '');

        return $this->mutate(
            fn() => $this->firewall($request)->addPortRule(
                $request->input('port'),
                $request->input('proto'),
                $request->input('action'),
                trim((string) $request->input('from', '')),
            ),
            'firewall.rule.add',
            $detail,
        );
    }

    public function addIp(Request $request): JsonResponse
    {
        $request->validate([
            'ip'     => 'required|string|max:64',
            'action' => 'required|in:allow,deny,reject',
        ]);

        return $this->mutate(
            fn() => $this->firewall($request)->addIpRule($request->input('ip'), $request->input('action')),
            'firewall.rule.add',
            "{$request->input('action')} from {$request->input('ip')}",
        );
    }

    public function deleteRule(Request $request, int $n): JsonResponse
    {
        return $this->mutate(
            fn() => $this->firewall($request)->deleteRule($n),
            'firewall.rule.delete',
            "Deleted firewall rule #{$n}",
        );
    }

    /**
     * Run a ufw mutation. Guard violations become 422s, and the audit entry is
     * only written once ufw actually reports success.
     */
    private function mutate(callable $fn, string $action, string $detail): JsonResponse
    {
        try {
            $result = $fn();
        } catch (\RuntimeException $e) {
            ActivityLogger::danger('firewall.blocked', $e->getMessage());

            return response()->json(['error' => $e->getMessage()], 422);
        }

        if (! $result['ok']) {
            ActivityLogger::danger($action . '.failed', $detail . ' — ' . ($result['error'] ?: 'ufw command failed'));

            return response()->json(['error' => $result['error'] ?: 'ufw command failed'], 500);
        }

        ActivityLogger::warning($action, $detail);

        return response()->json(['ok' => true]);
    }
}
