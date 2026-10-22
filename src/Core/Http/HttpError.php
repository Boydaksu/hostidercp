<?php
declare(strict_types=1);
namespace Hostider\Core\Http;
final class HttpError extends \RuntimeException
{
    private const MESSAGES = [400 => 'Invalid request', 401 => 'Authentication required',
        403 => 'Access denied', 404 => 'Resource not found', 405 => 'Method not allowed',
        409 => 'Request conflict', 413 => 'Request too large', 415 => 'JSON required',
        422 => 'Validation failed', 429 => 'Too many requests', 503 => 'Service unavailable'];
    public function __construct(public readonly int $status, public readonly string $errorCode)
    {
        parent::__construct(self::MESSAGES[$status] ?? 'Request failed');
    }
}
