<?php
declare(strict_types=1);
namespace Hostider\Core\Http;
final class Router
{
    private array $routes = [];
    public function add(string $method, string $path, \Closure $handler, array $middleware = []): void
    {
        if (isset($this->routes[$path][$method])) {
            throw new \LogicException('Duplicate route');
        }
        $this->routes[$path][$method] = [$handler, $middleware];
    }

    public function dispatch(Request $request, string $trace): Response
    {
        if (!isset($this->routes[$request->path])) {
            throw new HttpError(404, 'NOT_FOUND');
        }
        if (!isset($this->routes[$request->path][$request->method])) {
            throw new HttpError(405, 'METHOD_NOT_ALLOWED');
        }
        [$handler, $middleware] = $this->routes[$request->path][$request->method];
        $next = static fn(Request $r): Response => $handler($r, $trace);
        foreach (array_reverse($middleware) as $layer) {
            $downstream = $next;
            $next = static fn(Request $r): Response => $layer($r, $downstream);
        }
        return $next($request);
    }
}
