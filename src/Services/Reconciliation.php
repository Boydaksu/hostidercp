<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\{CanonicalJson,Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\{Authorization,Crypto,Principal};
use Hostider\Providers\Mock\DurableCpanel;use Hostider\Providers\Contract\Operation;
/** Provider evidence is distinct from payment/finance reconciliation. No create/retry path exists here. */
final readonly class Reconciliation
{
    private Repository $repository;
    public function __construct(private Database $db,private Config $config){$this->repository=new Repository($db,$config);}
    private function authorize(Principal $actor,string $customer,string $service): void
    {
        if($this->config->environment!=='test'||!$actor->staff){throw new HttpError(403,'F3_TEST_ONLY');}
        (new Authorization())->require($actor,'services.reconcile',$customer,privileged:true,service:$service);
    }
    public function inspect(string $id,string $customer,Principal $actor,string $trace): array
    {
        $this->authorize($actor,$customer,$id);$service=$this->repository->find($id,$customer,false);
        if($service['status']!=='UNKNOWN'||$service['imported']){throw new HttpError(409,'SERVICE_NOT_RECONCILABLE');}
        $provider=$this->db->execute("SELECT p.* FROM svc_provider_operations p JOIN svc_operations o ON o.id=p.lifecycle_operation_id WHERE o.id=? AND o.status='UNKNOWN'",[$service['current_operation_id']])->fetch();if(!$provider||!$provider['server_id']){throw new HttpError(409,'PROVIDER_BINDING_MISSING');}
        $adapter=new DurableCpanel($this->db,$this->config,$provider['server_id']);$result=$adapter->inspectAccount(new Operation(Id::uuid(),'inspectAccount',null,['create_operation_id'=>$provider['id']]));$proof=$result->status==='SUCCESS'&&$result->externalResourceId!==null?$adapter->evidence($result->externalResourceId):null;
        return $this->db->transaction(function()use($id,$customer,$actor,$trace,$service,$provider,$proof): array{
            $this->authorize($actor,$customer,$id);$current=$this->repository->find($id,$customer);if($current['status']!=='UNKNOWN'||$current['version']!==$service['version']||$current['current_operation_id']!==$provider['lifecycle_operation_id']){throw new HttpError(409,'SERVICE_CHANGED_DURING_INSPECT');}
            $evidence=Id::uuid();$key=base64_decode($this->config->key,true);$json=CanonicalJson::encode(['proof'=>$proof,'provider_operation_id'=>$provider['id'],'server_id'=>$provider['server_id']]);
            $this->db->execute('INSERT INTO svc_inspections(id,service_id,operation_id,service_version,actor_id,found,evidence_encrypted,fingerprint,expires_at) VALUES (?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE))',[$evidence,$id,$provider['lifecycle_operation_id'],$service['version'],$actor->actorId,$proof!==null?1:0,(new Crypto($key))->encrypt($json,'svc-inspection:'.$evidence),hash_hmac('sha256',$json,$key)]);
            // Inspection is an append-only event; it must not alter the service version bound by the evidence.
            $this->repository->event($current,'PROVIDER_INSPECTED',$actor,$trace);return ['inspection_id'=>$evidence,'found'=>$proof!==null,'decision'=>$proof===null?'MANUAL_REVIEW_NO_RETRY':'REVIEW_ATTACH_EXISTING'];
        });
    }
    public function resolve(string $id,string $customer,string $inspection,Principal $actor,string $trace): array
    {
        $this->authorize($actor,$customer,$id);
        return $this->db->transaction(function()use($id,$customer,$inspection,$actor,$trace): array{
            $identity=$this->repository->find($id,$customer,false);$source=$this->db->execute('SELECT o.status,o.refund_status,b.status AS bill_status,b.refund_status AS bill_refund FROM orders o JOIN billing_request_states b ON b.request_id=o.payment_request_id WHERE o.id=? FOR UPDATE',[$identity['order_id']])->fetch();$service=$this->repository->find($id,$customer);
            $this->authorize($actor,$customer,$id);$evidence=$this->db->execute('SELECT *,expires_at>UTC_TIMESTAMP(6) AS live FROM svc_inspections WHERE id=? AND service_id=?',[$inspection,$id])->fetch();
            if(!$evidence||$evidence['actor_id']!==$actor->actorId){throw new HttpError(403,'INSPECTION_SCOPE_MISMATCH');}
            if(!$evidence['live']||!$evidence['found']||$evidence['service_version']!==$service['version']||$service['status']!=='UNKNOWN'||$service['current_operation_id']!==$evidence['operation_id']){throw new HttpError(409,'INSPECTION_REQUIRES_MANUAL_REVIEW');}
            if(!$source||$source['status']!=='READY_FOR_PROVISIONING'||$source['refund_status']!=='NONE'||$source['bill_status']!=='PAID'||$source['bill_refund']!=='NONE'){throw new HttpError(409,'SERVICE_SOURCE_CHANGED');}
            $operation=$this->db->execute('SELECT * FROM svc_operations WHERE id=? FOR UPDATE',[$evidence['operation_id']])->fetch();$provider=$this->db->execute('SELECT * FROM svc_provider_operations WHERE lifecycle_operation_id=? FOR UPDATE',[$operation['id']])->fetch();
            if($operation['status']!=='UNKNOWN'||$provider['status']!=='UNKNOWN'){throw new HttpError(409,'OPERATION_NOT_UNKNOWN');}
            $key=base64_decode($this->config->key,true);$json=(new Crypto($key))->decrypt($evidence['evidence_encrypted'],'svc-inspection:'.$inspection);if(!hash_equals($evidence['fingerprint'],hash_hmac('sha256',$json,$key))){throw new HttpError(409,'INSPECTION_INTEGRITY');}$data=json_decode($json,true,32,JSON_THROW_ON_ERROR);$proof=$data['proof'];
            $reservation=$this->db->execute('SELECT package_name FROM hosting_reservations WHERE operation_id=?',[$operation['id']])->fetch();
            if($data['provider_operation_id']!==$provider['id']||$data['server_id']!==$provider['server_id']||$proof['server_id']!==$provider['server_id']||$proof['service_id']!==$id||$proof['create_operation_id']!==$provider['id']||$proof['status']!=='ACTIVE'||!$reservation||$proof['package_name']!==$reservation['package_name']){throw new HttpError(409,'INSPECTION_BINDING_MISMATCH');}
            $period=$this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE',[$service['current_period_id']])->fetch();$cycle=json_decode($period['cycle_snapshot'],true,32,JSON_THROW_ON_ERROR);$start=new \DateTimeImmutable($proof['starts_at'],new \DateTimeZone('UTC'));$end=$cycle['kind']==='ONCE'?null:\Hostider\Catalog\BillingCycle::next($start,$cycle['kind'],(int)$cycle['interval_count'])->format('Y-m-d H:i:s.u');
            if($period['status']!=='PENDING_PROVIDER'||$end!==$proof['ends_at']){throw new HttpError(409,'INSPECTION_PERIOD_MISMATCH');}
            $proofJson=CanonicalJson::encode($proof);$hash=hash_hmac('sha256',$proofJson,$key);$periodEvidence=Id::uuid();
            $this->db->execute('INSERT INTO svc_resources(id,service_id,server_id,external_id,operation_id,evidence_hash) VALUES (?,?,?,?,?,?)',[Id::uuid(),$id,$provider['server_id'],$proof['id'],$operation['id'],$hash]);
            $this->db->execute('INSERT INTO svc_period_evidence(id,period_id,operation_id,evidence_encrypted,fingerprint) VALUES (?,?,?,?,?)',[$periodEvidence,$period['id'],$operation['id'],(new Crypto($key))->encrypt($proofJson,'svc-evidence:'.$periodEvidence),$hash]);$this->db->execute("UPDATE svc_periods SET starts_at=?,ends_at=?,status='VERIFIED',version=version+1 WHERE id=?",[$proof['starts_at'],$proof['ends_at'],$period['id']]);
            $resolution=Id::uuid();$this->db->execute('INSERT INTO svc_resolutions(id,service_id,operation_id,inspection_id,actor_id,trace_id) VALUES (?,?,?,?,?,?)',[$resolution,$id,$operation['id'],$inspection,$actor->actorId,$trace]);$this->repository->transition($service,'ACTIVE',$actor,$trace);
            // UNKNOWN operation remains immutable history; a separate resolution supplies the operator decision.
            return ['resolution_id'=>$resolution,'service_id'=>$id,'status'=>'ACTIVE'];
        });
    }
}
