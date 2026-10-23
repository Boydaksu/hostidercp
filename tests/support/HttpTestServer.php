<?php
declare(strict_types=1);
/** Loopback-only test infrastructure. Never logs request/response bodies, headers, tokens or environment values. */
final class HttpTestTransportFailure extends RuntimeException {}
final class HttpTestServer
{
    private mixed $process=null;
    private array $lastStatus=[];
    private string $id;
    private string $stdout;
    private string $stderr;
    private int $port=0;
    private bool $stopped=false;
    private bool $debugger=false;
    private array $runtime=[];
    private string $engine;
    public function __construct(private string $root,array $environment,string $router,int $requestedPort=0)
    {
        if(($environment['APP_ENV']??null)!=='test'||$requestedPort<0||$requestedPort>65535){throw new LogicException('Isolated test environment required');}
        $this->id=bin2hex(random_bytes(12));$directory=$root.'/storage/ci-diagnostics';if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory)){throw new RuntimeException('Diagnostics directory unavailable');}
        $this->stdout=$directory.'/'.$this->id.'.stdout.private';$this->stderr=$directory.'/'.$this->id.'.stderr.private';$environment['HTTP_TEST_INSTANCE']=$this->id;
        $this->engine=$environment['HTTP_TEST_ENGINE']??'interpreter';if(!in_array($this->engine,['inherit','interpreter'],true)){throw new LogicException('Unknown test engine');}
        $command=[PHP_BINARY];if($this->engine==='interpreter'){$command=array_merge($command,['-d','opcache.jit=disable','-d','opcache.jit_buffer_size=0']);}$command=array_merge($command,['-S','127.0.0.1:'.$requestedPort,'-t',$root.'/public',$router]);$this->debugger=($environment['HTTP_TEST_GDB']??'')==='1';
        if($this->debugger){$command=array_merge(['gdb','--batch','-ex','set pagination off','-ex','set disable-randomization off','-ex','set print frame-arguments none','-ex','run','-ex','thread apply all bt','--args'],$command);}
        $pipes=[];$this->process=proc_open($command,[0=>['pipe','r'],1=>['file',$this->stdout,'w'],2=>['file',$this->stderr,'w']],$pipes,$root,$environment);
        if(!is_resource($this->process)){throw new RuntimeException('HTTP test server launch failed');}fclose($pipes[0]);
        try{$this->awaitReady();}catch(Throwable $e){$this->stop();throw $e;}
    }
    private function status(): array
    {
        if(is_resource($this->process)&&($this->lastStatus===[]||($this->lastStatus['running']??false))){$this->lastStatus=proc_get_status($this->process);}return $this->lastStatus;
    }
    private function awaitReady(): void
    {
        $deadline=hrtime(true)+10_000_000_000;
        do{
            if(!($this->status()['running']??false)){throw $this->failure('STARTUP_EXIT',0,'readiness');}
            $text=is_file($this->stderr)?file_get_contents($this->stderr):'';
            if(preg_match('~http://127\.0\.0\.1:([0-9]+)~',(string)$text,$m)){$this->port=(int)$m[1];
                try{[$code,$body]=$this->request('/__http_test_ready',waiting:true);$data=json_decode($body,true);if($code===200&&($data['instance']??null)===$this->id&&($this->debugger||($data['pid']??null)===($this->status()['pid']??null))){$this->runtime=$data['runtime']??[];return;}}catch(HttpTestTransportFailure){if(!($this->status()['running']??false)){throw $this->failure('READINESS_EXIT',0,'readiness');}}
            }
            usleep(20000);
        }while(hrtime(true)<$deadline);
        throw $this->failure('READINESS_TIMEOUT',0,'readiness');
    }
    public function request(string $path,array $options=[],bool $waiting=false): array
    {
        if(!str_starts_with($path,'/')||str_contains($path,"\r")||str_contains($path,"\n")){throw new LogicException('Invalid loopback path');}
        if(!$waiting&&!($this->status()['running']??false)){throw $this->failure('SERVER_EXIT',0,$path);}
        $headers=[];$curl=curl_init('http://127.0.0.1:'.$this->port.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>1000,CURLOPT_TIMEOUT_MS=>$waiting?500:10000,CURLOPT_HEADERFUNCTION=>function($curl,string $line)use(&$headers): int{$headers[]=$line;return strlen($line);}]+$options);
        $body=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$errno=curl_errno($curl);curl_close($curl);
        if($body===false||$code===0){throw $this->failure('CURL_TRANSPORT',$errno,$path);}
        return [$code,$body,$headers];
    }
    private function failure(string $reason,int $errno,string $path): HttpTestTransportFailure
    {
        if($reason==='CURL_TRANSPORT'){for($i=0;$i<25&&($this->status()['running']??false);++$i){usleep(10000);}}
        $snapshot=$this->diagnostics($reason)+['curl_errno'=>$errno,'curl_error'=>$errno===0?'none':curl_strerror($errno),'route_sha256'=>hash('sha256',(string)parse_url($path,PHP_URL_PATH))];$this->save($snapshot);return new HttpTestTransportFailure('HTTP_TEST_TRANSPORT '.json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }
    public function diagnostics(string $reason='SNAPSHOT'): array
    {
        $status=$this->status();$stderr=is_file($this->stderr)?(string)file_get_contents($this->stderr):'';$stdout=is_file($this->stdout)?(string)file_get_contents($this->stdout):'';$markers=[];
        foreach(['segmentation_fault'=>'~segmentation fault|signal 11~i','listen_failed'=>'~Failed to listen|Address already in use~i','memory_exhausted'=>'~Allowed memory size|Out of memory~i','fatal_error'=>'~Fatal error~i','heap_corruption'=>'~zend_mm_heap corrupted|double free|malloc\(\)~i'] as $name=>$pattern){if(preg_match($pattern,$stderr.$stdout)){$markers[]=$name;}}
        preg_match_all('/^#[0-9]+\s+(?:0x[0-9a-f]+\s+in\s+)?([A-Za-z_][A-Za-z0-9_:]*)\s*\(/m',$stdout,$frames);preg_match('/Program received signal (SIG[A-Z0-9]+)/',$stdout,$signal);
        return ['instance'=>$this->id,'reason'=>$reason,'port'=>$this->port,'engine'=>$this->engine,'server_runtime'=>$this->runtime,'running'=>(bool)($status['running']??false),'exitcode'=>$status['exitcode']??null,'signaled'=>$status['signaled']??null,'termsig'=>$status['termsig']??null,'stderr_bytes'=>strlen($stderr),'stderr_sha256'=>hash('sha256',$stderr),'stdout_bytes'=>strlen($stdout),'markers'=>$markers,'native_frames'=>$frames[1],'debuggee_signal'=>$signal[1]??null,'php'=>PHP_VERSION,'os'=>PHP_OS_FAMILY,'parent_ini'=>['opcache.enable_cli'=>ini_get('opcache.enable_cli'),'opcache.jit'=>ini_get('opcache.jit'),'opcache.jit_buffer_size'=>ini_get('opcache.jit_buffer_size')]];
    }
    private function save(array $snapshot): void {file_put_contents($this->root.'/storage/ci-diagnostics/'.$this->id.'-'.$snapshot['reason'].'.json',json_encode($snapshot,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));}
    public function stop(): void
    {
        if($this->stopped){return;}$this->stopped=true;
        if(is_resource($this->process)){
            if($this->status()['running']??false){proc_terminate($this->process);$deadline=hrtime(true)+2_000_000_000;while(($this->status()['running']??false)&&hrtime(true)<$deadline){usleep(10000);}if($this->status()['running']??false){proc_terminate($this->process,9);}}
            $this->save($this->diagnostics('STOP'));proc_close($this->process);$this->process=null;
        }
        // Raw files can contain arbitrary runtime messages. Only allowlisted JSON is a CI artifact.
        foreach([$this->stdout,$this->stderr] as $file){if(is_file($file)){unlink($file);}}
    }
    public function __destruct(){ $this->stop(); }
}
