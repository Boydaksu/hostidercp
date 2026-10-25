<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
use Hostider\Providers\VoyarMutationFence;
use Hostider\Core\Http\HttpError;
$sdk=realpath(dirname(__DIR__).'/references/voyar-api-client-user-provided.zip');
same('6f76355c1fb32b9c86889e74c68f5caafec03c3670e140e61b53543432c3eb7c',hash_file('sha256',$sdk));
// Load the unmodified supplied reference read-only. No extraction, default CurlTransport or network.
spl_autoload_register(static function(string $class)use($sdk): void{
    $prefix='Voyar\\Client\\';if(str_starts_with($class,$prefix)){
        $relative=substr($class,strlen($prefix));if(!preg_match('/^[A-Za-z0-9_\\\\]+$/D',$relative)){throw new RuntimeException('Invalid SDK class');}
        require 'zip://'.$sdk.'#voyar-api-client/src/Voyar/Client/'.str_replace('\\','/',$relative).'.php';
    }
});
final class SyntheticVoyarTransport implements \Voyar\Client\Http\Transport
{
    public int $mutations=0;public int $logins=0;
    public function __construct(private string $scenario='AUTH',private ?VoyarMutationFence $fence=null){}
    public function send(string $method,string $url,array $headers,$body,$sink=null): \Voyar\Client\Http\RawResponse
    {
        if(str_ends_with($url,'/auth/login')){++$this->logins;return new \Voyar\Client\Http\RawResponse(200,json_encode(['status'=>'success','token'=>['token'=>'synthetic-token','expired_at'=>'2099-01-01 00:00:00']],JSON_THROW_ON_ERROR));}
        if($method!=='POST'){throw new RuntimeException('Unexpected synthetic request');}
        $send=function(): \Voyar\Client\Http\RawResponse{
            ++$this->mutations;
            if($this->scenario==='TIMEOUT'){throw new \Voyar\Client\Exception\TransportException('Synthetic connection timeout');}
            if($this->scenario==='BODY_LOST'){return new \Voyar\Client\Http\RawResponse(200,'');}
            return $this->mutations===1?new \Voyar\Client\Http\RawResponse(401,'{"status":"error","logout":true}'):new \Voyar\Client\Http\RawResponse(200,'{"status":"success"}');
        };
        return $this->fence?$this->fence->send($send):$send();
    }
}
function syntheticVoyar(SyntheticVoyarTransport $transport): \Voyar\Client\Client
{
    $store=new \Voyar\Client\Auth\InMemoryTokenStore();$store->save(new \Voyar\Client\Auth\Token('synthetic-token'));
    return \Voyar\Client\Client::withCredentials('synthetic@example.invalid','synthetic-password',$store,['base_url'=>'https://synthetic.example.invalid','max_retries'=>0],$transport);
}
function sdkMutation(\Voyar\Client\Client $sdk,string $action): void
{
    if($action==='create'){$sdk->services()->create(['pid'=>1,'osid'=>1,'locationid'=>1,'type'=>'ondemand']);return;}
    $service=$sdk->service('SYNTHETIC-VM');
    match($action){'renew'=>$service->extend(['days'=>30]),'terminate'=>$service->terminate(),'reinstall'=>$service->reinstall(['osid'=>1]),'upgrade'=>$service->upgrade(['pid'=>2]),'start'=>$service->vm()->start(),'stop'=>$service->vm()->stop(),'restart'=>$service->vm()->restart(),'password'=>$service->vm()->password(),'vnc'=>$service->vm()->createVncSession()};
}
test('G-V02 source SDK invisibly repeats all sampled financial destructive and credential POSTs after auth refresh',function(): void{
    foreach(['create','renew','terminate','reinstall','upgrade','start','stop','restart','password','vnc'] as $action){$transport=new SyntheticVoyarTransport();sdkMutation(syntheticVoyar($transport),$action);same(2,$transport->mutations);same(1,$transport->logins);}
});
test('G-V03 mutation transport fence blocks the second SDK POST even after successful auth refresh',function(): void{
    foreach(['create','renew','terminate','reinstall','upgrade','start','stop','restart','password','vnc'] as $action){$transport=new SyntheticVoyarTransport(fence:new VoyarMutationFence(testConfig('test')));same('VOYAR_MUTATION_REPLAY_BLOCKED',raises(HttpError::class,fn()=>sdkMutation(syntheticVoyar($transport),$action))->errorCode);same(1,$transport->mutations);same(1,$transport->logins);}
});
test('G-V03 network timeout and missing response body are distinct and neither resets dispatch fence',function(): void{
    foreach(['TIMEOUT','BODY_LOST'] as $scenario){$fence=new VoyarMutationFence(testConfig('test'));$transport=new SyntheticVoyarTransport($scenario,$fence);raises(\Voyar\Client\Exception\TransportException::class,fn()=>sdkMutation(syntheticVoyar($transport),'create'));same(1,$transport->mutations);same(0,$transport->logins);raises(HttpError::class,fn()=>$fence->send(fn()=>null));same(1,$transport->mutations);}
    foreach(['staging','production'] as $environment){raises(HttpError::class,fn()=>new VoyarMutationFence(testConfig($environment)));}
});
runTests();
