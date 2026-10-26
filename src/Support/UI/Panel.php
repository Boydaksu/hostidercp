<?php
declare(strict_types=1);
namespace Hostider\Support\UI;
use Hostider\Core\Http\Response;
final class Panel
{
    public static function response(string $ctx): Response
    {
        return new Response(200,[],html:'<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="support-context" content="'.$ctx.'"><title>Hostider · Destek merkezi</title><link rel="stylesheet" href="/assets/identity.css"><link rel="stylesheet" href="/assets/support.css"><script src="/assets/support-state.js" defer></script><script src="/assets/support.js" defer></script></head><body><header><a class="brand" href="/'.$ctx.'">hostider</a><h1>Destek merkezi</h1><a href="/'.$ctx.'">Hesap ve güvenlik</a><span id="account"></span></header><p class="scope">TEST / MOCK · HELD veya mock transport kabulü gerçek teslim değildir. Canlı e-posta ve sağlayıcı işlemleri kapalıdır.</p><div id="notice" role="status" aria-live="polite"></div><main class="support-layout"><section id="inbox" aria-label="Talepler"></section><section id="conversation" aria-label="Görüşme"></section><aside id="operations" aria-label="İşlemler"></aside></main><dialog id="review" aria-labelledby="review-title"></dialog></body></html>');
    }
}
