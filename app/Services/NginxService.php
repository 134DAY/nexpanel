<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Thin wrapper around an Nginx installation using the Debian/Ubuntu
 * sites-available / sites-enabled convention.
 */
class NginxService
{
    private string $available = '/etc/nginx/sites-available';
    private string $enabled   = '/etc/nginx/sites-enabled';

    public function available(): bool
    {
        return $this->binary() !== '' && is_dir($this->available);
    }

    /** All virtual hosts defined under sites-available. */
    public function sites(): array
    {
        if (! is_dir($this->available)) {
            return [];
        }
        $sites = [];
        foreach (array_diff(scandir($this->available) ?: [], ['.', '..']) as $file) {
            $path = $this->available . '/' . $file;
            if (! is_file($path)) {
                continue;
            }
            $conf = (string) @file_get_contents($path);
            $isEnabled = file_exists($this->enabled . '/' . $file);
            $sites[] = [
                'id'            => $file,
                'domain'        => $this->parseServerName($conf) ?: $file,
                'document_root' => $this->parseRoot($conf) ?: '—',
                'php_version'   => $this->parsePhp($conf),
                'ssl'           => $this->parseSsl($conf),
                'status'        => $isEnabled ? 'active' : 'disabled',
            ];
        }
        usort($sites, fn($a, $b) => strcmp($a['domain'], $b['domain']));

        return $sites;
    }

    public function readConfig(string $site): ?string
    {
        $path = $this->available . '/' . basename($site);
        if (! is_file($path)) {
            return null;
        }

        return (string) @file_get_contents($path);
    }

    /** Create a vhost, enable it, reload nginx. Returns a status message. */
    public function createSite(string $domain, string $docRoot, string $phpVersion, bool $withSsl): string
    {
        $name = basename($domain);
        $confPath = $this->available . '/' . $name;
        if (file_exists($confPath)) {
            throw new \RuntimeException("A site named {$name} already exists.");
        }

        $config = $this->buildConfig($domain, $docRoot, $phpVersion);
        if (@file_put_contents($confPath, $config) === false) {
            throw new \RuntimeException('Cannot write config (need root permission on /etc/nginx).');
        }
        @mkdir($docRoot, 0755, true);

        // Enable via symlink.
        $link = $this->enabled . '/' . $name;
        if (! file_exists($link)) {
            @symlink($confPath, $link);
        }

        $this->testAndReload();

        $message = "Website {$domain} created and enabled.";
        if ($withSsl) {
            $message .= ' ' . $this->requestCertificate($domain);
        }

        return $message;
    }

    /** Toggle enabled state via the sites-enabled symlink. Returns new state. */
    public function toggleSite(string $site): bool
    {
        $name = basename($site);
        $confPath = $this->available . '/' . $name;
        if (! is_file($confPath)) {
            throw new \RuntimeException('Site not found.');
        }
        $link = $this->enabled . '/' . $name;

        if (file_exists($link)) {
            @unlink($link);
            $enabled = false;
        } else {
            if (! @symlink($confPath, $link)) {
                throw new \RuntimeException('Cannot create symlink (need root permission).');
            }
            $enabled = true;
        }
        $this->testAndReload();

        return $enabled;
    }

    public function deleteSite(string $site): void
    {
        $name = basename($site);
        @unlink($this->enabled . '/' . $name);
        $confPath = $this->available . '/' . $name;
        if (is_file($confPath) && ! @unlink($confPath)) {
            throw new \RuntimeException('Cannot delete config (need root permission).');
        }
        $this->testAndReload();
    }

    // ---------------------------------------------------------------- helpers

    private function binary(): string
    {
        return trim(Process::run('command -v nginx')->output());
    }

    private function testAndReload(): void
    {
        $test = Process::run(['nginx', '-t']);
        if (! $test->successful()) {
            throw new \RuntimeException('nginx config test failed: ' . trim($test->errorOutput() ?: $test->output()));
        }
        // Prefer systemctl reload, fall back to `nginx -s reload`.
        $reload = Process::run(['systemctl', 'reload', 'nginx']);
        if (! $reload->successful()) {
            Process::run(['nginx', '-s', 'reload']);
        }
    }

    private function requestCertificate(string $domain): string
    {
        if (trim(Process::run('command -v certbot')->output()) === '') {
            return 'SSL skipped: certbot is not installed.';
        }
        $result = Process::timeout(120)->run([
            'certbot', '--nginx', '-d', $domain, '-n', '--agree-tos',
            '--register-unsafely-without-email', '--redirect',
        ]);

        return $result->successful()
            ? 'SSL certificate issued.'
            : 'SSL request failed: ' . trim($result->errorOutput() ?: $result->output());
    }

    private function buildConfig(string $domain, string $docRoot, string $phpVersion): string
    {
        $sock = "/run/php/php{$phpVersion}-fpm.sock";

        return <<<NGINX
        server {
            listen 80;
            listen [::]:80;
            server_name {$domain};

            root {$docRoot};
            index index.php index.html index.htm;

            location / {
                try_files \$uri \$uri/ /index.php?\$query_string;
            }

            location ~ \.php\$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass unix:{$sock};
            }

            location ~ /\.(?!well-known).* {
                deny all;
            }

            access_log /var/log/nginx/{$domain}.access.log;
            error_log  /var/log/nginx/{$domain}.error.log;
        }

        NGINX;
    }

    private function parseServerName(string $conf): ?string
    {
        if (preg_match('/^\s*server_name\s+([^;]+);/m', $conf, $m)) {
            $names = preg_split('/\s+/', trim($m[1]));
            return $names[0] ?? null;
        }

        return null;
    }

    private function parseRoot(string $conf): ?string
    {
        return preg_match('/^\s*root\s+([^;]+);/m', $conf, $m) ? trim($m[1]) : null;
    }

    private function parsePhp(string $conf): string
    {
        return preg_match('/php([0-9]+\.[0-9]+)-fpm/', $conf, $m) ? $m[1] : '—';
    }

    private function parseSsl(string $conf): bool
    {
        return (bool) preg_match('/listen\s+443|ssl_certificate\s/', $conf);
    }
}
