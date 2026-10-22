<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Hostider\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
date_default_timezone_set('UTC');
