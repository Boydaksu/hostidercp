<?php
declare(strict_types=1);
use Hostider\Commerce\{Services,Commands,Confirmations};
use Hostider\Core\Id;
use Hostider\Core\Security\Crypto;
use Hostider\Identity\Totp;
function commerceUser(Services $services,string $prefix): array
{
    $identity=$services->identity;$db=$services->db;$password=bin2hex(random_bytes(20));$email=$prefix.'-'.str_replace('-','',Id::uuid()).'@example.invalid';
    $identity->register($email,'Synthetic commerce customer',$password,Id::uuid());$user=$db->execute('SELECT id FROM identity_users WHERE email=?',[$email])->fetchColumn();
    $notification=$db->execute("SELECT * FROM identity_notifications WHERE user_id=? AND template='verify'",[$user])->fetch();$token=json_decode((new Crypto(base64_decode($services->config->key,true)))->decrypt($notification['body_encrypted'],'notification:'.$notification['id']),true,32,JSON_THROW_ON_ERROR)['token'];$identity->completeToken($token,'verify');
    $reference=Id::uuid();$identity->login($email,$password,'client',$reference,Id::uuid());$identity->stepUp($reference,'client',$password,'');$customer=$db->execute('SELECT customer_id FROM identity_memberships WHERE user_id=?',[$user])->fetchColumn();return compact('user','email','password','reference','customer');
}
function commerceAdmin(Services $services): array
{
    $db=$services->db;foreach(['identity_confirmations','identity_impersonations','identity_api_credentials','identity_devices','identity_sessions','identity_notifications','identity_security_events','identity_recovery','identity_mfa','identity_tokens','identity_resource_scopes','identity_grants','identity_assignments','identity_staff','identity_memberships','identity_customers','identity_credentials','identity_users'] as $table){$db->execute('DELETE FROM '.$table);}$db->execute('UPDATE identity_settings SET bootstrapped=FALSE');$db->execute('DELETE FROM rate_limits');
    $identity=$services->identity;$password=bin2hex(random_bytes(20));$email='commerce-admin@example.invalid';$user=$identity->bootstrap($email,'Synthetic commerce admin',$password);$reference=Id::uuid();$identity->login($email,$password,'admin',$reference,Id::uuid());$factor=$identity->enroll($reference,'admin',$password);$recovery=$identity->confirmEnrollment($reference,'admin',Totp::code($factor['secret'],intdiv(time(),30)))['recovery_codes'];$identity->stepUp($reference,'admin',$password,$recovery[7]);return compact('user','email','password','reference','recovery');
}
function commercePrepare(Services $services,array $user,string $context,string $action,array $payload,?string $customer): array
{
    $services->db->execute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');return $services->identity->withStepUp($user['reference'],$context,fn(array $u,array $s): array=>(new Confirmations($services,new Commands($services)))->prepare($u,$s,$action,$payload,$customer));
}
function commerceExecute(Services $services,array $user,string $context,string $action,array $payload,?string $customer,string $confirmation,bool $accepted=true): array
{
    $services->db->execute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');return $services->identity->withStepUp($user['reference'],$context,fn(array $u,array $s): array=>(new Confirmations($services,new Commands($services)))->execute($u,$s,$action,$payload,$customer,['id'=>$confirmation,'accepted'=>$accepted],Id::uuid()));
}
