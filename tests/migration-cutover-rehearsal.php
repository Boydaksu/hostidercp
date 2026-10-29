<?php
declare(strict_types=1);
require __DIR__.'/harness.php';
require __DIR__.'/migration-recovery-target-fixture.php';
use Hostider\Migration\Contract;
use Hostider\Migration\Cutover\{Preflight, Simulation, Rehearsal};
use Hostider\Migration\Operations\RecoveryInspector;
use Hostider\Migration\Reconciliation\Report;

function rehearsalCase(Closure $work): void
{
    try {
        $f=targetFixture();
        putenv('SYNTHETIC_PREFLIGHT_ALLOWED=1'); putenv('SYNTHETIC_REHEARSAL_ALLOWED=1');
        $f['context']=targetContext($f);
        $f['recovery_reader']=RecoveryInspector::open($f['context'],targetPolicy($f),$f['target_password']);
        $f['preflight']=Preflight::open($f['reader'],$f['recovery_reader'],$f['context'],$f['loaded']['checkpoint'],time()+180);
        $work($f);
    } finally {
        putenv('APP_ENV=test'); putenv('SYNTHETIC_IMPORT_ALLOWED=1');
        putenv('SYNTHETIC_BACKUP_ALLOWED=1'); putenv('SYNTHETIC_RECOVERY_ALLOWED=1');
        putenv('SYNTHETIC_PREFLIGHT_ALLOWED=1'); putenv('SYNTHETIC_REHEARSAL_ALLOWED=1');
        targetCleanup();
    }
}
function rehearsalOpen(array $f, string $id='a', ?int $expires=null): Rehearsal
{
    return Rehearsal::open($f['preflight'],hash('sha256','SYNTHETIC rehearsal '.$id),$expires??time()+120);
}
function rehearsalStep(Rehearsal $r, string $stage): array
{
    $s=Simulation::named('baseline');$e=$r->issue($stage,$s);$r->accept($e,$s);return $e;
}
function rehearsalPrefix(Rehearsal $r,int $count): void
{
    foreach(array_slice(Rehearsal::STAGES,0,$count) as $stage){rehearsalStep($r,$stage);}
}
function rehearsalBlocked(Rehearsal $r,string $code): array
{
    $c=$r->finish()->content;
    same('BLOCKED',$c['status']);same([$code],$c['reasons']);
    same(false,$c['cutover_allowed']);same(false,$c['effects_allowed']);same('BLOCKED',$c['real_gate_status']);
    return $c;
}

