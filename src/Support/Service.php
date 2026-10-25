<?php
declare(strict_types=1);
namespace Hostider\Support;
use Hostider\Commerce\Services;
use Hostider\Core\{CanonicalJson,Id,Idempotency,Validation};
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Audit,AuditEvent,Crypto};
use Hostider\Core\Queue\{Outbox,Queue};
use Hostider\Services\SupportLinks;
final readonly class Service
{
    public Access $access;
    private Crypto $crypto;
    public function __construct(public Services $c)
    {
        $this->access=new Access($c);$this->crypto=new Crypto(base64_decode($c->config->key,true));
    }
    private function gate(): void{if($this->c->config->environment!=='test'){throw new HttpError(503,'F5_TEST_ONLY_GATE');}}
    public static function version(mixed $v): int
    {
        if(!is_int($v)||$v<1||$v>2147483647){throw new HttpError(422,'INVALID_SUPPORT_VERSION');}return $v;
    }
    private static function body(mixed $v): string
    {
        if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||trim($v)===''||mb_strlen($v)>16000||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v)){throw new HttpError(422,'INVALID_SUPPORT_BODY');}return $v;
    }
    public function command(string $reference,string $context,string $action,array $p,string $key,string $trace): array
    {
        $this->gate();$this->fields($context,$action,$p);
        $hash=hash_hmac('sha256',CanonicalJson::encode($p),base64_decode($this->c->config->key,true));
        $work=function(bool $replay)use($reference,$context,$action,$p,$trace): array{
            return $this->c->identity->locked($reference,$context,function(array $u,array $s)use($action,$p,$trace,$replay): array{
                $this->authorize($u,$s,$action,$p);
                if($replay){return [];}
                return match($action){'create'=>$this->create($u,$s,$p,$trace),'reply','note'=>$this->reply($u,$s,$p,$action,$trace),'state','move'=>$this->change($u,$s,$p,$action,$trace),'department','department-new'=>$this->department($u,$s,$p,$trace),'member'=>$this->member($u,$s,$p,$trace),default=>throw new HttpError(422,'UNKNOWN_SUPPORT_ACTION')};
            });
        };
        return (new Idempotency($this->c->db))->run($reference,'support:'.$context.':'.$action,$key,['hmac'=>$hash],fn(): array=>$work(false),fn(): array=>$work(true));
    }
    private function fields(string $ctx,string $action,array $p): void
    {
        $allowed=$ctx==='client'?['create','reply']:['reply','note','state','move','department','department-new','member'];if(!in_array($action,$allowed,true)){throw new HttpError(403,'SUPPORT_ACTION_FORBIDDEN');}
        $fields=match($action){'create'=>['department_id','subject','body','priority','service_ids'],'reply','note'=>['id','version','body'],'state'=>['id','version','status'],'move'=>['id','version','department_id'],'department'=>['id','version','name','enabled','allow_reopen','allow_hold'],'department-new'=>['name','enabled','allow_reopen','allow_hold'],default=>['department_id','user_id','enabled','version']};
        Validation::fields($p,$fields,$action==='create'?['department_id','subject','body','priority']:$fields);
        foreach(['id','department_id','user_id'] as $f){if(array_key_exists($f,$p)){Access::id($p[$f]);}}
        if(array_key_exists('version',$p)){self::version($p['version']);}
        if(array_key_exists('body',$p)){self::body($p['body']);}
        if($action==='create'){
            Validation::text($p['subject'],191);if(!in_array($p['priority'],['LOW','NORMAL','HIGH','CRITICAL'],true)){throw new HttpError(422,'INVALID_SUPPORT_PRIORITY');}
            $ids=$p['service_ids']??[];if(!is_array($ids)||!array_is_list($ids)||count($ids)>10){throw new HttpError(422,'INVALID_SUPPORT_LINKS');}foreach($ids as $v){Access::id($v);}if(count(array_unique($ids))!==count($ids)){throw new HttpError(422,'INVALID_SUPPORT_LINKS');}
        }
        if(in_array($action,['department','department-new'],true)){Validation::text($p['name'],191);}
        foreach(['enabled','allow_reopen','allow_hold'] as $f){if(array_key_exists($f,$p)&&!is_bool($p[$f])){throw new HttpError(422,'BOOLEAN_REQUIRED');}}
        if($action==='state'&&(!is_string($p['status'])||!in_array($p['status'],['NEW','WAITING_STAFF','WAITING_CUSTOMER','ON_HOLD','RESOLVED','CLOSED'],true))){throw new HttpError(422,'INVALID_SUPPORT_STATE');}
    }
    private function authorize(array $u,array $s,string $action,array $p): void
    {
        $a=$this->access->actor($u,$s);
        if(in_array($action,['department','department-new','member'],true)){$this->access->management($u,$s);if($action!=='department-new'){$this->access->department($p['department_id']??$p['id']);}return;}
        if($action==='create'){
            if($a->staff||$a->customerId===null){throw new HttpError(403,'CLIENT_REQUIRED');}$d=$this->access->department($p['department_id']);if(!$d['enabled']){throw new HttpError(404,'SUPPORT_NOT_FOUND');}
            $this->access->require($u,$s,$a->customerId,$d['id']);foreach($p['service_ids']??[] as $id){(new SupportLinks($this->c))->owned($u,$s,$a->customerId,$id);}return;
        }
        $perm=match($action){'reply'=>'support.ticket.reply','note'=>'support.ticket.internal_note',default=>'support.ticket.manage'};
        if($action==='move'){
            $source=$this->c->db->execute('SELECT department_id FROM support_tickets WHERE id=?',[$p['id']])->fetchColumn();$deps=[$p['department_id']];if($source){$deps[]=$source;}sort($deps,SORT_STRING);foreach(array_unique($deps) as $id){$this->access->department($id);}
        }
        $row=$this->access->ticket($u,$s,$p['id'],$perm);
        if($action==='move'){$d=$this->access->department($p['department_id']);if(!$d['enabled']){throw new HttpError(404,'SUPPORT_NOT_FOUND');}$this->access->require($u,$s,$row['customer_id'],$d['id'],'support.ticket.manage');}
    }
    private function create(array $u,array $s,array $p,string $trace): array
    {
        $a=$this->access->actor($u,$s);$id=Id::uuid();$number='HD-'.strtoupper(bin2hex(random_bytes(12)));
        $this->c->db->execute("INSERT INTO support_tickets(id,number,customer_id,requester_id,department_id,subject_encrypted,requested_priority,effective_priority,channel,status,opened_by,session_id,provenance) VALUES (?,?,?,?,?,?,?,?,'PANEL','NEW',?,?,'TEST_PANEL')",[$id,$number,$a->customerId,$u['id'],$p['department_id'],$this->crypto->encrypt($p['subject'],'support-subject:'.$id),$p['priority'],$p['priority'],$u['id'],$s['id']]);
        $row=$this->access->ticket($u,$s,$id);$message=$this->message($u,$s,$row,'customer_reply',$p['body']);
        foreach($p['service_ids']??[] as $service){$this->c->db->execute('INSERT INTO support_ticket_links(ticket_id,customer_id,service_id,created_by) VALUES (?,?,?,?)',[$id,$row['customer_id'],$service,$u['id']]);}
        $this->event($u,$s,$row,'CREATED',$trace,true,message:$message);return ['id'=>$id,'number'=>$number,'version'=>1,'message_id'=>$message];
    }
    private function message(array $u,array $s,array $row,string $type,string $body): string
    {
        $id=Id::uuid();$this->c->db->execute("INSERT INTO support_messages(id,ticket_id,customer_id,type,customer_visible,body_encrypted,author_id,session_id,identity_version,source) VALUES (?,?,?,?,?,?,?,?,?,'TEST_PANEL')",[$id,$row['id'],$row['customer_id'],$type,(int)($type!=='internal_note'),$this->crypto->encrypt($body,'support-message:'.$id),$u['id'],$s['id'],$u['identity_version']]);return $id;
    }
    private function reply(array $u,array $s,array $p,string $action,string $trace): array
    {
        $row=$this->access->ticket($u,$s,$p['id'],$action==='note'?'support.ticket.internal_note':'support.ticket.reply');$this->expected($row,$p['version']);$d=$this->access->department($row['department_id']);$a=$this->access->actor($u,$s);$type=$action==='note'?'internal_note':($a->staff?'staff_reply':'customer_reply');$to=$row['status'];
        if($type!=='internal_note'){
            $target=$a->staff?'WAITING_CUSTOMER':'WAITING_STAFF';
            if(in_array($to,['CLOSED','RESOLVED'],true)){State::transition($to,'WAITING_STAFF',$d);$to='WAITING_STAFF';}
            if($to!==$target){State::transition($to,$target,$d);}$to=$target;
        }
        $message=$this->message($u,$s,$row,$type,$p['body']);$next=$this->update($row,$to,$row['department_id']);$this->event($u,$s,$next,strtoupper($type),$trace,$type!=='internal_note',$row,message:$message);return ['id'=>$row['id'],'message_id'=>$message,'version'=>(int)$next['version']];
    }
    private function expected(array $row,int $version): void{if((int)$row['version']!==$version){throw new HttpError(409,'SUPPORT_VERSION_CONFLICT');}}
    private function update(array $row,string $status,string $department): array
    {
        $n=$this->c->db->execute('UPDATE support_tickets SET status=?,department_id=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?',[$status,$department,$row['id'],$row['version']]);if($n->rowCount()!==1){throw new HttpError(409,'SUPPORT_VERSION_CONFLICT');}return array_replace($row,['status'=>$status,'department_id'=>$department,'version'=>(int)$row['version']+1]);
    }
    private function change(array $u,array $s,array $p,string $action,string $trace): array
    {
        $row=$this->access->ticket($u,$s,$p['id'],'support.ticket.manage');$this->expected($row,$p['version']);$status=$row['status'];$dep=$row['department_id'];
        if($action==='state'){State::transition($status,$p['status'],$this->access->department($dep));$status=$p['status'];}else{$dep=$p['department_id'];if($dep===$row['department_id']){throw new HttpError(409,'SUPPORT_DEPARTMENT_UNCHANGED');}}
        $next=$this->update($row,$status,$dep);$this->event($u,$s,$next,strtoupper($action),$trace,true,$row);return ['id'=>$row['id'],'version'=>$next['version']];
    }
    private function department(array $u,array $s,array $p,string $trace): array
    {
        if(!isset($p['id'])){$id=Id::uuid();$this->c->db->execute('INSERT INTO support_departments(id,name,enabled,allow_reopen,allow_hold) VALUES (?,?,?,?,?)',[$id,$p['name'],(int)$p['enabled'],(int)$p['allow_reopen'],(int)$p['allow_hold']]);$this->managementEvent($u,$s,$id,'DEPARTMENT_CREATED',$trace);return ['id'=>$id,'version'=>1];}
        $row=$this->access->department($p['id']);$this->expected($row,$p['version']);
        $this->c->db->execute('UPDATE support_departments SET name=?,enabled=?,allow_reopen=?,allow_hold=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$p['name'],(int)$p['enabled'],(int)$p['allow_reopen'],(int)$p['allow_hold'],$p['id']]);
        $this->managementEvent($u,$s,$p['id'],'DEPARTMENT',$trace);return ['id'=>$p['id'],'version'=>$p['version']+1];
    }
    private function member(array $u,array $s,array $p,string $trace): array
    {
        $d=$this->access->department($p['department_id']);$this->expected($d,$p['version']);
        if(!$this->c->db->execute("SELECT 1 FROM identity_staff f JOIN identity_users u ON u.id=f.user_id WHERE f.user_id=? AND u.status='ACTIVE'",[$p['user_id']])->fetchColumn()){throw new HttpError(422,'ACTIVE_STAFF_REQUIRED');}
        if($p['enabled']){$this->c->db->execute('INSERT INTO support_department_members(department_id,user_id,created_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)',[$d['id'],$p['user_id'],$u['id']]);}else{$this->c->db->execute('DELETE FROM support_department_members WHERE department_id=? AND user_id=?',[$d['id'],$p['user_id']]);}
        $this->c->db->execute('UPDATE support_departments SET version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$d['id']]);$this->managementEvent($u,$s,$p['user_id'],$p['enabled']?'MEMBER_ADDED':'MEMBER_REMOVED',$trace,$d['id']);return ['id'=>$d['id'],'version'=>$p['version']+1];
    }
    private function managementEvent(array $u,array $s,string $subject,string $kind,string $trace,?string $dep=null): void
    {
        $this->c->db->execute("INSERT INTO support_events(id,subject_id,kind,actor_id,session_id,identity_version,to_department,trace_id,provenance) VALUES (?,?,?,?,?,?,?,?,'TEST_PANEL')",[Id::uuid(),$subject,$kind,$u['id'],$s['id'],$u['identity_version'],$dep??$subject,$trace]);(new Audit($this->c->db))->record($this->access->actor($u,$s),AuditEvent::SupportChanged,$subject,$trace);
    }
    private function event(array $u,array $s,array $row,string $kind,string $trace,bool $public,?array $old=null,?string $message=null): void
    {
        $id=Id::uuid();$this->c->db->execute("INSERT INTO support_events(id,ticket_id,customer_id,subject_id,kind,actor_id,session_id,identity_version,ticket_version,from_state,to_state,from_department,to_department,trace_id,provenance) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'TEST_PANEL')",[$id,$row['id'],$row['customer_id'],$message??$row['id'],$kind,$u['id'],$s['id'],$u['identity_version'],$row['version'],$old['status']??null,$row['status'],$old['department_id']??null,$row['department_id'],$trace]);(new Audit($this->c->db))->record($this->access->actor($u,$s),AuditEvent::SupportChanged,$row['id'],$trace);
        if($public){(new Outbox($this->c->db,new Queue($this->c->db)))->append($id,'support.changed',$row['id'],['ticket_id'=>$row['id'],'message_id'=>$message],$trace);}
    }
    public function view(string $reference,string $context,string $view,array $p): array
    {
        $this->gate();Validation::fields($p,$view==='detail'?['id']:($view==='list'?['offset','q']:[]),$view==='detail'?['id']:[]);
        return $this->c->identity->locked($reference,$context,function(array $u,array $s)use($view,$p): array{
            $a=$this->access->actor($u,$s);
            if($view==='departments'){
                if($a->staff){$this->access->management($u,$s);return $this->c->db->execute('SELECT id,name,enabled,allow_reopen,allow_hold,version FROM support_departments ORDER BY id')->fetchAll();}
                if($a->customerId===null){throw new HttpError(403,'CUSTOMER_REQUIRED');}$this->c->identity->authorize($u,$s,'support.view',$a->customerId);return $this->c->db->execute('SELECT id,name FROM support_departments WHERE enabled=TRUE ORDER BY id')->fetchAll();
            }
            if($view==='detail'){return $this->detail($u,$s,$this->access->ticket($u,$s,Access::id($p['id'])));}
            $offset=$p['offset']??'0';if(!is_string($offset)||!ctype_digit($offset)||(int)$offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}$q=isset($p['q'])?Validation::text($p['q'],191):null;
            if(!$a->staff&&$a->customerId===null){throw new HttpError(403,'CUSTOMER_REQUIRED');}
            [$where,$params]=$this->access->listing($a);$rows=$this->c->db->execute('SELECT t.id FROM support_tickets t WHERE '.$where.' ORDER BY t.id LIMIT 50 OFFSET '.(int)$offset,$params)->fetchAll();$out=[];
            foreach($rows as $row){try{$t=$this->access->ticket($u,$s,$row['id']);$v=$this->summary($t);if($q===null||mb_stripos($v['subject'],$q)!==false||str_contains($v['number'],$q)){$out[]=$v;}}catch(HttpError $e){if($e->status!==404){throw $e;}}}return $out;
        });
    }
    private function summary(array $row): array
    {
        $out=array_intersect_key($row,array_flip(['id','number','customer_id','department_id','status','requested_priority','effective_priority','channel','created_at','updated_at']));$out['version']=(int)$row['version'];$out['subject']=$this->crypto->decrypt($row['subject_encrypted'],'support-subject:'.$row['id']);return $out;
    }
    private function detail(array $u,array $s,array $row): array
    {
        $a=$this->access->actor($u,$s);$notes=false;if($a->staff){try{$this->access->require($u,$s,$row['customer_id'],$row['department_id'],'support.ticket.internal_note');$notes=true;}catch(HttpError $e){if($e->status!==404){throw $e;}}}
        $out=$this->summary($row);$messages=$this->c->db->execute('SELECT id,type,customer_visible,author_id,created_at,body_encrypted FROM support_messages WHERE ticket_id=? AND customer_id=?'.($notes?'':" AND type<>'internal_note' AND customer_visible=TRUE").' ORDER BY created_at,id',[$row['id'],$row['customer_id']])->fetchAll();
        foreach($messages as &$m){$m['body']=$this->crypto->decrypt($m['body_encrypted'],'support-message:'.$m['id']);unset($m['body_encrypted']);$m['customer_visible']=(bool)$m['customer_visible'];}unset($m);$out['messages']=$messages;$out['service_ids']=[];
        foreach($this->c->db->execute('SELECT service_id FROM support_ticket_links WHERE ticket_id=? AND customer_id=?',[$row['id'],$row['customer_id']])->fetchAll() as $link){try{$out['service_ids'][]=(new SupportLinks($this->c))->owned($u,$s,$row['customer_id'],$link['service_id']);}catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}}}return $out;
    }
}
