<?php
declare(strict_types=1);
// Only a loopback test fixture simulates HTTPS; production code never trusts forwarded input.
if (getenv('APP_ENV')!=='test') { http_response_code(404); exit; }
$_SERVER['HTTPS']='on';
return require dirname(__DIR__).'/public/index.php';
