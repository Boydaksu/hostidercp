<?php
declare(strict_types=1);
namespace Hostider\Core\Database;
use Hostider\Core\Config;
final readonly class Database
{
    public function __construct(public \PDO $pdo) {}
    public static function connect(Config $config): self
    {
        $pdo = new \PDO($config->dsn, $config->dbUser, $config->dbPassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return new self($pdo);
    }

    public function transaction(\Closure $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new \LogicException('Nested transactions forbidden');
        }
        $this->pdo->beginTransaction();
        try {
            $result = $work($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public function requireTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new \LogicException('Transaction required');
        }
    }
}
