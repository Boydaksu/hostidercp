<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';
use Hostider\Core\Security\Session;
test('session HTTPS/cookie separation, rotation and expiry reset', function (): void {
    $_SERVER['HTTPS']='off'; raises(RuntimeException::class,fn()=>Session::start('admin'));
    $_SERVER['HTTPS']='on'; Session::start('admin');
    same('__Host-hostider_admin',session_name());
    $params=session_get_cookie_params(); same(true,$params['secure']); same(true,$params['httponly']); same('Strict',$params['samesite']);
    $id=session_id(); Session::rotate(); same(false,$id===session_id()); same(64,strlen($_SESSION['csrf']));
    $_SESSION['principal']='test'; $_SESSION['_seen']=time()-2000;
    session_write_close(); Session::start('admin'); same(false,isset($_SESSION['principal'])); Session::end();
    Session::start('client'); same('__Host-hostider_client',session_name()); Session::end();
});
runTests();
