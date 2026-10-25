<?php
declare(strict_types=1);
namespace Hostider\CloudCosts;
use Hostider\Core\{Config,CanonicalJson};use Hostider\Core\Database\Database;use Hostider\Core\Queue\{Worker,Queue,Outbox};
final readonly class EventSink
{
    public function __construct(private Database $db,private Config $config){}
    public function register(Worker $worker): void
    {
        foreach(['cloud-cost.recorded'=>'cost_id','cloud-cost.reconciled'=>'reconciliation_id'] as $type=>$field){
            $worker->register($type,function(array $p)use($type,$field): string{
                if($this->config->environment!=='test'||array_keys($p)!==[$field]||!is_string($p[$field])){return 'FAILED';}
                $events=$this->db->execute('SELECT event_uuid,payload FROM outbox_events WHERE event_type=? AND aggregate_id=?',[$type,$p[$field]])->fetchAll();$found=false;
                foreach($events as $e){if(CanonicalJson::fromJson($e['payload'])===CanonicalJson::encode($p)){(new Outbox($this->db,new Queue($this->db)))->consume('cloud-cost.delivery.v1',$e['event_uuid'],static fn(): null=>null);$found=true;}}return $found?'SUCCEEDED':'FAILED';
            });
        }
    }
}
