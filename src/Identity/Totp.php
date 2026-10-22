<?php
declare(strict_types=1);
namespace Hostider\Identity;
final class Totp
{
    public static function secret(): string
    {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits='';
        foreach (str_split(random_bytes(20)) as $byte) { $bits.=str_pad(decbin(ord($byte)),8,'0',STR_PAD_LEFT); }
        $out=''; foreach (str_split($bits,5) as $part) { $out.=$alphabet[bindec($part)]; } return $out;
    }
    public static function code(#[\SensitiveParameter] string $secret,int $counter): string
    {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits='';
        foreach (str_split($secret) as $char) { $pos=strpos($alphabet,$char); if ($pos===false) { throw new \InvalidArgumentException('Invalid TOTP secret'); } $bits.=str_pad(decbin($pos),5,'0',STR_PAD_LEFT); }
        $key=''; foreach (str_split($bits,8) as $byte) { if (strlen($byte)===8) { $key.=chr(bindec($byte)); } }
        $hash=hash_hmac('sha1',pack('N2',intdiv($counter,4294967296),$counter%4294967296),$key,true);
        $offset=ord($hash[19])&15; $number=unpack('N',substr($hash,$offset,4))[1]&2147483647;
        return str_pad((string)($number%1000000),6,'0',STR_PAD_LEFT);
    }
    public static function counter(string $secret,#[\SensitiveParameter] string $code,int $last,int $now): ?int
    {
        if (!preg_match('/^[0-9]{6}$/D',$code)) { return null; }
        $current=intdiv($now,30);
        foreach ([$current-1,$current,$current+1] as $counter) { if ($counter>$last && hash_equals(self::code($secret,$counter),$code)) { return $counter; } }
        return null;
    }
}
