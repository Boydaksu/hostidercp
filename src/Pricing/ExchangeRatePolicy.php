<?php
declare(strict_types=1);
namespace Hostider\Pricing;
use Hostider\Core\Http\HttpError;
final readonly class ExchangeRatePolicy
{
    public function __construct(public int $maxAgeSeconds)
    {
        if($maxAgeSeconds<1 || $maxAgeSeconds>86400){throw new \InvalidArgumentException('Explicit FX freshness policy required');}
    }
    public function validate(array $row,\DateTimeImmutable $now): void
    {
        $observed=new \DateTimeImmutable($row['observed_at'],new \DateTimeZone('UTC'));
        if(!(bool)$row['trusted'] || $observed>$now || $observed->getTimestamp()+$this->maxAgeSeconds<=$now->getTimestamp()) { throw new HttpError(409,'FX_UNTRUSTED_OR_STALE'); }
    }
}
