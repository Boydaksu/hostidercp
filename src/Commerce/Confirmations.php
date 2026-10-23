<?php
declare(strict_types=1);
namespace Hostider\Commerce;
use Hostider\Core\{CanonicalJson,Id};
use Hostider\Core\Http\HttpError;
/** Pure plan/validate; actual commands are never executed for preview. */
final readonly class Confirmations
{
    public function __construct(private Services $services,private Commands $commands) {}
    private function planDigest(array $plan,string $key): string
    {
        $normalize=function(array $data)use(&$normalize): array {foreach($data as $k=>$v){if(in_array($k,['created_at','expires_at'],true)){unset($data[$k]);}elseif(is_array($v)){$data[$k]=$normalize($v);}}return $data;};
        return hash_hmac('sha256',CanonicalJson::encode($normalize($plan)),$key);
    }
    public function prepare(array $user,array $session,string $action,array $payload,?string $customer): array
    {
        $s=$this->services;$s->db->requireTransaction();$actor=$this->commands->authorize($user,$session,$action,$customer);
        $state=new State($s->db);$locked=$state->lock($customer,$actor->actorId);$plan=$this->commands->plan($action,$payload,$customer,$actor);
        $id=Id::uuid();$key=base64_decode($s->config->key,true);$s->db->execute('INSERT INTO commerce_confirmations(id,actor_id,session_id,identity_version,action,customer_id,payload_hmac,state_hmac,plan_hmac,expires_at) VALUES (?,?,?,?,?,?,?,?,?,TIMESTAMPADD(MINUTE,5,UTC_TIMESTAMP(6)))',[$id,$user['id'],$session['id'],$user['identity_version'],$action,$customer,hash_hmac('sha256',CanonicalJson::encode($payload),$key),$state->digest($locked,$key),$this->planDigest($plan,$key)]);
        $s->identity->confirmationEvent($user['id'],$id,false);return ['id'=>$id,'expires_in'=>300,'action'=>$action,'plan'=>$plan,'confirmation_required'=>true];
    }
    public function execute(array $user,array $session,string $action,array $payload,?string $customer,array $confirmation,string $trace): array
    {
        $s=$this->services;$s->db->requireTransaction();$actor=$this->commands->authorize($user,$session,$action,$customer);
        if(($confirmation['accepted']??null)!==true||!is_string($confirmation['id']??null)){throw new HttpError(403,'EXPLICIT_FINANCIAL_CONFIRMATION_REQUIRED');}
        $row=$s->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) valid FROM commerce_confirmations WHERE id=? FOR UPDATE',[$confirmation['id']])->fetch();$key=base64_decode($s->config->key,true);
        if(!$row||$row['actor_id']!==$user['id']||$row['session_id']!==$session['id']||(string)$row['identity_version']!==(string)$user['identity_version']||$row['action']!==$action||$row['customer_id']!==$customer||!hash_equals($row['payload_hmac'],hash_hmac('sha256',CanonicalJson::encode($payload),$key))){throw new HttpError(403,'FINANCIAL_CONFIRMATION_MISMATCH');}
        if(!(bool)$row['valid']||$row['used_at']!==null){throw new HttpError(409,'FINANCIAL_CONFIRMATION_EXPIRED_OR_USED');}
        $state=new State($s->db);$current=$state->lock($customer,$actor->actorId);
        if(!hash_equals($row['state_hmac'],$state->digest($current,$key))){throw new HttpError(409,'FINANCIAL_STATE_CHANGED_REVIEW_AGAIN');}
        $plan=$this->commands->plan($action,$payload,$customer,$actor);if(!hash_equals($row['plan_hmac'],$this->planDigest($plan,$key))){throw new HttpError(409,'FINANCIAL_PLAN_CHANGED_REVIEW_AGAIN');}$result=$this->commands->execute($action,$payload,$customer,$actor,$trace);
        $s->db->execute('UPDATE commerce_confirmations SET used_at=UTC_TIMESTAMP(6) WHERE id=?',[$row['id']]);$s->identity->confirmationEvent($user['id'],$row['id'],true);return $result;
    }
}
