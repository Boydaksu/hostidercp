<?php
declare(strict_types=1);
namespace Hostider\Pricing;
use Hostider\Core\Http\HttpError;
use Hostider\Finance\Decimal;
final class PromotionEngine
{
    public function apply(Decimal $amount,array $promotions): array
    {
        if(count($promotions)>1 && in_array(false,array_map(fn(array $p): bool=>(bool)$p['combinable'],$promotions),true)){throw new HttpError(409,'PROMOTION_COMBINATION_FORBIDDEN');}
        $original=$amount; $snapshots=[];
        foreach($promotions as $row) {
            $amount=$amount->multiply((new Decimal('1'))->subtract(new Decimal($row['rate'])));
            $snapshots[]=['id'=>$row['id'],'code'=>$row['code'],'version'=>(string)$row['version'],'rate'=>$row['rate'],'combinable'=>(bool)$row['combinable']];
        }
        return ['amount'=>$amount,'discount'=>$original->subtract($amount),'promotions'=>$snapshots];
    }
}
