<?php
declare(strict_types=1);
namespace Hostider\Hosting;
use Hostider\Core\{CanonicalJson,Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\Crypto;
final readonly class Pools
{
    public function __construct(private Database $db,private Config $config){}
    /** Internal mock setup; public staff configuration is authorized by the application layer. */
    public function configure(string $product,int $capacity=10,int $weight=1,array $selection=[]): array
    {
        if($this->config->environment!=='test'){throw new HttpError(403,'F3_TEST_ONLY');}
        $work=function()use($product,$capacity,$weight,$selection): array{
            if($capacity<1||$weight<1||$weight>65535){throw new HttpError(422,'INVALID_POOL_LIMITS');}
            $pool=Id::uuid();$server=Id::uuid();$mapping=Id::uuid();$key=base64_decode($this->config->key,true);
            $this->db->execute("INSERT INTO hosting_pools(id,name) VALUES (?,'Synthetic test pool')",[$pool]);
            $this->db->execute("INSERT INTO hosting_servers(id,pool_id,credential_encrypted,capacity,weight,healthy,observed_at,fresh_seconds,capabilities) VALUES (?,?,?,?,?,TRUE,UTC_TIMESTAMP(6),300,?)",[$server,$pool,(new Crypto($key))->encrypt('synthetic-not-a-live-token','hosting-server:'.$server),$capacity,$weight,json_encode(['createAccount','inspectAccount','suspendAccount','unsuspendAccount','changePackage','terminateAccount'],JSON_THROW_ON_ERROR)]);
            $this->db->execute("INSERT INTO hosting_mappings(id,product_id,pool_id,package_name,reseller_scope,selection_hash) VALUES (?,?,?,'mock_basic','mock_reseller',?)",[$mapping,$product,$pool,$this->selectionHash($selection)]);return compact('pool','server','mapping');
        };return $this->db->pdo->inTransaction()?$work():$this->db->transaction($work);
    }
    /** Called while the service/operation lock is held. Reservation is frozen before dispatch. */
    public function reserve(array $service,string $operation,array $selection): array
    {
        $this->db->requireTransaction();if($this->config->environment!=='test'){throw new HttpError(403,'F3_TEST_ONLY');}
        $old=$this->db->execute('SELECT * FROM hosting_reservations WHERE operation_id=?',[$operation])->fetch();if($old){return $old;}
        $servers=$this->db->execute("SELECT s.*,m.id AS mapping_id,m.package_name,m.reseller_scope FROM hosting_servers s JOIN hosting_pools p ON p.id=s.pool_id JOIN hosting_mappings m ON m.pool_id=p.id WHERE m.product_id=? AND m.selection_hash=? AND m.enabled=TRUE AND p.enabled=TRUE AND s.enabled=TRUE AND s.healthy=TRUE AND s.environment='mock' AND s.observed_at<=UTC_TIMESTAMP(6) AND TIMESTAMPDIFF(SECOND,s.observed_at,UTC_TIMESTAMP(6))<s.fresh_seconds ORDER BY s.weight DESC,s.id",[$service['product_id'],$this->selectionHash($selection)])->fetchAll();
        foreach($servers as $candidate){
            $server=$this->db->execute('SELECT * FROM hosting_servers WHERE id=? FOR UPDATE',[$candidate['id']])->fetch();
            $fresh=$this->db->execute('SELECT healthy AND enabled AND observed_at<=UTC_TIMESTAMP(6) AND TIMESTAMPDIFF(SECOND,observed_at,UTC_TIMESTAMP(6))<fresh_seconds FROM hosting_servers WHERE id=?',[$server['id']])->fetchColumn();
            $capabilities=json_decode($server['capabilities'],true,32,JSON_THROW_ON_ERROR);$used=(int)$this->db->execute("SELECT COUNT(*) FROM hosting_reservations WHERE server_id=? AND status='HELD'",[$server['id']])->fetchColumn();
            if(!$fresh||$used>=(int)$server['capacity']||!in_array('createAccount',$capabilities,true)||!in_array('inspectAccount',$capabilities,true)){continue;}
            $this->db->execute('INSERT INTO hosting_reservations(operation_id,server_id,service_id,mapping_id,package_name,reseller_scope) VALUES (?,?,?,?,?,?)',[$operation,$server['id'],$service['id'],$candidate['mapping_id'],$candidate['package_name'],$candidate['reseller_scope']]);
            return $this->db->execute('SELECT * FROM hosting_reservations WHERE operation_id=?',[$operation])->fetch();
        }throw new HttpError(409,'NO_VERIFIED_MOCK_CAPACITY');
    }
    public function releaseDefinitiveFailure(string $operation): void
    {
        $this->db->requireTransaction();$this->db->execute("UPDATE hosting_reservations SET status='RELEASED' WHERE operation_id=?",[$operation]);
    }
    public function releaseTerminatedService(string $service): void
    {
        $this->db->requireTransaction();$this->db->execute("UPDATE hosting_reservations SET status='RELEASED' WHERE service_id=? AND status='HELD'",[$service]);
    }
    private function selectionHash(array $selection): string
    {
        return hash('sha256',CanonicalJson::encode(array_intersect_key($selection,array_flip(['variant_id','options','addons']))));
    }
    public function mapSelection(string $product,string $pool,string $package,string $reseller,array $selection): string
    {
        $this->db->requireTransaction();if($this->config->environment!=='test'||!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$package)||!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$reseller)){throw new HttpError(422,'INVALID_MOCK_MAPPING');}$id=Id::uuid();$this->db->execute('INSERT INTO hosting_mappings(id,product_id,pool_id,package_name,reseller_scope,selection_hash) VALUES (?,?,?,?,?,?)',[$id,$product,$pool,$package,$reseller,$this->selectionHash($selection)]);return $id;
    }
    public function existingMapping(string $server,string $product,array $selection): array
    {
        $this->db->requireTransaction();$row=$this->db->execute("SELECT m.* ,s.capabilities FROM hosting_servers s JOIN hosting_pools p ON p.id=s.pool_id JOIN hosting_mappings m ON m.pool_id=p.id WHERE s.id=? AND m.product_id=? AND m.selection_hash=? AND m.enabled=TRUE AND p.enabled=TRUE AND s.enabled=TRUE AND s.environment='mock' AND s.healthy=TRUE AND s.observed_at<=UTC_TIMESTAMP(6) AND TIMESTAMPDIFF(SECOND,s.observed_at,UTC_TIMESTAMP(6))<s.fresh_seconds FOR UPDATE",[$server,$product,$this->selectionHash($selection)])->fetch();if(!$row||!in_array('changePackage',json_decode($row['capabilities'],true,32,JSON_THROW_ON_ERROR),true)){throw new HttpError(409,'VERIFIED_PACKAGE_MAPPING_CAPABILITY_REQUIRED');}return $row;
    }
}
