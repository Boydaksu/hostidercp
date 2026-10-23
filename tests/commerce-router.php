<?php
declare(strict_types=1);
if(getenv('APP_ENV')!=='test'){http_response_code(404);exit;}
if(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)==='/__http_test_ready'){
    $cache=function_exists('opcache_get_status')?opcache_get_status(false):false;
    header('Content-Type: application/json');echo json_encode(['instance'=>getenv('HTTP_TEST_INSTANCE'),'pid'=>getmypid(),'runtime'=>['jit'=>ini_get('opcache.jit'),'jit_buffer_size'=>ini_get('opcache.jit_buffer_size'),'opcache_enabled'=>$cache['opcache_enabled']??false,'jit_status'=>$cache['jit']??null]],JSON_THROW_ON_ERROR);return;
}
return require __DIR__.'/identity-router.php';
