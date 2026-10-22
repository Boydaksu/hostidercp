<?php
declare(strict_types=1);
namespace Hostider\Pricing;
use Hostider\Core\Http\HttpError;
use Hostider\Finance\{Currency,Decimal,Money,Rounding};
final class TaxCalculator
{
    public function calculate(Decimal $amount,Currency $currency,?array $tax): array
    {
        if($tax===null){throw new HttpError(409,'EXPLICIT_TAX_POLICY_REQUIRED');}
        $rate=new Decimal($tax['rate']); $base=Money::fromDecimal($amount,$currency,Rounding::HalfUp);
        if((bool)$tax['inclusive']) {
            $taxAmount=Money::fromDecimal($base->decimal()->multiply($rate)->divide((new Decimal('1'))->add($rate),$currency->exponent,Rounding::HalfUp),$currency,Rounding::TowardZero);
            $negativeTax=$taxAmount->minor==='0'?'0':'-'.$taxAmount->minor;
            $net=$base->add(new Money($negativeTax,$currency)); $total=$base;
        } else { $net=$base; $taxAmount=Money::fromDecimal($base->decimal()->multiply($rate),$currency,Rounding::HalfUp); $total=$net->add($taxAmount); }
        return ['net_minor'=>$net->minor,'tax_minor'=>$taxAmount->minor,'total_minor'=>$total->minor,'tax_policy'=>['id'=>$tax['id'],'version'=>(string)$tax['version'],'rate'=>(string)$rate,'inclusive'=>(bool)$tax['inclusive']]];
    }
}
