<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\NginxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function __construct(private readonly NginxService $nginx) {}

    public function index()
    {
        $available = $this->nginx->available();
        $sites = $available ? $this->nginx->sites() : [];

        $stats = [
            'total'       => count($sites),
            'active'      => count(array_filter($sites, fn($s) => $s['status'] === 'active')),
            'ssl_enabled' => count(array_filter($sites, fn($s) => $s['ssl'])),
            'disabled'    => count(array_filter($sites, fn($s) => $s['status'] === 'disabled')),
        ];

        return view('websites.index', [
            'sites'     => $sites,
            'stats'     => $stats,
            'available' => $available,
            'isMock'    => false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain'        => 'required|string|regex:/^[a-zA-Z0-9.-]+$/|max:253',
            'document_root' => 'nullable|string|max:512',
            'php_version'   => 'nullable|string|max:8',
            'ssl'           => 'nullable',
        ]);

        if (! $this->nginx->available()) {
            return back()->with('error', 'Nginx is not installed on this system.');
        }

        try {
            $result = $this->nginx->createSite(
                domain: $data['domain'],
                docRoot: $data['document_root'] ?? "/var/www/{$data['domain']}/public",
                phpVersion: $data['php_version'] ?? '8.2',
                withSsl: (bool) $request->boolean('ssl'),
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed: ' . $e->getMessage());
        }

        ActivityLogger::log('website.create', "Created vhost {$data['domain']}");

        return redirect()->route('websites.index')->with('success', $result);
    }

    public function toggle(Request $request, string $site): JsonResponse
    {
        if (! $this->nginx->available()) {
            return response()->json(['error' => 'Nginx not available'], 400);
        }
        try {
            $enabled = $this->nginx->toggleSite($site);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        ActivityLogger::log('website.toggle', ($enabled ? 'Enabled' : 'Disabled') . " vhost {$site}");

        return response()->json(['ok' => true, 'enabled' => $enabled]);
    }

    public function config(Request $request, string $site): JsonResponse
    {
        $conf = $this->nginx->readConfig($site);
        if ($conf === null) {
            return response()->json(['error' => 'Config not found'], 404);
        }

        return response()->json(['config' => $conf, 'site' => $site]);
    }

    public function destroy(Request $request, string $site): JsonResponse
    {
        if (! $this->nginx->available()) {
            return response()->json(['error' => 'Nginx not available'], 400);
        }
        try {
            $this->nginx->deleteSite($site);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        ActivityLogger::log('website.delete', "Deleted vhost {$site}", 'warning');

        return response()->json(['ok' => true]);
    }
}
