<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final class Report implements \JsonSerializable
{
    private array $checks=[];
    private const REASONS=['OBSERVED','MISSING','UNVERIFIED','UNSAFE','ACCESS_UNAVAILABLE','DENIED','VERSION_MISMATCH','NO_CONNECTION','DEPENDENCY_MISSING','OPERATOR_DECLARATION'];
    public function add(string $code,string $status,string $reason): void
    {
        $defs=Catalog::definitions();
        if(!isset($defs[$code])||isset($this->checks[$code])||!in_array($status,['PASS','FAIL','UNKNOWN','NOT_APPLICABLE'],true)||!in_array($reason,self::REASONS,true)){throw new \InvalidArgumentException('Preflight check contract');}
        [$feature,$class,$source,$description,$action]=$defs[$code];
        $this->checks[$code]=['check_code'=>$code,'feature'=>$feature,'classification'=>$class,'status'=>$status,'reason'=>$reason,'explanation'=>$description,'operator_action'=>$action,'source'=>$source,'verification'=>$status==='UNKNOWN'?'UNVERIFIED':'LOCAL_OBSERVATION_ONLY'];
    }
    public function jsonSerialize(): array
    {
        $defs=Catalog::definitions();if(count($this->checks)!==count($defs)){throw new \LogicException('Incomplete check inventory');}
        $checks=[];$requiredFail=false;$requiredUnknown=false;
        foreach($defs as $code=>$def){$c=$this->checks[$code]??throw new \LogicException('Missing check');$checks[]=$c;if($c['classification']==='OPERATIONAL_VALIDATION'&&$c['status']==='FAIL'){$requiredFail=true;}if($c['classification']==='REQUIRED'){$requiredFail=$requiredFail||$c['status']==='FAIL';$requiredUnknown=$requiredUnknown||$c['status']!=='PASS';}}
        $overall=$requiredFail?'PREFLIGHT_BLOCKED':($requiredUnknown?'PREFLIGHT_INCOMPLETE':'PREFLIGHT_PASS_WITHIN_CHECKED_SCOPE');
        $features=[];foreach(['core','identity','queue','documents','domains','offline_backup','source_discovery'] as $feature){$subset=array_filter($checks,fn($v)=>$v['feature']===$feature&&$v['classification']!=='OPERATIONAL_VALIDATION');$fail=$requiredFail;$unknown=$requiredUnknown;foreach($subset as $c){$fail=$fail||$c['status']==='FAIL';$unknown=$unknown||$c['status']!=='PASS';}$features[$feature]=$fail?'BLOCKED_BY_OBSERVED_PREREQUISITE':($unknown?'UNVERIFIED':'PREREQUISITES_OBSERVED_ONLY');}
        return ['format_version'=>1,'scope'=>'LOCAL_CLI_AND_READ_ONLY_METADATA_ONLY','status'=>$overall,'checks'=>$checks,'feature_prerequisites'=>$features,'gates'=>['G-O01'=>'OPEN','G-O02'=>'OPEN','OPS-002'=>'OPEN','OPS-003'=>'OPEN'],'production_allowed'=>false,'effects_allowed'=>false,'real_gate_status'=>'BLOCKED'];
    }
    public static function denied(): array
    {
        return ['format_version'=>1,'scope'=>'LOCAL_CLI_AND_READ_ONLY_METADATA_ONLY','status'=>'PREFLIGHT_BLOCKED','checks'=>[['check_code'=>'INPUT_CONTRACT','feature'=>'preflight','classification'=>'REQUIRED','status'=>'FAIL','reason'=>'UNSAFE','explanation'=>'Private input or runtime contract unavailable.','operator_action'=>'Review the documented private descriptor and CLI contract; no error details are emitted.']],'gates'=>['G-O01'=>'OPEN','G-O02'=>'OPEN','OPS-002'=>'OPEN','OPS-003'=>'OPEN'],'production_allowed'=>false,'effects_allowed'=>false,'real_gate_status'=>'BLOCKED'];
    }
}
