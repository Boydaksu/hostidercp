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
    /** Canonical test-adapter seam; caller owns Message-ID dedupe and the outer DB transaction. */
    public function inbound(string $reference,string $action,array $p,string $trace): array
    {
        $this->gate();$this->c->db->requireTransaction();if(!in_array($action,['create','reply'],true)){throw new HttpError(403,'MAIL_CANONICAL_ACTION');}$this->fields('client',$action,$p);
        return $this->c->identity->locked($reference,'client',function($u,$s)use($action,$p,$trace){if($u['administrative']!==null||!$u['verified_at']){throw new HttpError(403,'MAIL_VERIFIED_CUSTOMER_REQUIRED');}$this->authorize($u,$s,$action,$p);return $action==='create'?$this->create($u,$s,$p,$trace):$this->reply($u,$s,$p,'reply',$trace);});
    }
    public static function version(mixed $v): int
    {
        if(!is_int($v)||$v<1||$v>2147483647){throw new HttpError(422,'INVALID_SUPPORT_VERSION');}return $v;
    }
    /** Native UI outer transaction owns idempotency and exact draft cleanup. */
    public function compose(string $ref,string $ctx,string $action,array $p,string $trace): array
    {
        $this->gate();$this->c->db->requireTransaction();if(!in_array($action,['reply','note','state','move'],true)){throw new HttpError(422,'SUPPORT_COMPOSE_ACTION');}$this->fields($ctx,$action,$p);$work=function($u,$s)use($action,$p,$trace){$this->authorize($u,$s,$action,$p);return in_array($action,['reply','note'],true)?$this->reply($u,$s,$p,$action,$trace):$this->change($u,$s,$p,$action,$trace);};return $ctx==='admin'?$this->c->identity->withStepUp($ref,$ctx,$work):$this->c->identity->locked($ref,$ctx,$work);
    }
    /** Scoped delegation uses the same canonical authorization/state/message/event path. */
    public function delegated(string $reference,string $action,array $p,string $trace): array
    {
        $this->gate();$this->c->db->requireTransaction();if(!in_array($action,['state','note'],true)){throw new HttpError(422,'DELEGATED_SUPPORT_ACTION_INVALID');}$this->fields('admin',$action,$p);
        return $this->c->identity->withStepUp($reference,'admin',function($u,$s)use($action,$p,$trace){$this->authorize($u,$s,$action,$p);return $action==='note'?$this->reply($u,$s,$p,$action,$trace):$this->change($u,$s,$p,$action,$trace);});
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
        $this->event($u,$s,$row,'CREATED',$trace,true,message:$message);return $this->slaResult(['id'=>$id,'number'=>$number,'version'=>1,'message_id'=>$message]);
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
        $message=$this->message($u,$s,$row,$type,$p['body']);$next=$this->update($row,$to,$row['department_id']);$this->event($u,$s,$next,strtoupper($type),$trace,$type!=='internal_note',$row,message:$message);return $this->slaResult(['id'=>$row['id'],'message_id'=>$message,'version'=>(int)$next['version']]);
    }
    private function slaResult(array $result): array
    {
        if($this->c->db->execute('SELECT sla_status FROM support_work_tickets WHERE ticket_id=?',[$result['id']])->fetchColumn()==='UNAVAILABLE'){$result+=['sla_status'=>'UNAVAILABLE','warning'=>'SLA_CALENDAR_UNAVAILABLE'];}return $result;
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
        $next=$this->update($row,$status,$dep);$this->event($u,$s,$next,strtoupper($action),$trace,true,$row);return $this->slaResult(['id'=>$row['id'],'version'=>$next['version']]);
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
        if($public){(new Outbox($this->c->db,new Queue($this->c->db)))->append($id,'support.changed',$row['id'],['event_uuid'=>$id,'ticket_id'=>$row['id'],'message_id'=>$message],$trace);}
        (new \Hostider\Support\Work\Engine($this->c))->observe($row,$kind,$id);
        if($public&&$kind==='STAFF_REPLY'){(new \Hostider\Support\Mail\Outbound(new \Hostider\Support\Mail\Common($this->c)))->stage($row,$message,$id,$trace);}
    }

    /** Fixed system-policy action. No staff/session identity or arbitrary state is accepted. */
    public function autoClose(array $candidate,string $trace): bool
    {
        $this->gate();$this->c->db->requireTransaction();
        $proof=(new \Hostider\Support\AutoClose\Eligibility($this->c))->locked($candidate);
        if($proof===null){return false;}
        $row=$proof['ticket'];State::transition($row['status'],'CLOSED',$proof['department']);
        $next=$this->update($row,'CLOSED',$row['department_id']);
        $actor=new \Hostider\Core\Security\Principal('SYSTEM_AUTO_CLOSE',[],customerId:$row['customer_id']);
        $event=(new \Hostider\Support\Work\Store($this->c))->event($next,$row['department_id'],'AUTO_CLOSED',$actor,$trace,
            ['policy_id'=>$proof['policy']['id'],'policy_version'=>(int)$proof['policy']['revision'],'resolution_event'=>$proof['resolution']['id']],
            source:$proof['resolution']['id']);
        $this->c->db->execute("INSERT INTO support_auto_close_events(id,ticket_id,customer_id,department_id,policy_id,policy_version,resolution_event,from_version,to_version,actor_kind) VALUES (?,?,?,?,?,?,?,?,?,'SYSTEM_AUTO_CLOSE')",
            [$event,$row['id'],$row['customer_id'],$row['department_id'],$proof['policy']['id'],$proof['policy']['revision'],$proof['resolution']['id'],$row['version'],$next['version']]);
        return true;
    }

    public function view(string $reference,string $context,string $view,array $p): array
    {
        $this->gate();Validation::fields($p,$view==='detail'?['id']:($view==='list'?['offset','q']:[]),$view==='detail'?['id']:[]);
        return $this->c->identity->locked($reference,$context,function(array $u,array $s)use($view,$p): array{
            $a=$this->access->actor($u,$s);
            if($view==='list'&&array_key_exists('q',$p)){throw new HttpError(422,'SUPPORT_SEARCH_UNSUPPORTED');}
            if($view==='departments'){
                if($a->staff){$this->access->management($u,$s);return $this->c->db->execute('SELECT id,name,enabled,allow_reopen,allow_hold,version FROM support_departments ORDER BY id')->fetchAll();}
                if($a->customerId===null){throw new HttpError(403,'CUSTOMER_REQUIRED');}$this->c->identity->authorize($u,$s,'support.view',$a->customerId);return $this->c->db->execute('SELECT id,name FROM support_departments WHERE enabled=TRUE ORDER BY id')->fetchAll();
            }
            if($view==='detail'){return $this->detail($u,$s,$this->access->ticket($u,$s,Access::id($p['id'])));}
            $offset=$p['offset']??'0';if(!is_string($offset)||!ctype_digit($offset)||(int)$offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}
            if(!$a->staff&&$a->customerId===null){throw new HttpError(403,'CUSTOMER_REQUIRED');}
            [$where,$params]=$this->access->listing($a);$rows=$this->c->db->execute('SELECT t.id FROM support_tickets t WHERE '.$where.' ORDER BY t.id LIMIT 50 OFFSET '.(int)$offset,$params)->fetchAll();$out=[];
            foreach($rows as $row){try{$t=$this->access->ticket($u,$s,$row['id']);$out[]=$this->summary($t);}catch(HttpError $e){if($e->status!==404){throw $e;}}}return $out;
        });
    }
    private function summary(array $row): array
    {
        $out=array_intersect_key($row,array_flip(['id','number','customer_id','department_id','status','requested_priority','effective_priority','channel','created_at','updated_at']));$out['version']=(int)$row['version'];$out['subject']=$this->crypto->decrypt($row['subject_encrypted'],'support-subject:'.$row['id']);$origin=$this->c->db->execute("SELECT s.origin FROM support_mail_sources s JOIN support_events e ON e.id=s.event_id WHERE s.ticket_id=? AND s.customer_id=? AND e.kind='CREATED'",[$row['id'],$row['customer_id']])->fetchColumn();if($origin){$out['mail_origin']=$origin;}return $out;
    }
    private function detail(array $u,array $s,array $row): array
    {
        $a=$this->access->actor($u,$s);$canAttach=false;try{$this->access->require($u,$s,$row['customer_id'],$row['department_id'],'support.ticket.reply');$canAttach=true;}catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}}$notes=false;if($a->staff){try{$this->access->require($u,$s,$row['customer_id'],$row['department_id'],'support.ticket.internal_note');$notes=true;}catch(HttpError $e){if($e->status!==404){throw $e;}}}
        $out=$this->summary($row);$messages=$this->c->db->execute('SELECT id,type,customer_visible,author_id,created_at,body_encrypted FROM support_messages WHERE ticket_id=? AND customer_id=?'.($notes?'':" AND type<>'internal_note' AND customer_visible=TRUE").' ORDER BY created_at,id',[$row['id'],$row['customer_id']])->fetchAll();
        foreach($messages as &$m){$m['body']=$this->crypto->decrypt($m['body_encrypted'],'support-message:'.$m['id']);unset($m['body_encrypted']);$origin=$this->c->db->execute('SELECT origin FROM support_mail_sources WHERE message_id=? AND customer_id=?',[$m['id'],$row['customer_id']])->fetchColumn();if($origin){$m['mail_origin']=$origin;}$m['customer_visible']=(bool)$m['customer_visible'];$m['can_attach']=$canAttach&&$m['customer_visible']&&$m['type']!=='internal_note'&&$m['author_id']===$u['id'];if(!$a->staff){unset($m['author_id']);}}unset($m);$out['messages']=$messages;$out['service_ids']=[];
        if($notes){foreach($out['messages'] as &$item){if($item['type']==='internal_note'){$origin=$this->c->db->execute("SELECT execution_id,sponsor_id FROM support_advanced_events WHERE ticket_id=? AND customer_id=? AND kind='ACTION_note' AND execution_id IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.canonical_effect'))=? LIMIT 1",[$row['id'],$row['customer_id'],$item['id']])->fetch();if($origin){$item['system_origin']=['actor_kind'=>'SYSTEM_RULE','execution_id'=>$origin['execution_id'],'sponsor_id'=>$origin['sponsor_id']];}}}unset($item);}
        foreach($this->c->db->execute('SELECT service_id FROM support_ticket_links WHERE ticket_id=? AND customer_id=?',[$row['id'],$row['customer_id']])->fetchAll() as $link){try{$out['service_ids'][]=(new SupportLinks($this->c))->owned($u,$s,$row['customer_id'],$link['service_id']);}catch(HttpError $e){if(!in_array($e->status,[403,404],true)){throw $e;}}}return $out;
    }
}
