<?php

namespace App\Http\Controllers;

use App\Services\ServerMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly ServerMetricsService $metrics) {}

    public function index(): View { return view('dashboard.index'); }

    public function metrics(): JsonResponse { return response()->json($this->metrics->getAll()); }
}
