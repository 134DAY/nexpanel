<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class SSLController extends Controller
{
    private string $liveDir    = '/etc/letsencrypt/live';
    private string $renewalDir = '/etc/letsencrypt/renewal';

    public function index()
    {
        $available = $this->available();
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
            'certbotExists' => $this->certbotExists(),
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
        $args = ['certbot', $useNginx ? '--nginx' : 'certonly', '-d', $data['domain'],
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
        $result = Process::timeout(180)->run(['certbot', 'renew', '--cert-name', $data['domain']]);
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
        $result = Process::timeout(120)->run(['certbot', 'delete', '--cert-name', $data['domain'], '-n']);
        ActivityLogger::log('ssl.revoke', "Revoked/deleted certificate {$data['domain']}", 'warning');

        return $result->successful()
            ? back()->with('success', "Certificate {$data['domain']} deleted.")
            : back()->with('error', 'Delete failed: ' . trim($result->errorOutput() ?: $result->output()));
    }

    // ---------------------------------------------------------------- helpers

    private function available(): bool
    {
        return is_dir($this->liveDir) || $this->certbotExists();
    }

    private function certbotExists(): bool
    {
        return trim(Process::run('command -v certbot')->output()) !== '';
    }

    private function scanCertificates(): array
    {
        if (! is_dir($this->liveDir)) {
            return [];
        }
        $certs = [];
        foreach (array_diff(scandir($this->liveDir) ?: [], ['.', '..', 'README']) as $name) {
            $certFile = $this->liveDir . '/' . $name . '/cert.pem';
            if (! is_file($certFile)) {
                continue;
            }
            $parsed = $this->parseCert($certFile);
            if ($parsed === null) {
                continue;
            }
            $certs[] = array_merge($parsed, [
                'auto_renew' => is_file($this->renewalDir . '/' . $name . '.conf'),
            ]);
        }
        usort($certs, fn($a, $b) => $a['days_left'] <=> $b['days_left']);

        return $certs;
    }

    private function parseCert(string $certFile): ?array
    {
        $result = Process::run([
            'openssl', 'x509', '-in', $certFile, '-noout',
            '-subject', '-issuer', '-startdate', '-enddate', '-ext', 'subjectAltName',
        ]);
        if (! $result->successful()) {
            return null;
        }
        $out = $result->output();

        $cn = preg_match('/subject=.*?CN\s*=\s*([^,\n]+)/', $out, $m) ? trim($m[1]) : basename(dirname($certFile));
        $issuer = preg_match('/issuer=.*?O\s*=\s*([^,\n]+)/', $out, $m) ? trim($m[1]) : 'Unknown';
        $notBefore = preg_match('/notBefore=(.+)/', $out, $m) ? strtotime(trim($m[1])) : null;
        $notAfter  = preg_match('/notAfter=(.+)/', $out, $m) ? strtotime(trim($m[1])) : null;

        $san = [];
        if (preg_match('/DNS:.*/', $out, $m)) {
            foreach (explode(',', $m[0]) as $entry) {
                $entry = trim(str_replace('DNS:', '', $entry));
                if ($entry !== '') {
                    $san[] = $entry;
                }
            }
        }
        $isWildcard = (bool) array_filter($san, fn($d) => str_starts_with($d, '*.'));
        $daysLeft = $notAfter ? (int) ceil(($notAfter - time()) / 86400) : 0;

        $status = $daysLeft <= 0 ? 'expired' : ($daysLeft <= 30 ? 'expiring_soon' : 'valid');

        return [
            'domain'    => $cn,
            'san'       => $san,
            'issuer'    => $issuer,
            'type'      => $isWildcard ? 'Wildcard' : 'Single',
            'issued'    => $notBefore ? date('Y-m-d', $notBefore) : '—',
            'expiry'    => $notAfter ? date('Y-m-d', $notAfter) : '—',
            'days_left' => max($daysLeft, 0),
            'status'    => $status,
        ];
    }
}
