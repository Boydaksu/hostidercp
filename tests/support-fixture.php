<?php
declare(strict_types=1);
require_once __DIR__.'/domains-fixture.php';
require_once __DIR__.'/commerce-fixture.php';
function supportFixture(): array
{
    $db=isolatedDomainDb();(new \Hostider\Core\Database\Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
    $config=new \Hostider\Core\Config('test','https://test.example.invalid',base64_encode(random_bytes(32)),getenv('TEST_DB_DSN'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASSWORD')?:'');
    $c=new \Hostider\Commerce\Services($db,$config);$admin=commerceAdmin($c);$client=commerceUser($c,'support-client');$other=commerceUser($c,'support-other');
    $departments=array_column($db->execute('SELECT id FROM support_departments ORDER BY id')->fetchAll(),'id');$service=new \Hostider\Support\Service($c);
    return compact('db','config','c','admin','client','other','departments','service');
}
function supportGrant(array $f,string $department,?string $customer=null,?string $user=null): void
{
    $db=$f['db'];$user??=$f['admin']['user'];$customer??=$f['client']['customer'];
    $assignment=$db->execute('SELECT id FROM identity_assignments WHERE user_id=? AND customer_id IS NULL',[$user])->fetchColumn();
    $db->execute('INSERT IGNORE INTO support_department_members(department_id,user_id,created_by) VALUES (?,?,?)',[$department,$user,$f['admin']['user']]);
    foreach(['support.ticket.view','support.ticket.reply','support.ticket.manage','support.ticket.internal_note'] as $perm){$db->execute('INSERT INTO identity_grants(id,assignment_id,permission,customer_scope,department_scope) VALUES (?,?,?,?,?)',[\Hostider\Core\Id::uuid(),$assignment,$perm,$customer,$department]);}
}
function supportCounts($db): array
{
    $out=[];foreach(['support_tickets','support_messages','support_events','outbox_events','audit_logs','idempotency_keys'] as $t){$out[$t]=(int)$db->execute('SELECT COUNT(*) FROM '.$t)->fetchColumn();}return $out;
}
