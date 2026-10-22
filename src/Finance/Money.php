<?php
declare(strict_types=1);
namespace Hostider\Finance;
final readonly class Money implements \JsonSerializable
{
    public string $minor;
    public function __construct(string $minor,public Currency $currency)
    {
        if (!preg_match('/^-?(?:0|[1-9][0-9]{0,18})$/D',$minor) || $minor==='-0') { throw new \InvalidArgumentException('Noncanonical minor amount'); }
        if (Unsigned::compare(ltrim($minor,'-'),'9223372036854775807')>0) { throw new \OverflowException('Amount exceeds storage range'); }
        $this->minor=$minor;
    }
    public static function fromDecimal(Decimal $amount,Currency $currency,Rounding $rounding): self
    {
        $value=str_replace('.','',$amount->quantize($currency->exponent,$rounding));
        $digits=Unsigned::normalize(ltrim($value,'-'));
        return new self(str_starts_with($value,'-') && $digits!=='0'?'-'.$digits:$digits,$currency);
    }
    public function decimal(): Decimal
    {
        return (new Decimal($this->minor))->divide(new Decimal('1'.str_repeat('0',$this->currency->exponent)),$this->currency->exponent,Rounding::TowardZero);
    }
    public function add(self $other): self
    {
        if ($this->currency->code!==$other->currency->code) { throw new \InvalidArgumentException('Currency mismatch'); }
        return new self((string)(new Decimal($this->minor))->add(new Decimal($other->minor)),$this->currency);
    }
    public function jsonSerialize(): array { return ['minor'=>$this->minor,'currency'=>$this->currency->code,'exponent'=>$this->currency->exponent]; }
}
