<?php
declare(strict_types=1);
namespace Hostider\Support;
use Hostider\Core\Http\HttpError;
final class State
{
    public static function transition(string $from,string $to,array $department): void
    {
        $allowed=match($from){
            'NEW'=>['WAITING_STAFF','WAITING_CUSTOMER','RESOLVED','CLOSED'],
            'WAITING_STAFF'=>['WAITING_CUSTOMER','RESOLVED','CLOSED'],
            'WAITING_CUSTOMER'=>['WAITING_STAFF','RESOLVED','CLOSED'],
            'ON_HOLD'=>['WAITING_STAFF','WAITING_CUSTOMER','RESOLVED','CLOSED'],
            'RESOLVED'=>['WAITING_STAFF','CLOSED'],
            'CLOSED'=>(bool)$department['allow_reopen']?['WAITING_STAFF']:[],
            default=>[],
        };
        if((bool)$department['allow_hold']&&in_array($from,['NEW','WAITING_STAFF','WAITING_CUSTOMER'],true)){$allowed[]='ON_HOLD';}
        if(!in_array($to,$allowed,true)){throw new HttpError(409,'SUPPORT_STATE_CONFLICT');}
    }
}
