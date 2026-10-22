<?php
declare(strict_types=1);
namespace Hostider\Catalog;
final class BillingCycle
{
    public static function next(\DateTimeImmutable $start,string $kind,int $interval,?int $anchorDay=null): \DateTimeImmutable
    {
        if($interval<1||$interval>3660){throw new \InvalidArgumentException('Invalid cycle interval');}
        if($kind==='DAYS'){return $start->modify('+'.$interval.' days');}
        if($kind!=='MONTHS'){throw new \InvalidArgumentException('Nonrecurring cycle');}
        $month=$start->modify('first day of this month')->modify('+'.$interval.' months');
        $anchorDay??=(int)$start->format('d');if($anchorDay<1||$anchorDay>31){throw new \InvalidArgumentException('Invalid calendar anchor');}
        return $month->setDate((int)$month->format('Y'),(int)$month->format('m'),min($anchorDay,(int)$month->format('t')));
    }
}
