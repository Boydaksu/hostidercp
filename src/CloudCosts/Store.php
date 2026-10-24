<?php
declare(strict_types=1);
namespace Hostider\CloudCosts;
use Hostider\Core\{CanonicalJson,Config};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\Crypto;
final readonly class Store
{
    private Crypto $crypto;
    public function __construct(public Database $db,public Config $config){if($config->environment!=='test'){throw new HttpError(403,'CLOUD_COST_TEST_ONLY');}$this->crypto=new Crypto(base64_decode($config->key,true));}
    public function hash(array $v): string{return hash_hmac('sha256',CanonicalJson::encode($v),base64_decode($this->config->key,true));}
    public function encrypt(array $v,string $context): string{return $this->crypto->encrypt(CanonicalJson::encode($v),$context);}
    public function decrypt(string $v,string $hash,string $context): array{$a=json_decode($this->crypto->decrypt($v,$context),true,64,JSON_THROW_ON_ERROR);if(!hash_equals($this->hash($a),$hash)){throw new HttpError(409,'CLOUD_COST_INTEGRITY');}return $a;}
    public static function date(string $v): \DateTimeImmutable{$d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v,new \DateTimeZone('UTC'));if(!$d||$d->format('Y-m-d H:i:s')!==$v){throw new HttpError(422,'CLOUD_COST_TIME_INVALID');}return $d;}
    public function instance(string $id,bool $lock=true): array{$r=$this->db->execute('SELECT * FROM cloud_instances WHERE id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();if(!$r){throw new HttpError(404,'CLOUD_COST_INSTANCE_NOT_FOUND');}return $r;}
    public function ondemand(array $i): bool{return ($this->decrypt($i['spec_encrypted'],$i['fingerprint'],'cloud-spec:'.$i['id'])['type']??null)==='ondemand';}
    /** Retained CREATE proof, never a synthetic billing clock derived from observed_at. */
    public function binding(array $i): ?array{$r=$this->db->execute("SELECT * FROM cloud_effects WHERE instance_id=? AND kind='CREATE' AND state='ACTIVE' AND provenance='MOCK'",[$i['id']])->fetch();return $r&&$r['account_id']===$i['account_id']&&$r['resource_id']===$i['resource_id']&&$r['provider_resource_ref']===$i['provider_resource_ref']?$r:null;}
}
