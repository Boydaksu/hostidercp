<?php
declare(strict_types=1);
namespace Hostider\Finance;
final readonly class FxSnapshot implements \JsonSerializable
{
    public function __construct(public Currency $source,public Currency $target,public Decimal $rate,public Decimal $spread,public string $reference,public \DateTimeImmutable $observedAt,public Rounding $rounding)
    {
        if ($source->code===$target->code || $rate->compare(new Decimal('0'))<=0 || $spread->compare(new Decimal('0'))<0
            || $rate->compare(new Decimal('999999999999.999999999999'))>0 || $spread->compare(new Decimal('9999.999999999999'))>0
            || (string)new Decimal($rate->quantize(12,Rounding::TowardZero))!==(string)$rate
            || (string)new Decimal($spread->quantize(12,Rounding::TowardZero))!==(string)$spread
            || !preg_match('/^[A-Za-z0-9:_-]{1,128}$/D',$reference)) { throw new \InvalidArgumentException('Invalid FX snapshot'); }
    }
    public function convert(Money $original): Money
    {
        if ($original->currency->code!==$this->source->code) { throw new \InvalidArgumentException('FX source mismatch'); }
        return Money::fromDecimal($original->decimal()->multiply($this->rate)->multiply((new Decimal('1'))->add($this->spread)),$this->target,$this->rounding);
    }
    public function jsonSerialize(): array
    {
        return ['source'=>$this->source->code,'target'=>$this->target->code,'rate'=>(string)$this->rate,'spread'=>(string)$this->spread,'reference'=>$this->reference,'observed_at'=>$this->observedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),'rounding'=>$this->rounding->value];
    }
}
