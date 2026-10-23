<?php
declare(strict_types=1);
namespace Hostider\Commerce;
/** Read-only eligibility contract. It neither claims nor dispatches provider work. */
final class HandoffPolicy
{
    public static function evaluate(array $order,array $bill,array $activationPolicies,bool $releaseGatesClosed): array
    {
        $reason=match(true){
            !$releaseGatesClosed=>'RELEASE_GATES_OPEN',
            ($order['origin_environment']??'LEGACY_UNKNOWN')!=='production'=>'NON_PRODUCTION_OR_UNKNOWN_ORIGIN',
            ($order['status']??null)!=='READY_FOR_PROVISIONING'=>'ORDER_NOT_READY',
            ($order['refund_status']??null)!=='NONE'=>'REFUND_REVIEW_REQUIRED',
            !is_string($order['id']??null)||!is_string($order['customer_id']??null)||!is_string($order['payment_request_id']??null)||$order['id']===''||$order['customer_id']===''||$order['payment_request_id']===''=>'MISSING_CANONICAL_BINDING',
            $order['payment_request_id']!==($bill['id']??null)||$order['customer_id']!==($bill['customer_id']??null)||$order['id']!==($bill['order_id']??null)=>'BILL_BINDING_MISMATCH',
            ($bill['status']??null)!=='PAID'=>'FULL_PAYMENT_REQUIRED',
            $activationPolicies===[]||count(array_filter($activationPolicies,fn($policy)=>$policy!=='AUTO'))>0=>'MANUAL_OR_UNKNOWN_POLICY',
            default=>'ELIGIBLE_FOR_FUTURE_ATOMIC_CLAIM',
        };
        return ['eligible'=>$reason==='ELIGIBLE_FOR_FUTURE_ATOMIC_CLAIM','reason'=>$reason,'dispatch_enabled'=>false];
    }
}
