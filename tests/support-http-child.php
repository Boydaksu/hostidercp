<?php
declare(strict_types=1);
if(getenv('APP_ENV')!=='test'){exit(2);}
$p=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
if(!preg_match('~^http://127\.0\.0\.1:[0-9]+/api/v1/admin/support/reply$~D',$p['url'])){exit(2);}
$c=curl_init($p['url']);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIE=>$p['cookie'],CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.$p['csrf'],'Idempotency-Key: '.$p['key']],CURLOPT_POSTFIELDS=>json_encode($p['body'],JSON_THROW_ON_ERROR)]);$raw=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);$r=is_string($raw)?json_decode($raw,true):null;echo json_encode(['status'=>$status,'data'=>$r['data']??null,'code'=>$r['errors'][0]['code']??null],JSON_THROW_ON_ERROR);exit($status===0?1:0);
