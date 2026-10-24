<?php
declare(strict_types=1);
namespace Hostider\Cloud;
use Hostider\Core\{CanonicalJson,Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Audit,AuditEvent,Crypto,Principal};
use Hostider\Core\Queue\{Outbox,Queue};
final readonly class Store
{
    private string $key;private Crypto $crypto;
    public function __construct(public Database $db,public Config $config){if($config->environment!=='test'){throw new HttpError(403,'F4_TEST_ONLY');}$this->key=base64_decode($config->key,true);$this->crypto=new Crypto($this->key);}
    public function hash(array $value): string{return hash_hmac('sha256',CanonicalJson::encode($value),$this->key);}
    public function encrypt(array $value,string $context): string{return $this->crypto->encrypt(CanonicalJson::encode($value),$context);}
    public function decrypt(string $cipher,string $hash,string $context): array{$json=$this->crypto->decrypt($cipher,$context);if(!hash_equals($hash,hash_hmac('sha256',$json,$this->key))){throw new HttpError(409,'CLOUD_REQUEST_INTEGRITY');}return json_decode($json,true,64,JSON_THROW_ON_ERROR);}
    public function instance(string $id,bool $lock=true): array{if($lock){$this->db->requireTransaction();}$r=$this->db->execute('SELECT * FROM cloud_instances WHERE id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();if(!$r){throw new HttpError(404,'CLOUD_INSTANCE_NOT_FOUND');}return $r;}
    public function operation(string $id,bool $lock=true): array{if($lock){$this->db->requireTransaction();}$r=$this->db->execute('SELECT *,lease_until>UTC_TIMESTAMP(6) AS live FROM cloud_operations WHERE id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();if(!$r){throw new HttpError(404,'CLOUD_OPERATION_NOT_FOUND');}return $r;}
    public function request(array $operation): array{return $this->decrypt($operation['request_encrypted'],$operation['fingerprint'],'cloud-request:'.$operation['id']);}
    public function history(array $instance,?string $operation,string $kind,Principal $actor): void
    {
        $this->db->requireTransaction();$trace=Id::uuid();$this->db->execute('INSERT INTO cloud_history(id,instance_id,operation_id,kind,trace_id) VALUES (?,?,?,?,?)',[Id::uuid(),$instance['id'],$operation,$kind,$trace]);
        (new Audit($this->db))->record($actor,AuditEvent::ServiceChanged,$instance['service_id'],$trace);
        (new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'cloud.changed',$instance['id'],['instance_id'=>$instance['id']],$trace);
    }
    public function source(array $instance): bool
    {
        if($instance['imported']){return true;}
        return (new Entitlements($this->db,$this->config))->valid($instance);
    }
    public function authorize(array $instance,Principal $actor,bool $staff=false): void
    {
        (new \Hostider\Core\Security\Authorization())->require($actor,'services.manage',$instance['customer_id'],privileged:true,service:$instance['service_id']);if($actor->impersonating||($staff&&!$actor->staff)){throw new HttpError(403,'CLOUD_ACTOR_FORBIDDEN');}
    }
}
