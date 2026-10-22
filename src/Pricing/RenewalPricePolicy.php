<?php
declare(strict_types=1);
namespace Hostider\Pricing;
use Hostider\Core\Http\HttpError;
final class RenewalPricePolicy
{
    public function resolve(PriceSnapshot $current,?PriceSnapshot $protected,bool $priceProtected,bool $noticeRequired,bool $noticeSatisfied): PriceSnapshot
    {
        if($priceProtected){return $protected??throw new HttpError(409,'PROTECTED_PRICE_SNAPSHOT_REQUIRED');}
        if($noticeRequired&&!$noticeSatisfied){throw new HttpError(409,'PRICE_CHANGE_NOTICE_REQUIRED');}
        return $current;
    }
}
