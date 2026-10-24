<?php
declare(strict_types=1);
require __DIR__.'/harness.php';require __DIR__.'/database.php';require __DIR__.'/cloud-fixture.php';
use Hostider\Core\Database\Migrator;use Hostider\Cloud\Engine;use Hostider\Providers\Mock\DurableVoyar;use Hostider\Providers\VoyarMutationFence;use Hostider\Core\Http\HttpError;
$sdk=realpath(dirname(__DIR__).'/references/voyar-api-client-user-provided.zip');same('6f76355c1fb32b9c86889e74c68f5caafec03c3670e140e61b53543432c3eb7c',hash_file('sha256',$sdk));
spl_autoload_register(static function(string $class)use($sdk): void{$prefix='Voyar\\Client\\';if(str_starts_with($class,$prefix)){$relative=substr($class,strlen($prefix));if(!preg_match('/^[A-Za-z0-9_\\\\]+$/D',$relative)){throw new RuntimeException('Invalid SDK class');}require 'zip://'.$sdk.'#voyar-api-client/src/Voyar/Client/'.str_replace('\\','/',$relative).'.php';}});
final class DurableSyntheticCloudTransport implements \Voyar\Client\Http\Transport
{
    public int $mutations=0;public int $logins=0;private VoyarMutationFence $fence;
    public function __construct(private array $claim,Engine $engine,private DurableVoyar $provider){$this->fence=$engine->fence($claim);}
    public function send(string $method,string $url,array $headers,$body,$sink=null): \Voyar\Client\Http\RawResponse
    {
        if(str_ends_with($url,'/auth/login')){++$this->logins;return new \Voyar\Client\Http\RawResponse(200,'{"status":"success","token":{"token":"synthetic-token","expired_at":"2099-01-01 00:00:00"}}');}
        if($method!=='POST'){throw new RuntimeException('Unexpected SDK request');}
        $kind=$this->claim['request']['kind'];$ref=$this->claim['request']['provider_resource_ref'];
        $expected=match($kind){'CREATE'=>'/services/services/create','START'=>'/services/vm/start/'.$ref,'STOP'=>'/services/vm/stop/'.$ref,'RESTART'=>'/services/vm/restart/'.$ref,'REINSTALL'=>'/services/services/reinstall/'.$ref,'UPGRADE'=>'/services/services/upgrade/'.$ref,'TERMINATE'=>'/services/services/terminate/'.$ref};
        same(true,str_ends_with($url,$expected));
        return $this->fence->send(function(): \Voyar\Client\Http\RawResponse{++$this->mutations;$this->provider->mutate($this->claim['request'],$this->claim['fingerprint'],'AUTH_AFTER_EFFECT');return new \Voyar\Client\Http\RawResponse(401,'{"status":"error","logout":true}');});
    }
}
$db=isolatedDomainDb();(new Migrator($db,dirname(__DIR__).'/migrations'))->migrate();
test('F4-D original SDK auth POST replay is blocked by committed Cloud operation dispatch admission',function()use($db): void{
    foreach(['CREATE','START','STOP','RESTART','REINSTALL','UPGRADE','TERMINATE'] as $kind){$f=cloudFixture($db);if($kind!=='CREATE'){activateCloud($db,$f);}
        if($kind==='CREATE'){$op=$f['engine']->enqueue($f['instance'],$kind,[],$f['actor']);}
        elseif($kind==='TERMINATE'){$c=$f['engine']->prepare($f['instance'],$kind,[],$f['actor']);usleep(1100000);$op=$f['engine']->enqueue($f['instance'],$kind,[],$f['actor'],$c);}
        else{$op=cloudAction($f,$kind,match($kind){'REINSTALL'=>['osid'=>1],'UPGRADE'=>['pid'=>1],default=>[]});}
        $claim=$f['engine']->claim($op);$transport=new DurableSyntheticCloudTransport($claim,$f['engine'],new DurableVoyar($db,$f['config'],$claim['account_id']));
        $tokens=new \Voyar\Client\Auth\InMemoryTokenStore();$tokens->save(new \Voyar\Client\Auth\Token('synthetic-token'));
        $client=\Voyar\Client\Client::withCredentials('synthetic@example.invalid','synthetic-password',$tokens,['base_url'=>'https://synthetic.example.invalid','max_retries'=>0],$transport);
        $invoke=function()use($client,$kind,$claim): void{if($kind==='CREATE'){$client->services()->create($claim['request']['data']);return;}$s=$client->service($claim['request']['provider_resource_ref']);match($kind){'START'=>$s->vm()->start(),'STOP'=>$s->vm()->stop(),'RESTART'=>$s->vm()->restart(),'REINSTALL'=>$s->reinstall(['osid'=>1]),'UPGRADE'=>$s->upgrade(['pid'=>1]),'TERMINATE'=>$s->terminate()};};
        same('VOYAR_MUTATION_REPLAY_BLOCKED',raises(HttpError::class,$invoke)->errorCode);same(1,$transport->mutations);same(1,$transport->logins);same(1,(int)$db->execute('SELECT COUNT(*) FROM cloud_dispatches WHERE operation_id=?',[$op])->fetchColumn());same(1,(int)$db->execute('SELECT COUNT(*) FROM mock_voyar_calls WHERE operation_id=?',[$op])->fetchColumn());$f['engine']->lost($claim);same('UNKNOWN',$db->execute('SELECT status FROM cloud_operations WHERE id=?',[$op])->fetchColumn());
    }
});
runTests();
