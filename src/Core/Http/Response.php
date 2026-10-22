<?php
declare(strict_types=1);
namespace Hostider\Core\Http;
final readonly class Response
{
    public function __construct(public int $status, public array $envelope, public array $headers = []) {}

    public static function data(mixed $data, string $trace, int $status = 200): self
    {
        return new self($status, ['data' => $data, 'meta' => ['trace_id' => $trace], 'errors' => []]);
    }

    public static function error(int $status, string $code, string $message, string $trace): self
    {
        return new self($status, ['data' => null, 'meta' => ['trace_id' => $trace],
            'errors' => [['code' => $code, 'message' => $message, 'details' => new \stdClass(), 'trace_id' => $trace]]]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach (['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            'Referrer-Policy' => 'no-referrer', 'Strict-Transport-Security' => 'max-age=31536000'] + $this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo json_encode($this->envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
