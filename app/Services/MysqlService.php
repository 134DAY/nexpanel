<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Process;
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

    /** Server version string, e.g. "8.0.36" or "10.4.28-MariaDB". */
    public function version(): ?string
    {
        $pdo = $this->connect();
        if (! $pdo) {
            return null;
        }
        try {
            return (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        } catch (\Throwable $e) {
            return null;
        }
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

    /** Create a database plus a dedicated user granted full access to it. */
    public function createDatabaseWithUser(string $db, string $user, string $password, string $charset = 'utf8mb4'): void
    {
        $this->assertIdentifier($db);
        $this->assertIdentifier($user);
        $pdo = $this->pdoOrFail();
        $charset = preg_match('/^[a-z0-9_]+$/i', $charset) ? $charset : 'utf8mb4';
        $collation = $charset === 'utf8mb4' ? 'utf8mb4_unicode_ci' : ($charset . '_general_ci');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET {$charset} COLLATE {$collation}");

        $p = $pdo->quote($password);
        foreach (['localhost', '127.0.0.1'] as $host) {
            $u = $pdo->quote($user);
            $h = $pdo->quote($host);
            $pdo->exec("CREATE USER IF NOT EXISTS {$u}@{$h} IDENTIFIED BY {$p}");
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$db}`.* TO {$u}@{$h}");
        }
        $pdo->exec('FLUSH PRIVILEGES');
    }

    public function changeUserPassword(string $user, string $password): void
    {
        $this->assertIdentifier($user);
        $pdo = $this->pdoOrFail();
        $p = $pdo->quote($password);
        foreach (['localhost', '127.0.0.1'] as $host) {
            try {
                $pdo->exec('ALTER USER ' . $pdo->quote($user) . '@' . $pdo->quote($host) . " IDENTIFIED BY {$p}");
            } catch (\Throwable $e) {
                // user may not exist on that host — ignore
            }
        }
        $pdo->exec('FLUSH PRIVILEGES');
    }

    /**
     * Change the password of the admin account the panel itself connects with.
     * The caller must persist the new password (DB_ADMIN_PASSWORD) or the panel
     * loses access to MySQL on the next request.
     */
    public function changeAdminPassword(string $password): void
    {
        $user = (string) config('nexpanel.db_admin.user', 'root');
        $this->assertIdentifier($user);
        $pdo = $this->pdoOrFail();
        $p = $pdo->quote($password);

        $changed = false;
        foreach (['localhost', '127.0.0.1', '%'] as $host) {
            try {
                $pdo->exec('ALTER USER ' . $pdo->quote($user) . '@' . $pdo->quote($host) . " IDENTIFIED BY {$p}");
                $changed = true;
            } catch (\Throwable $e) {
                // that host grant does not exist — fine
            }
        }
        if (! $changed) {
            throw new \RuntimeException("Could not change the password for '{$user}'.");
        }
        $pdo->exec('FLUSH PRIVILEGES');
    }

    public function userExists(string $user): bool
    {
        $pdo = $this->connect();
        if (! $pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM mysql.user WHERE User = ? LIMIT 1');
            $stmt->execute([$user]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function databaseExists(string $name): bool
    {
        $pdo = $this->connect();
        if (! $pdo) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.schemata WHERE schema_name = ? LIMIT 1');
        $stmt->execute([$name]);

        return (bool) $stmt->fetchColumn();
    }

    public function userGrants(string $user, string $host = 'localhost'): array
    {
        $this->assertIdentifier($user);
        try {
            return $this->pdoOrFail()
                ->query('SHOW GRANTS FOR ' . $this->pdoOrFail()->quote($user) . '@' . $this->pdoOrFail()->quote($host))
                ->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function grantUserOnDb(string $user, string $db, string $host = 'localhost'): void
    {
        $this->assertIdentifier($user);
        $this->assertIdentifier($db);
        $pdo = $this->pdoOrFail();
        $pdo->exec("GRANT ALL PRIVILEGES ON `{$db}`.* TO " . $pdo->quote($user) . '@' . $pdo->quote($host));
        $pdo->exec('FLUSH PRIVILEGES');
    }

    public function revokeUserOnDb(string $user, string $db, string $host = 'localhost'): void
    {
        $this->assertIdentifier($user);
        $this->assertIdentifier($db);
        $pdo = $this->pdoOrFail();
        $pdo->exec("REVOKE ALL PRIVILEGES ON `{$db}`.* FROM " . $pdo->quote($user) . '@' . $pdo->quote($host));
        $pdo->exec('FLUSH PRIVILEGES');
    }

    /** Import a .sql dump into a database via the mysql client. */
    /** Import a dump into a database. Supports .sql, .gz, .tar.gz/.tgz, .zip. */
    public function importSql(string $db, string $file, string $origName = ''): void
    {
        $this->assertIdentifier($db);
        $name = strtolower($origName ?: $file);

        if (str_ends_with($name, '.tar.gz') || str_ends_with($name, '.tgz')) {
            $src = 'tar xzO -f ' . escapeshellarg($file);
        } elseif (str_ends_with($name, '.gz')) {
            $src = 'gunzip -c ' . escapeshellarg($file);
        } elseif (str_ends_with($name, '.zip')) {
            $src = 'unzip -p ' . escapeshellarg($file);
        } else {
            $src = 'cat ' . escapeshellarg($file);
        }

        $cmd = $src . ' | ' . $this->mysqlCli($db);
        $result = Process::timeout(600)->run(['bash', '-c', $cmd]);
        if (! $result->successful()) {
            throw new \RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'Import failed.');
        }
    }

    // ---------------------------------------------------------------- backups

    /** Directory where this database's backups live (web-user owned). */
    public function backupsDir(string $db): string
    {
        $this->assertIdentifier($db);
        $dir = storage_path('app/backups/' . $db);
        @mkdir($dir, 0755, true);

        return $dir;
    }

    /** Create a timestamped gzip backup. Returns the file path. */
    public function createBackup(string $db): string
    {
        $this->assertIdentifier($db);
        $file = $this->backupsDir($db) . '/' . $db . '_' . date('Ymd_His') . '.sql.gz';
        $cmd = $this->mysqldumpCli($db) . ' | gzip > ' . escapeshellarg($file);
        $result = Process::timeout(600)->run(['bash', '-c', $cmd]);
        if (! $result->successful() || ! is_file($file)) {
            @unlink($file);
            throw new \RuntimeException(trim($result->errorOutput()) ?: 'Backup failed.');
        }

        return $file;
    }

    public function listBackups(string $db): array
    {
        $dir = $this->backupsDir($db);
        $files = glob($dir . '/*') ?: [];
        $out = [];
        foreach ($files as $f) {
            if (! is_file($f)) {
                continue;
            }
            $out[] = [
                'name' => basename($f),
                'size' => $this->humanSize((int) @filesize($f)),
                'time' => date('Y-m-d H:i:s', (int) @filemtime($f)),
            ];
        }
        usort($out, fn($a, $b) => strcmp($b['time'], $a['time']));

        return $out;
    }

    public function restoreBackup(string $db, string $filename): void
    {
        $this->assertIdentifier($db);
        $file = $this->backupsDir($db) . '/' . basename($filename);
        if (! is_file($file)) {
            throw new \RuntimeException('Backup file not found.');
        }
        $this->importSql($db, $file, $filename);
    }

    public function deleteBackup(string $db, string $filename): void
    {
        $file = $this->backupsDir($db) . '/' . basename($filename);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function backupPath(string $db, string $filename): ?string
    {
        $file = $this->backupsDir($db) . '/' . basename($filename);

        return is_file($file) ? $file : null;
    }

    // ------------------------------------------------------------ recycle bin

    /**
     * Dump a database (plus its paired credentials) into the recycle bin, then
     * drop it. Nothing is dropped unless the dump succeeded.
     */
    public function recycleDatabase(string $db, ?array $credential = null): string
    {
        $this->assertIdentifier($db);
        $id = $db . '__' . date('Ymd_His');
        $dump = $this->recycleDir() . '/' . $id . '.sql.gz';

        $cmd = $this->mysqldumpCli($db) . ' | gzip > ' . escapeshellarg($dump);
        $result = Process::timeout(900)->run(['bash', '-c', $cmd]);
        if (! $result->successful() || ! is_file($dump) || filesize($dump) === 0) {
            @unlink($dump);
            throw new \RuntimeException(trim($result->errorOutput()) ?: 'Could not dump the database, so it was not deleted.');
        }

        $password = $credential['password'] ?? null;
        file_put_contents($this->recycleDir() . '/' . $id . '.json', json_encode([
            'db'         => $db,
            'username'   => $credential['username'] ?? null,
            // Encrypted at rest, like the db_credentials row it came from.
            'password'   => $password ? Crypt::encryptString($password) : null,
            'deleted_at' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT));

        $this->dropDatabase($db);

        return $id;
    }

    public function listRecycled(): array
    {
        $out = [];
        foreach (glob($this->recycleDir() . '/*.json') ?: [] as $metaFile) {
            $id = basename($metaFile, '.json');
            $dump = $this->recycleDir() . '/' . $id . '.sql.gz';
            if (! is_file($dump)) {
                continue;
            }
            $meta = json_decode((string) file_get_contents($metaFile), true) ?: [];
            $out[] = [
                'id'         => $id,
                'db'         => $meta['db'] ?? $id,
                'username'   => $meta['username'] ?? null,
                'size'       => $this->humanSize((int) @filesize($dump)),
                'deleted_at' => $meta['deleted_at'] ?? date('Y-m-d H:i:s', (int) @filemtime($dump)),
            ];
        }
        usort($out, fn($a, $b) => strcmp($b['deleted_at'], $a['deleted_at']));

        return $out;
    }

    /** Recreate a recycled database (and its paired user). Returns the metadata. */
    public function restoreRecycled(string $id): array
    {
        $meta = $this->recycleMeta($id);
        $db = $meta['db'];
        $this->assertIdentifier($db);

        if ($this->databaseExists($db)) {
            throw new \RuntimeException("Database '{$db}' already exists — rename or drop it first.");
        }

        if (! empty($meta['username']) && ! empty($meta['password'])) {
            $this->createDatabaseWithUser($db, $meta['username'], $meta['password']);
        } else {
            $this->createDatabase($db);
        }

        $this->importSql($db, $this->recycleDumpPath($id), $id . '.sql.gz');
        $this->deleteRecycled($id);

        return $meta;
    }

    public function deleteRecycled(string $id): void
    {
        $this->assertRecycleId($id);
        @unlink($this->recycleDir() . '/' . $id . '.json');
        @unlink($this->recycleDir() . '/' . $id . '.sql.gz');
    }

    public function recycleDumpPath(string $id): string
    {
        $this->assertRecycleId($id);
        $path = $this->recycleDir() . '/' . $id . '.sql.gz';
        if (! is_file($path)) {
            throw new \RuntimeException('Recycled dump not found.');
        }

        return $path;
    }

    private function recycleMeta(string $id): array
    {
        $this->assertRecycleId($id);
        $file = $this->recycleDir() . '/' . $id . '.json';
        $meta = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($meta) || empty($meta['db'])) {
            throw new \RuntimeException('Recycled database not found.');
        }

        if (! empty($meta['password'])) {
            try {
                $meta['password'] = Crypt::decryptString($meta['password']);
            } catch (\Throwable $e) {
                // Written under a different APP_KEY — restore the data without the user.
                $meta['password'] = null;
            }
        }

        return $meta;
    }

    private function recycleDir(): string
    {
        $dir = storage_path('app/recycle');
        @mkdir($dir, 0755, true);

        return $dir;
    }

    private function assertRecycleId(string $id): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+__\d{8}_\d{6}$/', $id)) {
            throw new \InvalidArgumentException('Invalid recycle bin entry.');
        }
    }

    private function mysqlCli(string $db): string
    {
        return $this->clientBase('mysql') . ' ' . escapeshellarg($db);
    }

    private function mysqldumpCli(string $db): string
    {
        return $this->clientBase('mysqldump') . ' ' . escapeshellarg($db);
    }

    private function clientBase(string $bin): string
    {
        $host = config('nexpanel.db_admin.host', '127.0.0.1');
        $port = config('nexpanel.db_admin.port', 3306);
        $user = config('nexpanel.db_admin.user', 'root');
        $pass = config('nexpanel.db_admin.password', '');
        $exe  = config('nexpanel.bin.' . $bin, $bin);

        $cmd = escapeshellarg($exe)
            . ' -h ' . escapeshellarg($host)
            . ' -P ' . escapeshellarg((string) $port)
            . ' -u ' . escapeshellarg($user);
        if ($pass !== '') {
            $cmd .= ' -p' . escapeshellarg($pass);
        }

        return $cmd;
    }

    public function primaryKey(string $db, string $table): ?string
    {
        foreach ($this->tableStructure($db, $table) as $col) {
            if (($col['Key'] ?? '') === 'PRI') {
                return $col['Field'];
            }
        }

        return null;
    }

    public function updateRow(string $db, string $table, string $pk, $pkValue, array $data): void
    {
        $this->assertIdentifier($db);
        $this->assertIdentifier($table);
        $this->assertIdentifier($pk);
        $set = [];
        $vals = [];
        foreach ($data as $col => $val) {
            if (! preg_match('/^[A-Za-z0-9_]+$/', (string) $col) || $col === $pk) {
                continue;
            }
            $set[] = "`{$col}` = ?";
            $vals[] = $val;
        }
        if (! $set) {
            return;
        }
        $vals[] = $pkValue;
        $this->pdoOrFail()->prepare("UPDATE `{$db}`.`{$table}` SET " . implode(', ', $set) . " WHERE `{$pk}` = ? LIMIT 1")->execute($vals);
    }

    public function deleteRow(string $db, string $table, string $pk, $pkValue): void
    {
        $this->assertIdentifier($db);
        $this->assertIdentifier($table);
        $this->assertIdentifier($pk);
        $this->pdoOrFail()->prepare("DELETE FROM `{$db}`.`{$table}` WHERE `{$pk}` = ? LIMIT 1")->execute([$pkValue]);
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

        return ['columns' => $columns, 'rows' => $rows, 'pk' => $this->primaryKey($db, $table)];
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
