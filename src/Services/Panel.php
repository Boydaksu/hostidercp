<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\Http\Response;
final class Panel
{
    public static function response(string $context): Response
    {
        $title=$context==='admin'?'Hizmet Yönetimi':'Hizmetlerim';return new Response(200,[],html:'<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="services-context" content="'.$context.'"><title>Hostider · '.$title.'</title><link rel="stylesheet" href="/assets/identity.css"><link rel="stylesheet" href="/assets/commerce.css"><script src="/assets/services.js" defer></script></head><body><aside id="sidebar"><a class="brand" href="/'.$context.'">hostider<span>automation</span></a><div class="workspace">'.$title.'</div><nav id="navigation" aria-label="Hizmet yönetimi"></nav><a class="identity-link" href="/'.$context.'/commerce">Satış ve ödemeler</a><a class="identity-link" href="/'.$context.'">Hesap ve güvenlik</a></aside><div class="shell"><header><div><span class="eyebrow">HOSTIDER / HİZMETLER</span><h1 id="heading">'.$title.'</h1></div><div id="account"></div></header><div id="scope"></div><main id="main" aria-live="polite"></main><div id="notice" role="status"></div><footer>Test ortamı · İşlemler mock sağlayıcıya aittir. Canlı hizmet kurulumu kapalıdır.</footer></div><dialog id="review" aria-labelledby="review-title"></dialog></body></html>');
    }
}
