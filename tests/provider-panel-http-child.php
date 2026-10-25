<?php
declare(strict_types=1);
// Secrets arrive only through the pipe; output contains status/code, never cookies or request data.
if(getenv('APP_ENV')!=='test'){exit(2);}
$p=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
if(!preg_match('~^http://127\.0\.0\.1:[0-9]+/api/v1/(client|admin)/provider-panel/execute$~D',$p['url'])){exit(2);}
$curl=curl_init($p['url']);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIE=>$p['cookie'],CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.$p['csrf'],'Idempotency-Key: '.$p['key']],CURLOPT_POSTFIELDS=>json_encode($p['body'],JSON_THROW_ON_ERROR)]);$body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$r=is_string($body)?json_decode($body,true):null;curl_close($curl);echo json_encode(['status'=>$status,'code'=>$r['errors'][0]['code']??null,'operation_id'=>$r['data']['operation_id']??null],JSON_THROW_ON_ERROR);exit($status===0?1:0);
