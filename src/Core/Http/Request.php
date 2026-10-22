<?php
declare(strict_types=1);
namespace Hostider\Core\Http;
final readonly class Request
{
    public function __construct(public string $method, public string $path, public array $headers = [],
        public string $body = '', public string $remoteAddress = '') {}

    public function header(string $name): string
    {
        return (string) ($this->headers[strtolower($name)] ?? '');
    }

    public function json(): array
    {
        if (strlen($this->body) > 65536) {
            throw new HttpError(413, 'BODY_LIMIT');
        }
        if (strtolower(explode(';', $this->header('content-type'))[0]) !== 'application/json') {
            throw new HttpError(415, 'JSON_REQUIRED');
        }
        try {
            $object = json_decode($this->body, false, 32, JSON_THROW_ON_ERROR);
            if (!$object instanceof \stdClass) {
                throw new \JsonException();
            }
            return get_object_vars($object);
        } catch (\JsonException) {
            throw new HttpError(400, 'INVALID_JSON');
        }
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        $headers['content-type'] = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        $body = file_get_contents('php://input', false, null, 0, 65537);
        return new self(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'),
            $headers, $body === false ? '' : $body, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }
}
