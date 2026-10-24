<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Id};use Hostider\Core\Http\HttpError;use Hostider\Core\Security\Crypto;
final readonly class Confirmations
{
    public function __construct(private Commands $commands){}
    public function prepare(array $user,array $session,string $action,array $payload,?string $customer): array
    {
        $c=$this->commands->c;$c->db->requireTransaction();$actor=$this->commands->authorize($user,$session,$action,$payload,$customer);$state=$this->commands->lockState($action,$payload,$customer);$now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));$plan=$this->commands->plan($action,$payload,$customer,$actor,$now);$id=Id::uuid();$key=base64_decode($c->config->key,true);$json=CanonicalJson::encode($plan);
        $c->db->execute('INSERT INTO svc_confirmations(id,actor_id,session_id,identity_version,customer_id,service_id,action,payload_hmac,state_hmac,plan_encrypted,plan_hmac,planned_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE))',[$id,$user['id'],$session['id'],$user['identity_version'],$customer,$this->commands->serviceId($action,$payload,$customer),$action,hash_hmac('sha256',CanonicalJson::encode($payload),$key),hash_hmac('sha256',CanonicalJson::encode($state),$key),(new Crypto($key))->encrypt($json,'svc-confirmation:'.$id),hash_hmac('sha256',$json,$key),$now->format('Y-m-d H:i:s.u')]);$c->identity->confirmationEvent($user['id'],$id,false);return ['id'=>$id,'action'=>$action,'plan'=>$plan,'expires_in'=>300,'confirmation_required'=>true];
    }
    public function execute(array $user,array $session,string $action,array $payload,?string $customer,array $confirmation,string $trace): array
    {
        $c=$this->commands->c;$c->db->requireTransaction();$actor=$this->commands->authorize($user,$session,$action,$payload,$customer);if(($confirmation['accepted']??null)!==true||!is_string($confirmation['id']??null)){throw new HttpError(403,'EXPLICIT_SERVICE_CONFIRMATION_REQUIRED');}
        $row=$c->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) AS live FROM svc_confirmations WHERE id=? FOR UPDATE',[$confirmation['id']])->fetch();$key=base64_decode($c->config->key,true);
        if(!$row||$row['actor_id']!==$user['id']||$row['session_id']!==$session['id']||(string)$row['identity_version']!==(string)$user['identity_version']||$row['action']!==$action||$row['customer_id']!==$customer||!hash_equals($row['payload_hmac'],hash_hmac('sha256',CanonicalJson::encode($payload),$key))){throw new HttpError(403,'SERVICE_CONFIRMATION_MISMATCH');}if(!$row['live']||$row['used_at']!==null){throw new HttpError(409,'SERVICE_CONFIRMATION_EXPIRED_OR_USED');}
        $state=$this->commands->lockState($action,$payload,$customer);if(!hash_equals($row['state_hmac'],hash_hmac('sha256',CanonicalJson::encode($state),$key))){throw new HttpError(409,'SERVICE_STATE_CHANGED_REVIEW_AGAIN');}$now=new \DateTimeImmutable($row['planned_at'],new \DateTimeZone('UTC'));$plan=$this->commands->plan($action,$payload,$customer,$actor,$now);if(!hash_equals($row['plan_hmac'],hash_hmac('sha256',CanonicalJson::encode($plan),$key))){throw new HttpError(409,'SERVICE_PLAN_CHANGED_REVIEW_AGAIN');}
        $result=$this->commands->execute($action,$payload,$customer,$actor,$user,$session,$trace,$now);$c->db->execute('UPDATE svc_confirmations SET used_at=UTC_TIMESTAMP(6) WHERE id=?',[$row['id']]);$c->identity->confirmationEvent($user['id'],$row['id'],true);return $result;
    }
}
