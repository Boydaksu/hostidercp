<?php
declare(strict_types=1);
namespace Hostider\CloudCosts;
use Hostider\Finance\{Currency,Decimal,Money,Rounding,FxSnapshot};use Hostider\Pricing\TaxCalculator;use Hostider\Core\{Validation};use Hostider\Core\Http\HttpError;
/** All policies are explicit synthetic fixtures; no real Voyar billing rules are asserted. */
final class Calculator
{
    public static function policy(array $p): void
    {
        Validation::fields($p,['currency','hourly','quantum_seconds','minimum_seconds','minimum_minor','rounding','tax_rate','addons','fees','valid_from','valid_until','max_age_seconds','spec_hash','fx','provenance'],['currency','hourly','quantum_seconds','minimum_seconds','minimum_minor','rounding','tax_rate','addons','fees','valid_from','valid_until','max_age_seconds','spec_hash','fx','provenance']);
        new Currency($p['currency']);$r=new Decimal($p['hourly']);$tax=new Decimal($p['tax_rate']);new Money($p['minimum_minor'],new Currency($p['currency']));Rounding::from($p['rounding']);
        if($p['provenance']!=='MOCK'||$r->compare(new Decimal('0'))<0||$tax->compare(new Decimal('0'))<0||$tax->compare(new Decimal('1'))>0||str_starts_with($p['minimum_minor'],'-')||!preg_match('/^[a-f0-9]{64}$/D',$p['spec_hash'])||Store::date($p['valid_until'])<=Store::date($p['valid_from'])){throw new HttpError(422,'CLOUD_TARIFF_INVALID');}
        foreach(['quantum_seconds'=>[1,3600],'minimum_seconds'=>[0,86400],'max_age_seconds'=>[1,604800]] as $key=>[$min,$max]){if(!is_int($p[$key])||$p[$key]<$min||$p[$key]>$max){throw new HttpError(422,'CLOUD_TARIFF_INVALID');}}
        if(!is_array($p['addons'])||count($p['addons'])>20){throw new HttpError(422,'CLOUD_TARIFF_INVALID');}
        foreach($p['addons'] as $key=>$a){if(!is_string($key)||!preg_match('/^[a-z0-9_-]{1,32}$/D',$key)||!is_array($a)||array_keys($a)!==['hourly','max_quantity']||(new Decimal($a['hourly']))->compare(new Decimal('0'))<0||!is_int($a['max_quantity'])||$a['max_quantity']<1||$a['max_quantity']>1000){throw new HttpError(422,'CLOUD_TARIFF_ADDON_INVALID');}}
        if(!is_array($p['fees'])||count($p['fees'])>20){throw new HttpError(422,'CLOUD_TARIFF_FEE_INVALID');}
        foreach($p['fees'] as $key=>$fee){if(!is_string($key)||!preg_match('/^[a-z0-9_-]{1,32}$/D',$key)||!is_array($fee)){throw new HttpError(422,'CLOUD_TARIFF_FEE_INVALID');}Validation::fields($fee,['minor','max_quantity'],['minor','max_quantity']);$money=new Money($fee['minor'],new Currency($p['currency']));if(str_starts_with($money->minor,'-')||!is_int($fee['max_quantity'])||$fee['max_quantity']<1||$fee['max_quantity']>1000){throw new HttpError(422,'CLOUD_TARIFF_FEE_INVALID');}}
        if($p['fx']!==null){self::fx($p['fx']);if($p['fx']['source']!==$p['currency']){throw new HttpError(422,'CLOUD_TARIFF_FX_MISMATCH');}}
    }
    public static function fx(array $f): FxSnapshot{Validation::fields($f,['source','target','rate','spread','reference','observed_at','rounding'],['source','target','rate','spread','reference','observed_at','rounding']);return new FxSnapshot(new Currency($f['source']),new Currency($f['target']),new Decimal($f['rate']),new Decimal($f['spread']),$f['reference'],Store::date($f['observed_at']),Rounding::from($f['rounding']));}
    public static function calculate(array $p,int $seconds,array $addons,array $fees=[]): array
    {
        self::policy($p);if($seconds<1||$seconds>31622400){throw new HttpError(422,'CLOUD_USAGE_DURATION_INVALID');}
        $q=$p['quantum_seconds'];$billed=max($p['minimum_seconds'],intdiv($seconds+$q-1,$q)*$q);$hourly=new Decimal($p['hourly']);$addonHourly=new Decimal('0');
        foreach($addons as $key=>$quantity){if(!isset($p['addons'][$key])||!is_int($quantity)||$quantity<0||$quantity>$p['addons'][$key]['max_quantity']){throw new HttpError(409,'CLOUD_USAGE_ADDON_UNAUTHORIZED');}$addonHourly=$addonHourly->add((new Decimal($p['addons'][$key]['hourly']))->multiply(new Decimal((string)$quantity)));}
        $currency=new Currency($p['currency']);$round=Rounding::from($p['rounding']);
        // Divide directly to the final currency exponent to avoid double rounding.
        $amount=$hourly->add($addonHourly)->multiply(new Decimal((string)$billed))->divide(new Decimal('3600'),$currency->exponent,$round);
        $minimum=(new Money($p['minimum_minor'],$currency))->decimal();if($amount->compare($minimum)<0){$amount=$minimum;}
        $feeMinor=new Decimal('0');foreach($fees as $key=>$quantity){if(!isset($p['fees'][$key])||!is_int($quantity)||$quantity<0||$quantity>$p['fees'][$key]['max_quantity']){throw new HttpError(409,'CLOUD_USAGE_FEE_UNAUTHORIZED');}$feeMinor=$feeMinor->add((new Decimal($p['fees'][$key]['minor']))->multiply(new Decimal((string)$quantity)));}
        $feeMoney=new Money((string)$feeMinor,$currency);$rule=['id'=>'synthetic-provider-tax','version'=>1,'rate'=>$p['tax_rate'],'inclusive'=>false];
        $tax=(new TaxCalculator())->calculate($amount,$currency,$rule);$feeTax=(new TaxCalculator())->calculate($feeMoney->decimal(),$currency,$rule);
        $usageTotal=new Money($tax['total_minor'],$currency);$feeTotal=new Money($feeTax['total_minor'],$currency);$total=$usageTotal->add($feeTotal);
        $net=(string)(new Decimal($tax['net_minor']))->add(new Decimal($feeTax['net_minor']));$taxMinor=(string)(new Decimal($tax['tax_minor']))->add(new Decimal($feeTax['tax_minor']));
        $fx=$p['fx']===null?null:self::fx($p['fx']);$report=$fx?$fx->convert($total):$total;
        return ['provenance'=>'MOCK','seconds'=>$seconds,'billed_seconds'=>$billed,'hourly'=>$p['hourly'],'addon_hourly'=>(string)$addonHourly,'addons'=>$addons,'currency'=>$currency->code,'net_minor'=>$net,'tax_minor'=>$taxMinor,'usage_net_minor'=>$tax['net_minor'],'fee_minor'=>$feeMoney->minor,'fees'=>$fees,'usage_total_minor'=>$usageTotal->minor,'fee_total_minor'=>$feeTotal->minor,'total_minor'=>$total->minor,'tax_policy'=>$tax['tax_policy'],'rounding'=>$round->value,'tax_rounding'=>'HALF_UP_PER_COMPONENT','fx'=>$p['fx'],'reporting'=>$report->jsonSerialize(),'usage_reporting'=>($fx?$fx->convert($usageTotal):$usageTotal)->jsonSerialize(),'tax_recoverable'=>false,'policy'=>$p];
    }
}
