<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\Http\HttpError;
final class State
{
    private const NEXT=[
        'PENDING_PAYMENT'=>['PENDING_APPROVAL','QUEUED','FAILED'],
        'PENDING_APPROVAL'=>['QUEUED','FAILED'],
        'QUEUED'=>['PROVISIONING','TERMINATING','FAILED','UNKNOWN'],
        'PROVISIONING'=>['ACTIVE','FAILED','UNKNOWN'],
        'ACTIVE'=>['SUSPENDED','CANCEL_SCHEDULED','TERMINATING','UNKNOWN'],
        'SUSPENDED'=>['ACTIVE','CANCEL_SCHEDULED','TERMINATING','UNKNOWN'],
        'CANCEL_SCHEDULED'=>['ACTIVE','SUSPENDED','TERMINATING','UNKNOWN'],
        'TERMINATING'=>['TERMINATED','FAILED','UNKNOWN'],
        'FAILED'=>['QUEUED','TERMINATING'],
        'UNKNOWN'=>['ACTIVE','SUSPENDED','TERMINATED','FAILED'],
        'TERMINATED'=>[],
    ];
    public static function assertTransition(string $from,string $to): void {if(!in_array($to,self::NEXT[$from]??[],true)){throw new HttpError(409,'INVALID_SERVICE_TRANSITION');}}
}
