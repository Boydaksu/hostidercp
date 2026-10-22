<?php
declare(strict_types=1);
namespace Hostider\Finance;

final readonly class Decimal implements \JsonSerializable
{
    private string $digits;
    private int $scale;
    private bool $negative;
    public function __construct(string $value)
    {
        if (!preg_match('/^(-?)([0-9]{1,240})(?:\.([0-9]{1,240}))?$/D',$value,$m)) {
            throw new \InvalidArgumentException('Invalid exact decimal');
        }
        $fraction=rtrim($m[3]??'','0'); $this->scale=strlen($fraction);
        $this->digits=Unsigned::normalize($m[2].$fraction);
        $this->negative=$m[1]==='-' && $this->digits!=='0';
    }
    private static function fromParts(string $digits,int $scale,bool $negative): self
    {
        $digits=str_pad($digits,$scale+1,'0',STR_PAD_LEFT);
        $value=$scale===0?$digits:substr($digits,0,-$scale).'.'.substr($digits,-$scale);
        return new self(($negative?'-':'').$value);
    }
    private function align(self $other): array
    {
        $scale=max($this->scale,$other->scale);
        return [$this->digits.str_repeat('0',$scale-$this->scale),$other->digits.str_repeat('0',$scale-$other->scale),$scale];
    }
    public function compare(self $other): int
    {
        if ($this->negative!==$other->negative) { return $this->negative?-1:1; }
        [$a,$b]=$this->align($other);
        return Unsigned::compare($a,$b)*($this->negative?-1:1);
    }
    public function add(self $other): self
    {
        [$a,$b,$scale]=$this->align($other);
        if ($this->negative===$other->negative) { return self::fromParts(Unsigned::add($a,$b),$scale,$this->negative); }
        $compare=Unsigned::compare($a,$b);
        return self::fromParts($compare>=0?Unsigned::subtract($a,$b):Unsigned::subtract($b,$a),$scale,$compare>=0?$this->negative:$other->negative);
    }
    public function subtract(self $other): self { return $this->add($other->negate()); }
    public function negate(): self { return self::fromParts($this->digits,$this->scale,!$this->negative); }
    public function multiply(self $other): self
    {
        return self::fromParts(Unsigned::multiply($this->digits,$other->digits),$this->scale+$other->scale,$this->negative!==$other->negative);
    }
    public function divide(self $other,int $scale,Rounding $rounding): self
    {
        self::validateScale($scale);
        $shift=$other->scale+$scale-$this->scale;
        $a=$this->digits.str_repeat('0',max(0,$shift));
        $b=$other->digits.str_repeat('0',max(0,-$shift));
        [$q,$r]=Unsigned::divide($a,$b);
        return self::fromParts(self::rounded($q,$r,$b,$rounding),$scale,$this->negative!==$other->negative);
    }
    public function quantize(int $scale,Rounding $rounding): string
    {
        self::validateScale($scale);
        if ($scale>=$this->scale) { $digits=$this->digits.str_repeat('0',$scale-$this->scale); }
        else {
            $divisor='1'.str_repeat('0',$this->scale-$scale);
            [$q,$r]=Unsigned::divide($this->digits,$divisor); $digits=self::rounded($q,$r,$divisor,$rounding);
        }
        $digits=str_pad($digits,$scale+1,'0',STR_PAD_LEFT);
        $value=$scale===0?$digits:substr($digits,0,-$scale).'.'.substr($digits,-$scale);
        return ($this->negative && Unsigned::normalize(str_replace('.','',$value))!=='0'?'-':'').$value;
    }
    private static function rounded(string $q,string $r,string $divisor,Rounding $rounding): string
    {
        $compare=Unsigned::compare(Unsigned::multiply($r,'2'),$divisor);
        $up=$rounding!==Rounding::TowardZero && ($compare>0 || ($compare===0 && ($rounding===Rounding::HalfUp || (ord($q[strlen($q)-1])-48)%2===1)));
        return $up?Unsigned::add($q,'1'):$q;
    }
    private static function validateScale(int $scale): void
    {
        if ($scale<0 || $scale>240) { throw new \InvalidArgumentException('Unsupported decimal scale'); }
    }
    public function __toString(): string { return $this->quantize($this->scale,Rounding::TowardZero); }
    public function jsonSerialize(): string { return (string)$this; }
}
