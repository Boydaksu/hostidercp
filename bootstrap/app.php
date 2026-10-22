<?php
declare(strict_types=1);
use Hostider\Core\{Config, Container, SafeLogger};
use Hostider\Core\Database\{Database, Migrator};
use Hostider\Core\Http\{Kernel, Router, Request, Response, HttpError};
use Hostider\Core\Security\RateLimiter;
require_once __DIR__ . '/autoload.php';
if (PHP_VERSION_ID < 80300 || PHP_INT_SIZE !== 8) {
    throw new RuntimeException('64-bit PHP 8.3+ required');
}
foreach (['pdo_mysql','sodium','mbstring','json','session'] as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException('Required PHP extension missing');
    }
}
$container = new Container();
$container->bind(Config::class, static fn(): Config => Config::fromEnvironment());
$container->get(Config::class);
$container->bind(Database::class, static function (Container $c): Database {
    try {
        $db = Database::connect($c->get(Config::class));
        (new Migrator($db, dirname(__DIR__) . '/migrations'))->assertReady();
        return $db;
    } catch (Throwable) {
        throw new HttpError(503, 'DATABASE_UNAVAILABLE');
    }
});
$container->bind(RateLimiter::class, static fn(Container $c): RateLimiter => new RateLimiter($c->get(Database::class), base64_decode($c->get(Config::class)->key, true)));
$router = new Router();
$identityConfig=$container->get(Config::class);
(new \Hostider\Identity\Controller(static fn(): \Hostider\Identity\IdentityService => new \Hostider\Identity\IdentityService($container->get(Database::class),$identityConfig),$identityConfig))->routes($router);
foreach(['admin','client'] as $panelContext) {
    $router->add('GET','/'.$panelContext,static fn(): Response=>\Hostider\Identity\Panel::response($panelContext));
    $router->add('GET','/'.$panelContext.'/',static fn(): Response=>\Hostider\Identity\Panel::response($panelContext));
}
$router->add('GET', '/api/v1/health', static fn(Request $r, string $trace): Response => Response::data(['status' => 'ok', 'phase' => 'F0'], $trace), [
    static function (Request $request, Closure $next) use ($container): Response {
        if (!$container->get(RateLimiter::class)->allow('health:' . $request->remoteAddress)) {
            throw new HttpError(429, 'RATE_LIMITED');
        }
        return $next($request);
    },
]);
return new Kernel($router, new SafeLogger(static fn(string $line): bool => error_log($line)));
