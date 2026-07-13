<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ServerMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_exposes_primary_ip(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'cpu', 'ram', 'disk', 'network', 'ip', 'uptime', 'hostname', 'os', 'services',
        ]);
        $this->assertIsString($response->json('ip'));
    }

    public function test_primary_ip_is_never_loopback(): void
    {
        $ip = (new ServerMetricsService())->getPrimaryIp();

        // Either a real address or the explicit N/A fallback — never loopback,
        // and never an empty string (so the UI always has something to show).
        $this->assertNotSame('', $ip);
        $this->assertNotSame('127.0.0.1', $ip);
    }
}
