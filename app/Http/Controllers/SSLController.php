<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class SSLController extends Controller
{
    public function index()
    {
        $available = $this->certbotExists();
        $certs = $available ? $this->scanCertificates() : [];

        $stats = [
            'total'    => count($certs),
            'valid'    => count(array_filter($certs, fn($c) => $c['status'] === 'valid')),
            'expiring' => count(array_filter($certs, fn($c) => $c['status'] === 'expiring_soon')),
            'expired'  => count(array_filter($certs, fn($c) => $c['status'] === 'expired')),
        ];

        return view('ssl.index', [
            'certs'         => $certs,
            'stats'         => $stats,
            'available'     => $available,
            'certbotExists' => $available,
            'isMock'        => false,
        ]);
    }

    public function issue(Request $request)
    {
        $data = $request->validate([
            'domain' => 'required|string|regex:/^[a-zA-Z0-9.*-]+$/|max:253',
        ]);
        if (! $this->certbotExists()) {
            return back()->with('error', 'certbot is not installed on this system.');
        }

        $useNginx = trim(Process::run('command -v nginx')->output()) !== '';
        $args = [...$this->sudo(), 'certbot', $useNginx ? '--nginx' : 'certonly', '-d', $data['domain'],
            '-n', '--agree-tos', '--register-unsafely-without-email'];
        if (! $useNginx) {
            $args[] = '--standalone';
        }

        $result = Process::timeout(180)->run($args);
        ActivityLogger::log('ssl.issue', "Issued certificate for {$data['domain']}");

        return $result->successful()
            ? back()->with('success', "Certificate issued for {$data['domain']}.")
            : back()->with('error', 'certbot failed: ' . trim($result->errorOutput() ?: $result->output()));
    }

    public function renew(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string|max:253']);
        if (! $this->certbotExists()) {
            return back()->with('error', 'certbot is not installed.');
        }
        $result = Process::timeout(180)->run([...$this->sudo(), 'certbot', 'renew', '--cert-name', $data['domain']]);
        ActivityLogger::log('ssl.renew', "Renewed certificate {$data['domain']}");

        return $result->successful()
            ? back()->with('success', "Renewal run for {$data['domain']}.")
            : back()->with('error', 'Renewal failed: ' . trim($result->errorOutput() ?: $result->output()));
    }

    public function revoke(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string|max:253']);
        if (! $this->certbotExists()) {
            return back()->with('error', 'certbot is not installed.');
        }
        $result = Process::timeout(120)->run([...$this->sudo(), 'certbot', 'delete', '--cert-name', $data['domain'], '-n']);
        ActivityLogger::log('ssl.revoke', "Revoked/deleted certificate {$data['domain']}", 'warning');

        return $result->successful()
            ? back()->with('success', "Certificate {$data['domain']} deleted.")
            : back()->with('error', 'Delete failed: ' . trim($result->errorOutput() ?: $result->output()));
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

    private function certbotExists(): bool
    {
        return trim(Process::run('command -v certbot')->output()) !== '';
    }

    /**
     * List certificates via `certbot certificates` (uses the sudo-allowed
     * certbot binary, so it works as the www-data web user without needing
     * read access to /etc/letsencrypt).
     */
    private function scanCertificates(): array
    {
        $res = Process::timeout(20)->run([...$this->sudo(), 'certbot', 'certificates']);
        if (! $res->successful()) {
            return [];
        }

        return $this->parseCertbotOutput($res->output());
    }

    private function parseCertbotOutput(string $output): array
    {
        $certs = [];
        // Split into per-certificate blocks.
        $blocks = preg_split('/Certificate Name:\s*/', $output);
        foreach ($blocks as $block) {
            if (! preg_match('/^([^\s]+)/', trim($block), $nameM)) {
                continue;
            }
            $name = $nameM[1];
            $domains = preg_match('/Domains:\s*(.+)/', $block, $m) ? preg_split('/\s+/', trim($m[1])) : [$name];

            $daysLeft = 0;
            $expiry = '—';
            $statusWord = 'VALID';
            if (preg_match('/Expiry Date:\s*([0-9-]+)[^\(]*\((\w+)[:,]?\s*([0-9]+)?\s*day/i', $block, $e)) {
                $expiry = $e[1];
                $statusWord = strtoupper($e[2]);
                $daysLeft = isset($e[3]) ? (int) $e[3] : 0;
            }

            $status = $statusWord === 'EXPIRED' || $statusWord === 'INVALID'
                ? 'expired'
                : ($daysLeft <= 30 ? 'expiring_soon' : 'valid');

            $isWildcard = (bool) array_filter($domains, fn($d) => str_starts_with($d, '*.'));

            $certs[] = [
                'domain'     => $domains[0] ?? $name,
                'san'        => $domains,
                'issuer'     => "Let's Encrypt",
                'type'       => $isWildcard ? 'Wildcard' : 'Single',
                'issued'     => '—',
                'expiry'     => $expiry,
                'days_left'  => max($daysLeft, 0),
                'status'     => $status,
                'auto_renew' => true,
            ];
        }
        usort($certs, fn($a, $b) => $a['days_left'] <=> $b['days_left']);

        return $certs;
    }
}
