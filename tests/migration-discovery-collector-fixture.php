<?php
declare(strict_types=1);
require_once __DIR__.'/migration-import-fixture.php';
require_once __DIR__.'/migration-inventory-fixture.php';
use Hostider\Migration\Discovery\{Collector,MetadataSession};

function goFixture(string $mode='reader'):array
{
    $admin=importAdmin();$root=$admin['root'];$schema='go03'.bin2hex(random_bytes(8));
    if($mode==='underscore'){$schema='go03_'.bin2hex(random_bytes(8));}
    $user='go03'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(32));
    $host=(string)$root->execute("SELECT SUBSTRING_INDEX(USER(),'@',-1)")->fetchColumn();
    if($host!=='localhost'&&filter_var($host,FILTER_VALIDATE_IP)===false){throw new RuntimeException('GO_TEST_HOST');}
    $role='go03'.bin2hex(random_bytes(8));$GLOBALS['go_owned'][]=[$root,$schema,$user,$host,$role];
    $root->execute('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    $root->execute("CREATE USER '".$user."'@'".$host."' IDENTIFIED BY '".$password."'");
    $scope=str_replace('_','\\_',$schema);
    if($mode!=='none'){$root->execute("GRANT SELECT ON `".$scope."`.* TO '".$user."'@'".$host."'".($mode==='grant_option'?' WITH GRANT OPTION':''));}
    $root->execute('CREATE TABLE `'.$schema.'`.synthetic_inventory (id BIGINT NOT NULL,code VARCHAR(32) NOT NULL,value VARCHAR(64) NULL,PRIMARY KEY(id),UNIQUE KEY synthetic_unique(code),KEY synthetic_composite(value,code)) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    $root->execute('INSERT INTO `'.$schema.'`.synthetic_inventory VALUES (1,?,?)',['SYNTHETIC_CODE_ONLY','SYNTHETIC_ROW_CANARY_NOT_REAL_PII']);
    if($mode==='writer'){$root->execute("GRANT UPDATE ON `".$scope."`.* TO '".$user."'@'".$host."'");}
    if($mode==='global'){$root->execute("GRANT SELECT ON *.* TO '".$user."'@'".$host."'");}
    if($mode==='table_writer'){$root->execute("GRANT INSERT ON `".$schema."`.synthetic_inventory TO '".$user."'@'".$host."'");}
    if($mode==='role'){$root->execute('CREATE ROLE '.$role);$root->execute("GRANT ".$role." TO '".$user."'@'".$host."'");}
    if($mode==='routine'){$root->execute('CREATE PROCEDURE `'.$schema.'`.synthetic_routine() SELECT 1');$root->execute("GRANT EXECUTE ON PROCEDURE `".$schema."`.synthetic_routine TO '".$user."'@'".$host."'");}
    $directory=sys_get_temp_dir().'/go03-test-'.bin2hex(random_bytes(12));
    if(!mkdir($directory,0700)){throw new RuntimeException('GO_TEST_DIRECTORY');}
    $GLOBALS['go_directories'][]=$directory;
    return ['root'=>$root,'schema'=>$schema,'user'=>$user,'password'=>$password,'host'=>$host,'port'=>$admin['port'],
        'directory'=>$directory,'key'=>random_bytes(32),'source'=>'source-'.inventoryId('go-source'),
        'snapshot'=>'snapshot-'.inventoryId('go-snapshot')];
}
function goCleanup():void
{
    foreach(array_reverse($GLOBALS['go_owned']??[]) as [$db,$schema,$user,$host,$role]){
        if(preg_match('/^go03_?[a-f0-9]{16}$/D',$schema)!==1||preg_match('/^go03[a-f0-9]{16}$/D',$user)!==1||preg_match('/^go03[a-f0-9]{16}$/D',$role)!==1){throw new RuntimeException('GO_TEST_OWNERSHIP');}
        $db->execute('DROP DATABASE IF EXISTS `'.$schema.'`');$db->execute("DROP USER IF EXISTS '".$user."'@'".$host."'");$db->execute('DROP ROLE IF EXISTS '.$role);
    }$GLOBALS['go_owned']=[];
    foreach($GLOBALS['go_directories']??[] as $d){if(preg_match('~/go03-test-[a-f0-9]{24}$~D',$d)!==1){throw new RuntimeException('GO_TEST_DIRECTORY_OWNERSHIP');}rmdir($d);}$GLOBALS['go_directories']=[];
}
function goCase(callable $body,string $mode='reader'):void {try{$body(goFixture($mode));}finally{goCleanup();}}
function goSession(array $f):MetadataSession {return MetadataSession::connect($f['port'],$f['user'],$f['password'],MetadataSession::PURPOSE);}
function goRun(array $f,?MetadataSession $s=null):array {return (new Collector())->collect($s??goSession($f),$f['schema'],$f['source'],$f['snapshot'],$f['directory'],$f['key']);}
function goRaw(array $f,MetadataSession $s):array
{
    $r=['schema'=>$s->read('schema',['schema'=>$f['schema']]),'tables'=>[]];
    foreach($s->read('tables',['schema'=>$f['schema']]) as $t){
        $t['columns']=$s->read('columns',['schema'=>$f['schema'],'table'=>$t['TABLE_NAME']]);
        $t['indexes']=$s->read('indexes',['schema'=>$f['schema'],'table'=>$t['TABLE_NAME']]);$r['tables'][]=$t;
    }return $r;
}
function goExport(array $f,array $raw):array
{
    return (new ReflectionMethod(Collector::class,'export'))->invoke(null,$raw,$f['source'],$f['snapshot'],'2026-10-29T00:00:00Z');
}
function goChild(array $f,array $extra=[],bool $ack=true,bool $badPermissions=false):array
{
    $config=tmpfile();$key=tmpfile();
    try{
        $data=['port'=>$f['port'],'account'=>$f['user'],'password'=>$f['password'],'schema'=>$f['schema'],'source_id'=>$f['source'],'snapshot_id'=>$f['snapshot']];
        fwrite($config,json_encode($data,JSON_THROW_ON_ERROR));fwrite($key,$f['key']);rewind($config);rewind($key);
        if($badPermissions){chmod(stream_get_meta_data($config)['uri'],0644);}
        $cmd=[PHP_BINARY,dirname(__DIR__).'/bin/discovery-whmcs.php','--config-fd=3','--key-fd=4','--private-dir='.$f['directory']];
        if($ack){$cmd[]='--ack-owner-approved-read-only-discovery';}array_push($cmd,...$extra);
        $proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w'],3=>$config,4=>$key],$pipes,dirname(__DIR__));
        if(!is_resource($proc)){throw new RuntimeException('GO_TEST_CHILD');}
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
        return ['exit'=>$exit,'stderr'=>$err,'out'=>$out,'report'=>json_decode($out,true,32,JSON_THROW_ON_ERROR)];
    }finally{fclose($config);fclose($key);}
}
