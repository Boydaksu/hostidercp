<?php
declare(strict_types=1);
namespace Hostider\Billing;
use Hostider\Core\Http\HttpError;
final readonly class DocumentStore
{
    private string $root;
    public function __construct(string $root,string $publicRoot)
    {
        if(!is_dir($root)&&!mkdir($root,0700,true)&&!is_dir($root)){throw new \RuntimeException('Document storage unavailable');}
        $resolved=realpath($root);$public=realpath($publicRoot);
        if($resolved===false||$public===false||str_starts_with(strtolower(str_replace('\\','/',$resolved)).'/',strtolower(str_replace('\\','/',$public)).'/')){throw new \RuntimeException('Protected document root required');}
        $this->root=$resolved;
    }
    private function path(string $id): string
    {
        if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$id)){throw new HttpError(422,'INVALID_UPLOAD_ID');}
        $path=$this->root.DIRECTORY_SEPARATOR.$id.'.pdf';if(is_link($path)){throw new HttpError(409,'DOCUMENT_LINK_FORBIDDEN');}return $path;
    }
    public function append(string $id,int $offset,string $bytes): int
    {
        if($offset<0||strlen($bytes)<1||strlen($bytes)>32768||$offset+strlen($bytes)>10485760){throw new HttpError(413,'DOCUMENT_UPLOAD_LIMIT');}
        $path=$this->path($id);clearstatcache(true,$path);if(($offset===0&&file_exists($path))||($offset>0&&!is_file($path))){throw new HttpError(409,'UPLOAD_FILE_STATE_CONFLICT');}$file=fopen($path,$offset===0?'x+b':'r+b');if($file===false){throw new HttpError(409,'UPLOAD_FILE_UNAVAILABLE');}
        try{if(!flock($file,LOCK_EX)){throw new \RuntimeException('Upload lock unavailable');}$stat=fstat($file);if($stat['size']!==$offset){throw new HttpError(409,'UPLOAD_OFFSET_CONFLICT');}fseek($file,$offset);if(fwrite($file,$bytes)!==strlen($bytes)||!fflush($file)){throw new \RuntimeException('Incomplete upload');}return $offset+strlen($bytes);}
        finally{fclose($file);}
    }
    public function read(string $id,int $size): string
    {
        $path=$this->path($id);clearstatcache(true,$path);
        if(!is_file($path)||filesize($path)!==$size||$size<8||$size>10485760){throw new HttpError(409,'DOCUMENT_SIZE_MISMATCH');}
        $bytes=file_get_contents($path);if($bytes===false){throw new HttpError(409,'DOCUMENT_UNAVAILABLE');}return $bytes;
    }
    public function validate(string $bytes): void
    {
        if(!str_starts_with($bytes,'%PDF-')||(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes)!=='application/pdf'||!preg_match('/%%EOF\s*$/D',$bytes)){throw new HttpError(422,'VALID_PDF_REQUIRED');}
        // Defense in depth. Downloads are attachments with sandbox; this is not a full PDF sanitizer.
        if(preg_match('~/(JavaScript|JS|OpenAction|Launch|EmbeddedFile|AA)\b~i',$bytes)){throw new HttpError(422,'ACTIVE_PDF_FORBIDDEN');}
    }
}
