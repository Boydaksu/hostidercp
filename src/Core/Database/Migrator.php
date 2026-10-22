<?php
declare(strict_types=1);
namespace Hostider\Core\Database;
final readonly class Migrator
{
    public function __construct(private Database $db, private string $directory) {}

    public function assertReady(): void
    {
        $files = glob($this->directory . '/*.php') ?: [];
        $history = $this->db->execute('SELECT version,checksum,status FROM schema_migrations')->fetchAll();
        if (count($files) !== count($history)) {
            throw new \RuntimeException('Schema is not current');
        }
        $expected = [];
        foreach ($files as $file) {
            $expected[basename($file)] = hash_file('sha256', $file);
        }
        foreach ($history as $row) {
            if ($row['status'] !== 'APPLIED' || !isset($expected[$row['version']]) || !hash_equals($expected[$row['version']], $row['checksum'])) {
                throw new \RuntimeException('Schema drift or incomplete migration');
            }
        }
    }

    public function migrate(): array
    {
        if ($this->db->pdo->inTransaction()) {
            throw new \LogicException('Migration cannot run in a transaction');
        }
        $version = (string) $this->db->execute('SELECT VERSION()')->fetchColumn();
        if (!str_contains($version, 'MariaDB') || version_compare(explode('-', $version)[0], '10.11', '<')) {
            throw new \RuntimeException('MariaDB 10.11+ required');
        }
        if ((int) $this->db->execute("SELECT GET_LOCK('hostider_schema', 0)")->fetchColumn() !== 1) {
            throw new \RuntimeException('Migration already running');
        }
        try {
            $this->db->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, checksum CHAR(64) CHARACTER SET ascii NOT NULL, status VARCHAR(16) NOT NULL, applied_at DATETIME(6) NULL) ENGINE=InnoDB");
            $files = glob($this->directory . '/*.php') ?: [];
            sort($files, SORT_STRING);
            $known = [];
            foreach ($files as $file) {
                $known[basename($file)] = hash_file('sha256', $file);
            }
            $history = $this->db->execute('SELECT * FROM schema_migrations ORDER BY version')->fetchAll();
            foreach ($history as $row) {
                if (!isset($known[$row['version']]) || !hash_equals($row['checksum'], $known[$row['version']]) || $row['status'] !== 'APPLIED') {
                    throw new \RuntimeException('Migration drift or incomplete DDL; manual recovery required');
                }
            }
            $applied = array_column($history, 'version');
            $completed = [];
            foreach ($files as $file) {
                $name = basename($file);
                if (in_array($name, $applied, true)) {
                    continue;
                }
                if ($applied !== [] && strcmp($name, max($applied)) < 0) {
                    throw new \RuntimeException('Out-of-order migration');
                }
                $statements = (static fn(string $migrationFile): mixed => require $migrationFile)($file);
                if (!is_array($statements) || $statements === []) {
                    throw new \RuntimeException('Invalid migration');
                }
                $this->db->execute("INSERT INTO schema_migrations VALUES (?, ?, 'APPLYING', NULL)", [$name, $known[$name]]);
                foreach ($statements as $sql) {
                    $this->db->pdo->exec($sql);
                }
                $this->db->execute("UPDATE schema_migrations SET status='APPLIED', applied_at=UTC_TIMESTAMP(6) WHERE version=?", [$name]);
                $completed[] = $name;
                $applied[] = $name;
            }
            return $completed;
        } finally {
            $this->db->execute("SELECT RELEASE_LOCK('hostider_schema')");
        }
    }
}
