<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '0'); // Only structured, allowlisted application logs are emitted.
ini_set('zend.exception_ignore_args', '1');
require_once dirname(__DIR__) . '/bootstrap/autoload.php';
use Hostider\Core\Http\{Request, Response};
use Hostider\Core\Id;
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException('Runtime error', 0, $severity, $file, $line);
});
try {
    $kernel = require dirname(__DIR__) . '/bootstrap/app.php';
    $kernel->handle(Request::fromGlobals())->send();
} catch (Throwable) {
    Response::error(503, 'UNAVAILABLE', 'Service unavailable', Id::uuid())->send();
}
