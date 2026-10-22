<?php
declare(strict_types=1);
namespace Hostider\Pricing;
use Hostider\Catalog\Repository;
use Hostider\Core\Http\HttpError;
use Hostider\Finance\{Currency,Decimal};
final readonly class PriceResolver
{
    public function __construct(private Repository $catalog,private FxRateRepository $fx,private ExchangeRatePolicy $policy) {}
    public function resolve(array $product,string $item,string $cycle,Currency $currency,\DateTimeImmutable $now): array
    {
        $strategy=$product['pricing_strategy'];
        $price=$this->catalog->price($item,$cycle,$currency->code);
        if($strategy!=='SOURCE_FX' && $price!==null){return ['amount'=>new Decimal($price['amount']),'price_id'=>$price['id'],'price_version'=>(string)$price['version'],'source_currency'=>$currency->code,'source_amount'=>$price['amount'],'fx'=>null];}
        if($strategy==='FIXED_PER_CURRENCY'){throw new HttpError(409,'INDEPENDENT_PRICE_NOT_FOUND');}
        $source=new Currency($product['source_currency']); $price=$this->catalog->price($item,$cycle,$source->code);
        if($price===null){throw new HttpError(409,'SOURCE_PRICE_NOT_FOUND');}
        $amount=new Decimal($price['amount']); $fx=null;
        if($source->code!==$currency->code){$rate=$this->fx->resolve($source,$currency,$this->policy,$now);$fx=['rate_id'=>$rate['id']]+$rate['snapshot']->jsonSerialize();$amount=$amount->multiply($rate['snapshot']->rate)->multiply((new Decimal('1'))->add($rate['snapshot']->spread));}
        return ['amount'=>$amount,'price_id'=>$price['id'],'price_version'=>(string)$price['version'],'source_currency'=>$source->code,'source_amount'=>$price['amount'],'fx'=>$fx];
    }
}
