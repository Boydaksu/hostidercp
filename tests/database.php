<?php
declare(strict_types=1);
use Hostider\Core\Config;
use Hostider\Core\Database\Database;
function integrationDb(): Database
{
    $dsn = getenv('TEST_DB_DSN') ?: '';
    if (getenv('APP_ENV') !== 'test' || !preg_match('/dbname=(hostider_f0_test[a-zA-Z0-9_]*)(?:;|$)/', $dsn)) {
        throw new RuntimeException('Destructive tests require APP_ENV=test and isolated hostider_f0_test database');
    }
    return Database::connect(new Config('test','https://test.example.invalid',base64_encode(random_bytes(32)),
        $dsn, getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASSWORD') ?: ''));
}
function cleanJobs(Database $db): void
{
    foreach (['dead_letters','job_attempts','jobs','outbox_events','inbox_events','idempotency_keys','audit_logs','rate_limits'] as $table) {
        $db->execute('DELETE FROM ' . $table);
    }
}
