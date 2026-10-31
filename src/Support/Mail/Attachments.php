<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Core\{Id,Idempotency};
use Hostider\Core\Http\HttpError;
use Hostider\Support\Access;
final readonly class Attachments
{
    // Exact synthetic bytes are a test scanner fixture, NEVER an antivirus result.
    public const FIXTURE="%PDF-1.4\n% Hostider synthetic inert scanner fixture\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    public function __construct(private Common $m,public FileStore $store){}
    public static function validate(string $name,string $bytes): void
    {
        if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,80}\.pdf$/D',$name)){throw new HttpError(422,'MAIL_FILE_NAME');}if(strlen($bytes)<8||strlen($bytes)>16384){throw new HttpError(413,'MAIL_FILE_LIMIT');}if(!str_starts_with($bytes,'%PDF-1.')||!str_contains(substr($bytes,-32),'%%EOF')||(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes)!=='application/pdf'||preg_match('~/(JavaScript|JS|OpenAction|AA|Launch|EmbeddedFile|Filespec|RichMedia|AcroForm|URI|XFA)\b|<script|<svg|PK\x03\x04|%PDF-.*%PDF-~is',$bytes)){throw new HttpError(422,'MAIL_FILE_UNSAFE');}
    }
    public function save(array $t,string $message,string $actor,string $name,string $bytes,string $trace): string
    {
        $this->m->gate();$db=$this->m->c->db;$db->requireTransaction();self::validate($name,$bytes);$msg=$db->execute("SELECT type FROM support_messages WHERE id=? AND ticket_id=? AND customer_id=? AND customer_visible=TRUE AND type<>'internal_note'",[$message,$t['id'],$t['customer_id']])->fetchColumn();if(!$msg){throw new HttpError(404,'MAIL_NOT_FOUND');}$count=$db->execute('SELECT COUNT(*) n,COALESCE(SUM(byte_count),0) bytes FROM support_mail_files WHERE message_id=?',[$message])->fetch();if((int)$count['n']>=4||(int)$count['bytes']+strlen($bytes)>65536||(int)$db->execute('SELECT COALESCE(SUM(byte_count),0) FROM support_mail_files WHERE department_id=? AND purged_at IS NULL',[$t['department_id']])->fetchColumn()+strlen($bytes)>16777216){throw new HttpError(413,'MAIL_ATTACHMENT_TOTAL');}
        if($db->execute("SELECT 1 FROM support_mail_outbound WHERE message_id=? AND status IN ('ACCEPTED_BY_TRANSPORT','PENDING') AND attempt_count>0",[$message])->fetchColumn()){throw new HttpError(409,'MAIL_MANIFEST_FROZEN');}$id=Id::uuid();$this->store->put($id,$bytes);$days=(int)$this->m->retention($t['department_id'])['file_days'];try{$db->execute('INSERT INTO support_mail_files(id,ticket_id,customer_id,department_id,message_id,uploader_id,name_encrypted,sha256,byte_count,scan_status,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,TIMESTAMPADD(DAY,?,UTC_TIMESTAMP(6)))',[$id,$t['id'],$t['customer_id'],$t['department_id'],$message,$actor,$this->m->crypto->encrypt($name,'support-file-name:'.$id),hash('sha256',$bytes),strlen($bytes),hash_equals(hash('sha256',self::FIXTURE),hash('sha256',$bytes))?'TEST_CLEAN':'QUARANTINED',$days]);}catch(\Throwable $e){$this->store->remove($id);throw $e;}return $id;
    }
    private function authorized(array $u,array $s,string $id): array
    {
        $db=$this->m->c->db;$file=$db->execute('SELECT * FROM support_mail_files WHERE id=?',[Access::id($id)])->fetch();if(!$file){throw new HttpError(404,'MAIL_NOT_FOUND');}$t=(new Access($this->m->c))->ticket($u,$s,$file['ticket_id']);if($t['customer_id']!==$file['customer_id']){throw new HttpError(404,'MAIL_NOT_FOUND');}if($u['administrative']!==null&&!$db->execute('SELECT 1 FROM support_department_members WHERE department_id=? AND user_id=? FOR UPDATE',[$t['department_id'],$u['id']])->fetchColumn()){throw new HttpError(404,'MAIL_NOT_FOUND');}$message=$db->execute("SELECT 1 FROM support_messages WHERE id=? AND ticket_id=? AND customer_id=? AND customer_visible=TRUE AND type<>'internal_note'",[$file['message_id'],$t['id'],$t['customer_id']])->fetchColumn();if(!$message||$file['purged_at']!==null){throw new HttpError(404,'MAIL_NOT_FOUND');}(new \Hostider\Support\Split\Projection($this->m->c))->fileScope($u,$s,$file);$file['access_department_id']=$t['department_id'];return $file;
    }
    public function upload(string $ref,string $ctx,array $p,string $key,string $trace): array
    {
        $this->m->gate();\Hostider\Core\Validation::fields($p,['ticket_id','message_id','version','name','base64'],['ticket_id','message_id','version','name','base64']);foreach(['ticket_id','message_id'] as $k){Access::id($p[$k]);}\Hostider\Support\Service::version($p['version']);if(!is_string($p['base64'])||!is_string($p['name'])||strlen($p['base64'])>22000||($bytes=base64_decode($p['base64'],true))===false){throw new HttpError(422,'MAIL_FILE_ENCODING');}self::validate($p['name'],$bytes);
        return (new Idempotency($this->m->c->db))->run($ref,'mail-upload',$key,['hmac'=>$this->m->hmac(\Hostider\Core\CanonicalJson::encode($p))],fn()=>$this->m->c->identity->locked($ref,$ctx,function($u,$s)use($p,$bytes,$trace){$t=(new Access($this->m->c))->ticket($u,$s,$p['ticket_id'],'support.ticket.reply');if((int)$t['version']!==$p['version']){throw new HttpError(409,'SUPPORT_VERSION_CONFLICT');}(new \Hostider\Support\Split\Projection($this->m->c))->messageWritable($p['message_id']);$author=$this->m->c->db->execute('SELECT author_id FROM support_messages WHERE id=? AND ticket_id=?',[$p['message_id'],$t['id']])->fetchColumn();if($author!==$u['id']){throw new HttpError(404,'MAIL_NOT_FOUND');}$id=$this->save($t,$p['message_id'],$u['id'],$p['name'],$bytes,$trace);$t=(new \Hostider\Support\Work\Store($this->m->c))->bump($t);$this->m->event($id,'FILE_STORED',(new Access($this->m->c))->actor($u,$s),$trace);return ['id'=>$id,'version'=>(int)$t['version'],'scan_status'=>hash_equals($bytes,self::FIXTURE)?'TEST_CLEAN':'QUARANTINED'];}),fn()=>$this->m->c->identity->locked($ref,$ctx,fn($u,$s)=>(new Access($this->m->c))->ticket($u,$s,$p['ticket_id'],'support.ticket.reply')));
    }
    public function token(string $ref,string $ctx,string $id): array
    {
        $this->m->gate();return $this->m->c->db->transaction(fn()=>$this->m->c->identity->locked($ref,$ctx,function($u,$s)use($id){$f=$this->authorized($u,$s,$id);if($f['scan_status']!=='TEST_CLEAN'){throw new HttpError(423,'MAIL_FILE_QUARANTINED');}$s['customer_id']=$f['customer_id'];return ['token'=>(new Tokens($this->m))->issue('DOWNLOAD',$u,$s,$f['access_department_id'],$f['ticket_id'],$id,ttl:60),'expires_in'=>60,'scan_status'=>'TEST_CLEAN_NOT_ANTIVIRUS'];}));
    }
    public function listing(string $ref,string $ctx,string $ticket,string $message): array
    {
        $this->m->gate();return $this->m->c->db->transaction(fn()=>$this->m->c->identity->locked($ref,$ctx,function($u,$s)use($ticket,$message){$t=(new Access($this->m->c))->ticket($u,$s,$ticket);if(!$this->m->c->db->execute("SELECT 1 FROM support_messages WHERE id=? AND ticket_id=? AND customer_id=? AND customer_visible=TRUE AND type<>'internal_note'",[$message,$ticket,$t['customer_id']])->fetchColumn()){throw new HttpError(404,'MAIL_NOT_FOUND');}$out=[];foreach($this->m->c->db->execute('SELECT * FROM support_mail_files WHERE ticket_id=? AND customer_id=? AND message_id=? AND purged_at IS NULL ORDER BY id LIMIT 4',[$ticket,$t['customer_id'],$message])->fetchAll() as $f){$this->authorized($u,$s,$f['id']);$out[]=['id'=>$f['id'],'name'=>$this->m->crypto->decrypt($f['name_encrypted'],'support-file-name:'.$f['id']),'mime'=>'application/pdf','bytes'=>(int)$f['byte_count'],'scan_status'=>$f['scan_status']];}return $out;}));
    }
    public function download(string $ref,string $ctx,string $id,string $value,string $trace): array
    {
        $this->m->gate();return $this->m->c->db->transaction(fn()=>$this->m->c->identity->locked($ref,$ctx,function($u,$s)use($id,$value,$trace){$f=$this->authorized($u,$s,$id);$token=(new Tokens($this->m))->check($value,'DOWNLOAD');if($token['actor_id']!==$u['id']||$token['session_id']!==$s['id']||$token['identity_version']!==(int)$u['identity_version']||$token['file_id']!==$id||$token['ticket_id']!==$f['ticket_id']||$token['customer_id']!==$f['customer_id']||$token['department_id']!==$f['access_department_id']||$f['scan_status']!=='TEST_CLEAN'){throw new HttpError(403,'MAIL_DOWNLOAD_DENIED');}$bytes=$this->store->read($id,(int)$f['byte_count'],$f['sha256']);$this->m->event($id,'FILE_DOWNLOADED',(new Access($this->m->c))->actor($u,$s),$trace);return ['bytes'=>$bytes,'filename'=>$id.'.pdf'];}));
    }
}
