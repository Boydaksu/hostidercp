<?php
declare(strict_types=1);
// Manual operator CLI only. No autoload-side connection or background execution.
require dirname(__DIR__).'/bootstrap/autoload.php';
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
set_error_handler(static function (): never { throw new RuntimeException('GO_RUNTIME_DENIED'); });
exit(Hostider\Migration\Discovery\DiscoveryCli::main($argv));
