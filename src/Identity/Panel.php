<?php
declare(strict_types=1);
namespace Hostider\Identity;
use Hostider\Core\Http\Response;
final class Panel
{
    public static function response(string $context): Response
    {
        $title=$context==='admin'?'Yönetim Merkezi':'Müşteri Paneli';
        $html='<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="identity-context" content="'.$context.'"><title>Hostider · '.$title.'</title><link rel="stylesheet" href="/assets/identity.css"><script src="/assets/identity.js" defer></script></head><body><aside id="sidebar"><a class="brand" href="/'.$context.'">hostider<span>automation</span></a><div class="workspace">'.$title.'</div><nav id="navigation" aria-label="Ana menü"></nav><div class="sidebar-footer">Güvenli hesap yönetimi<br><small>Identity · F1</small></div></aside><div class="shell"><header><div><span class="eyebrow">HOSTIDER / '.$title.'</span><h1 id="heading">Hesabınıza hoş geldiniz</h1></div><div id="account"></div></header><main id="main" aria-live="polite"></main><div id="notice" role="status" aria-live="polite"></div><footer>Kimlik ve erişim yönetimi · Finans ve sağlayıcı işlemleri bu sürümde açık değildir.</footer></div></body></html>';
        return new Response(200,[],html:$html);
    }
}
