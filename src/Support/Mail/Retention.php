<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Core\Id;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Principal;
final readonly class Retention
{
    public function __construct(private Common $m,private FileStore $store){}
    public function run(int $max=25): array
    {
        $this->m->gate();if($max<1||$max>25){throw new HttpError(422,'MAIL_BATCH_LIMIT');}$db=$this->m->c->db;$n=0;
        foreach(['support_mail_inbound','support_mail_files'] as $table){$rows=$db->execute('SELECT id FROM '.$table.' WHERE expires_at<=UTC_TIMESTAMP(6) AND hold_content=FALSE AND purged_at IS NULL ORDER BY id LIMIT '.$max)->fetchAll();foreach($rows as $v){$db->transaction(function()use($table,$v,$db,&$n){$r=$db->execute('SELECT * FROM '.$table.' WHERE id=? AND expires_at<=UTC_TIMESTAMP(6) AND hold_content=FALSE AND purged_at IS NULL FOR UPDATE',[$v['id']])->fetch();if(!$r){return;}$db->execute('UPDATE '.$table.' SET purged_at=UTC_TIMESTAMP(6),version=version+1'.($table==='support_mail_inbound'?',raw_encrypted=NULL':'').' WHERE id=?',[$r['id']]);$this->m->event($r['id'],'CONTENT_PURGED',new Principal('mail-retention',[]),Id::uuid(),['kind'=>$table==='support_mail_inbound'?'RAW':'FILE']);++$n;});}}
        // DB deny/audit commits BEFORE irreversible bytes removal; failed removals retry safely.
        $removed=0;foreach($db->execute('SELECT id FROM support_mail_files WHERE purged_at IS NOT NULL AND physical_removed=FALSE ORDER BY id LIMIT '.$max)->fetchAll() as $row){$this->store->remove($row['id']);$db->transaction(function()use($db,$row){$r=$db->execute('SELECT physical_removed FROM support_mail_files WHERE id=? FOR UPDATE',[$row['id']])->fetch();if(!$r['physical_removed']){$db->execute('UPDATE support_mail_files SET physical_removed=TRUE WHERE id=?',[$row['id']]);$this->m->event($row['id'],'FILE_BYTES_REMOVED',new Principal('mail-retention',[]),Id::uuid());}});++$removed;}
        $known=array_column($db->execute('SELECT id FROM support_mail_files LIMIT 10001')->fetchAll(),'id');$orphans=count($known)>10000?[]:$this->store->orphans($known,$max);foreach($orphans as $id){$db->transaction(fn()=>$this->m->event($id,'ORPHAN_REMOVED',new Principal('mail-retention',[]),Id::uuid()));}return ['purged'=>$n,'removed_checked'=>$removed,'orphans'=>count($orphans),'test_only'=>true];
    }
}
