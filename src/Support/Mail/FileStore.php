<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Security\Crypto;
use Hostider\Support\Access;
final readonly class FileStore
{
    private string $root;
    public function __construct(string $root,string $public,private Crypto $crypto)
    {
        if(!is_dir($root)&&!mkdir($root,0700,true)&&!is_dir($root)){throw new HttpError(503,'MAIL_STORAGE_UNAVAILABLE');}$r=realpath($root);$p=realpath($public);if(!$r||!$p||is_link($root)||str_starts_with(strtolower(str_replace('\\','/',$r)).'/',strtolower(str_replace('\\','/',$p)).'/')){throw new HttpError(503,'MAIL_STORAGE_UNSAFE');}if(PHP_OS_FAMILY!=='Windows'&&(fileperms($r)&0077)!==0){throw new HttpError(503,'MAIL_STORAGE_UNSAFE');}$this->root=$r;
    }
    private function path(string $id): string{Access::id($id);$p=$this->root.DIRECTORY_SEPARATOR.$id.'.bin';if(is_link($p)){throw new HttpError(409,'MAIL_FILE_LINK');}return $p;}
    public function put(string $id,string $bytes): void
    {
        $p=$this->path($id);$v=$this->crypto->encrypt($bytes,'support-file:'.$id);$lock=fopen($this->root.DIRECTORY_SEPARATOR.'.lock','c+b');if(!$lock){throw new HttpError(503,'MAIL_STORAGE_UNAVAILABLE');}try{if(!flock($lock,LOCK_EX)){throw new HttpError(503,'MAIL_STORAGE_UNAVAILABLE');}$n=0;foreach(new \DirectoryIterator($this->root) as $entry){if($entry->getExtension()==='bin'&&++$n>=4096){throw new HttpError(503,'MAIL_STORAGE_BUDGET');}}$f=fopen($p,'x+b');if(!$f){throw new HttpError(503,'MAIL_STORAGE_UNAVAILABLE');}try{if(PHP_OS_FAMILY!=='Windows'&&!chmod($p,0600)){throw new HttpError(503,'MAIL_STORAGE_UNSAFE');}if(fwrite($f,$v)!==strlen($v)||!fflush($f)){throw new HttpError(503,'MAIL_STORAGE_WRITE');}}finally{fclose($f);}}finally{fclose($lock);}
    }
    public function read(string $id,int $size,string $sha): string
    {
        $p=$this->path($id);clearstatcache(true,$p);if(!is_file($p)||filesize($p)>24000||(PHP_OS_FAMILY!=='Windows'&&(fileperms($p)&0077)!==0)){throw new HttpError(404,'MAIL_FILE_UNAVAILABLE');}$raw=file_get_contents($p);if(!is_string($raw)){throw new HttpError(404,'MAIL_FILE_UNAVAILABLE');}$v=$this->crypto->decrypt($raw,'support-file:'.$id);if(strlen($v)!==$size||!hash_equals($sha,hash('sha256',$v))){throw new HttpError(409,'MAIL_FILE_INTEGRITY');}return $v;
    }
    public function remove(string $id): void{$p=$this->path($id);if(is_file($p)&&!unlink($p)){throw new HttpError(503,'MAIL_STORAGE_REMOVE');}}
    public function orphans(array $known,int $limit=25): array{$removed=[];$n=0;foreach(new \DirectoryIterator($this->root) as $file){if(++$n>4098||count($removed)>=$limit){break;}$name=$file->getFilename();if($file->isLink()||!preg_match('/^([a-f0-9-]{36})\.bin$/D',$name,$m)||$file->getMTime()>time()-86400||in_array($m[1],$known,true)){continue;}$this->remove($m[1]);$removed[]=$m[1];}return $removed;}
}
