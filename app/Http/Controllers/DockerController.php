<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\DockerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DockerController extends Controller
{
    public function __construct(private readonly DockerService $docker) {}

    public function index()
    {
        $available = $this->docker->available();
        $running = $available && $this->docker->running();

        return view('docker.index', [
            'available' => $available,
            'running'   => $running,
            'version'   => $running ? $this->docker->version() : null,
            'stats'     => $running ? $this->docker->stats() : ['total' => 0, 'running' => 0, 'stopped' => 0, 'images' => 0],
        ]);
    }

    /** JSON: current containers + images (polled by the page). */
    public function data(): JsonResponse
    {
        if (! $this->docker->available() || ! $this->docker->running()) {
            return response()->json(['containers' => [], 'images' => [], 'stats' => ['total' => 0, 'running' => 0, 'stopped' => 0, 'images' => 0]]);
        }

        return response()->json([
            'containers' => $this->docker->containers(),
            'images'     => $this->docker->images(),
            'stats'      => $this->docker->stats(),
        ]);
    }

    public function action(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'     => 'required|string|max:128',
            'action' => 'required|in:' . implode(',', DockerService::CONTAINER_ACTIONS),
        ]);

        $res = $this->docker->containerAction($data['id'], $data['action']);
        ActivityLogger::log(
            'docker.' . $data['action'],
            ucfirst($data['action']) . " container {$data['id']}",
            $data['action'] === 'remove' ? 'warning' : 'info'
        );

        return response()->json($res, $res['ok'] ? 200 : 500);
    }

    public function logs(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|string|max:128']);

        return response()->json(['logs' => $this->docker->logs($data['id'])]);
    }

    public function pull(Request $request): JsonResponse
    {
        $data = $request->validate(['image' => 'required|string|max:200']);
        $res = $this->docker->pull($data['image']);
        ActivityLogger::log('docker.pull', "Pulled image {$data['image']}", $res['ok'] ? 'info' : 'warning');

        return response()->json($res, $res['ok'] ? 200 : 500);
    }

    public function removeImage(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|string|max:128']);
        $res = $this->docker->removeImage($data['id']);
        ActivityLogger::log('docker.image.remove', "Removed image {$data['id']}", 'warning');

        return response()->json($res, $res['ok'] ? 200 : 500);
    }
}
