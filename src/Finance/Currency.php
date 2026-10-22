<?php
declare(strict_types=1);
namespace Hostider\Finance;
final readonly class Currency
{
    public int $exponent;
    public function __construct(public string $code)
    {
        $exponents=['TRY'=>2,'USD'=>2,'EUR'=>2,'JPY'=>0,'KWD'=>3];
        $this->exponent=$exponents[$code]??throw new \InvalidArgumentException('Unsupported currency');
    }
}
