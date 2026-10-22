<?php
declare(strict_types=1);
namespace Hostider\Identity;
use Hostider\Core\{Id,Config,Validation};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Core\Security\{Audit,AuditEvent,Authorization,Crypto,Principal,RateLimiter};

final readonly class IdentityService
{
    public Repository $repository;
    private Crypto $crypto;
    private string $key;
    public function __construct(public Database $db,Config $config)
    {
        $this->repository=new Repository($db); $this->key=base64_decode($config->key,true);
        $this->crypto=new Crypto($this->key);
    }
    public function digest(#[\SensitiveParameter] string $secret): string { return hash_hmac('sha256',$secret,$this->key); }
    private function email(string $email): string
    {
        $email=strtolower(trim($email));
        if (strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) { throw new HttpError(422,'INVALID_EMAIL'); }
        return $email;
    }
    private function event(?string $user,AuditEvent $event,string $subject,?string $customer=null): void
    {
        $this->db->requireTransaction(); $trace=Id::uuid();
        $this->db->execute('INSERT INTO identity_security_events(id,user_id,code,trace_id) VALUES (?,?,?,?)',[Id::uuid(),$user,$event->value,$trace]);
        (new Audit($this->db))->record(new Principal($user ?? 'anonymous',[],customerId:$customer),$event,$subject,$trace);
    }
    private function createUser(string $email,string $name,string $hash,bool $verified=false): string
    {
        $id=Id::uuid();
        $this->db->execute('INSERT INTO identity_users(id,email,display_name,verified_at) VALUES (?,?,?,'.($verified?'UTC_TIMESTAMP(6)':'NULL').')',[$id,$email,Validation::text($name)]);
        $this->db->execute('INSERT INTO identity_credentials(user_id,password_hash) VALUES (?,?)',[$id,$hash]);
        $this->event($id,AuditEvent::UserCreated,$id); return $id;
    }
    public function assign(string $user,string $role,?string $customer=null): string
    {
        $this->db->requireTransaction(); $id=Id::uuid();
        $this->db->execute('INSERT INTO identity_assignments VALUES (?,?,?,?)',[$id,$user,$role,$customer]); return $id;
    }
    public function bootstrap(string $email,string $name,#[\SensitiveParameter] string $password): string
    {
        $hash=Passwords::hash($password); $email=$this->email($email);
        return $this->db->transaction(function () use($email,$name,$hash): string {
            $settings=$this->db->execute('SELECT * FROM identity_settings WHERE id=1 FOR UPDATE')->fetch();
            if ((bool)$settings['bootstrapped'] || $this->db->execute('SELECT 1 FROM identity_staff LIMIT 1')->fetchColumn()) { throw new HttpError(409,'BOOTSTRAP_CLOSED'); }
            $id=$this->createUser($email,$name,$hash,true);
            $this->db->execute('INSERT INTO identity_staff VALUES (?,TRUE)',[$id]);
            $role=$this->db->execute("SELECT id FROM identity_roles WHERE name='Super Admin'")->fetchColumn();
            $this->assign($id,$role); $this->db->execute('UPDATE identity_settings SET bootstrapped=TRUE WHERE id=1'); return $id;
        });
    }
    private function notification(string $user,string $template,array $body): void
    {
        $id=Id::uuid();
        $this->db->execute('INSERT INTO identity_notifications(id,user_id,template,body_encrypted) VALUES (?,?,?,?)',[$id,$user,$template,$this->crypto->encrypt(json_encode($body,JSON_THROW_ON_ERROR),'notification:'.$id)]);
        (new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'identity.mail.held',$user,['notification_id'=>$id],Id::uuid());
    }
    private function token(string $user,string $purpose,?string $customer=null,?string $role=null): void
    {
        $secret=bin2hex(random_bytes(32)); $id=Id::uuid();
        $this->db->execute('UPDATE identity_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE user_id=? AND purpose=? AND customer_id<=>? AND consumed_at IS NULL',[$user,$purpose,$customer]);
        $this->db->execute('INSERT INTO identity_tokens(id,user_id,purpose,secret_hash,customer_id,role_id,expires_at) VALUES (?,?,?,?,?,?,TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)))',[$id,$user,$purpose,$this->digest($secret),$customer,$role,$purpose==='reset'?1800:86400]);
        $this->notification($user,$purpose,['token'=>$secret]);
    }
    public function register(string $email,string $name,#[\SensitiveParameter] string $password,string $network): void
    {
        $email=$this->email($email); $hash=Passwords::hash($password); $this->throttle('register:'.$network,5);
        try {
            $this->db->transaction(function () use($email,$name,$hash): void {
                if ($this->db->execute('SELECT 1 FROM identity_users WHERE email=?',[$email])->fetchColumn()) { return; }
                $user=$this->createUser($email,$name,$hash); $customer=Id::uuid();
                $this->db->execute('INSERT INTO identity_customers(id,name,currency) VALUES (?,?,?)',[$customer,Validation::text($name),'TRY']);
                $this->db->execute('INSERT INTO identity_memberships VALUES (?,?,TRUE)',[$user,$customer]);
                $role=$this->db->execute("SELECT id FROM identity_roles WHERE name='Müşteri Sahibi'")->fetchColumn();
                $this->assign($user,$role,$customer); $this->token($user,'verify');
            });
        } catch (\PDOException $e) { if (($e->errorInfo[1] ?? 0)!==1062) { throw $e; } }
    }
    private function throttle(string $bucket,int $limit=10): void
    {
        if (!(new RateLimiter($this->db,$this->key))->allow('identity:'.$bucket,$limit,300)) { throw new HttpError(429,'RATE_LIMITED'); }
    }
    public function requestToken(string $email,string $purpose,string $network): void
    {
        if (!in_array($purpose,['reset','verify'],true)) { throw new HttpError(422,'INVALID_PURPOSE'); }
        $this->throttle('token:'.$network,5); $email=$this->email($email);
        $this->db->transaction(function () use($email,$purpose): void {
            $u=$this->db->execute('SELECT id FROM identity_users WHERE email=? AND status=\'ACTIVE\' FOR UPDATE',[$email])->fetch();
            if ($u) { $this->token($u['id'],$purpose); }
        });
    }
    private function consume(string $secret,string $purpose): array
    {
        $row=$this->db->execute('SELECT * FROM identity_tokens WHERE secret_hash=? AND purpose=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE',[$this->digest($secret),$purpose])->fetch();
        if (!$row) { throw new HttpError(422,'INVALID_OR_EXPIRED_TOKEN'); }
        $this->db->execute('UPDATE identity_tokens SET consumed_at=UTC_TIMESTAMP(6) WHERE id=?',[$row['id']]); return $row;
    }
    public function completeToken(string $secret,string $purpose,?string $password=null): void
    {
        $hash=$purpose==='reset'?Passwords::hash($password ?? ''):null;
        $this->db->transaction(function () use($secret,$purpose,$hash): void {
            // Find the owner, then use the same user-first lock order as session mutations.
            $owner=$this->db->execute('SELECT user_id FROM identity_tokens WHERE secret_hash=?',[$this->digest($secret)])->fetchColumn();
            if (!$owner) { throw new HttpError(422,'INVALID_OR_EXPIRED_TOKEN'); }
            $this->repository->user($owner,true); $row=$this->consume($secret,$purpose);
            if ($purpose==='verify') { $this->db->execute('UPDATE identity_users SET verified_at=UTC_TIMESTAMP(6) WHERE id=?',[$owner]); $this->event($owner,AuditEvent::EmailVerified,$owner); }
            elseif ($purpose==='reset') { $this->db->execute('UPDATE identity_credentials SET password_hash=?,changed_at=UTC_TIMESTAMP(6) WHERE user_id=?',[$hash,$owner]); $this->invalidate($owner); $this->event($owner,AuditEvent::PasswordChanged,$owner); }
            elseif ($purpose==='invite') {
                if (!$row['customer_id'] || !$row['role_id']) { throw new HttpError(422,'INVALID_INVITE'); }
                $this->db->execute('INSERT INTO identity_memberships VALUES (?,?,FALSE)',[$owner,$row['customer_id']]); $this->assign($owner,$row['role_id'],$row['customer_id']);
                $this->invalidate($owner); $this->event($owner,AuditEvent::InvitationAccepted,$row['id'],$row['customer_id']);
            } else { throw new HttpError(422,'INVALID_PURPOSE'); }
        });
    }
    public function login(string $email,#[\SensitiveParameter] string $password,string $context,string $nativeReference,string $network,?string $device=null): array
    {
        if (!in_array($context,['admin','client'],true)) { throw new HttpError(422,'INVALID_CONTEXT'); }
        $email=$this->email($email); $this->throttle('login-network:'.$network,30); $this->throttle('login-account:'.$email,10);
        $row=$this->db->execute('SELECT u.id,c.password_hash FROM identity_users u JOIN identity_credentials c ON c.user_id=u.id WHERE u.email=?',[$email])->fetch();
        $hash=$row?$row['password_hash']:$this->db->execute('SELECT dummy_hash FROM identity_settings WHERE id=1')->fetchColumn();
        $verified=Passwords::verify($password,$hash);
        return $this->db->transaction(function () use($row,$hash,$verified,$password,$context,$nativeReference,$network,$device): array {
            $user=$row?$this->repository->user($row['id'],true):null;
            if (!$verified || !$user || $user['status']!=='ACTIVE' || !$user['verified_at'] || !hash_equals($hash,$user['password_hash']) || ($context==='admin' && $user['administrative']===null)) {
                $this->event($user['id'] ?? null,AuditEvent::LoginFailed,Id::uuid()); return ['denied'=>true];
            }
            if (Passwords::needsRehash($hash)) { $this->db->execute('UPDATE identity_credentials SET password_hash=? WHERE user_id=?',[Passwords::hash($password),$user['id']]); }
            $customer=$context==='client'?$this->db->execute('SELECT customer_id FROM identity_memberships WHERE user_id=? ORDER BY customer_id LIMIT 1',[$user['id']])->fetchColumn():null;
            if ($context==='client' && !$customer) { return ['denied'=>true]; }
            $factor=$this->db->execute('SELECT enabled FROM identity_mfa WHERE user_id=?',[$user['id']])->fetchColumn();
            $required=$user['administrative']!==null || (bool)$this->db->execute('SELECT mfa_required FROM identity_customers WHERE id=?',[$customer])->fetchColumn();
            $trusted=$device && $user['administrative']===null && $this->db->execute('SELECT id FROM identity_devices WHERE user_id=? AND secret_hash=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP(6)',[$user['id'],$this->digest($device)])->fetchColumn();
            $mfa=(!$factor && !$required) || ($trusted && $factor);
            $id=Id::uuid(); $deviceHash=$this->digest($network);
            if ($this->db->execute('SELECT 1 FROM identity_sessions WHERE user_id=? AND device_hash<>? LIMIT 1',[$user['id'],$deviceHash])->fetchColumn()) { $this->event($user['id'],AuditEvent::SuspiciousLogin,$id); $this->notification($user['id'],'suspicious',['session_id'=>$id]); }
            $this->db->execute('INSERT INTO identity_sessions(id,user_id,native_reference,context,identity_version,customer_id,mfa_verified,verified_at,expires_at,device_hash) VALUES (?,?,?,?,?,?,?,NULL,TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)),?)',[$id,$user['id'],$nativeReference,$context,$user['identity_version'],$customer?:null,(int)$mfa,$mfa?28800:600,$deviceHash]);
            $this->event($user['id'],AuditEvent::LoginSucceeded,$id,$customer?:null);
            return ['id'=>$id,'stage'=>$mfa?'active':($factor?'mfa':'enroll'),'customer_id'=>$customer?:null];
        });
    }
    public function locked(string $reference,string $context,\Closure $work,bool $pending=false): mixed
    {
        return $this->db->transaction(function () use($reference,$context,$work,$pending): mixed {
            $candidate=$this->repository->session($reference,$context);
            $user=$this->repository->user($candidate['user_id'],true);
            $session=$this->repository->session($reference,$context,true); $this->repository->validate($user,$session);
            if (!$pending && !(bool)$session['mfa_verified']) { throw new HttpError(403,'MFA_REQUIRED'); }
            $this->db->execute('UPDATE identity_sessions SET last_seen_at=UTC_TIMESTAMP(6) WHERE id=?',[$session['id']]);
            return $work($user,$session);
        });
    }
    public function principal(array $user,array $session): Principal
    {
        $imp=$this->db->execute('SELECT * FROM identity_impersonations WHERE session_id=? AND ended_at IS NULL AND expires_at>UTC_TIMESTAMP(6) ORDER BY created_at DESC LIMIT 1',[$session['id']])->fetch();
        if (!$imp) { return $this->repository->principal($user,$session); }
        $target=$this->repository->user($imp['target_user_id']);
        $targetSession=$session; $targetSession['context']='client'; $targetSession['customer_id']=$imp['customer_id'];
        $p=$this->repository->principal($target,$targetSession);
        $allowed=array_values(array_intersect($p->permissions,['account.view','members.view','services.view','support.view']));
        return new Principal($user['id'],[], $imp['customer_id'],$p->departments,true,true,(int)$session['expiry'],(int)$session['verified'],true,$p->grants,$allowed,false);
    }
    public function authorize(array $user,array $session,string $permission,?string $customer=null,?string $service=null,?string $department=null,bool $privileged=false): Principal
    {
        $this->db->requireTransaction(); $principal=$this->principal($user,$session);
        if ($service!==null) { if ($customer===null) { throw new HttpError(403,'CUSTOMER_REQUIRED'); } $this->repository->owns($service,$customer,$department); }
        (new Authorization())->require($principal,$permission,$customer,$department,$privileged,null,$service); return $principal;
    }
    public function invalidate(string $user): void
    {
        $this->db->requireTransaction();
        $this->db->execute('UPDATE identity_users SET identity_version=identity_version+1 WHERE id=?',[$user]);
        $this->db->execute('UPDATE identity_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL',[$user]);
        $this->db->execute('UPDATE identity_devices SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL',[$user]);
    }
    public function stepUp(string $reference,string $context,string $password,string $code): void
    {
        $this->throttle('stepup:'.$reference,5);
        $ok=$this->locked($reference,$context,function (array $u,array $s) use($password,$code): bool {
            $hasFactor=(bool)$this->db->execute('SELECT enabled FROM identity_mfa WHERE user_id=?',[$u['id']])->fetchColumn();
            if (!Passwords::verify($password,$u['password_hash']) || ($hasFactor && !$this->factor($u['id'],$code))) { $this->event($u['id'],AuditEvent::MfaFailed,$s['id']); return false; }
            $this->db->execute('UPDATE identity_sessions SET verified_at=UTC_TIMESTAMP(6) WHERE id=?',[$s['id']]); return true;
        }); if (!$ok) { throw new HttpError(403,'REAUTHENTICATION_FAILED'); }
    }
    private function factor(string $user,string $code): bool
    {
        $factor=$this->db->execute('SELECT * FROM identity_mfa WHERE user_id=? FOR UPDATE',[$user])->fetch();
        if (!$factor || !$factor['enabled']) { return false; }
        $counter=Totp::counter($this->crypto->decrypt($factor['secret_encrypted'],'totp:'.$user),$code,(int)$factor['last_counter'],time());
        if ($counter!==null) { $this->db->execute('UPDATE identity_mfa SET last_counter=? WHERE user_id=?',[$counter,$user]); return true; }
        $recovery=$this->db->execute('SELECT id FROM identity_recovery WHERE user_id=? AND code_hash=? AND used_at IS NULL FOR UPDATE',[$user,$this->digest($code)])->fetchColumn();
        if ($recovery) { $this->db->execute('UPDATE identity_recovery SET used_at=UTC_TIMESTAMP(6) WHERE id=?',[$recovery]); $this->event($user,AuditEvent::MfaRecoveryUsed,$recovery); return true; } return false;
    }
    public function enroll(string $reference,string $context,string $password): array
    {
        $this->throttle('enroll:'.$reference,5);
        return $this->locked($reference,$context,function(array $u,array $s) use($password): array {
            if (!Passwords::verify($password,$u['password_hash'])) { throw new HttpError(403,'REAUTHENTICATION_FAILED'); }
            if ($this->db->execute('SELECT 1 FROM identity_mfa WHERE user_id=? AND enabled=TRUE',[$u['id']])->fetchColumn()) { throw new HttpError(409,'MFA_ALREADY_ENABLED'); }
            $secret=Totp::secret();
            $this->db->execute('INSERT INTO identity_mfa(user_id,secret_encrypted) VALUES (?,?) ON DUPLICATE KEY UPDATE secret_encrypted=VALUES(secret_encrypted),last_counter=-1',[$u['id'],$this->crypto->encrypt($secret,'totp:'.$u['id'])]);
            return ['secret'=>$secret,'uri'=>'otpauth://totp/Hostider:'.rawurlencode($u['email']).'?secret='.$secret.'&issuer=Hostider&digits=6&period=30'];
        },true);
    }
    public function confirmEnrollment(string $reference,string $context,string $code): array
    {
        $this->throttle('confirm:'.$reference,5);
        return $this->locked($reference,$context,function(array $u,array $s) use($code): array {
            $factor=$this->db->execute('SELECT * FROM identity_mfa WHERE user_id=? AND enabled=FALSE FOR UPDATE',[$u['id']])->fetch();
            $counter=$factor?Totp::counter($this->crypto->decrypt($factor['secret_encrypted'],'totp:'.$u['id']),$code,-1,time()):null;
            if ($counter===null) { throw new HttpError(422,'INVALID_MFA_CODE'); }
            $this->db->execute('UPDATE identity_mfa SET enabled=TRUE,last_counter=? WHERE user_id=?',[$counter,$u['id']]);
            $codes=$this->recovery($u['id']); $this->activate($s['id']); $this->event($u['id'],AuditEvent::MfaEnrolled,$u['id']); return ['recovery_codes'=>$codes];
        },true);
    }
    private function recovery(string $user): array
    {
        $this->db->execute('DELETE FROM identity_recovery WHERE user_id=?',[$user]); $codes=[];
        for($i=0;$i<8;++$i) { $code=bin2hex(random_bytes(12)); $codes[]=$code; $this->db->execute('INSERT INTO identity_recovery(id,user_id,code_hash) VALUES (?,?,?)',[Id::uuid(),$user,$this->digest($code)]); } return $codes;
    }
    private function activate(string $session): void
    {
        $this->db->execute('UPDATE identity_sessions SET mfa_verified=TRUE,verified_at=UTC_TIMESTAMP(6),expires_at=TIMESTAMPADD(HOUR,8,UTC_TIMESTAMP(6)) WHERE id=?',[$session]);
    }
    public function verifyMfa(string $reference,string $context,string $code): void
    {
        $this->throttle('mfa:'.$reference,5);
        $ok=$this->locked($reference,$context,function(array $u,array $s) use($code): bool {
            if (!$this->factor($u['id'],$code)) { $this->event($u['id'],AuditEvent::MfaFailed,$s['id']); return false; }
            $this->activate($s['id']); $this->event($u['id'],AuditEvent::MfaSucceeded,$s['id']); return true;
        },true); if (!$ok) { throw new HttpError(403,'INVALID_MFA_CODE'); }
    }
    public function changePassword(string $reference,string $context,string $current,string $next): void
    {
        $hash=Passwords::hash($next);
        $this->locked($reference,$context,function(array $u,array $s) use($current,$hash): void {
            $this->fresh($s); if (!Passwords::verify($current,$u['password_hash'])) { throw new HttpError(403,'REAUTHENTICATION_FAILED'); }
            $this->db->execute('UPDATE identity_credentials SET password_hash=?,changed_at=UTC_TIMESTAMP(6) WHERE user_id=?',[$hash,$u['id']]); $this->invalidate($u['id']); $this->event($u['id'],AuditEvent::PasswordChanged,$u['id']);
        });
    }
    private function fresh(array $s): void
    {
        if ((int)$s['verified']<=time()-300 || (int)$s['verified']>time()) { throw new HttpError(403,'STEP_UP_REQUIRED'); }
        if ($this->db->execute('SELECT 1 FROM identity_impersonations WHERE session_id=? AND ended_at IS NULL',[$s['id']])->fetchColumn()) { throw new HttpError(403,'IMPERSONATION_RESTRICTED'); }
    }
    public function disableMfa(string $reference,string $context): void
    {
        $this->locked($reference,$context,function(array $u,array $s): void {
            $this->fresh($s);
            if ($u['administrative']!==null || $this->db->execute('SELECT 1 FROM identity_memberships m JOIN identity_customers c ON c.id=m.customer_id WHERE m.user_id=? AND c.mfa_required=TRUE',[$u['id']])->fetchColumn()) { throw new HttpError(403,'MFA_REQUIRED_BY_POLICY'); }
            $this->db->execute('DELETE FROM identity_recovery WHERE user_id=?',[$u['id']]); $this->db->execute('DELETE FROM identity_mfa WHERE user_id=?',[$u['id']]); $this->invalidate($u['id']); $this->event($u['id'],AuditEvent::MfaRemoved,$u['id']);
        });
    }
    public function regenerateRecovery(string $reference,string $context): array
    {
        return $this->locked($reference,$context,function(array $u,array $s): array { $this->fresh($s); $codes=$this->recovery($u['id']); $this->event($u['id'],AuditEvent::MfaRecoveryReplaced,$u['id']); return ['recovery_codes'=>$codes]; });
    }
    public function trustDevice(string $reference,string $context): array
    {
        return $this->locked($reference,$context,function(array $u,array $s): array {
            $this->fresh($s); $secret=bin2hex(random_bytes(32)); $id=Id::uuid();
            $this->db->execute('INSERT INTO identity_devices(id,user_id,secret_hash,expires_at) VALUES (?,?,?,TIMESTAMPADD(DAY,30,UTC_TIMESTAMP(6)))',[$id,$u['id'],$this->digest($secret)]); $this->event($u['id'],AuditEvent::DeviceCreated,$id); return ['device_token'=>$secret,'id'=>$id];
        });
    }
    public function revokeSession(string $reference,string $context,?string $target=null,bool $all=false): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($target,$all): void {
            if ($all || ($target!==null && $target!==$s['id'])) { $this->fresh($s); }
            $this->db->execute('UPDATE identity_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND '.($all?'revoked_at IS NULL':'id=?'),$all?[$u['id']]:[$u['id'],$target ?? $s['id']]);
            $this->event($u['id'],AuditEvent::SessionRevoked,$target ?? $s['id']);
        },true);
    }
}
