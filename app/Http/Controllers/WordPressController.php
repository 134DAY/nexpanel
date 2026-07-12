<?php

namespace App\Http\Controllers;

use App\Models\DbCredential;
use App\Services\ActivityLogger;
use App\Services\MysqlService;
use App\Services\NginxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * One-click WordPress installer. Orchestrates the existing Nginx + MySQL
 * services: creates a database and a dedicated user, provisions an nginx
 * vhost, downloads WordPress core and writes a ready-to-run wp-config.php.
 */
class WordPressController extends Controller
{
    public function __construct(
        private readonly NginxService $nginx,
        private readonly MysqlService $mysql,
    ) {}

    public function index()
    {
        return view('wordpress.index', [
            'nginxOk' => $this->nginx->available(),
            'mysqlOk' => $this->mysql->available(),
            'sites'   => $this->installedSites(),
            'phpVersions' => $this->phpVersions(),
        ]);
    }

    public function install(Request $request)
    {
        $data = $request->validate([
            'domain'      => 'required|string|regex:/^[a-zA-Z0-9.-]+$/|max:253',
            'php_version' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'title'       => 'required|string|max:100',
            'admin_user'  => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_]+$/'],
            'admin_pass'  => 'required|string|min:6|max:255',
            'admin_email' => 'required|email|max:150',
            'ssl'         => 'nullable',
        ]);

        if (! $this->nginx->available() || ! $this->mysql->available()) {
            return back()->with('error', 'WordPress needs both Nginx and MySQL available.');
        }

        $domain  = $data['domain'];
        $docRoot = "/var/www/{$domain}";
        if (is_dir($docRoot) && is_file("{$docRoot}/wp-load.php")) {
            return back()->with('error', "WordPress already appears to be installed at {$docRoot}.");
        }

        // Derive a safe db name / user from the domain.
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $domain));
        $slug = trim(substr($slug, 0, 40), '_') ?: 'site';
        $dbName = 'wp_' . $slug;
        $dbUser = substr('wp_' . $slug, 0, 32);
        $dbPass = Str::random(20);

        try {
            // 1) Database + dedicated user.
            $this->mysql->createDatabaseWithUser($dbName, $dbUser, $dbPass, 'utf8mb4');
            DbCredential::updateOrCreate(['db_name' => $dbName], ['username' => $dbUser, 'password' => $dbPass]);

            // 2) Nginx vhost pointing at the WordPress dir.
            $this->nginx->createSite($domain, $docRoot, $data['php_version'], false);

            // 3) Download core + write wp-config.php (as root, atomically).
            $this->deployWordPress($docRoot, $dbName, $dbUser, $dbPass);

            // 4) Optional SSL.
            $sslMsg = '';
            if ($request->boolean('ssl')) {
                $sslMsg = ' ' . $this->nginx->createSite($domain, $docRoot, $data['php_version'], true);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'WordPress install failed: ' . $e->getMessage());
        }

        ActivityLogger::log('wordpress.install', "Installed WordPress at {$domain} (db {$dbName})");

        return back()->with('success',
            "WordPress installed at {$domain}. Finish setup in your browser: http://{$domain}/wp-admin/install.php " .
            "— login {$data['admin_user']} / (the password you chose). DB: {$dbName}.{$sslMsg}");
    }

    // ---------------------------------------------------------------- internals

    /** Download WordPress core into $docRoot and drop a ready wp-config.php. */
    private function deployWordPress(string $docRoot, string $db, string $user, string $pass): void
    {
        if (! preg_match('#^/var/www/[A-Za-z0-9._/-]+$#', $docRoot)) {
            throw new \RuntimeException('Unsafe install path.');
        }

        // Build wp-config.php locally (real creds + fresh salts) in writable storage.
        $config = $this->buildWpConfig($db, $user, $pass);
        $tmpCfg = storage_path('app/wp-config-' . uniqid() . '.php');
        if (@file_put_contents($tmpCfg, $config) === false) {
            throw new \RuntimeException('Could not stage wp-config.php.');
        }

        $d   = escapeshellarg($docRoot);
        $cfg = escapeshellarg($tmpCfg);
        $script = implode(' && ', [
            'set -e',
            "mkdir -p {$d}",
            'curl -fsSL https://wordpress.org/latest.tar.gz -o /tmp/nex-wp.tgz',
            "tar xzf /tmp/nex-wp.tgz -C {$d} --strip-components=1 --no-same-owner",
            'rm -f /tmp/nex-wp.tgz',
            "mv {$cfg} {$d}/wp-config.php",
            "chown -R www-data:www-data {$d}",
        ]);

        $ok = $this->runRoot($script);
        @unlink($tmpCfg);
        if (! $ok || ! is_file("{$docRoot}/wp-load.php")) {
            throw new \RuntimeException('Downloading/extracting WordPress core failed (check internet + permissions).');
        }
    }

    private function buildWpConfig(string $db, string $user, string $pass): string
    {
        $salts = '';
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $k) {
            $salts .= "define('{$k}', '" . Str::random(64) . "');\n";
        }
        $dbE = addslashes($db);
        $uE  = addslashes($user);
        $pE  = addslashes($pass);

        return <<<PHP
        <?php
        define('DB_NAME', '{$dbE}');
        define('DB_USER', '{$uE}');
        define('DB_PASSWORD', '{$pE}');
        define('DB_HOST', 'localhost');
        define('DB_CHARSET', 'utf8mb4');
        define('DB_COLLATE', '');

        {$salts}
        \$table_prefix = 'wp_';

        define('WP_DEBUG', false);

        if ( ! defined('ABSPATH') ) {
            define('ABSPATH', __DIR__ . '/');
        }
        require_once ABSPATH . 'wp-settings.php';
        PHP;
    }

    /** WordPress sites = vhost doc roots that contain wp-load.php. */
    private function installedSites(): array
    {
        if (! $this->nginx->available()) {
            return [];
        }
        $out = [];
        foreach ($this->nginx->sites() as $site) {
            $root = $site['document_root'] ?? '';
            if ($root && $root !== '—' && is_file(rtrim($root, '/') . '/wp-load.php')) {
                $out[] = [
                    'domain' => $site['domain'],
                    'root'   => $root,
                    'php'    => $site['php_version'],
                    'ssl'    => $site['ssl'],
                    'status' => $site['status'],
                ];
            }
        }

        return $out;
    }

    private function phpVersions(): array
    {
        $found = [];
        foreach (glob('/run/php/php*-fpm.sock') ?: [] as $sock) {
            if (preg_match('/php([0-9.]+)-fpm/', $sock, $m)) {
                $found[] = $m[1];
            }
        }
        sort($found);

        return $found ?: ['8.3', '8.2', '8.1'];
    }

    private function runRoot(string $cmd): bool
    {
        $runner = '/usr/local/bin/nexpanel-run';
        if (! is_file($runner)) {
            return false;
        }
        $sudo = (function_exists('posix_geteuid') && posix_geteuid() === 0) ? [] : ['sudo', '-n'];

        return Process::timeout(300)->input($cmd)->run([...$sudo, $runner])->successful();
    }
}
