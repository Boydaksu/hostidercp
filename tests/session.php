<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';
ob_start();
use Hostider\Core\Security\Session;
test('session HTTPS/cookie separation, rotation and expiry reset', function (): void {
    $_SERVER['HTTPS']='off'; raises(RuntimeException::class,fn()=>Session::start('admin'));
    $_SERVER['HTTPS']='on'; Session::start('admin'); $_COOKIE[session_name()]=session_id();
    same('__Host-hostider_admin',session_name());
    $params=session_get_cookie_params(); same(true,$params['secure']); same(true,$params['httponly']); same('Strict',$params['samesite']);
    same(PHP_SESSION_NONE,session_status());
    $id=session_id(); $rotated=Session::rotate('admin'); same(false,$id===session_id()); same(64,strlen($rotated->data['csrf']));
    $_COOKIE[session_name()]=session_id();
    Session::mutate('admin',fn(array $data): array => $data+['principal'=>'test']);
    session_start(); $_SESSION['_seen']=time()-2000; session_write_close();
    raises(\Hostider\Core\Http\HttpError::class,fn()=>Session::start('admin'));
    unset($_COOKIE[session_name()]); $fresh=Session::start('admin'); same(false,isset($fresh->data['principal']));
    $_COOKIE[session_name()]=session_id(); Session::end('admin');
    Session::start('client'); same('__Host-hostider_client',session_name()); $_COOKIE[session_name()]=session_id(); Session::end('client');
});
runTests();
