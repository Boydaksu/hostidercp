<?php
declare(strict_types=1);
namespace Hostider\Core\Http;
use Hostider\Core\Id;
use Hostider\Core\SafeLogger;
final readonly class Kernel
{
    public function __construct(private Router $router, private SafeLogger $logger) {}
    public function handle(Request $request): Response
    {
        $trace = Id::uuid();
        try {
            if (strlen($request->body) > 65536) {
                throw new HttpError(413, 'BODY_LIMIT');
            }
            return $this->router->dispatch($request, $trace);
        } catch (HttpError $e) {
            return Response::error($e->status, $e->errorCode, $e->getMessage(), $trace);
        } catch (\Throwable $e) {
            $this->logger->failure($trace, $e);
            return Response::error(500, 'INTERNAL_ERROR', 'An internal error occurred', $trace);
        }
    }
}
