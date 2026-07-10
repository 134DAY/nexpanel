<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Thin wrapper around ufw + ss.
 *
 * Every mutation goes through guardPort()/guardRule(): the panel must never be
 * able to lock its own operator out of the box. Blocking SSH or the port the
 * panel is served on is refused, not warned about.
 */
class FirewallService
{
    private const TIMEOUT = 20;

    /** The port the panel itself is reachable on — set per request. */
    private int $panelPort;

    public function __construct(?int $panelPort = null)
    {
        $this->panelPort = $panelPort ?: 80;
    }

    // ------------------------------------------------------------------ state

    public function available(): bool
    {
        return trim(Process::run('command -v ufw')->output()) !== '';
    }

    public function enabled(): bool
    {
        return str_contains($this->status(), 'Status: active');
    }

    /** Ports we refuse to block or delete an allow-rule for. */
    public function protectedPorts(): array
    {
        return array_values(array_unique([$this->sshPort(), $this->panelPort]));
    }

    /** SSH port from sshd_config, falling back to 22. */
    public function sshPort(): int
    {
        $config = @file_get_contents('/etc/ssh/sshd_config') ?: '';
        if (preg_match('/^\s*Port\s+(\d+)/mi', $config, $m)) {
            return (int) $m[1];
        }

        return 22;
    }

    /** Ports currently bound by some process, as ['22' => 'tcp', ...]. */
    public function listeningPorts(): array
    {
        $out = Process::timeout(self::TIMEOUT)->run('ss -tulnH')->output();
        $ports = [];
        foreach (explode("\n", trim($out)) as $line) {
            $cols = preg_split('/\s+/', trim($line));
            if (count($cols) < 5) {
                continue;
            }
            // Column 4 is the local address:port (IPv6 uses [::]:80).
            if (preg_match('/:(\d+)$/', $cols[4], $m)) {
                $ports[$m[1]] = strtolower($cols[0]);
            }
        }

        return $ports;
    }

    // ------------------------------------------------------------------ rules

    /**
     * Parsed `ufw status numbered`. Each rule is
     * ['n', 'to', 'action', 'direction', 'from', 'kind' => port|ip, 'port', 'proto'].
     */
    public function rules(): array
    {
        return $this->parseRules($this->status(numbered: true), $this->listeningPorts());
    }

    /** Pure parser for `ufw status numbered` — no IO, so it can be tested. */
    public function parseRules(string $raw, array $listening = []): array
    {
        $rules = [];

        foreach (explode("\n", $raw) as $line) {
            if (! preg_match('/^\[\s*(\d+)\]\s+(.+?)\s{2,}(ALLOW|DENY|REJECT|LIMIT)\s+(IN|OUT)\s+(.+?)\s*$/', $line, $m)) {
                continue;
            }
            [, $n, $to, $action, $direction, $from] = $m;
            // ufw lists a mirrored (v6) rule for every rule; one row is enough.
            if (str_contains($to, '(v6)')) {
                continue;
            }

            $port  = null;
            $proto = null;
            if (preg_match('#^(\d+(?::\d+)?)(?:/(tcp|udp))?$#', trim($to), $p)) {
                $port  = $p[1];
                $proto = $p[2] ?? 'tcp/udp';
            }

            $rules[] = [
                'n'         => (int) $n,
                'to'        => trim($to),
                'action'    => $action,
                'direction' => $direction,
                'from'      => trim($from),
                'kind'      => $port !== null ? 'port' : 'ip',
                'port'      => $port,
                'proto'     => $proto,
                'listening' => $port !== null && ! str_contains($port, ':') && isset($listening[$port]),
                'protected' => $port !== null && in_array((int) $port, $this->protectedPorts(), true),
            ];
        }

        return $rules;
    }

    public function enable(): array
    {
        // Open the lifelines before the default-deny policy takes effect.
        foreach ($this->protectedPorts() as $port) {
            $this->run('ufw allow ' . (int) $port . '/tcp');
        }

        return $this->run('ufw --force enable');
    }

    public function disable(): array
    {
        return $this->run('ufw disable');
    }

    /** @param string $proto tcp|udp|both */
    public function addPortRule(string $port, string $proto, string $action, string $from = ''): array
    {
        $this->guardPort($port, $action);

        $verb = $this->verb($action);

        if ($from !== '') {
            $cmd = "ufw {$verb} from " . escapeshellarg($from) . ' to any port ' . escapeshellarg($port)
                 . ($proto === 'both' ? '' : ' proto ' . $proto);
        } else {
            // ufw wants "3306/tcp", but a bare "3306" for both protocols.
            $cmd = "ufw {$verb} " . escapeshellarg($proto === 'both' ? $port : "{$port}/{$proto}");
        }

        return $this->run($cmd);
    }

    public function addIpRule(string $ip, string $action): array
    {
        return $this->run('ufw ' . $this->verb($action) . ' from ' . escapeshellarg($ip));
    }

    public function deleteRule(int $n): array
    {
        $rule = collect($this->rules())->firstWhere('n', $n);
        if ($rule === null) {
            throw new \RuntimeException('Rule not found.');
        }
        $this->guardRule($rule);

        return $this->run('ufw --force delete ' . $n);
    }

    // ---------------------------------------------------------------- guards

    /** Refuse to add a rule that blocks SSH or the panel. */
    private function guardPort(string $port, string $action): void
    {
        if ($action === 'allow') {
            return;
        }
        $single = (int) explode(':', $port)[0];
        $last   = (int) (explode(':', $port)[1] ?? $single);
        foreach ($this->protectedPorts() as $p) {
            if ($p >= $single && $p <= $last) {
                throw new \RuntimeException(
                    "Refusing to {$action} port {$p} — it is the SSH or panel port. You would lock yourself out."
                );
            }
        }
    }

    /** Refuse to delete the allow-rule keeping SSH or the panel reachable. */
    private function guardRule(array $rule): void
    {
        if ($rule['protected'] && $rule['action'] === 'ALLOW') {
            throw new \RuntimeException(
                "Refusing to delete the ALLOW rule for port {$rule['port']} — it is the SSH or panel port."
            );
        }
    }

    // --------------------------------------------------------------- plumbing

    private function verb(string $action): string
    {
        return match ($action) {
            'allow'  => 'allow',
            'deny'   => 'deny',
            'reject' => 'reject',
            default  => throw new \RuntimeException("Unknown action: {$action}"),
        };
    }

    private function status(bool $numbered = false): string
    {
        if (! $this->available()) {
            return '';
        }

        return $this->run('ufw status' . ($numbered ? ' numbered' : ''))['output'];
    }

    /** All ufw calls need root; go through the same wrapper the rest of the app uses. */
    private function run(string $cmd): array
    {
        $runner = '/usr/local/bin/nexpanel-run';
        if (! is_file($runner)) {
            return ['ok' => false, 'output' => '', 'error' => 'Privileged runner not installed.'];
        }
        $r = Process::timeout(self::TIMEOUT)->input($cmd)->run([...$this->sudo(), $runner]);

        return [
            'ok'     => $r->successful(),
            'output' => $r->output(),
            'error'  => trim($r->errorOutput()),
        ];
    }

    private function sudo(): array
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return [];
        }

        return ['sudo', '-n'];
    }
}
