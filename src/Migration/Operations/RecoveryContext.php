<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;
use Hostider\Core\Security\Crypto;
use Hostider\Migration\{Contract,MappingEvidence};
use Hostider\Migration\Reconciliation\Finance;

/** Private immutable reference; no conversion to native original/runtime authority. */
final readonly class RecoveryContext
{
    private function __construct(private array $reference,private array $meta,private RecoveryAuthorization $authority,public array $verified,public string $attestationFingerprint) {}
    public static function open(#[\SensitiveParameter] string $capsule,#[\SensitiveParameter] Crypto $crypto,RecoveryAuthorization $authority): self
    {
        $verified=ColdRecovery::verify($capsule,$crypto,$authority);$outer=json_decode($capsule,true,12,JSON_THROW_ON_ERROR);
        $plain=$crypto->decrypt($outer['ciphertext'],'f6d-backup:'.$verified['backup_id']);$v=json_decode($plain,true,32,JSON_THROW_ON_ERROR);
        Contract::require(Contract::hash($v['data'])===$verified['backup_id'],'R2_REFERENCE_INTEGRITY');$authority->check($outer['attestation']);
        return new self($v['data'],$outer['attestation'],$authority,$verified,Contract::hash($outer['attestation']));
    }
    public function fence(): void {$this->authority->check($this->meta);}
    public function columns(string $table): array {return array_keys($this->reference['tables'][$table][0]);}
    public function schemaDigest(): string {return Contract::hash($this->reference['schema']);}
    public function compare(array $tables,RecoveryTargetPolicy $target): array
    {
        $this->fence();$target->check($this->verified['backup_id']);$expected=$this->reference['tables'];$reasons=[];$counts=[];
        foreach($expected as $table=>$rows){$left=array_map(Contract::hash(...),$rows);$right=array_map(Contract::hash(...),$tables[$table]);$match=0;foreach($left as $hash){$i=array_search($hash,$right,true);if($i!==false){++$match;array_splice($right,$i,1);}}
            $counts[$table]=['source'=>count($rows),'target'=>count($tables[$table]),'matched'=>$match,'unmatched'=>count($rows)-$match,'extra'=>count($right)];if($match!==count($rows)||$right!==[]){$reasons[]=strtoupper($table).'_MISMATCH';}}
        $ownership=false;$privacy=false;$owners=[];foreach($expected['f6b_entities'] as $row){$owners[$row['source_key']]=$row;}
        foreach($tables['f6b_entities'] as $row){$old=$owners[$row['source_key']]??null;if(!$old){continue;}if($row['owner_key']!==$old['owner_key']){$ownership=true;}
            try{$a=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);$b=json_decode($old['payload_json'],true,32,JSON_THROW_ON_ERROR);
                if(($a['customer']??null)!==($b['customer']??null)){$ownership=true;}if($row['entity_type']==='message'&&[($a['kind']??null),($a['visibility']??null)]!==[($b['kind']??null),($b['visibility']??null)]){$privacy=true;}}
            catch(\Throwable){$privacy=true;}}
        $facts=[];$identities=[];$finance=['status'=>'NOT_AVAILABLE'];
        try{foreach($tables['f6b_mappings'] as $row){$m=MappingEvidence::fromArray(json_decode($row['evidence_json'],true,32,JSON_THROW_ON_ERROR));$identities[$row['source_key']]=$m->data;}
            foreach($tables['f6b_entities'] as $row){$m=$identities[$row['source_key']]??null;Contract::require($m!==null,'R2_MAPPING_MISSING');$facts[$row['source_key']]=['type'=>$row['entity_type'],'payload'=>json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR),'instance'=>$m['source_instance'],'id'=>$m['source_id']];}
            $actual=Finance::totals($facts);$source=$this->reference['report']['finance']['source'];$finance=['source'=>$source,'target'=>$actual,'equal'=>Contract::hash($source)===Contract::hash($actual),'differences_by_currency'=>Finance::differences($source,$actual)];if(!$finance['equal']){$reasons[]='FINANCE_MISMATCH';}}
        catch(\Throwable){$reasons[]='FINANCE_UNVERIFIABLE';}
        if($ownership){$reasons[]='CUSTOMER_OR_ATTACHMENT_OWNERSHIP_MISMATCH';}if($privacy){$reasons[]='PRIVATE_VISIBILITY_MISMATCH';}
        $root=Contract::hash($tables);if($root!==$this->verified['logical_sha256']){$reasons[]='EVIDENCE_ROOT_MISMATCH';}
        sort($reasons);$status=($ownership||$privacy)?'BLOCKED':($reasons===[]?'PASS':'REVIEW_REQUIRED');$public=0;$private=0;$attachments=0;
        foreach($facts as $f){if($f['type']==='message'){if(($f['payload']['kind']??null)==='PUBLIC_REPLY'&&($f['payload']['visibility']??null)===['state'=>'KNOWN','value'=>'PUBLIC']){++$public;}else{++$private;}}if($f['type']==='attachment'){++$attachments;}}
        return ['classification'=>'SYNTHETIC','contract'=>'hostider.readonly-recovery.v1','status'=>$status==='PASS'?'MATCHED_REFERENCE':($status==='REVIEW_REQUIRED'?'REFERENCE_DIFFERENCES':'BLOCKED'),'verification_scope'=>'REFERENCE_COMPARISON_ONLY_NO_DB_AUTHORITY','backup_id'=>$this->verified['backup_id'],
            'recovery_attestation_sha256'=>$this->attestationFingerprint,'source_roots_sha256'=>$this->verified['source_roots_sha256'],'original_target_snapshot'=>$this->verified['original_target_snapshot'],
            'target_policy_sha256'=>$target->fingerprint($this->verified['logical_sha256']),'candidate_evidence_sha256'=>$root,'source_target_counts'=>$counts,
            'mapping_provenance_checkpoint_sha256'=>Contract::hash([$tables['f6b_mappings'],$tables['f6b_provenance'],$tables['f6b_runs'],$tables['f6b_history']]),'immutable_evidence'=>$root===$this->verified['logical_sha256']?'MATCHED':'DIFFERENCES',
            'finance'=>$finance,'support'=>['public_messages'=>$public,'private_or_unverified_messages'=>$private,'attachments'=>$attachments,'projection'=>'AGGREGATE_NO_CLIENT_ACCESS'],
            'original_hmac'=>'PRESERVED_ENROLMENT_EVIDENCE_NOT_RECOMPUTED','runtime_authority'=>'NOT_GRANTED','native_application_restore'=>'BLOCKED',
            'provider_operations'=>['CREATE'=>0,'RENEW'=>0,'TERMINATE'=>0],'native_writes'=>0,'effects_allowed'=>false,'reasons'=>$reasons,
            'original_archive_bytes'=>'NOT_VERIFIED','production_restore'=>'NOT_VERIFIED','key_custody'=>'NOT_VERIFIED'];
    }
}
