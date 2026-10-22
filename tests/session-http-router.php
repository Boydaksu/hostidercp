<?php
declare(strict_types=1);
// Test-only fixture; never referenced by public/index.php or the production router.
require dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Core\Security\Session;
use Hostider\Core\Http\HttpError;
if (getenv('APP_ENV') !== 'test') { http_response_code(404); exit; }
session_save_path(getenv('SESSION_TEST_DIR'));
$_SERVER['HTTPS']='on'; // Loopback fixture emulates TLS termination; production never does this.
try {
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    if ($path==='/rotate') { Session::rotate('admin'); }
    elseif ($path==='/end') { Session::end('admin'); }
    elseif ($path==='/read') { Session::start('admin'); }
    else { http_response_code(404); }
} catch (HttpError $error) { http_response_code($error->status); }
header('Content-Type: application/json'); echo '{}';
