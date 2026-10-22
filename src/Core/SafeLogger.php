<?php
declare(strict_types=1);
namespace Hostider\Core;
final class SafeLogger
{
    public function __construct(private readonly \Closure $sink) {}

    public function failure(string $trace, \Throwable $error): void
    {
        // Never accept exception messages, stack arguments, requests, URLs or payloads.
        ($this->sink)(json_encode(['event' => 'application.failure', 'trace_id' => $trace,
            'exception' => get_class($error)], JSON_THROW_ON_ERROR));
    }
}
