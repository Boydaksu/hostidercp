<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final class Filesystem
{
    private static function beneath(string $path,string $root): bool {return $path===$root||str_starts_with($path,$root.'/');}
    /** Resolve no links or dot traversal. Never create/read file contents/change permissions. */
    private static function path(string $path,bool $directory): array
    {
        if(!str_starts_with($path,'/')||strlen($path)>4096||preg_match('/[\x00-\x1f\\\\]/',$path)||in_array('..',explode('/',$path),true)||in_array('.',explode('/',$path),true)){throw new \InvalidArgumentException('Unsafe path');}
        $path=rtrim($path,'/');if($path===''){$path='/';}
        $p=$path;for($i=0;$i<128;++$i){if(is_link($p)){throw new \InvalidArgumentException('Linked path');}if($p==='/'){break;}$p=dirname($p);if($i===127){throw new \InvalidArgumentException('Path bound');}}
        $s=lstat($path);$real=realpath($path);
        if($s===false||$real!==$path||($s['mode']&0170000)!==($directory?0040000:0100000)||(!$directory&&$s['nlink']!==1)){throw new \InvalidArgumentException('Path metadata');}
        return ['path'=>$path,'mode'=>$s['mode']&07777,'owner'=>$s['uid'],'readable'=>is_readable($path),'writable'=>is_writable($path),'executable'=>$directory?is_executable($path):false];
    }
    private static function inspectPath(string $path,bool $directory,?string $public,string $kind): array
    {
        try{
            $v=self::path($path,$directory);
            if($public!==null&&self::beneath($v['path'],$public)){return ['FAIL','UNSAFE'];}
            if($kind==='config'){$uid=PrivateFd::uid();if(!in_array($v['mode'],[0400,0600],true)||!$v['readable']||($uid!==null&&$v['owner']!==$uid)){return ['FAIL','UNSAFE'];}return $uid===null?['UNKNOWN','UNVERIFIED']:['PASS','OBSERVED'];}
            // Shared files-session directories may legitimately be sticky and administrator-owned.
            $sharedSticky=$kind==='session'&&($v['mode']&01000)!==0;
            if(($v['mode']&0022)!==0&&!$sharedSticky){return ['FAIL','UNSAFE'];}
            return $v['readable']&&$v['writable']&&$v['executable']?['PASS','OBSERVED']:['FAIL','ACCESS_UNAVAILABLE'];
        }catch(\InvalidArgumentException){return ['FAIL','UNSAFE'];}catch(\Throwable){return ['UNKNOWN','ACCESS_UNAVAILABLE'];}
    }
    public static function inspect(array $deployment): array
    {
        $allowed=['webroot','config_file','storage','session_path','web_php_version'];
        if(array_diff(array_keys($deployment),$allowed)!==[]){throw new \InvalidArgumentException('Deployment fields');}
        foreach($deployment as $k=>$v){if($k==='web_php_version'){if(!is_int($v)||$v<10000||$v>999999){throw new \InvalidArgumentException('Web version');}}elseif(!is_string($v)||$v===''){throw new \InvalidArgumentException('Deployment path');}}
        $root=dirname(__DIR__,2);$public=$root.'/public';$out=[];
        if(!isset($deployment['webroot'])){$out['WEBROOT_PATH']=['UNKNOWN','UNVERIFIED'];}
        else{try{$v=self::path($deployment['webroot'],true);$out['WEBROOT_PATH']=$v['path']===$public&&$v['readable']&&$v['executable']?['PASS','OBSERVED']:['FAIL','UNSAFE'];}catch(\InvalidArgumentException){$out['WEBROOT_PATH']=['FAIL','UNSAFE'];}catch(\Throwable){$out['WEBROOT_PATH']=['UNKNOWN','ACCESS_UNAVAILABLE'];}}
        $out['CONFIG_FILE']=isset($deployment['config_file'])?self::inspectPath($deployment['config_file'],false,$public,'config'):['UNKNOWN','UNVERIFIED'];
        $storage=$deployment['storage']??$root.'/storage';$out['STORAGE_PATH']=$storage===$root.'/storage'?self::inspectPath($storage,true,$public,'storage'):['FAIL','UNSAFE'];
        $session=$deployment['session_path']??null;
        if($session===null&&ini_get('session.save_handler')==='files'){$parts=explode(';',(string)ini_get('session.save_path'));$session=end($parts);if($session===''){$session=sys_get_temp_dir();}}
        $out['SESSION_PATH']=ini_get('session.save_handler')==='files'&&is_string($session)&&$session!==''?self::inspectPath($session,true,$public,'session'):['UNKNOWN','UNVERIFIED'];
        $out['OPEN_BASEDIR']=['UNKNOWN','UNVERIFIED'];$out['WRITE_AND_LOCK']=['UNKNOWN','UNVERIFIED'];$out['DISK_CAPACITY']=['UNKNOWN','UNVERIFIED'];$out['BACKUP_HEADROOM']=['UNKNOWN','UNVERIFIED'];
        try{$v=self::path($storage,true);$free=disk_free_space($v['path']);if($free!==false){$out['DISK_CAPACITY']=$free>0?['UNKNOWN','UNVERIFIED']:['FAIL','MISSING'];$out['BACKUP_HEADROOM']=$free>=134217728?['PASS','OBSERVED']:['FAIL','MISSING'];if($free<=0){$out['STORAGE_PATH']=['FAIL','MISSING'];}}}catch(\Throwable){}
        return $out;
    }
}
