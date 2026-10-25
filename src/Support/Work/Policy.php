<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Core\{Validation,CanonicalJson};
use Hostider\Core\Http\HttpError;
final class Policy
{
    public const PRIORITIES=['LOW','NORMAL','HIGH','CRITICAL'];
    public const METRICS=['FIRST','NEXT','RESOLUTION'];
    public static function array(mixed $v): array{if($v instanceof \stdClass){$v=json_decode(CanonicalJson::encode($v),true,32,JSON_THROW_ON_ERROR);}if(!is_array($v)){throw new HttpError(422,'WORK_OBJECT_REQUIRED');}return $v;}
    public static function integer(mixed $v,int $min=1,int $max=525600): int{if(!is_int($v)||$v<$min||$v>$max){throw new HttpError(422,'INVALID_WORK_INTEGER');}return $v;}
    public static function validate(array $p): array
    {
        Validation::fields($p,['calendar','targets','pause','risk_percent','assignment'],['calendar','targets','pause','risk_percent','assignment']);$p['calendar']=(new Calendar(self::array($p['calendar'])))->spec;
        $p['targets']=self::array($p['targets']);if(array_diff(self::PRIORITIES,array_keys($p['targets']))||count($p['targets'])!==4){throw new HttpError(422,'EXPLICIT_PRIORITY_TARGETS_REQUIRED');}
        foreach($p['targets'] as &$targets){$targets=self::array($targets);Validation::fields($targets,self::METRICS,self::METRICS);foreach($targets as $v){self::integer($v);}}unset($targets);
        $p['pause']=self::array($p['pause']);Validation::fields($p['pause'],self::METRICS,self::METRICS);foreach($p['pause'] as $states){if(!is_array($states)||!array_is_list($states)||count($states)!==count(array_unique($states,SORT_REGULAR))||array_filter($states,static fn(mixed $v): bool=>!is_string($v)||!in_array($v,['ON_HOLD','WAITING_CUSTOMER'],true))){throw new HttpError(422,'INVALID_SLA_PAUSE');}}
        self::integer($p['risk_percent'],1,99);$p['assignment']=self::array($p['assignment']);Validation::fields($p['assignment'],['mode','weights'],['mode','weights']);if(!in_array($p['assignment']['mode'],['MANUAL','ROUND_ROBIN','LEAST_LOAD'],true)){throw new HttpError(422,'INVALID_ASSIGNMENT_MODE');}$p['assignment']['weights']=self::array($p['assignment']['weights']);Validation::fields($p['assignment']['weights'],self::PRIORITIES,self::PRIORITIES);foreach($p['assignment']['weights'] as $v){self::integer($v,1,1000);}return $p;
    }
    public static function roster(array $r): array
    {
        Validation::fields($r,['calendar','available','leaves','priorities','expires_at'],['calendar','available','leaves','priorities','expires_at']);$r['calendar']=(new Calendar(self::array($r['calendar'])))->spec;
        if(!is_bool($r['available'])||!is_array($r['leaves'])||!array_is_list($r['leaves'])||count($r['leaves'])>100||!is_array($r['priorities'])||!array_is_list($r['priorities'])||array_filter($r['priorities'],static fn(mixed $v): bool=>!is_string($v)||!in_array($v,self::PRIORITIES,true))||count($r['priorities'])!==count(array_unique($r['priorities'],SORT_REGULAR))||!is_string($r['expires_at'])){throw new HttpError(422,'INVALID_ROSTER');}Time::parse($r['expires_at']);foreach($r['leaves'] as $l){if(!is_array($l)||array_keys($l)!==[0,1]||!is_string($l[0])||!is_string($l[1])||Time::parse($l[0])>=Time::parse($l[1])){throw new HttpError(422,'INVALID_LEAVE_INTERVAL');}}return $r;
    }
}
