<?php
declare(strict_types=1);
namespace Hostider\Core;

final readonly class Config
{
    public function __construct(
        public string $environment,
        public string $url,
        #[\SensitiveParameter] public string $key,
        public string $dsn,
        public string $dbUser,
        #[\SensitiveParameter] public string $dbPassword,
        public bool $provisioning = false,
        public bool $payments = false,
        public bool $mail = false,
    ) {
        if (!in_array($environment, ['test', 'staging', 'production'], true)
            || !str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false
            || strlen((string) base64_decode($key, true)) !== 32
            || !preg_match('/^mysql:.*dbname=[a-zA-Z0-9_]+(?:;|$)/', $dsn)
            || !str_contains($dsn, 'charset=utf8mb4') || $dbUser === '') {
            throw new \InvalidArgumentException('Invalid application configuration');
        }
        if ($environment !== 'production' && ($provisioning || $payments || $mail)) {
            throw new \InvalidArgumentException('Live effects forbidden outside production');
        }
    }

    public static function fromEnvironment(): self
    {
        $read = static fn(string $name): string => (($v = getenv($name)) === false ? '' : $v);
        $flag = static function (string $name) use ($read): bool {
            $v = $read($name);
            if (!in_array($v, ['', 'false', 'true'], true)) {
                throw new \InvalidArgumentException('Invalid effect flag');
            }
            return $v === 'true';
        };
        return new self($read('APP_ENV'), $read('APP_URL'), $read('APP_KEY'), $read('DB_DSN'),
            $read('DB_USER'), $read('DB_PASSWORD'), $flag('PROVISIONING_ALLOWED'),
            $flag('PAYMENT_LIVE_ALLOWED'), $flag('EXTERNAL_MAIL_ALLOWED'));
    }
}
