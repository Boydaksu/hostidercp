<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
final class Factory
{
    public static function files(Common $m): Attachments{$root=dirname(__DIR__,3);return new Attachments($m,new FileStore($root.'/storage/private/support-mail/'.hash('sha256',$m->c->config->dsn),$root.'/public',$m->crypto));}
}
