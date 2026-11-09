<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Core\Http\HttpError;
final class Time
{
    public const MINUTE=60_000_000;
    public static function parse(string $s): int
    {
        if(!preg_match('/^(20[0-9]{2})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.([0-9]{1,6}))?$/D',$s,$m)){throw new HttpError(422,'INVALID_WORK_TIME');}
        $v=substr($s,0,19).'.'.str_pad($m[7]??'',6,'0');$d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u',$v,new \DateTimeZone('UTC'));
        if(!$d||$d->format('Y-m-d H:i:s.u')!==$v){throw new HttpError(422,'INVALID_WORK_TIME');}return $d->getTimestamp()*1_000_000+(int)$d->format('u');
    }
    public static function date(int $us): \DateTimeImmutable
    {
        $d=\DateTimeImmutable::createFromFormat('U.u',sprintf('%d.%06d',intdiv($us,1_000_000),$us%1_000_000));if(!$d){throw new \LogicException('Invalid work time');}return $d->setTimezone(new \DateTimeZone('UTC'));
    }
    public static function format(int $us): string{return self::date($us)->format('Y-m-d H:i:s.u');}
    public static function now(\Hostider\Core\Database\Database $db): int{return self::parse($db->execute('SELECT UTC_TIMESTAMP(6)')->fetchColumn());}
    public static function minutes(int $us): int{return intdiv(max(0,$us)+self::MINUTE-1,self::MINUTE);}
}
