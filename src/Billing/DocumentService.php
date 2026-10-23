<?php
declare(strict_types=1);
namespace Hostider\Billing;
use Hostider\Core\{Config,Id,Validation};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\{Audit,AuditEvent,Crypto,Principal};
final readonly class DocumentService
{
    private Crypto $crypto;
    public function __construct(private Database $db,Config $config,private DocumentStore $store,private Repository $billing){$this->crypto=new Crypto(base64_decode($config->key,true));}
    private function mutation(Principal $actor): void {$this->db->requireTransaction();if($actor->impersonating||!$actor->staff){throw new HttpError(403,'DOCUMENT_STAFF_REQUIRED');}}
    public function start(string $request,string $customer,Principal $actor): string
    {
        $this->mutation($actor);$this->billing->request($request,$customer,true);$id=Id::uuid();$this->db->execute('INSERT INTO billing_uploads(id,actor_id,request_id,customer_id) VALUES (?,?,?,?)',[$id,$actor->actorId,$request,$customer]);return $id;
    }
    private function upload(string $id,Principal $actor): array
    {
        $row=$this->db->execute('SELECT * FROM billing_uploads WHERE id=? AND actor_id=? FOR UPDATE',[$id,$actor->actorId])->fetch();if(!$row){throw new HttpError(404,'UPLOAD_NOT_FOUND');}if((bool)$row['finalized']){throw new HttpError(409,'UPLOAD_FINALIZED');}return $row;
    }
    public function chunk(string $id,int $offset,string $bytes,Principal $actor): int
    {
        $this->mutation($actor);$row=$this->upload($id,$actor);if((int)$row['received_bytes']!==$offset){throw new HttpError(409,'UPLOAD_OFFSET_CONFLICT');}
        $size=$this->store->append($id,$offset,$bytes);$this->db->execute('UPDATE billing_uploads SET received_bytes=? WHERE id=?',[$size,$id]);return $size;
    }
    public function finish(string $id,string $documentNumber,string $documentDate,string $documentType,Principal $actor,string $trace,array $taxMetadata=[]): string
    {
        $this->mutation($actor);Validation::text($documentNumber,128);if(!in_array($documentType,['EXTERNAL_E_INVOICE','EXTERNAL_E_ARCHIVE','EXTERNAL_OFFICIAL_PDF'],true)){throw new HttpError(422,'INVALID_DOCUMENT_TYPE');}
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$documentDate,new \DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d')!==$documentDate){throw new HttpError(422,'INVALID_DOCUMENT_DATE');}
        Validation::fields($taxMetadata,['issuer_tax_id','customer_tax_id','jurisdiction'],[]);foreach($taxMetadata as $value){Validation::text($value,128);}
        $row=$this->upload($id,$actor);$bytes=$this->store->read($id,(int)$row['received_bytes']);$this->store->validate($bytes);$document=Id::uuid();
        $metadata=$this->crypto->encrypt(json_encode(['number'=>$documentNumber,'date'=>$documentDate,'type'=>$documentType]+$taxMetadata,JSON_THROW_ON_ERROR),'official-document:'.$document);
        $this->db->execute('INSERT INTO billing_official_documents(id,request_id,customer_id,upload_id,sha256,size_bytes,metadata_encrypted,uploaded_by) VALUES (?,?,?,?,?,?,?,?)',[$document,$row['request_id'],$row['customer_id'],$id,hash('sha256',$bytes),strlen($bytes),$metadata,$actor->actorId]);
        $this->db->execute('UPDATE billing_uploads SET finalized=TRUE WHERE id=?',[$id]);(new Audit($this->db))->record($actor,AuditEvent::DocumentUploaded,$document,$trace);
        (new \Hostider\Core\Queue\Outbox($this->db,new \Hostider\Core\Queue\Queue($this->db)))->append(Id::uuid(),'billing.document_attached',$document,['document_id'=>$document],$trace);return $document;
    }
    public function preview(string $id,string $customer,Principal $actor): array
    {
        $this->mutation($actor);$row=$this->upload($id,$actor);if($row['customer_id']!==$customer){throw new HttpError(404,'UPLOAD_NOT_FOUND');}
        $bytes=$this->store->read($id,(int)$row['received_bytes']);$this->store->validate($bytes);return ['upload_id'=>$id,'request_id'=>$row['request_id'],'size_bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)];
    }
    public function download(string $id,string $customer): array
    {
        $row=$this->db->execute('SELECT * FROM billing_official_documents WHERE id=? AND customer_id=?',[$id,$customer])->fetch();if(!$row){throw new HttpError(404,'DOCUMENT_NOT_FOUND');}$this->billing->request($row['request_id'],$customer);
        $bytes=$this->store->read($row['upload_id'],(int)$row['size_bytes']);if(!hash_equals($row['sha256'],hash('sha256',$bytes))){throw new HttpError(409,'DOCUMENT_INTEGRITY_FAILURE');}return ['bytes'=>$bytes,'filename'=>$id.'.pdf'];
    }
    public function list(string $request,string $customer): array
    {
        $this->billing->request($request,$customer);$rows=$this->db->execute('SELECT * FROM billing_official_documents WHERE request_id=? AND customer_id=? ORDER BY created_at,id',[$request,$customer])->fetchAll();$out=[];
        foreach($rows as $row){$out[]=['id'=>$row['id'],'sha256'=>$row['sha256'],'size_bytes'=>(int)$row['size_bytes'],'created_at'=>$row['created_at'],'metadata'=>json_decode($this->crypto->decrypt($row['metadata_encrypted'],'official-document:'.$row['id']),true,32,JSON_THROW_ON_ERROR)];}return $out;
    }
}
