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
    private function transaction(\Closure $work): mixed
    {
        // Explicit module-level join for the HTTP idempotency/business transaction.
        return $this->db->pdo->inTransaction() ? $work() : $this->db->transaction($work);
    }
    public function recordDenial(?string $reference,string $context,AuditEvent $event): void
    {
        $user=null;
        if ($reference!==null) { try { $user=$this->repository->session($reference,$context)['user_id']; } catch(HttpError) {} }
        $this->transaction(fn()=>$this->event($user,$event,Id::uuid()));
    }
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
        return $this->transaction(function () use($email,$name,$hash): string {
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
            $this->transaction(function () use($email,$name,$hash): void {
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
        // HTTP callers throttle persistently before entering the outer idempotency TX.
        if ($this->db->pdo->inTransaction()) { return; }
        if (!(new RateLimiter($this->db,$this->key))->allow('identity:'.$bucket,$limit,300)) { throw new HttpError(429,'RATE_LIMITED'); }
    }
    public function requestToken(string $email,string $purpose,string $network): void
    {
        if (!in_array($purpose,['reset','verify'],true)) { throw new HttpError(422,'INVALID_PURPOSE'); }
        $this->throttle('token:'.$network,5); $email=$this->email($email);
        $this->transaction(function () use($email,$purpose): void {
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
        $this->transaction(function () use($secret,$purpose,$hash,$password): void {
            // Find the owner, then use the same user-first lock order as session mutations.
            $owner=$this->db->execute('SELECT user_id FROM identity_tokens WHERE secret_hash=?',[$this->digest($secret)])->fetchColumn();
            if (!$owner) { throw new HttpError(422,'INVALID_OR_EXPIRED_TOKEN'); }
            $user=$this->repository->user($owner,true); $row=$this->consume($secret,$purpose);
            if ($purpose==='verify') { $this->db->execute('UPDATE identity_users SET verified_at=UTC_TIMESTAMP(6) WHERE id=?',[$owner]); $this->event($owner,AuditEvent::EmailVerified,$owner); }
            elseif ($purpose==='reset') { $this->db->execute('UPDATE identity_credentials SET password_hash=?,changed_at=UTC_TIMESTAMP(6) WHERE user_id=?',[$hash,$owner]); $this->db->execute('UPDATE identity_users SET verified_at=COALESCE(verified_at,UTC_TIMESTAMP(6)) WHERE id=?',[$owner]); $this->invalidate($owner); $this->event($owner,AuditEvent::PasswordChanged,$owner); }
            elseif ($purpose==='invite') {
                if (!$row['customer_id'] || !$row['role_id']) { throw new HttpError(422,'INVALID_INVITE'); }
                if (!$user['verified_at']) {
                    $newHash=Passwords::hash($password ?? '');
                    $this->db->execute('UPDATE identity_credentials SET password_hash=? WHERE user_id=?',[$newHash,$owner]);
                    $this->db->execute('UPDATE identity_users SET verified_at=UTC_TIMESTAMP(6) WHERE id=?',[$owner]);
                }
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
        return $this->transaction(function () use($row,$hash,$verified,$password,$context,$nativeReference,$network,$device): array {
            if ($this->db->execute('SELECT 1 FROM identity_sessions WHERE native_reference=?',[$nativeReference])->fetchColumn()) { throw new HttpError(409,'LOGOUT_REQUIRED'); }
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
        return $this->transaction(function () use($reference,$context,$work,$pending): mixed {
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
        $imp=$this->db->execute('SELECT *,UNIX_TIMESTAMP(expires_at) AS expiry FROM identity_impersonations WHERE session_id=? AND ended_at IS NULL ORDER BY created_at DESC LIMIT 1',[$session['id']])->fetch();
        if (!$imp) { return $this->repository->principal($user,$session); }
        if ((int)$imp['expiry']<=time()) { throw new HttpError(403,'IMPERSONATION_EXPIRED'); }
        $target=$this->repository->user($imp['target_user_id']);
        if ($target['status']!=='ACTIVE') { throw new HttpError(403,'IMPERSONATION_TARGET_DISABLED'); }
        $targetSession=$session; $targetSession['context']='client'; $targetSession['customer_id']=$imp['customer_id'];
        $p=$this->repository->principal($target,$targetSession);
        $allowed=array_values(array_intersect($p->permissions,['account.view','members.view','services.view','support.view']));
        return new Principal($user['id'],[], $imp['customer_id'],$p->departments,true,true,(int)$session['expiry'],(int)$session['verified'],true,$p->grants,$allowed,false);
    }
    public function authorize(array $user,array $session,string $permission,?string $customer=null,?string $service=null,?string $department=null,bool $privileged=false): Principal
    {
        $this->db->requireTransaction(); $principal=$this->principal($user,$session);
        if ($service!==null) { if ($customer===null) { throw new HttpError(403,'CUSTOMER_REQUIRED'); } $owned=$this->repository->owns($service,$customer,$department); $department=$owned['department_id']; }
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
            $this->db->execute('UPDATE identity_sessions SET verified_at=UTC_TIMESTAMP(6) WHERE id=?',[$s['id']]);
            $this->event($u['id'],AuditEvent::SessionReauthenticated,$s['id']); return true;
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
            // Fence pre-enrollment sessions and API keys; retain only the enrolling session.
            $this->invalidate($u['id']);
            $this->db->execute('UPDATE identity_sessions SET revoked_at=NULL,identity_version=? WHERE id=?',[(int)$u['identity_version']+1,$s['id']]);
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
        $this->db->execute('UPDATE identity_sessions SET mfa_verified=TRUE,verified_at=NULL,expires_at=TIMESTAMPADD(HOUR,8,UTC_TIMESTAMP(6)) WHERE id=?',[$session]);
    }
    public function verifyMfa(string $reference,string $context,string $code): void
    {
        $this->throttle('mfa:'.$reference,5);
        $ok=$this->locked($reference,$context,function(array $u,array $s) use($code): bool {
            if ((bool)$s['mfa_verified']) { throw new HttpError(409,'MFA_CHALLENGE_NOT_PENDING'); }
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
    public function withStepUp(string $reference,string $context,\Closure $work): mixed
    {
        return $this->locked($reference,$context,function(array $u,array $s)use($work):mixed{$this->fresh($s);return $work($u,$s);});
    }
    public function confirmationEvent(string $user,string $id,bool $executed):void
    {
        $this->event($user,$executed?AuditEvent::CriticalConfirmed:AuditEvent::CriticalPrepared,$id);
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

    public function view(string $reference,string $context,string $view,?string $customer=null): array
    {
        return $this->locked($reference,$context,function(array $u,array $s) use($view,$customer): array {
            $p=$this->principal($u,$s);
            if ($view==='me') {
                return ['user'=>['id'=>$u['id'],'name'=>$u['display_name'],'email'=>$u['email']], 'context'=>$s['context'],'effective_customer'=>$p->customerId,'impersonating'=>$p->impersonating,'permissions'=>$p->impersonating?$p->effectivePermissions:$p->permissions,
                    'accounts'=>$this->db->execute('SELECT c.id,c.name,c.mfa_required FROM identity_memberships m JOIN identity_customers c ON c.id=m.customer_id WHERE m.user_id=?',[$u['id']])->fetchAll()];
            }
            if ($p->impersonating && !in_array($view,['account','members'],true)) { throw new HttpError(403,'IMPERSONATION_RESTRICTED'); }
            if ($view==='sessions') { return $this->db->execute('SELECT id,context,mfa_verified,expires_at,revoked_at,last_seen_at,created_at FROM identity_sessions WHERE user_id=? ORDER BY created_at DESC LIMIT 100',[$u['id']])->fetchAll(); }
            if ($view==='devices') { return $this->db->execute('SELECT id,expires_at,revoked_at,created_at FROM identity_devices WHERE user_id=? ORDER BY created_at DESC LIMIT 100',[$u['id']])->fetchAll(); }
            if ($view==='keys') { return $this->db->execute('SELECT id,customer_id,scopes,expires_at,revoked_at,last_used_at FROM identity_api_credentials WHERE user_id=? ORDER BY created_at DESC LIMIT 100',[$u['id']])->fetchAll(); }
            if ($view==='member-roles') {
                $this->authorize($u,$s,'members.manage',$customer ?? $p->customerId); $roles=[];
                foreach($this->db->execute('SELECT id,name FROM identity_roles')->fetchAll() as $role) {
                    $permissions=array_column($this->db->execute('SELECT permission FROM identity_role_permissions WHERE role_id=?',[$role['id']])->fetchAll(),'permission');
                    if ($permissions!==[] && !array_diff($permissions,$p->permissions) && !array_filter($permissions,fn(string $v): bool=>str_starts_with($v,'identity.'))) { $roles[]=$role; }
                } return $roles;
            }
            if ($view==='account' || $view==='members' || $view==='invitations') {
                $customer ??= $p->customerId; if (!$customer) { throw new HttpError(422,'CUSTOMER_REQUIRED'); }
                $this->authorize($u,$s,$view==='account'?'account.view':'members.view',$customer);
                if ($view==='account') { return $this->db->execute('SELECT id,name,currency,mfa_required,version FROM identity_customers WHERE id=?',[$customer])->fetch() ?: []; }
                if ($view==='members') { return $this->db->execute('SELECT u.id,u.display_name,u.email,m.is_owner FROM identity_memberships m JOIN identity_users u ON u.id=m.user_id WHERE m.customer_id=?',[$customer])->fetchAll(); }
                return $this->db->execute("SELECT id,user_id,role_id,expires_at,consumed_at FROM identity_tokens WHERE customer_id=? AND purpose='invite' ORDER BY expires_at DESC LIMIT 100",[$customer])->fetchAll();
            }
            if (!$p->staff) { throw new HttpError(403,'ADMIN_REQUIRED'); }
            if ($view==='users' || $view==='staff') {
                $this->authorize($u,$s,'identity.users.view');
                return $this->db->execute('SELECT u.id,u.email,u.display_name,u.status,u.verified_at,u.identity_version,s.administrative FROM identity_users u LEFT JOIN identity_staff s ON s.user_id=u.id '.($view==='staff'?'WHERE s.user_id IS NOT NULL ':'').'ORDER BY u.created_at DESC LIMIT 100')->fetchAll();
            }
            if ($view==='user') { $this->authorize($u,$s,'identity.users.view'); return $this->db->execute('SELECT id,email,display_name,status,verified_at,identity_version FROM identity_users WHERE id=?',[$customer])->fetch() ?: []; }
            if ($view==='customers') {
                $this->authorize($u,$s,'identity.customers.view'); $result=[];
                foreach($this->db->execute('SELECT id,name,currency,mfa_required,version FROM identity_customers ORDER BY created_at DESC LIMIT 100')->fetchAll() as $c) {
                    try { $this->authorize($u,$s,'identity.customers.view',$c['id']); $result[]=$c; } catch(HttpError) {}
                } return $result;
            }
            if ($view==='roles') { $this->authorize($u,$s,'identity.roles.manage'); return ['roles'=>$this->db->execute('SELECT * FROM identity_roles')->fetchAll(),'permissions'=>$this->db->execute('SELECT code FROM identity_permissions')->fetchAll(),'role_permissions'=>$this->db->execute('SELECT * FROM identity_role_permissions')->fetchAll(),'assignments'=>$this->db->execute('SELECT * FROM identity_assignments')->fetchAll(),'grants'=>$this->db->execute('SELECT * FROM identity_grants')->fetchAll()]; }
            if ($view==='events') { $this->authorize($u,$s,'identity.security.view'); return $this->db->execute('SELECT id,user_id,code,trace_id,created_at FROM identity_security_events ORDER BY created_at DESC LIMIT 100')->fetchAll(); }
            throw new HttpError(404,'NOT_FOUND');
        });
    }
    public function switchCustomer(string $reference,string $context,string $customer): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($customer): void {
            if ($s['context']!=='client' || !$this->db->execute('SELECT 1 FROM identity_memberships WHERE user_id=? AND customer_id=?',[$u['id'],$customer])->fetchColumn()) { throw new HttpError(403,'CUSTOMER_SCOPE'); }
            $factor=$this->db->execute('SELECT mfa_required FROM identity_customers WHERE id=?',[$customer])->fetchColumn();
            if ($factor && !$this->db->execute('SELECT 1 FROM identity_mfa WHERE user_id=? AND enabled=TRUE',[$u['id']])->fetchColumn()) { throw new HttpError(403,'MFA_ENROLLMENT_REQUIRED'); }
            $this->db->execute('UPDATE identity_sessions SET customer_id=? WHERE id=?',[$customer,$s['id']]);
        });
    }
    public function updateAccount(string $reference,string $context,string $customer,string $name,bool $mfaRequired,int $version): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($customer,$name,$mfaRequired,$version): void {
            $this->authorize($u,$s,'account.manage',$customer,privileged:true);
            $row=$this->db->execute('SELECT * FROM identity_customers WHERE id=? FOR UPDATE',[$customer])->fetch();
            if (!$row || (int)$row['version']!==$version) { throw new HttpError(409,'VERSION_CONFLICT'); }
            $this->db->execute('UPDATE identity_customers SET name=?,mfa_required=?,version=version+1 WHERE id=?',[Validation::text($name),(int)$mfaRequired,$customer]);
            if ((bool)$row['mfa_required']!==$mfaRequired) {
                foreach($this->db->execute('SELECT user_id FROM identity_memberships WHERE customer_id=? ORDER BY user_id',[$customer])->fetchAll() as $member) { $this->repository->user($member['user_id'],true); $this->invalidate($member['user_id']); }
            }
            $this->event($u['id'],AuditEvent::ConfigurationChanged,$customer,$customer);
        });
    }
    public function createCustomer(string $reference,string $context,string $name,string $currency): array
    {
        if (!in_array($currency,['TRY','USD','EUR'],true)) { throw new HttpError(422,'INVALID_CURRENCY'); }
        return $this->locked($reference,$context,function(array $u,array $s) use($name,$currency): array {
            $this->authorize($u,$s,'identity.customers.manage',privileged:true); $id=Id::uuid();
            $this->db->execute('INSERT INTO identity_customers(id,name,currency) VALUES (?,?,?)',[$id,Validation::text($name),$currency]); $this->event($u['id'],AuditEvent::ConfigurationChanged,$id); return ['id'=>$id];
        });
    }
    public function manageRole(string $reference,string $context,?string $role,string $name,array $permissions): array
    {
        return $this->locked($reference,$context,function(array $u,array $s) use($role,$name,$permissions): array {
            $p=$this->authorize($u,$s,'identity.roles.manage',privileged:true);
            if (!$p->staff || !$p->administrative || array_diff($permissions,$p->permissions)) { throw new HttpError(403,'PERMISSION_ESCALATION'); }
            $id=$role ?? Id::uuid();
            if ($role) {
                $this->db->execute('SELECT id FROM identity_roles WHERE id=? FOR UPDATE',[$role])->fetch();
                $this->db->execute('UPDATE identity_roles SET name=?,version=version+1 WHERE id=?',[Validation::text($name,64),$id]);
                $this->db->execute('DELETE FROM identity_role_permissions WHERE role_id=?',[$id]);
            } else { $this->db->execute('INSERT INTO identity_roles VALUES (?,?,1)',[$id,Validation::text($name,64)]); }
            foreach(array_unique($permissions) as $permission) { if (!is_string($permission)) { throw new HttpError(422,'INVALID_PERMISSION'); } $this->db->execute('INSERT INTO identity_role_permissions VALUES (?,?)',[$id,$permission]); }
            foreach($this->db->execute('SELECT DISTINCT user_id FROM identity_assignments WHERE role_id=? ORDER BY user_id',[$id])->fetchAll() as $target) { $this->repository->user($target['user_id'],true); $this->invalidate($target['user_id']); }
            $this->event($u['id'],AuditEvent::PermissionChanged,$id); return ['id'=>$id];
        });
    }
    public function assignRole(string $reference,string $context,string $target,string $role,?string $customer=null,?array $grant=null): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($target,$role,$customer,$grant): void {
            $p=$this->authorize($u,$s,'identity.roles.manage',privileged:true);
            if (!$p->staff || !$p->administrative) { throw new HttpError(403,'ADMIN_REQUIRED'); }
            $this->repository->user($target,true);
            if ($customer!==null && !$this->db->execute('SELECT 1 FROM identity_memberships WHERE user_id=? AND customer_id=?',[$target,$customer])->fetchColumn()) { throw new HttpError(403,'MEMBERSHIP_REQUIRED'); }
            $rp=array_column($this->db->execute('SELECT permission FROM identity_role_permissions WHERE role_id=?',[$role])->fetchAll(),'permission');
            if (array_diff($rp,$p->permissions)) { throw new HttpError(403,'PERMISSION_ESCALATION'); }
            if ($customer!==null) { foreach($rp as $permission) { if (str_starts_with($permission,'identity.')) { throw new HttpError(403,'ADMIN_ROLE_IN_CUSTOMER_SCOPE'); } } }
            if ($customer!==null) { foreach($rp as $permission) { $this->authorize($u,$s,$permission,$customer); } }
            $assignment=$this->assign($target,$role,$customer);
            if ($grant!==null) {
                Validation::fields($grant,['permission','customer','service','department'],['permission','customer']);
                foreach($grant as $value) { if (!is_string($value)) { throw new HttpError(422,'INVALID_GRANT'); } }
                if ($customer!==null && $grant['customer']!==$customer) { throw new HttpError(403,'GRANT_CUSTOMER_MISMATCH'); }
                if (!in_array($grant['permission'],$rp,true) || !$this->db->execute('SELECT 1 FROM identity_customers WHERE id=?',[$grant['customer']])->fetchColumn()) { throw new HttpError(422,'INVALID_GRANT'); }
                $this->authorize($u,$s,$grant['permission'],$grant['customer'],($grant['service'] ?? '')!==''?$grant['service']:null,($grant['department'] ?? '')!==''?$grant['department']:null);
                if (($grant['service'] ?? '')!=='' && $grant['service']!=='*') { $this->repository->owns($grant['service'],$grant['customer'],$grant['department'] ?? null); }
                $this->db->execute('INSERT INTO identity_grants VALUES (?,?,?,?,?,?)',[Id::uuid(),$assignment,$grant['permission'],$grant['customer'],$grant['service'] ?? '',$grant['department'] ?? '']);
            }
            $this->invalidate($target); $this->event($u['id'],AuditEvent::RoleAssigned,$target,$customer);
        });
    }
    public function staff(string $reference,string $context,string $target,bool $enabled,bool $administrative): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($target,$enabled,$administrative): void {
            $p=$this->authorize($u,$s,'identity.staff.manage',privileged:true);
            if (!$p->staff || !$p->administrative) { throw new HttpError(403,'ADMIN_REQUIRED'); }
            $this->repository->user($target,true);
            if ($target===$u['id']) { throw new HttpError(409,'SELF_STAFF_CHANGE_DENIED'); }
            if ($enabled) { $this->db->execute('INSERT INTO identity_staff VALUES (?,?) ON DUPLICATE KEY UPDATE administrative=VALUES(administrative)',[$target,(int)$administrative]); }
            else { $this->db->execute('DELETE FROM identity_staff WHERE user_id=?',[$target]); }
            $this->invalidate($target); $this->event($u['id'],AuditEvent::PermissionChanged,$target);
        });
    }
    public function invite(string $reference,string $context,string $customer,string $email,string $role): void
    {
        $email=$this->email($email); $randomHash=Passwords::hash(bin2hex(random_bytes(32)));
        $this->locked($reference,$context,function(array $u,array $s) use($customer,$email,$role,$randomHash): void {
            $p=$this->authorize($u,$s,'members.manage',$customer,privileged:true);
            $permissions=array_column($this->db->execute('SELECT permission FROM identity_role_permissions WHERE role_id=?',[$role])->fetchAll(),'permission');
            foreach($permissions as $permission) { if (str_starts_with($permission,'identity.') || !in_array($permission,$p->permissions,true)) { throw new HttpError(403,'PERMISSION_ESCALATION'); } }
            if ($permissions===[]) { throw new HttpError(422,'INVALID_ROLE'); }
            $target=$this->db->execute('SELECT id FROM identity_users WHERE email=?',[$email])->fetchColumn();
            if (!$target) { $target=$this->createUser($email,'Davet edilen kullanıcı',$randomHash); }
            $this->repository->user($target,true);
            if ($this->db->execute('SELECT 1 FROM identity_memberships WHERE user_id=? AND customer_id=?',[$target,$customer])->fetchColumn()) { throw new HttpError(409,'ALREADY_MEMBER'); }
            $this->token($target,'invite',$customer,$role); $this->event($u['id'],AuditEvent::InvitationCreated,$target,$customer);
        });
    }
    public function removeMembership(string $reference,string $context,string $customer,string $target): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($customer,$target): void {
            $this->authorize($u,$s,'members.manage',$customer,privileged:true); $this->repository->user($target,true);
            $member=$this->db->execute('SELECT is_owner FROM identity_memberships WHERE user_id=? AND customer_id=? FOR UPDATE',[$target,$customer])->fetch();
            if (!$member || $member['is_owner']) { throw new HttpError(409,'OWNER_MEMBERSHIP_PROTECTED'); }
            foreach($this->db->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id=?',[$target,$customer])->fetchAll() as $a) { $this->db->execute('DELETE FROM identity_grants WHERE assignment_id=?',[$a['id']]); }
            $this->db->execute('DELETE FROM identity_assignments WHERE user_id=? AND customer_id=?',[$target,$customer]);
            $this->db->execute('DELETE FROM identity_memberships WHERE user_id=? AND customer_id=?',[$target,$customer]); $this->invalidate($target); $this->event($u['id'],AuditEvent::PermissionChanged,$target,$customer);
        });
    }
    public function impersonate(string $reference,string $context,string $customer,string $target,string $reason): array
    {
        return $this->locked($reference,$context,function(array $u,array $s) use($customer,$target,$reason): array {
            $this->authorize($u,$s,'identity.impersonate',$customer,privileged:true); $this->fresh($s);
            if (!$this->db->execute('SELECT 1 FROM identity_memberships WHERE user_id=? AND customer_id=?',[$target,$customer])->fetchColumn()) { throw new HttpError(403,'CUSTOMER_SCOPE'); }
            $id=Id::uuid();
            $this->db->execute('INSERT INTO identity_impersonations(id,session_id,customer_id,target_user_id,reason_encrypted,expires_at) VALUES (?,?,?,?,?,TIMESTAMPADD(MINUTE,10,UTC_TIMESTAMP(6)))',[$id,$s['id'],$customer,$target,$this->crypto->encrypt(Validation::text($reason,512),'impersonation:'.$id)]);
            $this->event($u['id'],AuditEvent::ImpersonationStarted,$id,$customer); return ['id'=>$id,'customer_id'=>$customer];
        });
    }
    public function endImpersonation(string $reference,string $context,string $password,string $code): void
    {
        $this->throttle('imp-end:'.$reference,5);
        $ok=$this->locked($reference,$context,function(array $u,array $s) use($password,$code): bool {
            if (!Passwords::verify($password,$u['password_hash']) || !$this->factor($u['id'],$code)) { return false; }
            foreach($this->db->execute('SELECT id,customer_id FROM identity_impersonations WHERE session_id=? AND ended_at IS NULL FOR UPDATE',[$s['id']])->fetchAll() as $imp) { $this->db->execute('UPDATE identity_impersonations SET ended_at=UTC_TIMESTAMP(6) WHERE id=?',[$imp['id']]); $this->event($u['id'],AuditEvent::ImpersonationEnded,$imp['id'],$imp['customer_id']); }
            $this->db->execute('UPDATE identity_sessions SET verified_at=UTC_TIMESTAMP(6) WHERE id=?',[$s['id']]); return true;
        }); if (!$ok) { throw new HttpError(403,'REAUTHENTICATION_FAILED'); }
    }
    public function createKey(string $reference,string $context,array $scopes,int $seconds): array
    {
        if ($seconds<60 || $seconds>2592000 || $scopes===[] || !array_is_list($scopes)) { throw new HttpError(422,'INVALID_KEY_SCOPE'); }
        return $this->locked($reference,$context,function(array $u,array $s) use($scopes,$seconds): array {
            $p=$this->authorize($u,$s,'keys.manage',$s['customer_id'],privileged:true); $this->fresh($s);
            if (array_diff($scopes,$p->permissions)) { throw new HttpError(403,'PERMISSION_ESCALATION'); }
            $id=Id::uuid(); $secret=$id.'.'.bin2hex(random_bytes(32));
            $this->db->execute('INSERT INTO identity_api_credentials(id,user_id,customer_id,secret_hash,scopes,identity_version,expires_at) VALUES (?,?,?,?,?,?,TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)))',[$id,$u['id'],$s['customer_id'],$this->digest($secret),json_encode(array_values(array_unique($scopes)),JSON_THROW_ON_ERROR),$u['identity_version'],$seconds]);
            $this->event($u['id'],AuditEvent::ApiKeyCreated,$id); return ['id'=>$id,'key'=>$secret];
        });
    }
    public function changeKey(string $reference,string $context,string $id,?array $scopes=null): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($id,$scopes): void {
            $this->fresh($s); $p=$this->authorize($u,$s,'keys.manage',$s['customer_id'],privileged:true);
            $key=$this->db->execute('SELECT * FROM identity_api_credentials WHERE id=? AND user_id=? FOR UPDATE',[$id,$u['id']])->fetch();
            if (!$key) { throw new HttpError(404,'KEY_NOT_FOUND'); }
            if ($scopes!==null) {
                $prior=json_decode($key['scopes'],true,32,JSON_THROW_ON_ERROR);
                if ($scopes===[] || !array_is_list($scopes) || array_diff($scopes,$prior) || array_diff($scopes,$p->permissions)) { throw new HttpError(403,'PERMISSION_ESCALATION'); }
                $this->db->execute('UPDATE identity_api_credentials SET scopes=? WHERE id=?',[json_encode(array_values(array_unique($scopes)),JSON_THROW_ON_ERROR),$id]);
            } else { $this->db->execute('UPDATE identity_api_credentials SET revoked_at=UTC_TIMESTAMP(6) WHERE id=?',[$id]); }
            $this->event($u['id'],$scopes===null?AuditEvent::ApiKeyRevoked:AuditEvent::ApiKeyRotated,$id);
        });
    }
    public function withKey(string $token,string $permission,?string $customer,\Closure $work,?string $service=null): mixed
    {
        try {
            return $this->transaction(function () use($token,$permission,$customer,$work,$service): mixed {
                $candidate=$this->db->execute('SELECT user_id FROM identity_api_credentials WHERE secret_hash=?',[$this->digest($token)])->fetchColumn();
                if (!$candidate) { throw new HttpError(401,'INVALID_API_KEY'); }
                $u=$this->repository->user($candidate,true);
                $key=$this->db->execute('SELECT *,UNIX_TIMESTAMP(expires_at) AS expiry FROM identity_api_credentials WHERE secret_hash=? FOR UPDATE',[$this->digest($token)])->fetch();
                if (!$key || $key['revoked_at'] || (int)$key['expiry']<=time() || $u['status']!=='ACTIVE' || (int)$key['identity_version']!==(int)$u['identity_version']) { throw new HttpError(401,'INVALID_API_KEY'); }
                if (!in_array($permission,json_decode($key['scopes'],true,32,JSON_THROW_ON_ERROR),true) || ($key['customer_id']!==null && $key['customer_id']!==$customer)) { throw new HttpError(403,'KEY_SCOPE'); }
                $p=$this->repository->principal($u,['context'=>$key['customer_id']===null?'admin':'client','customer_id'=>$key['customer_id'],'mfa_verified'=>true,'expiry'=>$key['expiry'],'verified'=>0]);
                $department=null;
                if ($service!==null) { if (!$customer) { throw new HttpError(403,'CUSTOMER_REQUIRED'); } $department=$this->repository->owns($service,$customer)['department_id']; }
                (new Authorization())->require($p,$permission,$customer,$department,service:$service);
                $this->db->execute('UPDATE identity_api_credentials SET last_used_at=UTC_TIMESTAMP(6) WHERE id=?',[$key['id']]); return $work($p);
            });
        } catch(HttpError $e) {
            $this->transaction(fn()=>$this->event(null,AuditEvent::ApiKeyDenied,Id::uuid())); throw $e;
        }
    }
    public function revokeDevice(string $reference,string $context,string $id): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($id): void {
            $this->fresh($s); $this->db->execute('UPDATE identity_devices SET revoked_at=UTC_TIMESTAMP(6) WHERE id=? AND user_id=?',[$id,$u['id']]); $this->event($u['id'],AuditEvent::DeviceRevoked,$id);
        });
    }
    public function managedUser(string $reference,string $context,?string $id,string $name,?string $email=null,?string $status=null): array
    {
        $hash=$id===null?Passwords::hash(bin2hex(random_bytes(32))):null;
        return $this->locked($reference,$context,function(array $u,array $s) use($id,$name,$email,$status,$hash): array {
            $p=$this->authorize($u,$s,'identity.users.manage',privileged:true);
            if (!$p->staff || !$p->administrative) { throw new HttpError(403,'ADMIN_REQUIRED'); }
            if ($id===null) { $target=$this->createUser($this->email($email ?? ''),$name,$hash); $this->token($target,'reset'); return ['id'=>$target]; }
            if (!in_array($status,['ACTIVE','DISABLED'],true) || ($id===$u['id'] && $status!=='ACTIVE')) { throw new HttpError(409,'UNSAFE_USER_STATE'); }
            $this->repository->user($id,true); $this->db->execute('UPDATE identity_users SET display_name=?,status=? WHERE id=?',[Validation::text($name),$status,$id]);
            $this->invalidate($id); $this->event($u['id'],AuditEvent::ConfigurationChanged,$id); return ['id'=>$id];
        });
    }
    public function removeRole(string $reference,string $context,string $assignment): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($assignment): void {
            $p=$this->authorize($u,$s,'identity.roles.manage',privileged:true);
            if (!$p->staff || !$p->administrative) { throw new HttpError(403,'ADMIN_REQUIRED'); }
            $row=$this->db->execute('SELECT user_id FROM identity_assignments WHERE id=?',[$assignment])->fetch();
            if (!$row) { throw new HttpError(404,'ROLE_ASSIGNMENT_NOT_FOUND'); }
            if ($row['user_id']===$u['id']) { throw new HttpError(409,'SELF_ROLE_REMOVAL_DENIED'); }
            $this->repository->user($row['user_id'],true); $this->db->execute('DELETE FROM identity_grants WHERE assignment_id=?',[$assignment]);
            $this->db->execute('DELETE FROM identity_assignments WHERE id=?',[$assignment]); $this->invalidate($row['user_id']); $this->event($u['id'],AuditEvent::PermissionChanged,$row['user_id']);
        });
    }
    public function memberScope(string $reference,string $context,string $customer,string $target,string $permission,?string $service=null,?string $department=null): void
    {
        $this->locked($reference,$context,function(array $u,array $s) use($customer,$target,$permission,$service,$department): void {
            $p=$this->authorize($u,$s,'members.manage',$customer,privileged:true); $this->repository->user($target,true);
            if (!in_array($permission,$p->permissions,true) || str_starts_with($permission,'identity.')) { throw new HttpError(403,'PERMISSION_ESCALATION'); }
            if ($service==='*') { (new Authorization())->require($p,$permission,$customer,$department,service:'*'); }
            elseif ($service!==null) { $this->authorize($u,$s,$permission,$customer,$service,$department); }
            else { $this->authorize($u,$s,$permission,$customer,null,$department); }
            $assignment=$this->db->execute('SELECT a.id FROM identity_assignments a JOIN identity_memberships m ON m.user_id=a.user_id AND m.customer_id=a.customer_id JOIN identity_role_permissions rp ON rp.role_id=a.role_id WHERE a.user_id=? AND a.customer_id=? AND rp.permission=? AND m.is_owner=FALSE LIMIT 1',[$target,$customer,$permission])->fetchColumn();
            if (!$assignment) { throw new HttpError(403,'MEMBER_ROLE_SCOPE'); }
            if ($service!==null && $service!=='*') { $this->repository->owns($service,$customer,$department); }
            elseif ($department!==null && !$this->db->execute('SELECT 1 FROM identity_resource_scopes WHERE customer_id=? AND department_id=? LIMIT 1',[$customer,$department])->fetchColumn()) { throw new HttpError(404,'DEPARTMENT_NOT_FOUND'); }
            $this->db->execute('DELETE FROM identity_grants WHERE assignment_id=? AND permission=?',[$assignment,$permission]);
            $this->db->execute('INSERT INTO identity_grants VALUES (?,?,?,?,?,?)',[Id::uuid(),$assignment,$permission,$customer,$service ?? '',$department ?? '']);
            $this->invalidate($target); $this->event($u['id'],AuditEvent::PermissionChanged,$target,$customer);
        });
    }
}
