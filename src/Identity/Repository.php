<?php
declare(strict_types=1);
namespace Hostider\Identity;
use Hostider\Core\Database\Database;
use Hostider\Core\Security\{Principal,AccessGrant};
use Hostider\Core\Http\HttpError;
final readonly class Repository
{
    public function __construct(public Database $db) {}
    public function user(string $id,bool $lock=false): array
    {
        $u=$this->db->execute('SELECT u.*,c.password_hash,s.administrative FROM identity_users u JOIN identity_credentials c ON c.user_id=u.id LEFT JOIN identity_staff s ON s.user_id=u.id WHERE u.id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();
        if (!$u) { throw new HttpError(401,'AUTHENTICATION_REQUIRED'); } return $u;
    }
    public function session(string $reference,string $context,bool $lock=false): array
    {
        $s=$this->db->execute('SELECT *,UNIX_TIMESTAMP(expires_at) AS expiry,UNIX_TIMESTAMP(verified_at) AS verified FROM identity_sessions WHERE native_reference=? AND context=?'.($lock?' FOR UPDATE':''),[$reference,$context])->fetch();
        if (!$s) { throw new HttpError(401,'AUTHENTICATION_REQUIRED'); } return $s;
    }
    public function validate(array $user,array $session): void
    {
        if ($user['status']!=='ACTIVE' || $session['revoked_at']!==null || (int)$session['expiry']<=time() || (int)$session['identity_version']!==(int)$user['identity_version']) { throw new HttpError(401,'SESSION_REVOKED'); }
    }
    public function principal(array $user,array $session,?string $customer=null): Principal
    {
        $staff=$user['administrative']!==null && $session['context']==='admin';
        $customer ??= $session['customer_id'];
        if (!$staff && $customer!==null && !$this->db->execute('SELECT 1 FROM identity_memberships WHERE user_id=? AND customer_id=?',[$user['id'],$customer])->fetchColumn()) { throw new HttpError(403,'CUSTOMER_SCOPE'); }
        $rows=$this->db->execute('SELECT a.id,p.permission FROM identity_assignments a JOIN identity_role_permissions p ON p.role_id=a.role_id WHERE a.user_id=? AND '.($staff?'a.customer_id IS NULL':'a.customer_id=?'),$staff?[$user['id']]:[$user['id'],$customer])->fetchAll();
        $permissions=[]; $grants=[];
        $owner=!$staff && $customer!==null && (bool)$this->db->execute('SELECT is_owner FROM identity_memberships WHERE user_id=? AND customer_id=?',[$user['id'],$customer])->fetchColumn();
        $departments=$owner?array_column($this->db->execute('SELECT DISTINCT department_id FROM identity_resource_scopes WHERE customer_id=? AND department_id IS NOT NULL',[$customer])->fetchAll(),'department_id'):[];
        foreach ($rows as $row) {
            if (!$staff && str_starts_with($row['permission'],'identity.')) { continue; }
            $permissions[]=$row['permission'];
            if (!$staff && $customer!==null) {
                $grants[]=new AccessGrant($row['permission'],[$customer],$owner?['*']:[],$owner?['*']:[]);
            }
            foreach ($this->db->execute('SELECT * FROM identity_grants WHERE assignment_id=? AND permission=?',[$row['id'],$row['permission']])->fetchAll() as $g) {
                $grants[]=new AccessGrant($g['permission'],[$g['customer_scope']],$g['service_scope']===''?[]:[$g['service_scope']],$g['department_scope']===''?[]:[$g['department_scope']]);
                if ($g['department_scope']!=='') { $departments[]=$g['department_scope']; }
            }
        }
        return new Principal($user['id'],array_values(array_unique($permissions)),$customer,array_unique($departments),$staff,(bool)$session['mfa_verified'],(int)$session['expiry'],(int)$session['verified'],false,$grants,[],(bool)$user['administrative']);
    }
    public function owns(string $service,string $customer,?string $department=null): array
    {
        $row=$this->db->execute('SELECT customer_id,department_id FROM identity_resource_scopes WHERE service_id=? FOR UPDATE',[$service])->fetch();
        if (!$row || $row['customer_id']!==$customer || ($department!==null && $row['department_id']!==$department)) { throw new HttpError(404,'RESOURCE_NOT_FOUND'); }
        return $row;
    }
}
