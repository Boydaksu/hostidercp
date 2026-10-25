<?php
declare(strict_types=1);
namespace Hostider\Cloud;
use Hostider\Core\{CanonicalJson,Config};
use Hostider\Core\Database\Database;
use Hostider\Core\Queue\Worker as QueueWorker;
final readonly class EventSink
{
    public function __construct(private Database $db,private Config $config,private bool $completeTasks=true){}
    public function register(QueueWorker $worker): void
    {
        $worker->register('cloud.execute',fn(array $p): string=>$this->consume($p));
        $worker->register('cloud.changed',function(array $p): string{
            if($this->config->environment!=='test'||array_keys($p)!==['instance_id']||!is_string($p['instance_id'])){return 'FAILED';}
            $events=$this->db->execute("SELECT payload FROM outbox_events WHERE aggregate_id=? AND event_type='cloud.changed'",[$p['instance_id']])->fetchAll();
            return array_filter($events,static fn(array $e): bool=>CanonicalJson::fromJson($e['payload'])===CanonicalJson::encode($p))?'SUCCEEDED':'FAILED';
        });
    }
    public function consume(array $payload): string
    {
        if($this->config->environment!=='test'||array_keys($payload)!==['operation_id']||!is_string($payload['operation_id'])){return 'FAILED';}
        $events=$this->db->execute("SELECT event_uuid,payload FROM outbox_events WHERE aggregate_id=? AND event_type='cloud.execute'",[$payload['operation_id']])->fetchAll();
        if(!array_filter($events,static fn(array $e): bool=>CanonicalJson::fromJson($e['payload'])===CanonicalJson::encode($payload))){return 'FAILED';}
        if(!$this->db->execute('SELECT 1 FROM cloud_operations WHERE id=?',[$payload['operation_id']])->fetchColumn()){return 'FAILED';}
        $outbox=new \Hostider\Core\Queue\Outbox($this->db,new \Hostider\Core\Queue\Queue($this->db));$new=false;
        foreach($events as $event){if(CanonicalJson::fromJson($event['payload'])===CanonicalJson::encode($payload)){$new=$outbox->consume('cloud.delivery.v1',$event['event_uuid'],static fn(): null=>null)||$new;}}
        if($new){try{$worker=new Worker($this->db,$this->config,completeTasks:$this->completeTasks);for($n=0;$n<2;++$n){$worker->once($payload['operation_id']);}}catch(\Throwable){/* Delivery succeeded; durable operation/lease records own recovery, not the queue receipt. */}}
        return 'SUCCEEDED';
    }
}
