<?php
declare(strict_types=1);
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
require dirname(__DIR__).'/bootstrap/autoload.php';
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException('Preflight metadata unavailable',0,$severity,$file,$line);});
exit(\Hostider\HostingPreflight\Cli::main(array_slice($argv,1)));
