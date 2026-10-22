<?php
declare(strict_types=1);
namespace Hostider\Identity;
use Hostider\Core\{CanonicalJson,Id};
use Hostider\Core\Http\HttpError;

/** The HTTP application boundary for reviewed, same-actor critical commands. */
final readonly class CriticalActions
{
    public const ACTIONS=['role-save','role-assign','role-remove','staff-save','account-update','member-remove','member-scope','user-save','invite','mfa-disable','recovery','device-create','key-create','key-change','impersonate'];
    public function __construct(private IdentityService $identity) {}
    private function hash(array $data): string { return $this->identity->digest(CanonicalJson::encode($data)); }
    private function snapshot(string $action,array $data,array $actor): array
    {
        $db=$this->identity->db;$state=['actor'=>[['id'=>$actor['id'],'identity_version'=>$actor['identity_version']]]];
        $user=$data['user'] ?? (in_array($action,['user-save','staff-save'],true)?($data['id'] ?? null):null);
        $role=$data['role'] ?? ($action==='role-save'?($data['id'] ?? null):null);
        $customer=$data['customer'] ?? null;
        if($action==='role-remove'){
            $row=$db->execute('SELECT * FROM identity_assignments WHERE id=? FOR UPDATE',[$data['id']])->fetch();
            $state['assignment']=$row?[$row]:[];$user=$row['user_id'] ?? null;$role=$row['role_id'] ?? null;$customer=$row['customer_id'] ?? null;
        }
        if($action==='invite'){$user=$db->execute('SELECT id FROM identity_users WHERE email=? FOR UPDATE',[strtolower(trim($data['email']))])->fetchColumn()?:null;}
        if(in_array($action,['mfa-disable','recovery','device-create','key-create','key-change','impersonate'],true)){$user ??= $actor['id'];}
        if($user!==null){
            $state['user']=$db->execute('SELECT id,display_name,status,identity_version,verified_at FROM identity_users WHERE id=? FOR UPDATE',[$user])->fetchAll();
            $state['staff']=$db->execute('SELECT * FROM identity_staff WHERE user_id=? FOR UPDATE',[$user])->fetchAll();
            $state['memberships']=$db->execute('SELECT * FROM identity_memberships WHERE user_id=? ORDER BY customer_id FOR UPDATE',[$user])->fetchAll();
            $state['user_roles']=$db->execute('SELECT * FROM identity_assignments WHERE user_id=? ORDER BY id FOR UPDATE',[$user])->fetchAll();
            $state['grants']=$db->execute('SELECT g.* FROM identity_grants g JOIN identity_assignments a ON a.id=g.assignment_id WHERE a.user_id=? ORDER BY g.id FOR UPDATE',[$user])->fetchAll();
        }
        if($role!==null || $action==='role-save'){
            $state['role']=$db->execute('SELECT * FROM identity_roles WHERE '.($role!==null?'id':'name').'=? FOR UPDATE',[$role ?? $data['name']])->fetchAll();
            if($role!==null){
                $state['permissions']=$db->execute('SELECT * FROM identity_role_permissions WHERE role_id=? ORDER BY permission FOR UPDATE',[$role])->fetchAll();
                $state['affected_assignments']=$db->execute('SELECT * FROM identity_assignments WHERE role_id=? ORDER BY id FOR UPDATE',[$role])->fetchAll();
            }
        }
        if($customer!==null){
            $state['customer']=$db->execute('SELECT * FROM identity_customers WHERE id=? FOR UPDATE',[$customer])->fetchAll();
            $state['affected_members']=$db->execute('SELECT m.*,u.identity_version FROM identity_memberships m JOIN identity_users u ON u.id=m.user_id WHERE m.customer_id=? ORDER BY m.user_id FOR UPDATE',[$customer])->fetchAll();
        }
        $scope=$data['grant'] ?? null;$scopeCustomer=$scope instanceof \stdClass?($scope->customer ?? null):null;
        $scopeCustomer ??= $customer;
        if($scopeCustomer!==null){
            if($scopeCustomer!==$customer){$state['scope_customer']=$db->execute('SELECT * FROM identity_customers WHERE id=? FOR UPDATE',[$scopeCustomer])->fetchAll();}
            $state['resources']=$db->execute('SELECT * FROM identity_resource_scopes WHERE customer_id=? ORDER BY service_id FOR UPDATE',[$scopeCustomer])->fetchAll();
        }
        if($action==='invite'){$state['invitations']=$db->execute("SELECT id,user_id,role_id,expires_at,consumed_at FROM identity_tokens WHERE customer_id=? AND purpose='invite' ORDER BY id FOR UPDATE",[$customer])->fetchAll();}
        if($action==='mfa-disable'){$state['mfa']=$db->execute('SELECT user_id,enabled FROM identity_mfa WHERE user_id=? FOR UPDATE',[$user])->fetchAll();}
        if($action==='recovery'){$state['recovery']=$db->execute('SELECT id,used_at FROM identity_recovery WHERE user_id=? ORDER BY id FOR UPDATE',[$user])->fetchAll();}
        if(in_array($action,['key-create','key-change'],true)){$state['keys']=$db->execute('SELECT id,scopes,expires_at,revoked_at FROM identity_api_credentials WHERE user_id=? ORDER BY id FOR UPDATE',[$user])->fetchAll();}
        if($action==='device-create'){$state['devices']=$db->execute('SELECT id,expires_at,revoked_at FROM identity_devices WHERE user_id=? ORDER BY id FOR UPDATE',[$user])->fetchAll();}
        return $state;
    }
    public function prepare(string $reference,string $context,string $action,array $data,\Closure $command): array
    {
        if(!in_array($action,self::ACTIONS,true)){throw new HttpError(422,'INVALID_CRITICAL_ACTION');}
        return $this->identity->withStepUp($reference,$context,function(array $u,array $s)use($action,$data,$command):array{
            $db=$this->identity->db;
            // Validate using the real command, including authorization, with every write rolled back.
            // These Identity commands perform DB-only work; no mail or provider is dispatched here.
            $db->execute('SAVEPOINT identity_review');
            try{$command();}finally{$db->execute('ROLLBACK TO SAVEPOINT identity_review');$db->execute('RELEASE SAVEPOINT identity_review');}
            $state=$this->snapshot($action,$data,$u);$id=Id::uuid();
            $db->execute('INSERT INTO identity_confirmations(id,actor_id,session_id,identity_version,action,payload_hash,state_hash,expires_at) VALUES (?,?,?,?,?,?,?,TIMESTAMPADD(SECOND,300,UTC_TIMESTAMP(6)))',[$id,$u['id'],$s['id'],$u['identity_version'],$action,$this->hash($data),$this->hash($state)]);
            $this->identity->confirmationEvent($u['id'],$id,false);
            return ['id'=>$id,'action'=>$action,'current'=>$state,'proposed'=>(object)$data,'expires_in'=>300,'effect'=>$this->effect($action)];
        });
    }
    public function execute(string $reference,string $context,string $action,array $data,mixed $approval,\Closure $command): array
    {
        return $this->identity->withStepUp($reference,$context,function(array $u,array $s)use($action,$data,$approval,$command):array{
            if(!$approval instanceof \stdClass || array_diff(array_keys(get_object_vars($approval)),['id','accepted']) || ($approval->accepted ?? null)!==true || !is_string($approval->id ?? null)) {throw new HttpError(403,'CONFIRMATION_REQUIRED');}
            $db=$this->identity->db;$row=$db->execute('SELECT *,UNIX_TIMESTAMP(expires_at) AS expiry FROM identity_confirmations WHERE id=? FOR UPDATE',[$approval->id])->fetch();
            if(!$row || $row['actor_id']!==$u['id'] || $row['session_id']!==$s['id'] || (int)$row['identity_version']!==(int)$u['identity_version'] || $row['action']!==$action || !hash_equals($row['payload_hash'],$this->hash($data)) || $row['consumed_at']!==null || (int)$row['expiry']<=time()) {throw new HttpError(409,'CONFIRMATION_INVALID');}
            if(!hash_equals($row['state_hash'],$this->hash($this->snapshot($action,$data,$u)))) {throw new HttpError(409,'CONFIRMATION_STALE');}
            $db->execute('UPDATE identity_confirmations SET consumed_at=UTC_TIMESTAMP(6) WHERE id=?',[$row['id']]);
            $result=$command();$this->identity->confirmationEvent($u['id'],$row['id'],true);return $result;
        });
    }
    private function effect(string $action):string
    {
        return match($action){
            'role-save'=>'Rolün işlem izinleri değişir. Bu rolü taşıyan kullanıcıların oturumları ve eski API anahtarları geçersiz olur.',
            'role-assign','role-remove'=>'Hedef kullanıcının rolü ve belirtilen erişim kapsamı değişir; oturumları ve eski API anahtarları geçersiz olur.',
            'staff-save'=>'Hedef kullanıcının personel/yönetim yetkisi değişir; oturumları iptal edilir. Personel için MFA zorunludur.',
            'account-update'=>'Hesap bilgileri güncellenir. MFA politikası değişirse bütün üyelerin oturumları ve eski API anahtarları geçersiz olur.',
            'member-remove'=>'Hedef kullanıcının bu müşteri hesabına üyeliği ve ilgili rol/kapsamları kaldırılır; oturumları iptal edilir.',
            'member-scope'=>'Alt kullanıcının belirtilen müşteri, hizmet ve departmandaki işlem kapsamı değişir; oturumları iptal edilir.',
            'user-save'=>'Kullanıcı bilgileri/durumu değişir ve mevcut kullanıcının oturumları iptal edilir; yeni kullanıcı için etkinleştirme bildirimi hazırlanır.',
            'invite'=>'Belirtilen e-posta için bu müşteri ve role bağlı davet hazırlanır; kabul edildiğinde erişim kazanılır. Canlı gönderim kapalıdır.',
            'mfa-disable'=>'MFA kaldırılır; bütün oturumlar, cihazlar ve eski API anahtarları geçersiz olur.',
            'recovery'=>'Eski kurtarma kodları iptal edilip yeni tek kullanımlık kodlar oluşturulur.',
            'device-create'=>'Bu kullanıcı için 30 gün geçerli güvenilir cihaz oluşturulur; personel MFA veya kritik işlem doğrulaması atlanmaz.',
            'key-create'=>'Belirtilen izin ve süreyle entegrasyon anahtarı oluşturulur; ham anahtar yalnız bir kez gösterilir.',
            'key-change'=>'Bu API anahtarı iptal edilir veya izinleri daraltılır.',
            default=>'Gerçek personel kimliği korunarak 10 dakikalık sınırlı müşteri destek oturumu açılır; yönetici işlemleri engellenir.',
        };
    }
}
