<?php

namespace App\Services;

use PDO;

/**
 * Manages a local MySQL/MariaDB server via an admin PDO connection.
 * Credentials come from env (DB_ADMIN_*); defaults suit a fresh
 * root@localhost install.
 */
class MysqlService
{
    private const SYSTEM_SCHEMAS = ['information_schema', 'performance_schema', 'mysql', 'sys'];

    private ?PDO $pdo = null;
    private bool $tried = false;
    private ?string $error = null;

    public function available(): bool
    {
        return $this->connect() !== null;
    }

    public function error(): ?string
    {
        $this->connect();

        return $this->error;
    }

    private function connect(): ?PDO
    {
        if ($this->tried) {
            return $this->pdo;
        }
        $this->tried = true;

        $host   = config('nexpanel.db_admin.host', '127.0.0.1');
        $port   = config('nexpanel.db_admin.port', 3306);
        $user   = config('nexpanel.db_admin.user', 'root');
        $pass   = config('nexpanel.db_admin.password', '');
        $socket = config('nexpanel.db_admin.socket');

        $dsn = $socket
            ? "mysql:unix_socket={$socket}"
            : "mysql:host={$host};port={$port}";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT           => 3,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->pdo = null;
        }

        return $this->pdo;
    }

    public function databases(): array
    {
        $pdo = $this->connect();
        if (! $pdo) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count(self::SYSTEM_SCHEMAS), '?'));
        $stmt = $pdo->prepare("
            SELECT s.schema_name AS name,
                   s.default_character_set_name AS charset,
                   COALESCE(SUM(t.data_length + t.index_length), 0) AS size_bytes,
                   COUNT(t.table_name) AS tables
            FROM information_schema.schemata s
            LEFT JOIN information_schema.tables t ON t.table_schema = s.schema_name
            WHERE s.schema_name NOT IN ({$placeholders})
            GROUP BY s.schema_name, s.default_character_set_name
            ORDER BY s.schema_name
        ");
        $stmt->execute(self::SYSTEM_SCHEMAS);

        return array_map(fn($r) => [
            'name'        => $r['name'],
            'charset'     => $r['charset'] ?? '—',
            'size'        => $this->humanSize((int) $r['size_bytes']),
            'tables'      => (int) $r['tables'],
            'last_backup' => '—',
        ], $stmt->fetchAll());
    }

    public function totalSizeBytes(): int
    {
        $pdo = $this->connect();
        if (! $pdo) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count(self::SYSTEM_SCHEMAS), '?'));
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(data_length + index_length), 0)
            FROM information_schema.tables
            WHERE table_schema NOT IN ({$placeholders})
        ");
        $stmt->execute(self::SYSTEM_SCHEMAS);

        return (int) $stmt->fetchColumn();
    }

    public function totalSizeHuman(): string
    {
        return $this->humanSize($this->totalSizeBytes());
    }

    public function users(): array
    {
        $pdo = $this->connect();
        if (! $pdo) {
            return [];
        }
        try {
            $rows = $pdo->query("
                SELECT User AS username, Host AS host, Super_priv AS super
                FROM mysql.user ORDER BY User, Host
            ")->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(fn($r) => [
            'username'   => $r['username'],
            'host'       => $r['host'],
            'databases'  => $r['super'] === 'Y' ? 'All' : '—',
            'privileges' => $r['super'] === 'Y' ? 'ALL PRIVILEGES' : 'Limited',
        ], $rows);
    }

    public function createDatabase(string $name, string $charset = 'utf8mb4'): void
    {
        $this->assertIdentifier($name);
        $charset = preg_match('/^[a-z0-9_]+$/i', $charset) ? $charset : 'utf8mb4';
        $collation = $charset === 'utf8mb4' ? 'utf8mb4_unicode_ci' : ($charset . '_general_ci');
        $this->pdoOrFail()->exec("CREATE DATABASE `{$name}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    public function dropDatabase(string $name): void
    {
        $this->assertIdentifier($name);
        $this->pdoOrFail()->exec("DROP DATABASE `{$name}`");
    }

    /** Tables in a database with row count + size. */
    public function tables(string $db): array
    {
        $this->assertIdentifier($db);
        $stmt = $this->pdoOrFail()->prepare('
            SELECT table_name AS name, table_rows AS n_rows,
                   COALESCE(data_length + index_length, 0) AS size
            FROM information_schema.tables
            WHERE table_schema = ? ORDER BY table_name
        ');
        $stmt->execute([$db]);

        return array_map(fn($r) => [
            'name' => $r['name'],
            'rows' => (int) $r['n_rows'],
            'size' => $this->humanSize((int) $r['size']),
        ], $stmt->fetchAll());
    }

    /** First N rows of a table plus its column names. */
    public function tablePreview(string $db, string $table, int $limit = 100): array
    {
        $this->assertIdentifier($db);
        $this->assertIdentifier($table);
        $pdo = $this->pdoOrFail();
        $limit = max(1, min($limit, 500));

        $colStmt = $pdo->prepare('
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position
        ');
        $colStmt->execute([$db, $table]);
        $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

        $rows = $pdo->query("SELECT * FROM `{$db}`.`{$table}` LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC);

        return ['columns' => $columns, 'rows' => $rows];
    }

    /** Column definitions of a table (Field, Type, Null, Key, Default, Extra). */
    public function tableStructure(string $db, string $table): array
    {
        $this->assertIdentifier($db);
        $this->assertIdentifier($table);

        return $this->pdoOrFail()->query("SHOW COLUMNS FROM `{$db}`.`{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function truncateTable(string $db, string $table): void
    {
        $this->assertIdentifier($db);
        $this->assertIdentifier($table);
        $this->pdoOrFail()->exec("TRUNCATE TABLE `{$db}`.`{$table}`");
    }

    public function dropTable(string $db, string $table): void
    {
        $this->assertIdentifier($db);
        $this->assertIdentifier($table);
        $this->pdoOrFail()->exec("DROP TABLE `{$db}`.`{$table}`");
    }

    /** Insert a row; empty string values are skipped (use column default). */
    public function insertRow(string $db, string $table, array $data): void
    {
        $this->assertIdentifier($db);
        $this->assertIdentifier($table);
        $cols = [];
        $placeholders = [];
        $values = [];
        foreach ($data as $col => $val) {
            if (! preg_match('/^[A-Za-z0-9_]+$/', (string) $col) || $val === '') {
                continue;
            }
            $cols[] = "`{$col}`";
            $placeholders[] = '?';
            $values[] = $val;
        }
        if (! $cols) {
            throw new \RuntimeException('No values to insert.');
        }
        $sql = "INSERT INTO `{$db}`.`{$table}` (" . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
        $this->pdoOrFail()->prepare($sql)->execute($values);
    }

    /** Run an arbitrary SQL statement against a database (admin SQL console). */
    public function runQuery(string $db, string $sql): array
    {
        $this->assertIdentifier($db);
        $pdo = $this->pdoOrFail();
        $pdo->exec("USE `{$db}`");
        $stmt = $pdo->query($sql);

        if ($stmt->columnCount() > 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['columns' => $rows ? array_keys($rows[0]) : [], 'rows' => $rows, 'affected' => null];
        }

        return ['columns' => [], 'rows' => [], 'affected' => $stmt->rowCount()];
    }

    public function createUser(string $username, string $password, string $host = 'localhost'): void
    {
        $this->assertIdentifier($username);
        $pdo = $this->pdoOrFail();
        $u = $pdo->quote($username);
        $h = $pdo->quote($host);
        $p = $pdo->quote($password);
        // Identifiers in CREATE USER are string literals in MySQL syntax.
        $pdo->exec("CREATE USER {$u}@{$h} IDENTIFIED BY {$p}");
    }

    public function dropUser(string $username, string $host = 'localhost'): void
    {
        $this->assertIdentifier($username);
        $pdo = $this->pdoOrFail();
        $pdo->exec("DROP USER {$pdo->quote($username)}@{$pdo->quote($host)}");
    }

    /** Path to a fresh gzip dump of the given database, or throws. */
    public function dumpToFile(string $name): string
    {
        $this->assertIdentifier($name);
        $file = storage_path('app/backups');
        @mkdir($file, 0755, true);
        $file .= '/' . $name . '_' . date('Ymd_His') . '.sql';

        $host   = config('nexpanel.db_admin.host', '127.0.0.1');
        $user   = config('nexpanel.db_admin.user', 'root');
        $pass   = config('nexpanel.db_admin.password', '');
        $args = ['mysqldump', '-h', $host, '-u', $user];
        if ($pass !== '') {
            $args[] = '-p' . $pass;
        }
        $args[] = $name;

        $out = @fopen($file, 'w');
        $proc = proc_open($args, [1 => $out, 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($proc)) {
            throw new \RuntimeException('Could not start mysqldump');
        }
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if (is_resource($out)) {
            fclose($out);
        }
        if ($code !== 0) {
            @unlink($file);
            throw new \RuntimeException('mysqldump failed: ' . trim($err));
        }

        return $file;
    }

    private function pdoOrFail(): PDO
    {
        $pdo = $this->connect();
        if (! $pdo) {
            throw new \RuntimeException('Cannot connect to MySQL: ' . ($this->error ?? 'unknown error'));
        }

        return $pdo;
    }

    private function assertIdentifier(string $name): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Name may only contain letters, numbers and underscores.');
        }
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
