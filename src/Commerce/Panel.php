<?php
declare(strict_types=1);
namespace Hostider\Commerce;
use Hostider\Core\Http\Response;
final class Panel
{
    public static function response(string $context): Response
    {
        $title=$context==='admin'?'Satış ve Finans Yönetimi':'Alışveriş ve Ödemeler';
        return new Response(200,[],html:'<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="commerce-context" content="'.$context.'"><title>Hostider · '.$title.'</title><link rel="stylesheet" href="/assets/identity.css"><link rel="stylesheet" href="/assets/commerce.css"><script src="/assets/commerce.js" defer></script></head><body><aside id="sidebar"><a class="brand" href="/'.$context.'">hostider<span>automation</span></a><div class="workspace">'.$title.'</div><nav id="navigation" aria-label="Satış ve finans"></nav><a class="identity-link" href="/'.$context.'">Hesap ve güvenlik</a></aside><div class="shell"><header><div><span class="eyebrow">HOSTIDER / '.$title.'</span><h1 id="heading">'.$title.'</h1></div><div id="account"></div></header><div id="scope"></div><main id="main" aria-live="polite"></main><div id="notice" role="status"></div><footer>Canlı ödeme ve hizmet kurulumu kapalıdır. İç finans raporları resmî muhasebe belgesi değildir.</footer></div><dialog id="review" aria-labelledby="review-title"></dialog></body></html>');
    }
}
