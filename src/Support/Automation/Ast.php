<?php
declare(strict_types=1);
namespace Hostider\Support\Automation;
use Hostider\Core\Validation;
use Hostider\Core\Http\HttpError;
use Hostider\Support\Advanced\Common;
use Hostider\Support\Access;
use Hostider\Support\Work\Policy;
final class Ast
{
    public const TRIGGERS=['ticket_created','customer_replied','staff_replied','state_changed','assigned','sla_risk','incident_updated'];
    public const FIELDS=['department','channel','priority','tag','business_hours','customer_group','service_type','service_status','assigned','sla'];
    public static function rule(array $s): array
    {
        Validation::fields($s,['v','trigger','when','actions','enabled','order'],['v','trigger','when','actions','enabled','order']);if($s['v']!==1||!in_array($s['trigger'],self::TRIGGERS,true)||!is_bool($s['enabled'])){throw new HttpError(422,'RULE_SPEC_INVALID');}Policy::integer($s['order'],0,1000);$nodes=0;self::node(Common::object($s['when']),1,$nodes);if(!is_array($s['actions'])||!array_is_list($s['actions'])||count($s['actions'])<1||count($s['actions'])>4){throw new HttpError(422,'RULE_ACTION_LIMIT');}foreach($s['actions'] as $a){self::action(Common::object($a));if($s['trigger']==='incident_updated'&&$a['kind']==='state'&&in_array($a['value'],['RESOLVED','CLOSED'],true)){throw new HttpError(422,'INCIDENT_CANNOT_RESOLVE_TICKETS');}}return $s;
    }
    private static function node(array $n,int $depth,int &$count): void
    {
        if($depth>4||++$count>32){throw new HttpError(422,'RULE_AST_LIMIT');}
        if(isset($n['all'])||isset($n['any'])){$k=isset($n['all'])?'all':'any';Validation::fields($n,[$k],[$k]);if(!is_array($n[$k])||!array_is_list($n[$k])||count($n[$k])<1||count($n[$k])>8){throw new HttpError(422,'RULE_AST_INVALID');}foreach($n[$k] as $child){self::node(Common::object($child),$depth+1,$count);}return;}
        if(isset($n['not'])){Validation::fields($n,['not'],['not']);self::node(Common::object($n['not']),$depth+1,$count);return;}
        Validation::fields($n,['field','op','value'],['field','op','value']);if(!in_array($n['field'],self::FIELDS,true)||!in_array($n['op'],['eq','in'],true)){throw new HttpError(422,'RULE_PREDICATE_INVALID');}$values=$n['op']==='in'?$n['value']:[$n['value']];if(!is_array($values)||!array_is_list($values)||count($values)<1||count($values)>8){throw new HttpError(422,'RULE_VALUE_INVALID');}foreach($values as $v){self::value($n['field'],$v);}
    }
    private static function value(string $field,mixed $v): void
    {
        if($field==='business_hours'){if(!is_bool($v)){throw new HttpError(422,'RULE_VALUE_INVALID');}return;}if($field==='assigned'){if($v!==null){Access::id($v);}return;}if($field==='department'){Access::id($v);return;}
        Common::text($v,48);$allowed=match($field){'priority'=>['LOW','NORMAL','HIGH','CRITICAL'],'channel'=>['PANEL','TEST_ADAPTER'],'sla'=>['UNAVAILABLE','UNCONFIGURED','RUNNING','PAUSED','COMPLETED','BREACH','RISK','CANCELLED'],default=>null};if($allowed!==null&&!in_array($v,$allowed,true)){throw new HttpError(422,'RULE_VALUE_INVALID');}if($field==='tag'){self::tag($v);}
    }
    public static function tag(mixed $v): string{if(!is_string($v)||!preg_match('/^[a-z0-9][a-z0-9_-]{0,47}$/D',$v)){throw new HttpError(422,'RULE_TAG_INVALID');}return $v;}
    public static function action(array $a): void
    {
        Validation::fields($a,['kind','value'],['kind','value']);if(!is_string($a['kind'])){throw new HttpError(422,'RULE_ACTION_INVALID');}switch($a['kind']){case 'assign':if($a['value']!==null){Access::id($a['value']);}break;case 'state':if(!in_array($a['value'],['WAITING_STAFF','WAITING_CUSTOMER','ON_HOLD','RESOLVED','CLOSED'],true)){throw new HttpError(422,'RULE_STATE_INVALID');}break;case 'priority':self::value('priority',$a['value']);break;case 'tag':self::tag($a['value']);break;case 'note':Common::text($a['value'],2000);if(str_contains($a['value'],'{{')||str_contains($a['value'],'${')){throw new HttpError(422,'RULE_TEMPLATE_UNSUPPORTED');}break;case 'notify':if($a['value']!=='HELD'){throw new HttpError(422,'RULE_NOTIFICATION_HELD_ONLY');}break;case 'incident':Access::id($a['value']);break;default:throw new HttpError(422,'RULE_ACTION_UNSUPPORTED');}
    }
    /** Three-valued evaluation; unavailable input cannot become true through NOT. */
    public static function evaluate(array $n,array $facts): string
    {
        foreach(['all','any'] as $k){if(isset($n[$k])){$r=array_map(fn($x)=>self::evaluate($x,$facts),$n[$k]);if($k==='all'){return in_array('NO_MATCH',$r,true)?'NO_MATCH':(in_array('UNAVAILABLE',$r,true)?'UNAVAILABLE':'MATCH');}return in_array('MATCH',$r,true)?'MATCH':(in_array('UNAVAILABLE',$r,true)?'UNAVAILABLE':'NO_MATCH');}}
        if(isset($n['not'])){$r=self::evaluate($n['not'],$facts);return $r==='UNAVAILABLE'?$r:($r==='MATCH'?'NO_MATCH':'MATCH');}
        if(!array_key_exists($n['field'],$facts)||$facts[$n['field']]==='__UNAVAILABLE'){return 'UNAVAILABLE';}$v=$facts[$n['field']];$want=$n['op']==='in'?$n['value']:[$n['value']];$match=is_array($v)?array_intersect($v,$want)!==[]:in_array($v,$want,true);return $match?'MATCH':'NO_MATCH';
    }
}
