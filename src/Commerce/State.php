<?php
declare(strict_types=1);
namespace Hostider\Commerce;
use Hostider\Core\CanonicalJson;
use Hostider\Core\Database\Database;
/** Lock current business state before pure planning; no raw state is persisted or returned. */
final readonly class State
{
    public function __construct(private Database $db) {}
    public function lock(?string $customer,string $actor,string $action): array
    {
        $this->db->requireTransaction();$state=[];
        $versions=new \Hostider\Core\Database\VersionFence($this->db);
        if(in_array($action,Commands::CATALOG,true)||$action==='quote-create'){$state['catalog_version']=$versions->read('catalog',$action!=='quote-create');}
        if(in_array($action,['quote-create','fx-create','customer-currency','order-refund'],true)){$state['fx_version']=$versions->read('fx',$action==='fx-create');}
        if(in_array($action,['cash-category','expense-create','recurring-create','recurring-due','recurring-pay'],true)){$state['cash_version']=$versions->read('cash',true);}
        if($customer!==null){
            $state['account']=$this->db->execute('SELECT id,currency,version FROM identity_customers WHERE id=? FOR UPDATE',[$customer])->fetchAll();
            foreach(['orders','billing_requests','pricing_quotes','finance_wallets','finance_payments','finance_allocations','payment_attempts'] as $table){$state[$table]=$this->db->execute('SELECT * FROM '.$table.' WHERE customer_id=? ORDER BY id FOR UPDATE',[$customer])->fetchAll();}
            $state['billing_states']=$this->db->execute('SELECT s.* FROM billing_request_states s JOIN billing_requests r ON r.id=s.request_id WHERE r.customer_id=? ORDER BY s.request_id FOR UPDATE',[$customer])->fetchAll();
            $state['wallet_entries']=$this->db->execute('SELECT e.* FROM finance_wallet_entries e JOIN finance_wallets w ON w.id=e.wallet_id WHERE w.customer_id=? ORDER BY e.id FOR UPDATE',[$customer])->fetchAll();
            $state['refunds']=$this->db->execute('SELECT f.* FROM finance_refunds f JOIN finance_payments p ON p.id=f.payment_id WHERE p.customer_id=? ORDER BY f.id FOR UPDATE',[$customer])->fetchAll();
            $state['notices']=$this->db->execute('SELECT n.* FROM payment_bank_notices n JOIN payment_attempts a ON a.id=n.attempt_id WHERE a.customer_id=? ORDER BY n.id FOR UPDATE',[$customer])->fetchAll();
            $state['documents']=$this->db->execute('SELECT * FROM billing_official_documents WHERE customer_id=? ORDER BY id FOR UPDATE',[$customer])->fetchAll();
        }
        if($customer!==null){$state['reconciliations']=$this->db->execute('SELECT r.* FROM finance_reconciliations r JOIN finance_payments p ON p.id=r.subject_id WHERE p.customer_id=? ORDER BY r.id FOR UPDATE',[$customer])->fetchAll();$state['resolutions']=$this->db->execute('SELECT * FROM finance_reconciliation_resolutions WHERE customer_id=? ORDER BY id FOR UPDATE',[$customer])->fetchAll();}
        if(str_starts_with($action,'document-')){$state['uploads']=$this->db->execute('SELECT * FROM billing_uploads WHERE actor_id=? ORDER BY id FOR UPDATE',[$actor])->fetchAll();}
        return self::strings($state);
    }
    private static function strings(array $data): array
    {
        foreach($data as $key=>$value){if(is_array($value)){$data[$key]=self::strings($value);}elseif(is_int($value)){$data[$key]=(string)$value;}}return $data;
    }
    public function digest(array $state,string $key): string { return hash_hmac('sha256',CanonicalJson::encode($state),$key); }
}