test('F6-E-P1 end to end ordered rehearsal is verifiable read only and never grants cutover',function():void{
    rehearsalCase(function(array $f):void{
        $before=reconciliationState($f);$target=$f['recovery_reader']->inspect()->jsonSerialize()['fingerprint'];
        $r=rehearsalOpen($f);rehearsalPrefix($r,7);$c=$r->finish()->content;
        same('SYNTHETIC_REHEARSAL_COMPLETE',$c['status']);same([],$c['reasons']);
        same(7,$c['events_expected']);same(7,$c['events_accepted']);same(7,count($c['trace']));
        $previous=Contract::hash(['hostider.rehearsal-start.v1',$c['rehearsal_sha256']]);
        foreach($c['trace'] as $i=>$event){
            same(Rehearsal::STAGES[$i],$event['payload']['stage']);same($i+1,$event['payload']['ordinal']);
            same($previous,$event['payload']['previous_sha256']);same(Contract::hash($event['payload']),$event['event_sha256']);
            same(false,$event['payload']['effects_allowed']);same('NAMED_FIXTURE_ONLY',$event['payload']['simulation']);
            $previous=$event['event_sha256'];
        }
        same(Contract::hash($c['trace']),$c['trace_sha256']);
        same($f['preflight']->evaluate(Simulation::named('baseline'))->content['real_gates'],$c['real_gates']);
        same(30,count($c['real_gates']));same('OPEN',$c['real_gates']['G-O03']['status']);
        same(false,$c['cutover_allowed']);same(false,$c['effects_allowed']);same('BLOCKED',$c['real_gate_status']);
        same('NOT_VERIFIED',$c['global_snapshot_atomicity']);same(0,$c['native_writes']);same(0,$c['provider_operations_dispatched']);
        same($before,reconciliationState($f));same($target,$f['recovery_reader']->inspect()->jsonSerialize()['fingerprint']);
    });
});
test('F6-E-P1 identical identity lease evidence and order produce deterministic public trace',function():void{
    rehearsalCase(function(array $f):void{
        $expires=time()+120;$a=rehearsalOpen($f,expires:$expires);$b=rehearsalOpen($f,expires:$expires);
        rehearsalPrefix($a,7);rehearsalPrefix($b,7);
        $x=$a->finish()->jsonSerialize();same('SYNTHETIC_REHEARSAL_COMPLETE',$x['content']['status']);
        same($x,$b->finish()->jsonSerialize());same($x,$a->finish()->jsonSerialize());
    });
});
test('F6-E-P1 out of order issue permanently blocks the rehearsal',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);same('P1_EVENT_ORDER',raises(RuntimeException::class,fn()=>$r->issue('PIN_SNAPSHOT',Simulation::named('baseline')))->getMessage());
        raises(RuntimeException::class,fn()=>$r->issue('PREPARE',Simulation::named('baseline')));
        same(0,rehearsalBlocked($r,'P1_EVENT_ORDER')['events_accepted']);
    });
});
test('F6-E-P1 duplicate authentic event cannot advance twice',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);$e=rehearsalStep($r,'PREPARE');
        same('P1_EVENT_ORDER',raises(RuntimeException::class,fn()=>$r->accept($e,Simulation::named('baseline')))->getMessage());
        same(1,rehearsalBlocked($r,'P1_EVENT_ORDER')['events_accepted']);
    });
});
test('F6-E-P1 missing events expose only verified prefix and cannot complete',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);rehearsalPrefix($r,3);$c=rehearsalBlocked($r,'P1_MISSING_EVENTS');
        same(3,count($c['trace']));same(3,$c['events_accepted']);
    });
});
test('F6-E-P1 old token cannot replay into a new instance with the same rehearsal ID',function():void{
    rehearsalCase(function(array $f):void{
        $expires=time()+120;$old=rehearsalOpen($f,expires:$expires);$token=$old->issue('PREPARE',Simulation::named('baseline'));
        $fresh=rehearsalOpen($f,expires:$expires);
        same('P1_EVENT_AUTHENTICITY',raises(RuntimeException::class,fn()=>$fresh->accept($token,Simulation::named('baseline')))->getMessage());
        rehearsalBlocked($fresh,'P1_EVENT_AUTHENTICITY');
    });
});
test('F6-E-P1 evidence from another rehearsal identity is rejected',function():void{
    rehearsalCase(function(array $f):void{
        $a=rehearsalOpen($f,'a');$b=rehearsalOpen($f,'b');$token=$a->issue('PREPARE',Simulation::named('baseline'));
        $other=$b->issue('PREPARE',Simulation::named('baseline'));
        same(false,$token['payload']['rehearsal_sha256']===$other['payload']['rehearsal_sha256']);
        raises(RuntimeException::class,fn()=>$b->accept($token,Simulation::named('baseline')));
        rehearsalBlocked($b,'P1_EVENT_AUTHENTICITY');
    });
});
test('F6-E-P1 rehashing a tampered event fingerprint cannot replace its private MAC',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);$e=$r->issue('PREPARE',Simulation::named('baseline'));
        $e['payload']['preflight_sha256']=str_repeat('0',64);$e['mac']=Contract::hash($e['payload']);
        raises(RuntimeException::class,fn()=>$r->accept($e,Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_EVENT_AUTHENTICITY');
    });
});
test('F6-E-P1 own lease expiry rejects an already issued event',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f,expires:time()+2);$e=$r->issue('PREPARE',Simulation::named('baseline'));usleep(2100000);
        raises(RuntimeException::class,fn()=>$r->accept($e,Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_LEASE_EXPIRED');
        raises(InvalidArgumentException::class,fn()=>rehearsalOpen($f,expires:time()-1));
        raises(InvalidArgumentException::class,fn()=>rehearsalOpen($f,expires:time()+600));
    });
});
test('F6-E-P1 longer rehearsal lease cannot bypass an expired P0 reader lease',function():void{
    rehearsalCase(function(array $f):void{
        $p=Preflight::open($f['reader'],$f['recovery_reader'],$f['context'],$f['loaded']['checkpoint'],time()+2);
        $r=Rehearsal::open($p,hash('sha256','SYNTHETIC short P0'),time()+120);
        $e=$r->issue('PREPARE',Simulation::named('baseline'));usleep(2100000);
        raises(RuntimeException::class,fn()=>$r->accept($e,Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_PREFLIGHT_BLOCKED');
    });
});
test('F6-E-P1 old snapshot cannot be consumed after source root changes',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);$e=$r->issue('PREPARE',Simulation::named('baseline'));
        $f['setup']->execute("UPDATE f6b_source_snapshots SET snapshot_hash=REPEAT('0',64)");
        raises(RuntimeException::class,fn()=>$r->accept($e,Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_PREFLIGHT_BLOCKED');
    });
});
test('F6-E-P1 different restored logical root with unchanged counts blocks pinning',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);rehearsalPrefix($r,1);$before=$f['recovery_reader']->inspect()->content;
        $f['target']['setup']->execute("UPDATE f6b_entities SET payload_hash=REPEAT('0',64) ORDER BY target_id LIMIT 1");
        $after=$f['recovery_reader']->inspect()->content;
        same(false,$before['observed_target_evidence_sha256']===$after['observed_target_evidence_sha256']);
        foreach($before['source_target_counts'] as $table=>$count){same($count['target'],$after['source_target_counts'][$table]['target']);}
        raises(RuntimeException::class,fn()=>$r->issue('PIN_SNAPSHOT',Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_PREFLIGHT_BLOCKED');
    });
});
foreach([
    ['incomplete-delta',4,'FINAL_DELTA'],
    ['unknown-callback',3,'CHECK_IN_FLIGHT'],
    ['inflight-provider',3,'CHECK_IN_FLIGHT'],
    ['duplicate-actor',5,'CHECK_SINGLE_ACTOR'],
    ['missing-actor',5,'CHECK_SINGLE_ACTOR'],
    ['forged-gates',0,'PREPARE'],
    ['missing-gate',0,'PREPARE'],
] as [$scenario,$prefix,$stage]){
    test('F6-E-P1 existing P0 rejects rehearsal '.$scenario,function()use($scenario,$prefix,$stage):void{
        rehearsalCase(function(array $f)use($scenario,$prefix,$stage):void{
            $r=rehearsalOpen($f);rehearsalPrefix($r,$prefix);
            raises(RuntimeException::class,fn()=>$r->issue($stage,Simulation::named($scenario)));
            same($prefix,rehearsalBlocked($r,'P1_PREFLIGHT_BLOCKED')['events_accepted']);
        });
    });
}
test('F6-E-P1 missing final delta cannot skip to single actor check',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);rehearsalPrefix($r,4);
        raises(RuntimeException::class,fn()=>$r->issue('CHECK_SINGLE_ACTOR',Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_EVENT_ORDER');
    });
});
test('F6-E-P1 contradictory final delta rejects a previously issued baseline event',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);rehearsalPrefix($r,4);$e=$r->issue('FINAL_DELTA',Simulation::named('baseline'));
        reconciliationMutate($f,'wallet','wallet-a',static function(array $p):array{$p['liability']['minor']='9999';return $p;});
        raises(RuntimeException::class,fn()=>$r->accept($e,Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_PREFLIGHT_BLOCKED');
    });
});
test('F6-E-P1 final report rereads P0 and rejects evidence tampered after all seven events',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);rehearsalPrefix($r,7);$old=$r->finish()->jsonSerialize();
        same('SYNTHETIC_REHEARSAL_COMPLETE',$old['content']['status']);
        $f['target']['setup']->execute("UPDATE f6b_entities SET owner_key=REPEAT('0',64) WHERE entity_type='attachment'");
        $now=rehearsalBlocked($r,'P1_PREFLIGHT_BLOCKED');
        same(7,$now['events_accepted']);same(false,$old['fingerprint']===Contract::hash($now));
    });
});
test('F6-E-P1 trace copy tamper cannot change internal history or create an accepted input',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);rehearsalPrefix($r,7);$original=$r->finish()->jsonSerialize();$copy=$original;
        $copy['content']['trace'][0]['payload']['effects_allowed']=true;$copy['fingerprint']=Contract::hash($copy['content']);
        same($original,$r->finish()->jsonSerialize());
        raises(RuntimeException::class,fn()=>$r->accept($copy['content']['trace'][0],Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_EVENT_FORMAT');
    });
});
test('F6-E-P1 private data keys signatures event MACs and malformed input never enter the report',function():void{
    rehearsalCase(function(array $f):void{
        $r=rehearsalOpen($f);$token=$r->issue('PREPARE',Simulation::named('baseline'));$r->accept($token,Simulation::named('baseline'));
        foreach(array_slice(Rehearsal::STAGES,1) as $stage){rehearsalStep($r,$stage);}
        $text=json_encode($r->finish(),JSON_THROW_ON_ERROR);$outer=json_decode($f['capsule'],true,16,JSON_THROW_ON_ERROR);
        foreach([$token['mac'],$f['target_password'],$f['secret'],$f['schema'],$f['target']['schema'],$f['binding']['account'],
            base64_encode($f['key']),base64_encode($f['recovery_secret']),$outer['signature'],$outer['ciphertext'],
            'SYNTHETIC message private-a','synthetic/private-a.pdf',operationsPath($f['vault'])] as $secret){
            same(false,str_contains($text,$secret));
        }
        same(false,str_contains($text,'"mac"'));
        $token['private_message']='SYNTHETIC forbidden private data';
        raises(RuntimeException::class,fn()=>$r->accept($token,Simulation::named('baseline')));
        same(false,str_contains(json_encode($r->finish(),JSON_THROW_ON_ERROR),$token['private_message']));
    });
});
test('F6-E-P1 ordinary JSON and old Report fingerprint cannot authorize a rehearsal',function():void{
    rehearsalCase(function(array $f):void{
        $fake=new Report(['status'=>'SYNTHETIC_PREFLIGHT_COMPLETE','cutover_allowed'=>true]);
        raises(TypeError::class,fn()=>Rehearsal::open($fake,hash('sha256','SYNTHETIC fake'),time()+120));
        $r=rehearsalOpen($f);
        raises(TypeError::class,fn()=>$r->issue('PREPARE',json_decode('{"all_gates":true}',true)));
        raises(RuntimeException::class,fn()=>$r->accept($fake->jsonSerialize(),Simulation::named('baseline')));
        rehearsalBlocked($r,'P1_EVENT_FORMAT');
    });
});
test('F6-E-P1 live purpose environment and event permission escalation remain denied',function():void{
    rehearsalCase(function(array $f):void{
        raises(InvalidArgumentException::class,fn()=>Rehearsal::open($f['preflight'],hash('sha256','SYNTHETIC purpose'),time()+120,'CUTOVER_GO'));
        $r=rehearsalOpen($f);$e=$r->issue('PREPARE',Simulation::named('baseline'));$e['payload']['effects_allowed']=true;
        raises(RuntimeException::class,fn()=>$r->accept($e,Simulation::named('baseline')));rehearsalBlocked($r,'P1_EVENT_SCOPE');
        $s=rehearsalOpen($f);putenv('SYNTHETIC_REHEARSAL_ALLOWED=0');
        try{raises(RuntimeException::class,fn()=>$s->issue('PREPARE',Simulation::named('baseline')));}
        finally{putenv('SYNTHETIC_REHEARSAL_ALLOWED=1');}
        rehearsalBlocked($s,'P1_ENVIRONMENT_DENIED');
    });
});
try{$exit=executeTests();}finally{targetCleanup();}
exit($exit);
