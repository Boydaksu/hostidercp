<?php
declare(strict_types=1);
if(getenv('APP_ENV')!=='test'){http_response_code(404);exit;}
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if(is_string($path)&&preg_match('~^/assets/[a-z0-9-]+\.(?:js|css)$~D',$path)&&is_file(dirname(__DIR__).'/public'.$path)){return false;}
return require __DIR__.'/commerce-router.php';
