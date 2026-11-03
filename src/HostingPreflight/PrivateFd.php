<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final class PrivateFd
{
    public static function uid(): ?int
    {
        if(function_exists('posix_geteuid')){return posix_geteuid();}
        try{$s=@file_get_contents('/proc/self/status',false,null,0,8192);return is_string($s)&&preg_match('/^Uid:\s+[0-9]+\s+([0-9]+)/m',$s,$m)===1?(int)$m[1]:null;}catch(\Throwable){return null;}
    }
    /** Never uses writable modes or accepts path/pipe/socket descriptors. */
    public static function read(int $fd): array
    {
        if($fd<3||$fd>64){throw new \InvalidArgumentException('Private descriptor');}
        $h=null;
        try{
            $info=@file_get_contents('/proc/self/fdinfo/'.$fd,false,null,0,8192);
            if(!is_string($info)||preg_match('/^flags:\s+([0-7]+)/m',$info,$m)!==1||(octdec($m[1])&3)!==0){throw new \InvalidArgumentException('Read-only descriptor required');}
            $h=@fopen('php://fd/'.$fd,'rb');if(!is_resource($h)){throw new \InvalidArgumentException('Private descriptor');}
            $s=fstat($h);$uid=self::uid();
            if($s===false||$uid===null||($s['mode']&0170000)!==0100000||!in_array($s['mode']&0777,[0400,0600],true)||$s['uid']!==$uid||$s['nlink']!==1||$s['size']<2||$s['size']>8192||ftell($h)!==0){throw new \InvalidArgumentException('Private descriptor metadata');}
            $raw=stream_get_contents($h,8193);if(!is_string($raw)||strlen($raw)!==$s['size']){throw new \InvalidArgumentException('Private descriptor size');}
            $v=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($v)||array_is_list($v)){throw new \InvalidArgumentException('Private descriptor object');}return $v;
        }finally{if(is_resource($h)){fclose($h);}}
    }
}
