<?php
declare(strict_types=1);
namespace Hostider\Cloud;
use Hostider\Core\Config;
use Hostider\Core\Database\Database;
use Hostider\Providers\Mock\DurableVoyar;
final readonly class Worker
{
    private Engine $engine;
    public function __construct(private Database $db,private Config $config,private string $scenario='SUCCESS',private bool $completeTasks=true){$this->engine=new Engine($db,$config);}
    public function once(?string $id=null): bool
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('Cloud worker outside application transaction required');}
        $this->engine->expire();$claim=$this->engine->claim($id);if(!$claim){return false;}
        $provider=new DurableVoyar($this->db,$this->config,$claim['account_id']);
        if($claim['poll']){
            if($this->completeTasks&&$claim['task_id']){$provider->advance($claim['task_id']);}
            $observation=$this->engine->observe($claim['id']);$this->engine->finish($claim,$observation);
        }else{
            try{$result=$this->engine->fence($claim)->send(fn()=>$provider->mutate($claim['request'],$claim['fingerprint'],$this->scenario));}
            catch(\Throwable){$this->engine->lost($claim);return true;}
            $this->engine->accepted($claim,$result);
        }
        return true;
    }
    public function run(int $maximum=100,int $seconds=20): array
    {
        if($maximum<1||$maximum>100||$seconds<1||$seconds>20){throw new \InvalidArgumentException('Invalid Cloud worker budget');}
        $deadline=hrtime(true)+$seconds*1_000_000_000;$worked=0;$limit=ini_get('memory_limit');$bytes=(int)$limit;
        $bytes*=match(strtolower(substr($limit,-1))){'g'=>1024*1024*1024,'m'=>1024*1024,'k'=>1024,default=>1};
        $memory=$bytes>0?min(64*1024*1024,max(0,$bytes-8*1024*1024)):64*1024*1024;
        while($worked<$maximum&&hrtime(true)<$deadline&&memory_get_usage(true)<$memory&&$this->once()){++$worked;}
        return ['worked'=>$worked,'limit'=>$maximum,'budget_seconds'=>$seconds];
    }
}
