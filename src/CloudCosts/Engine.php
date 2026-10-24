<?php
declare(strict_types=1);
namespace Hostider\CloudCosts;
use Hostider\Core\{Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Queue\{Outbox,Queue};use Hostider\Finance\{Currency,Decimal,Money,ProviderCosts};
final readonly class Engine
{
    private Store $store;
    public function __construct(private Database $db,private Config $config,private ?\DateTimeImmutable $clock=null){$this->store=new Store($db,$config);}
    private function now(): \DateTimeImmutable{return $this->clock??new \DateTimeImmutable('now',new \DateTimeZone('UTC'));}
    public function submit(string $instance,string $usageReference): string
    {
        if(!preg_match('/^[A-Za-z0-9:_-]{1,128}$/D',$usageReference)){throw new HttpError(422,'CLOUD_USAGE_REFERENCE_INVALID');}
        return LocalTransactions::run($this->db,function()use($instance,$usageReference): string{
            $i=$this->store->instance($instance);$input=['instance_id'=>$instance,'account_id'=>$i['account_id'],'usage_ref'=>$usageReference];$hash=$this->store->hash($input);
            $old=$this->db->execute('SELECT * FROM cloud_cost_work WHERE account_id=? AND usage_ref=? FOR UPDATE',[$i['account_id'],$usageReference])->fetch();
            if($old){if(!hash_equals($old['fingerprint'],$hash)){throw new HttpError(409,'CLOUD_USAGE_REFERENCE_CONFLICT');}return $old['id'];}
            $id=Id::uuid();$this->db->execute('INSERT INTO cloud_cost_work(id,instance_id,account_id,usage_ref,fingerprint) VALUES (?,?,?,?,?)',[$id,$instance,$i['account_id'],$usageReference,$hash]);return $id;
        });
    }
    private function hold(array $w,string $status,string $code): void{$this->db->execute('UPDATE cloud_cost_work SET status=?,safe_code=?,checked_at=UTC_TIMESTAMP(6) WHERE id=?',[$status,$code,$w['id']]);}
    private function event(string $account,string $kind,string $reference): ?array
    {
        $e=$this->db->execute('SELECT * FROM mock_cloud_meter_events WHERE account_id=? AND kind=? AND provider_ref=?',[$account,$kind,$reference])->fetch();if(!$e){return null;}$e['payload']=$this->store->decrypt($e['payload_encrypted'],$e['fingerprint'],'cloud-meter:'.$e['id']);return $e;
    }
    public function once(?string $id=null,?string $before=null): bool
    {
        return LocalTransactions::run($this->db,function()use($id,$before): bool{
            $w=$this->db->execute("SELECT * FROM cloud_cost_work ".($id?'':'FORCE INDEX(ix_cloud_cost_admission) ')."WHERE admissible=TRUE".($id?' AND id=?':'').($before?' AND (checked_at IS NULL OR checked_at<?)':'').' ORDER BY checked_at,created_at,id LIMIT 1 FOR UPDATE SKIP LOCKED',array_merge($id?[$id]:[],$before?[$before]:[]))->fetch();if(!$w){return false;}
            $i=$this->store->instance($w['instance_id']);if(!hash_equals($w['fingerprint'],$this->store->hash(['instance_id'=>$w['instance_id'],'account_id'=>$w['account_id'],'usage_ref'=>$w['usage_ref']]))){$this->hold($w,'REVIEW','CLOUD_COST_WORK_INTEGRITY');return true;}if(!$this->store->ondemand($i)){$this->hold($w,'REVIEW','CLOUD_ONDEMAND_HOURLY_REQUIRED');return true;}$proof=$this->store->binding($i);
            $event=$this->event($i['account_id'],'USAGE',$w['usage_ref']);if(!$event){$this->hold($w,'UNVERIFIED','METERING_REQUIRED');return true;}
            if(!$proof){$this->hold($w,'UNVERIFIED','RESOURCE_PROOF_REQUIRED');return true;}$u=$event['payload'];
            try{
                if($w['account_id']!==$i['account_id']||$u['instance_id']!==$i['id']||$u['resource_ref']!==$proof['provider_resource_ref']||$u['task_ref']!==$proof['provider_task_ref']||$u['provenance']!=='MOCK'){throw new HttpError(409,'CLOUD_METER_SOURCE_MISMATCH');}
                $tariff=(new Tariffs($this->db,$this->config))->find($u['tariff_id']);if(!$tariff){$this->hold($w,'UNVERIFIED','TARIFF_REQUIRED');return true;}$p=$tariff['policy'];
                if($tariff['account_id']!==$i['account_id']||$p['spec_hash']!==$i['fingerprint']){throw new HttpError(409,'CLOUD_TARIFF_SOURCE_MISMATCH');}
                $start=Store::date($u['starts_at']);$end=Store::date($u['ends_at']);$observed=Store::date($u['observed_at']);$now=$this->now();
                if($end<=$start||$end>$observed||$observed>$now||$start<Store::date($p['valid_from'])||$end>Store::date($p['valid_until'])||$observed->getTimestamp()<$now->getTimestamp()-$p['max_age_seconds']){throw new HttpError(409,'CLOUD_METER_TIME_OR_STALE');}
                if($p['fx']!==null){$fxTime=Store::date($p['fx']['observed_at']);if($fxTime>$observed||$fxTime->getTimestamp()<$now->getTimestamp()-$p['max_age_seconds']){$this->hold($w,'UNVERIFIED','FX_EVIDENCE_STALE');return true;}}
                if($this->db->execute('SELECT 1 FROM cloud_usage_costs WHERE instance_id=? AND starts_at<? AND ends_at>? LIMIT 1',[$i['id'],$u['ends_at'],$u['starts_at']])->fetchColumn()){throw new HttpError(409,'CLOUD_USAGE_OVERLAP');}
                $last=$this->db->execute('SELECT sequence_no,ends_at FROM cloud_usage_costs WHERE instance_id=? ORDER BY sequence_no DESC LIMIT 1',[$i['id']])->fetch();$next=$last?(int)$last['sequence_no']+1:1;
                if(!is_int($u['sequence'])||$u['sequence']<1||$u['sequence']<$next||($last&&$start<Store::date($last['ends_at']))){throw new HttpError(409,'CLOUD_USAGE_SEQUENCE_INVALID');}
                if($u['sequence']>$next){$this->hold($w,'UNVERIFIED','USAGE_SEQUENCE_GAP');return true;}
                $calc=Calculator::calculate($p,$end->getTimestamp()-$start->getTimestamp(),$u['addons'],$u['fees']);
            }catch(\PDOException $e){throw $e;}catch(\Throwable $e){$this->hold($w,'REVIEW',$e instanceof HttpError?$e->errorCode:'CLOUD_METER_INPUT_INVALID');return true;}
            $calc['source']=['meter_event_id'=>$event['id'],'usage_ref'=>$w['usage_ref'],'tariff_id'=>$tariff['id'],'resource_ref'=>$proof['provider_resource_ref'],'task_ref'=>$proof['provider_task_ref'],'starts_at'=>$u['starts_at'],'ends_at'=>$u['ends_at'],'observed_at'=>$u['observed_at'],'sequence'=>$u['sequence']];
            $journal=(new ProviderCosts($this->db,$this->config))->accrueMock($w['id'],new Money($calc['total_minor'],new Currency($calc['currency'])));
            $this->db->execute("INSERT INTO cloud_usage_costs(id,instance_id,account_id,resource_id,meter_event_id,tariff_id,usage_ref,sequence_no,starts_at,ends_at,currency,total_minor,calculation_encrypted,fingerprint,journal_id,provenance) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'MOCK')",[$w['id'],$i['id'],$i['account_id'],$i['resource_id'],$event['id'],$tariff['id'],$w['usage_ref'],$u['sequence'],$u['starts_at'],$u['ends_at'],$calc['currency'],$calc['total_minor'],$this->store->encrypt($calc,'cloud-cost:'.$w['id']),$this->store->hash($calc),$journal]);
            $this->hold($w,'COSTED_MOCK','MOCK_METER_TARIFF_VERIFIED');(new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'cloud-cost.recorded',$w['id'],['cost_id'=>$w['id']],Id::uuid());return true;
        });
    }
    public function run(int $maximum=100,int $seconds=20): array
    {
        if($maximum<1||$maximum>100||$seconds<1||$seconds>20){throw new \InvalidArgumentException('Invalid cost worker budget');}$start=hrtime(true);$n=0;$before=$this->db->execute('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
        while($n<$maximum&&hrtime(true)-$start<$seconds*1_000_000_000&&memory_get_usage(true)<64*1024*1024&&$this->once(before:$before)){++$n;}return ['checked'=>$n,'max'=>$maximum,'budget_seconds'=>$seconds,'db_retry_counts'=>LocalTransactions::counters()];
    }
    /** Trusted internal evidence gateway; no customer projection is exposed. */
    public function cost(string $id): array
    {
        $r=$this->db->execute('SELECT * FROM cloud_usage_costs WHERE id=?',[$id])->fetch();if(!$r){throw new HttpError(409,'CLOUD_COST_UNVERIFIED');}$r['calculation']=$this->store->decrypt($r['calculation_encrypted'],$r['fingerprint'],'cloud-cost:'.$id);$r['instance']=$this->store->instance($r['instance_id'],false);if(!$this->store->ondemand($r['instance'])){throw new HttpError(409,'CLOUD_ONDEMAND_HOURLY_REQUIRED');}return $r;
    }
    public function reconcile(string $instance,string $invoiceReference): array
    {
        return LocalTransactions::run($this->db,function()use($instance,$invoiceReference): array{
            $i=$this->store->instance($instance);if(!$this->store->ondemand($i)){return ['status'=>'REVIEW','reason'=>'CLOUD_ONDEMAND_HOURLY_REQUIRED'];}$event=$this->event($i['account_id'],'INVOICE',$invoiceReference);if(!$event){return ['status'=>'UNVERIFIED','reason'=>'INVOICE_EVIDENCE_REQUIRED'];}
            $old=$this->db->execute("SELECT * FROM cloud_cost_reconciliations WHERE instance_id=? AND invoice_event_id=? AND status='MATCHED_MOCK' LIMIT 1",[$instance,$event['id']])->fetch();if($old){return ['id'=>$old['id'],'status'=>$old['status']];}
            $dependencies=[];foreach(array_slice(is_array($event['payload']['usage_refs'])?$event['payload']['usage_refs']:[],0,100) as $ref){if(is_string($ref)){$dependencies[]=$this->db->execute('SELECT id,usage_ref,currency,total_minor,starts_at,ends_at FROM cloud_usage_costs WHERE instance_id=? AND usage_ref=?',[$instance,$ref])->fetch();}}
            $basis=$this->store->hash(['resource'=>$i['resource_id'],'proof'=>$this->store->binding($i),'costs'=>$dependencies]);
            $old=$this->db->execute('SELECT * FROM cloud_cost_reconciliations WHERE instance_id=? AND invoice_event_id=? AND basis_hash=?',[$instance,$event['id'],$basis])->fetch();if($old){return ['id'=>$old['id'],'status'=>$old['status']];}
            $u=$event['payload'];$proof=$this->store->binding($i);$status='REVIEW';$reason='INVOICE_SOURCE_MISMATCH';$costs=[];$sum='0';$delta=null;
            try{
                if(!$proof||$u['instance_id']!==$instance||$u['resource_ref']!==$proof['provider_resource_ref']||$u['task_ref']!==$proof['provider_task_ref']||$u['provenance']!=='MOCK'){throw new HttpError(409,'INVOICE_SOURCE_MISMATCH');}
                $start=Store::date($u['starts_at']);$end=Store::date($u['ends_at']);$seen=Store::date($u['observed_at']);
                if($end<=$start||$seen<$end||$seen>$this->now()||!is_bool($u['final'])||!is_array($u['usage_refs'])||$u['usage_refs']===[]||count($u['usage_refs'])>100||count(array_unique($u['usage_refs']))!==count($u['usage_refs'])){throw new HttpError(409,'INVOICE_RANGE_INVALID');}
                $amount=new Money($u['total_minor'],new Currency($u['currency']));if(str_starts_with($amount->minor,'-')){throw new HttpError(409,'INVOICE_AMOUNT_INVALID');}$missing=false;
                foreach($u['usage_refs'] as $ref){$c=$this->db->execute('SELECT * FROM cloud_usage_costs WHERE instance_id=? AND account_id=? AND usage_ref=?',[$instance,$i['account_id'],$ref])->fetch();if(!$c){$missing=true;continue;}if($c['currency']!==$u['currency']||$this->db->execute('SELECT 1 FROM cloud_cost_invoice_bindings WHERE cost_id=?',[$c['id']])->fetchColumn()){throw new HttpError(409,'INVOICE_CURRENCY_OR_DUPLICATE');}$meta=$this->cost($c['id'])['calculation'];if($seen<Store::date($meta['source']['observed_at'])||$seen->getTimestamp()<$this->now()->getTimestamp()-$meta['policy']['max_age_seconds']){throw new HttpError(409,'INVOICE_OBSERVATION_STALE');}$costs[]=$c;$sum=(string)(new Decimal($sum))->add(new Decimal((string)$c['total_minor']));}
                if($missing){$status='UNVERIFIED';$reason='USAGE_COST_REQUIRED';}
                else{
                    usort($costs,static fn(array $a,array $b): int=>strcmp($a['starts_at'],$b['starts_at']));$cursor=$u['starts_at'];foreach($costs as $c){if($c['starts_at']!==$cursor){throw new HttpError(409,'INVOICE_COVERAGE_GAP');}$cursor=$c['ends_at'];}if($cursor!==$u['ends_at']){throw new HttpError(409,'INVOICE_COVERAGE_MISMATCH');}
                    $delta=(string)(new Decimal($amount->minor))->subtract(new Decimal($sum));$status=$delta==='0'?'MATCHED_MOCK':'REVIEW';$reason=$delta==='0'?'MOCK_USAGE_INVOICE_MATCH':'INVOICE_AMOUNT_DIFFERENCE';
                }
            }catch(\PDOException $e){throw $e;}catch(\Throwable $e){$status='REVIEW';$reason=$e instanceof HttpError?$e->errorCode:'INVOICE_INPUT_INVALID';}
            $id=Id::uuid();$data=['invoice_ref'=>$invoiceReference,'invoice'=>$u,'reason'=>$reason,'cost_ids'=>array_column($costs,'id'),'cost_total_minor'=>$sum,'difference_minor'=>$delta,'provenance'=>'MOCK'];
            $this->db->execute('INSERT INTO cloud_cost_reconciliations(id,instance_id,invoice_event_id,basis_hash,status,evidence_encrypted,fingerprint) VALUES (?,?,?,?,?,?,?)',[$id,$instance,$event['id'],$basis,$status,$this->store->encrypt($data,'cloud-invoice:'.$id),$this->store->hash($data)]);
            if($status==='MATCHED_MOCK'&&$u['final']){foreach($costs as $c){$this->db->execute('INSERT INTO cloud_cost_invoice_bindings(cost_id,reconciliation_id) VALUES (?,?)',[$c['id'],$id]);}}
            (new Outbox($this->db,new Queue($this->db)))->append(Id::uuid(),'cloud-cost.reconciled',$id,['reconciliation_id'=>$id],Id::uuid());return ['id'=>$id,'status'=>$status,'reason'=>$reason];
        });
    }
}
