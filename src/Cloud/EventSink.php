<?php
declare(strict_types=1);
namespace Hostider\Cloud;
use Hostider\Core\{CanonicalJson,Config};
use Hostider\Core\Database\Database;
use Hostider\Core\Queue\Worker as QueueWorker;
final readonly class EventSink
{
    public function __construct(private Database $db,private Config $config){}
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
        $events=$this->db->execute("SELECT payload FROM outbox_events WHERE aggregate_id=? AND event_type='cloud.execute'",[$payload['operation_id']])->fetchAll();
        if(!array_filter($events,static fn(array $e): bool=>CanonicalJson::fromJson($e['payload'])===CanonicalJson::encode($payload))){return 'FAILED';}
        $worker=new Worker($this->db,$this->config);for($n=0;$n<2;++$n){$worker->once($payload['operation_id']);}
        $status=$this->db->execute('SELECT status FROM cloud_operations WHERE id=?',[$payload['operation_id']])->fetchColumn();
        return match($status){'SUCCEEDED'=>'SUCCEEDED','FAILED'=>'FAILED',default=>'UNKNOWN'};
    }
}
