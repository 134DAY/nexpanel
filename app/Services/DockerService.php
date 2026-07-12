<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Thin wrapper around the `docker` CLI. The Docker socket is root-owned, so
 * every call goes through the same privileged runner the rest of the panel
 * uses. All user-supplied ids/names are shell-escaped and actions are limited
 * to a fixed whitelist — the panel never runs `docker run` with free-form args.
 */
class DockerService
{
    private const TIMEOUT = 60;

    /** Container lifecycle actions the panel is allowed to perform. */
    public const CONTAINER_ACTIONS = ['start', 'stop', 'restart', 'remove'];

    public function available(): bool
    {
        return trim(Process::run('command -v docker')->output()) !== '';
    }

    /** True when the docker daemon is reachable (not just the binary present). */
    public function running(): bool
    {
        return $this->docker("info --format '{{.ServerVersion}}'")['ok'];
    }

    public function version(): ?string
    {
        $r = $this->docker("version --format '{{.Server.Version}}'");

        return $r['ok'] ? (trim($r['out']) ?: null) : null;
    }

    /** All containers (running + stopped). */
    public function containers(): array
    {
        $r = $this->docker('ps -a --no-trunc --format "{{json .}}"');
        if (! $r['ok']) {
            return [];
        }
        $out = [];
        foreach (explode("\n", trim($r['out'])) as $line) {
            $c = json_decode(trim($line), true);
            if (! is_array($c)) {
                continue;
            }
            $state = strtolower($c['State'] ?? '');
            $out[] = [
                'id'      => substr($c['ID'] ?? '', 0, 12),
                'name'    => $c['Names'] ?? '',
                'image'   => $c['Image'] ?? '',
                'state'   => $state,
                'status'  => $c['Status'] ?? '',
                'ports'   => $c['Ports'] ?? '',
                'running' => $state === 'running',
            ];
        }
        usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /** Local images. */
    public function images(): array
    {
        $r = $this->docker('images --format "{{json .}}"');
        if (! $r['ok']) {
            return [];
        }
        $out = [];
        foreach (explode("\n", trim($r['out'])) as $line) {
            $i = json_decode(trim($line), true);
            if (! is_array($i)) {
                continue;
            }
            $out[] = [
                'id'         => substr(str_replace('sha256:', '', $i['ID'] ?? ''), 0, 12),
                'repository' => $i['Repository'] ?? '<none>',
                'tag'        => $i['Tag'] ?? '<none>',
                'size'       => $i['Size'] ?? '',
                'created'    => $i['CreatedSince'] ?? '',
            ];
        }

        return $out;
    }

    public function stats(): array
    {
        $containers = $this->containers();

        return [
            'total'   => count($containers),
            'running' => count(array_filter($containers, fn($c) => $c['running'])),
            'stopped' => count(array_filter($containers, fn($c) => ! $c['running'])),
            'images'  => count($this->images()),
        ];
    }

    /** Run a whitelisted lifecycle action against a container id/name. */
    public function containerAction(string $id, string $action): array
    {
        if (! in_array($action, self::CONTAINER_ACTIONS, true)) {
            return ['ok' => false, 'error' => 'Unknown action'];
        }
        $verb = $action === 'remove' ? 'rm -f' : $action;
        $r = $this->docker($verb . ' ' . escapeshellarg($id));

        return ['ok' => $r['ok'], 'error' => $r['ok'] ? null : trim($r['err'] ?: $r['out'])];
    }

    /** Last N lines of a container's logs. */
    public function logs(string $id, int $tail = 200): string
    {
        $r = $this->docker('logs --tail ' . (int) $tail . ' ' . escapeshellarg($id) . ' 2>&1');

        return $r['out'];
    }

    /** Pull an image by name (e.g. "nginx:latest"). */
    public function pull(string $image): array
    {
        // Image refs are limited to the safe character set docker itself allows.
        if (! preg_match('#^[a-zA-Z0-9._/:@-]+$#', $image)) {
            return ['ok' => false, 'error' => 'Invalid image name'];
        }
        $r = $this->docker('pull ' . escapeshellarg($image));

        return ['ok' => $r['ok'], 'error' => $r['ok'] ? null : trim($r['err'] ?: $r['out'])];
    }

    public function removeImage(string $id): array
    {
        $r = $this->docker('rmi ' . escapeshellarg($id));

        return ['ok' => $r['ok'], 'error' => $r['ok'] ? null : trim($r['err'] ?: $r['out'])];
    }

    // ---------------------------------------------------------------- internals

    /** Run `docker <args>` as root via the privileged runner. */
    private function docker(string $args): array
    {
        $runner = '/usr/local/bin/nexpanel-run';
        $cmd = 'docker ' . $args;
        if (is_file($runner)) {
            $r = Process::timeout(self::TIMEOUT)->input($cmd)->run([...$this->sudo(), $runner]);
        } else {
            $r = Process::timeout(self::TIMEOUT)->run(['bash', '-c', $cmd]);
        }

        return ['ok' => $r->successful(), 'out' => $r->output(), 'err' => $r->errorOutput()];
    }

    private function sudo(): array
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return [];
        }

        return ['sudo', '-n'];
    }
}
