<?php
declare(strict_types=1);
namespace Hostider\Migration\Discovery;
use Hostider\Migration\Contract;

/** Fresh loopback MariaDB session; only named, fixed information_schema reads exist. */
final class MetadataSession
{
    public const PURPOSE = 'OWNER_APPROVED_MANUAL_READ_ONLY_DISCOVERY';
    public const SQL = [
        'schema' => 'SELECT CURRENT_USER() AS account,SCHEMA_NAME,DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:schema LIMIT 2',
        'global' => 'SELECT GRANTEE,PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.USER_PRIVILEGES LIMIT 65',
        'grants' => 'SELECT GRANTEE,TABLE_SCHEMA,PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES LIMIT 65',
        'table_grants' => 'SELECT GRANTEE FROM information_schema.TABLE_PRIVILEGES LIMIT 1',
        'column_grants' => 'SELECT GRANTEE FROM information_schema.COLUMN_PRIVILEGES LIMIT 1',
        'roles' => 'SELECT ROLE_NAME FROM information_schema.APPLICABLE_ROLES LIMIT 1',
        'enabled' => 'SELECT ROLE_NAME FROM information_schema.ENABLED_ROLES WHERE ROLE_NAME IS NOT NULL LIMIT 1',
        'routines' => 'SELECT ROUTINE_SCHEMA FROM information_schema.ROUTINES LIMIT 1',
        'tables' => 'SELECT TABLE_NAME,TABLE_TYPE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema ORDER BY TABLE_NAME LIMIT 65',
        'columns' => 'SELECT COLUMN_NAME,ORDINAL_POSITION,DATA_TYPE,IS_NULLABLE,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION LIMIT 65',
        'indexes' => 'SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,INDEX_TYPE,COLLATION,IGNORED FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table ORDER BY INDEX_NAME,SEQ_IN_INDEX LIMIT 65',
    ];
    private int $queries = 0;
    private readonly int $started;
    private function __construct(private readonly \PDO $pdo, private readonly string $account)
    { $this->started = hrtime(true); }
    public static function connect(int $port, string $account, #[\SensitiveParameter] string $password, string $purpose): self
    {
        Contract::require($purpose === self::PURPOSE, 'GO_MANUAL_GATE');
        Contract::require($port > 0 && $port <= 65535 && preg_match('/^[A-Za-z0-9_]{1,64}$/D', $account) === 1, 'GO_CONNECTION_INPUT');
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;port='.$port.';dbname=information_schema;charset=utf8mb4', $account, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_TIMEOUT => 3,
                \PDO::MYSQL_ATTR_MULTI_STATEMENTS => false, \PDO::ATTR_PERSISTENT => false,
            ]);
            return new self($pdo, $account);
        } catch (\Throwable) { throw new \RuntimeException('GO_CONNECTION_DENIED'); }
    }
    public function __debugInfo(): array { return ['scope' => 'FIXED_INFORMATION_SCHEMA_ONLY']; }
    public function __serialize(): array { throw new \RuntimeException('GO_SESSION_SERIALIZE_DENIED'); }
    public function count(): int { return $this->queries; }
    public function read(string $name, #[\SensitiveParameter] array $params = []): array
    {
        Contract::require(isset(self::SQL[$name]), 'GO_QUERY_DENIED');
        Contract::require(++$this->queries <= 512 && hrtime(true)-$this->started < 30000000000, 'GO_QUERY_BUDGET');
        $expected = match ($name) { 'schema', 'tables' => ['schema'], 'columns', 'indexes' => ['schema', 'table'], default => [] };
        Contract::fields($params, $expected);
        $s = $this->pdo->prepare(self::SQL[$name]); $s->execute($params); $rows = $s->fetchAll(); $s->closeCursor();
        Contract::require(count($rows) <= 64 && hrtime(true)-$this->started < 30000000000, 'GO_METADATA_LIMIT');
        return $rows;
    }
    public function fence(#[\SensitiveParameter] string $schema): array
    {
        Contract::require(preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $schema) === 1, 'GO_SCHEMA_IDENTIFIER');
        $r = $this->read('schema', ['schema' => $schema]);
        Contract::require(count($r) === 1 && $r[0]['SCHEMA_NAME'] === $schema, 'GO_SCHEMA_UNAVAILABLE');
        $current = $r[0]['account'];
        Contract::require(is_string($current) && preg_match('/^([A-Za-z0-9_]+)@([A-Za-z0-9_.:%-]+)$/D', $current, $m) === 1
            && $m[1] === $this->account, 'GO_ACCOUNT_IDENTITY');
        $grantee = "'".$m[1]."'@'".$m[2]."'";
        foreach ($this->read('global') as $g) {
            Contract::require($g['GRANTEE'] === $grantee && $g['PRIVILEGE_TYPE'] === 'USAGE' && $g['IS_GRANTABLE'] === 'NO', 'GO_GLOBAL_PRIVILEGE');
        }
        $grants = $this->read('grants'); $expected = str_replace(['_', '%'], ['\\_', '\\%'], $schema);
        Contract::require(count($grants) === 1 && $grants[0]['GRANTEE'] === $grantee
            && $grants[0]['TABLE_SCHEMA'] === $expected && $grants[0]['PRIVILEGE_TYPE'] === 'SELECT'
            && $grants[0]['IS_GRANTABLE'] === 'NO', 'GO_READ_ONLY_SCOPE');
        foreach (['table_grants', 'column_grants', 'roles', 'enabled', 'routines'] as $kind) {
            Contract::require($this->read($kind) === [], 'GO_EXTRA_AUTHORITY');
        }
        unset($r[0]['account']); return $r[0];
    }
}
