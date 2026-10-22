<?php
declare(strict_types=1);
namespace Hostider\Finance;

/** Internal non-negative integer arithmetic; no conversion of amounts to machine numbers. */
final class Unsigned
{
    public static function normalize(string $value): string
    {
        if (!preg_match('/^[0-9]{1,240}$/D', $value)) { throw new \InvalidArgumentException('Invalid exact integer'); }
        return ltrim($value, '0') ?: '0';
    }
    public static function compare(string $a, string $b): int
    {
        $a=self::normalize($a); $b=self::normalize($b);
        return strlen($a) <=> strlen($b) ?: strcmp($a, $b) <=> 0;
    }
    public static function add(string $a, string $b): string
    {
        $a=self::normalize($a); $b=self::normalize($b); $carry=0; $out='';
        for ($i=strlen($a)-1,$j=strlen($b)-1; $i>=0 || $j>=0 || $carry; --$i,--$j) {
            $n=($i>=0?ord($a[$i])-48:0)+($j>=0?ord($b[$j])-48:0)+$carry;
            $out=chr(48+$n%10).$out; $carry=intdiv($n,10);
        }
        return self::normalize($out);
    }
    public static function subtract(string $a, string $b): string
    {
        if (self::compare($a,$b)<0) { throw new \UnderflowException('Negative unsigned result'); }
        $a=self::normalize($a); $b=self::normalize($b); $borrow=0; $out='';
        for ($i=strlen($a)-1,$j=strlen($b)-1; $i>=0; --$i,--$j) {
            $n=ord($a[$i])-48-($j>=0?ord($b[$j])-48:0)-$borrow;
            $borrow=$n<0?1:0; $out=chr(48+$n+10*$borrow).$out;
        }
        return self::normalize($out);
    }
    public static function multiply(string $a, string $b): string
    {
        $a=self::normalize($a); $b=self::normalize($b); $out='0';
        for ($i=0; $i<strlen($b); ++$i) {
            $out=self::normalize($out.'0'); $digit=ord($b[$i])-48;
            for ($j=0; $j<$digit; ++$j) { $out=self::add($out,$a); }
        }
        return $out;
    }
    /** @return array{string,string} quotient and remainder */
    public static function divide(string $a, string $b): array
    {
        $a=self::normalize($a); $b=self::normalize($b);
        if ($b==='0') { throw new \DivisionByZeroError(); }
        $quotient=''; $remainder='0';
        for ($i=0; $i<strlen($a); ++$i) {
            $remainder=self::normalize(($remainder==='0'?'':$remainder).$a[$i]); $digit=0;
            while (self::compare($remainder,$b)>=0) { $remainder=self::subtract($remainder,$b); ++$digit; }
            $quotient.=chr(48+$digit);
        }
        return [self::normalize($quotient),$remainder];
    }
}
