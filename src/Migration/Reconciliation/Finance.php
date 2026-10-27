<?php
declare(strict_types=1);
namespace Hostider\Migration\Reconciliation;
use Hostider\Finance\{Currency,Money};
use Hostider\Migration\Contract;

/** Metadata totals, not a second ledger or a payment decision. */
final class Finance
{
    public static function totals(array $facts): array
    {
        $result=[];$allocated=[];$orders=[];$unknown=[];
        foreach($facts as $key=>$fact){
            $p=$fact['payload'];$type=$fact['type'];
            $field=match($type){'order'=>'total','payment','allocation','refund','document'=>'amount','wallet'=>'liability',default=>null};
            if($field===null){continue;}
            Contract::require(isset($p[$field]) && is_array($p[$field]),'F6C_MONEY_UNAVAILABLE');
            Contract::fields($p[$field],['minor','currency']);
            $amount=new Money($p[$field]['minor'],new Currency($p[$field]['currency']));
            Contract::require(!str_starts_with($amount->minor,'-'),'F6C_MONEY_NEGATIVE');
            $category=match($type){'order'=>'order_total','payment'=>'payment_amount','wallet'=>'wallet_liability','document'=>match($p['kind']){'PROFORMA'=>'proforma','OFFICIAL_PDF'=>'official_pdf',default=>throw new \InvalidArgumentException('F6C_DOCUMENT_KIND')},default=>$type};
            self::sum($result,$category,$amount);
            if($type==='payment'){
                if(($p['state']['state']??null)!=='KNOWN'){$unknown['confirmed_collection']=true;}
                elseif($p['state']['value']==='CONFIRMED'){self::sum($result,'confirmed_collection',$amount);}
            }
            if($type==='allocation'){$owner=Contract::hash([$fact['instance'],$p['order']]);self::sum($allocated,$owner,$amount);}
            if($type==='order'){$orders[Contract::hash([$fact['instance'],$fact['id']])]=$amount;}
        }
        foreach($orders as $key=>$amount){$funded=$allocated[$key][$amount->currency->code]??new Money('0',$amount->currency);$negative=$funded->minor==='0'?'0':'-'.$funded->minor;self::sum($result,'remaining_balance',$amount->add(new Money($negative,$amount->currency)));}
        $out=[];ksort($result,SORT_STRING);
        foreach($result as $category=>$currencies){ksort($currencies,SORT_STRING);foreach($currencies as $currency=>$money){$out[$category][$currency]=$money->minor;}}
        foreach($unknown as $category=>$flag){$out[$category]=['status'=>'NOT_AVAILABLE'];}
        $absent=array_values(array_diff(['order_total','payment_amount','confirmed_collection','allocation','refund','wallet_liability','remaining_balance','proforma','official_pdf'],array_keys($out)));
        return ['status'=>'OBSERVED_SYNTHETIC_METADATA','totals'=>$out,'no_facts_in_observed_scope'=>$absent,'ledger_debit'=>'NOT_AVAILABLE','ledger_credit'=>'NOT_AVAILABLE','real_payment_reconciliation'=>'NOT_VERIFIED'];
    }
    public static function differences(array $source,array $target): array
    {
        $out=[];$categories=array_unique([...array_keys($source['totals']),...array_keys($target['totals'])]);sort($categories,SORT_STRING);
        foreach($categories as $category){$left=$source['totals'][$category]??[];$right=$target['totals'][$category]??[];
            if(isset($left['status'])||isset($right['status'])){$out[$category]=['status'=>'NOT_AVAILABLE'];continue;}
            $currencies=array_unique([...array_keys($left),...array_keys($right)]);sort($currencies,SORT_STRING);
            foreach($currencies as $currency){$value=$left[$currency]??'0';$negative=$value==='0'?'0':'-'.$value;$out[$category][$currency]=(new Money($right[$currency]??'0',new Currency($currency)))->add(new Money($negative,new Currency($currency)))->minor;}
        }return $out;
    }
    private static function sum(array &$totals,string $category,Money $amount): void
    {
        $currency=$amount->currency->code;$totals[$category][$currency]=isset($totals[$category][$currency])?$totals[$category][$currency]->add($amount):$amount;
    }
}
