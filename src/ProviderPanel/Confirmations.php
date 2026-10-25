<?php
declare(strict_types=1);
namespace Hostider\ProviderPanel;
use Hostider\Core\Id;
use Hostider\Core\Http\HttpError;
final readonly class Confirmations
{
    public function __construct(private Commands $commands){}
    public function prepare(array $u,array $s,string $action,array $p,string $customer): array
    {
        $c=$this->commands->c;$a=$this->commands->authorize($u,$s,$action,$p,$customer);$state=$this->commands->state($p,$customer,$u,$s);if($action==='resolve'&&!in_array($p['outcome'],$state['evidence']['allowed_outcomes'],true)){throw new HttpError(409,'PANEL_EVIDENCE_OUTCOME_NOT_VERIFIED');}$native=$this->commands->nativePrepare($action,$p,$customer,$a,$s['native_reference']);$id=Id::uuid();$wait=0;
        if($action==='cloud-action'&&$p['kind']==='TERMINATE'){$i=$this->commands->instance($p['service_id'],$customer);$wait=(new \Hostider\Cloud\Catalog($c->db,$c->config))->find($i['catalog_id'])['policy']['termination_wait_seconds'];}
        $c->db->execute('INSERT INTO panel_confirmations(id,actor_id,session_id,identity_version,customer_id,service_id,action,payload_hmac,state_hmac,native_confirmation,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP(6)))',[$id,$u['id'],$s['id'],$u['identity_version'],$customer,$p['service_id'],$action,$this->commands->hash($p),$this->commands->hash($state),$native,$wait+300]);$c->identity->confirmationEvent($u['id'],$id,false);
        return ['id'=>$id,'action'=>$action,'wait_seconds'=>$wait,'expires_in'=>$wait+300,'plan'=>['service_id'=>$p['service_id'],'service_state'=>$state['service']['status'],'action'=>$action,'kind'=>$p['kind']??null,'outcome'=>$p['outcome']??null,'evidence'=>$state['evidence']??null,'provenance'=>'MOCK','irreversible'=>$action==='cloud-action'&&in_array($p['kind'],['REINSTALL','TERMINATE'],true),'provider_cost_ends'=>false,'effect'=>'Kalıcı mock işlem kaydı; sağlayıcı kanıtı olmadan başarı sayılmaz.']];
    }
    public function execute(array $u,array $s,string $action,array $p,string $customer,array $confirmation,string $trace): array
    {
        $c=$this->commands->c;$a=$this->commands->authorize($u,$s,$action,$p,$customer);
        if(array_diff(array_keys($confirmation),['id','accepted'])||($confirmation['accepted']??null)!==true){throw new HttpError(403,'EXPLICIT_PANEL_CONFIRMATION_REQUIRED');}Access::id($confirmation['id']??null);
        $r=$c->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) AS fresh FROM panel_confirmations WHERE id=? FOR UPDATE',[$confirmation['id']])->fetch();
        if(!$r||$r['actor_id']!==$u['id']||$r['session_id']!==$s['id']||(string)$r['identity_version']!==(string)$u['identity_version']||$r['customer_id']!==$customer||$r['service_id']!==$p['service_id']||$r['action']!==$action||!hash_equals($r['payload_hmac'],$this->commands->hash($p))){throw new HttpError(403,'PANEL_CONFIRMATION_MISMATCH');}
        if(!$r['fresh']||$r['used_at']!==null){throw new HttpError(409,'PANEL_CONFIRMATION_EXPIRED_OR_USED');}
        if(!hash_equals($r['state_hmac'],$this->commands->hash($this->commands->state($p,$customer,$u,$s)))){throw new HttpError(409,'PANEL_STATE_CHANGED_REVIEW_AGAIN');}
        $result=$this->commands->execute($action,$p,$customer,$a,$u,$s,$r['native_confirmation'],$trace);$c->db->execute('UPDATE panel_confirmations SET used_at=UTC_TIMESTAMP(6) WHERE id=?',[$r['id']]);$c->identity->confirmationEvent($u['id'],$r['id'],true);return $result;
    }
}
