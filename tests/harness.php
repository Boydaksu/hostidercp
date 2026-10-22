<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/autoload.php';
error_reporting(E_ALL);
ini_set('zend.exception_ignore_args', '1');
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$tests = [];
function test(string $name, Closure $work): void { $GLOBALS['tests'][$name] = $work; }
function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) { throw new RuntimeException('Assertion failed: ' . var_export($expected, true) . ' != ' . var_export($actual, true)); }
}
function raises(string $class, Closure $work): Throwable
{
    try { $work(); } catch (Throwable $e) {
        if (!$e instanceof $class) { throw new RuntimeException('Unexpected exception class: ' . get_class($e)); }
        return $e;
    }
    throw new RuntimeException('Expected exception: ' . $class);
}
function runTests(): never
{
    exit(executeTests());
}
function executeTests(): int
{
    $failed = 0;
    foreach ($GLOBALS['tests'] as $name => $work) {
        try { $work(); echo 'PASS ' . $name . PHP_EOL; }
        catch (Throwable $e) { ++$failed; echo 'FAIL ' . $name . ': ' . $e->getMessage() . PHP_EOL; }
    }
    echo count($GLOBALS['tests']) . ' tests, ' . $failed . ' failures' . PHP_EOL;
    return $failed === 0 ? 0 : 1;
}
function testConfig(string $env = 'staging'): \Hostider\Core\Config
{
    return new \Hostider\Core\Config($env, 'https://test.example.invalid', base64_encode(random_bytes(32)),
        'mysql:host=127.0.0.1;dbname=hostider_test;charset=utf8mb4', 'test', '');
}
