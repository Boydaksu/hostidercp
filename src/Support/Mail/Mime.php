<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Core\Http\HttpError;
/** Deliberately strict, bounded MIME subset; unsupported input never becomes trusted text. */
final class Mime
{
    public static function address(string $v): string{if(!preg_match('/^[A-Za-z0-9.!#$%&\x27*+\/=?^_`{|}~-]{1,64}@[A-Za-z0-9.-]{1,190}$/D',$v)||filter_var($v,FILTER_VALIDATE_EMAIL)===false){throw new HttpError(422,'MAIL_ADDRESS_INVALID');}return strtolower($v);}
    public static function messageId(string $v): string{if(!preg_match('/^<[A-Za-z0-9.!#$%&\x27*+\/=?^_`{|}~-]{1,128}@[A-Za-z0-9.-]{1,128}>$/D',$v)){throw new HttpError(422,'MAIL_MESSAGE_ID_INVALID');}return $v;}
    public static function parse(string $raw): array
    {
        if(strlen($raw)>131072||strlen($raw)<4||str_contains($raw,"\0")||preg_match('/(?<!\r)\n|\r(?!\n)/',$raw)){throw new HttpError(422,'MAIL_RAW_LIMIT_OR_FORMAT');}
        [$h,$body]=self::part($raw);$from=self::address($h['from']??'');self::address($h['to']??'');$id=isset($h['message-id'])?self::messageId($h['message-id']):null;$refs=[];
        foreach(['in-reply-to','references'] as $key){if(isset($h[$key])){foreach(explode(' ',$h[$key]) as $ref){if($ref!==''){$refs[]=self::messageId($ref);}}}}$refs=array_values(array_unique($refs));if(count($refs)>10){throw new HttpError(422,'MAIL_REFERENCE_LIMIT');}
        $subject=$h['subject']??'';if($subject===''||mb_strlen($subject)>191||!mb_check_encoding($subject,'UTF-8')||str_contains($subject,'=?')){throw new HttpError(422,'MAIL_SUBJECT_UNSUPPORTED');}
        $loop=($h['auto-submitted']??'no')!=='no'||($h['return-path']??'')==='<' .'>'||in_array(strtolower($h['precedence']??''),['bulk','junk','list'],true)||isset($h['x-hostider-loop'])||str_starts_with(strtolower($h['content-type']??''),'multipart/report');
        $files=[];$text='';if(!$loop){$type=strtolower($h['content-type']??'text/plain; charset=utf-8');
            if(str_starts_with($type,'multipart/mixed;')){if(!preg_match('/^multipart\/mixed; boundary="([A-Za-z0-9_-]{1,60})"$/iD',$h['content-type'],$m)){throw new HttpError(422,'MAIL_MULTIPART_UNSUPPORTED');}$sep='--'.$m[1];$parts=explode($sep,$body);if(count($parts)<3||count($parts)>7||trim(array_shift($parts))!==''||trim(array_pop($parts))!=='--'){throw new HttpError(422,'MAIL_MULTIPART_LIMIT');}foreach($parts as $n=>$part){if(!str_starts_with($part,"\r\n")||!str_ends_with($part,"\r\n")){throw new HttpError(422,'MAIL_MULTIPART_INVALID');}[$ph,$pb]=self::part(substr($part,2,-2));if($n===0){$text=self::text($ph,$pb);}else{if(strtolower($ph['content-type']??'')!=='application/pdf'||($ph['content-transfer-encoding']??'')!=='base64'||!preg_match('/^attachment; filename="([^"]{1,100})"$/D',$ph['content-disposition']??'',$name)){throw new HttpError(422,'MAIL_ATTACHMENT_UNSUPPORTED');}$bytes=base64_decode(str_replace("\r\n",'',$pb),true);if($bytes===false){throw new HttpError(422,'MAIL_ATTACHMENT_ENCODING');}Attachments::validate($name[1],$bytes);$files[]=['name'=>$name[1],'bytes'=>$bytes];}}}
            else{$text=self::text($h,$body);}}
        if(count($files)>4||array_sum(array_map(fn($f)=>strlen($f['bytes']),$files))>65536){throw new HttpError(413,'MAIL_ATTACHMENT_TOTAL');}
        return ['from'=>$from,'message_id'=>$id,'references'=>$refs,'reply_token'=>$h['x-hostider-reply-token']??null,'subject'=>$subject,'body'=>$text,'files'=>$files,'loop'=>$loop];
    }
    private static function part(string $raw): array
    {
        $pair=explode("\r\n\r\n",$raw,2);if(count($pair)!==2||strlen($pair[0])>8192){throw new HttpError(422,'MAIL_HEADER_LIMIT');}$lines=explode("\r\n",$pair[0]);if(count($lines)>40){throw new HttpError(422,'MAIL_HEADER_LIMIT');}$headers=[];
        foreach($lines as $l){if(strlen($l)>998||!preg_match('/^([A-Za-z0-9-]{1,60}):[ \t]*([^\r\n\x00-\x1f]*)$/D',$l,$m)){throw new HttpError(422,'MAIL_HEADER_INVALID');}$k=strtolower($m[1]);if(isset($headers[$k])){throw new HttpError(422,'MAIL_DUPLICATE_HEADER');}$headers[$k]=trim($m[2]);}return [$headers,$pair[1]];
    }
    private static function text(array $h,string $body): string
    {
        if(!in_array(strtolower($h['content-type']??'text/plain; charset=utf-8'),['text/plain','text/plain; charset=utf-8'],true)||!in_array(strtolower($h['content-transfer-encoding']??'8bit'),['8bit','7bit'],true)||strlen($body)>16000||trim($body)===''||!mb_check_encoding($body,'UTF-8')||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$body)){throw new HttpError(422,'MAIL_BODY_UNSUPPORTED');}return $body;
    }
}
