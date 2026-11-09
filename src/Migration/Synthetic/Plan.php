<?php
declare(strict_types=1);
namespace Hostider\Migration\Synthetic;
use Hostider\Migration\{Contract, Entity, MappingEvidence, SourceEnvelope, SourceIdentity, SnapshotValidator};

final readonly class Plan
{
    private function __construct(public array $items, public string $hash, public array $reasons, public array $counts) {}
    public static function build(Fixture $fixture): self
    {
        try {
            $items=$fixture->envelopes(); $report=(new SnapshotValidator())->validate($items);
            if (!$report->eligible()) { return new self([], $report->snapshotFingerprint, $report->reasons, []); }
            $remaining=[]; foreach ($items as $e) { $remaining[$e->identity->key()]=$e; } ksort($remaining,SORT_STRING);
            $ordered=[]; $done=[]; $counts=[];
            while ($remaining !== []) {
                $progress=false;
                foreach ($remaining as $key=>$e) {
                    if (array_diff(self::dependencies($e),array_keys($done)) !== []) { continue; }
                    $ordered[]=$e; $done[$key]=true; unset($remaining[$key]); $progress=true;
                    $type=$e->identity->entity->value; $counts[$type]=($counts[$type]??0)+1;
                }
                Contract::require($progress,'F6B_DEPENDENCY_CYCLE');
            }
            ksort($counts,SORT_STRING);
            return new self($ordered,Contract::hash(['f6b.v1',$report->snapshotFingerprint,array_map(static fn(SourceEnvelope $e): string=>$e->identity->key(),$ordered)]),[],$counts);
        } catch (\InvalidArgumentException|\JsonException|\OverflowException $error) {
            $code=preg_match('/^[A-Z0-9_]{1,80}$/D',$error->getMessage())===1 ? $error->getMessage() : 'F6B_CONTRACT_REJECTED';
            return new self([],Contract::hash(['REVIEW',$fixture->digest(),$code]),[$code],[]);
        }
    }
    public static function target(SourceEnvelope $e): string { return Contract::hash(['f6b-target.v1',$e->identity->key()]); }
    /** Bind the entire immutable source snapshot before any prefix can commit. */
    public function snapshots(): array
    {
        $groups=[];
        foreach($this->items as $e){$key=Contract::hash(['f6b-source-snapshot.v1',$e->identity->instance,$e->metadata['snapshot']]);$groups[$key][$e->identity->key()]=$e->fingerprint;}
        $roots=[];foreach($groups as $key=>$members){ksort($members,SORT_STRING);$roots[$key]=Contract::hash($members);}ksort($roots,SORT_STRING);return $roots;
    }
    public static function dependencies(SourceEnvelope $e): array
    {
        $p=$e->payload; $refs=[];
        if(isset($p['customer'])){$refs[]=new SourceIdentity($e->identity->instance,Entity::Customer,$p['customer']);}
        $fields=match($e->identity->entity){
            Entity::Membership=>['user'=>Entity::User], Entity::Service=>['order'=>Entity::Order], Entity::Resource=>['service'=>Entity::Service],
            Entity::Allocation=>['payment'=>Entity::Payment,'order'=>Entity::Order], Entity::Refund=>['payment'=>Entity::Payment], Entity::Document=>['order'=>Entity::Order],
            Entity::Ticket=>['requester'=>Entity::User], Entity::Message=>['ticket'=>Entity::Ticket,'author'=>Entity::User], Entity::Attachment=>['ticket'=>Entity::Ticket,'message'=>Entity::Message], default=>[]};
        foreach($fields as $field=>$entity){$refs[]=new SourceIdentity($e->identity->instance,$entity,$p[$field]);}
        return array_map(static fn(SourceIdentity $i): string=>$i->key(),$refs);
    }
    public static function claims(SourceEnvelope $e): array
    {
        $p=$e->payload;
        $claims=match($e->identity->entity){
            Entity::User=>['EMAIL'=>[strtolower($p['email'])]],
            Entity::Resource=>['EXTERNAL_RESOURCE'=>[$p['provider'],$p['account'],$p['external_id']]],
            Entity::Payment=>['EXTERNAL_PAYMENT'=>[$p['external_ref']]],
            Entity::Wallet=>['WALLET_OWNER'=>[$e->identity->instance,$p['customer']]],
            Entity::Membership=>['MEMBERSHIP'=>[$e->identity->instance,$p['customer'],$p['user']]],
            default=>[]};
        $result=[];foreach($claims as $kind=>$tuple){$result[$kind]=Contract::hash($tuple);}return $result;
    }
    public function manifest(string $mode, array $reasons=[]): array
    {
        $all=array_values(array_unique([...$this->reasons,...$reasons]));sort($all,SORT_STRING);
        $proposals=[];foreach($this->items as $e){$mapping=MappingEvidence::propose($e,$e->identity->entity->value,self::target($e));$proposals[$e->identity->key()]=['target'=>self::target($e),'evidence'=>$mapping->data['evidence_sha256'],'dependencies'=>self::dependencies($e)];}ksort($proposals,SORT_STRING);
        return ['classification'=>'SYNTHETIC','mode'=>$mode,'status'=>$all===[]?'PLANNED':'REVIEW_REQUIRED','plan_hash'=>$this->hash,
            'counts'=>$this->counts,'mapping_plan'=>['records'=>count($proposals),'sha256'=>Contract::hash($proposals)],'source_snapshot_roots'=>$this->snapshots(),'reasons'=>$all,'effects_allowed'=>false,'imported_records'=>0];
    }
}
