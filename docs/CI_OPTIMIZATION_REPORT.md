# CI optimizasyonu — çalışma ve kanıt kaydı

## Yetki, başlangıç ve uygulama planı

Başlangıç: `fb8a217619196b650d82a89a16b3d2680736cf2d`.
Kabul edilmiş uygulama: `e0d460d0aea05f496135d169ecbb64ce94659cc9`.
Bu iş CI yapılandırması/yardımcıları ve F6 plan belgeleriyle sınırlıdır.
Uygulama algoritmaları, eski testler ve migration001–054 değiştirilmeyecek.

Kapsam: otomatik FAST ile açık tetiklenen, exact SHA doğrulamalı FULL ACCEPTANCE
ayrımı; güvenli etki yönlendirme; docs-only hafif kontrol; gerçek Actions kanıtları.
OPS-001/005, SEC-001–004 ve mevcut tüm regresyonların korunması esastır.

Dosyalar: `.github/workflows/f0.yml` (FULL), `fast.yml` (FAST), `tools/ci/`
(suite envanteri, etki seçimi, kaynak/belge kontrolleri ve bunların testleri), bu rapor,
F6_PLAN, README ve ADR-023. Üretim bağımlılığı veya Node worker eklenmez.

Migration: yok. Test: yönlendirme/negatif SHA kontrolleri yerel; gerçek FAST PHP8.3/8.4,
gerçek docs-only çalışma ve son CI kaynak SHA'sında FULL PHP8.3/8.4.
FULL mevcut52 suite/582 PHP, Chromium11, state3, bağımsız1/4/8 worker,
10/30/20 tekrar, migration empty/upgrade/replay, lint, PHPStan ve iki audit'i korur.
Yeni CI yardımcı kontrolleri mevcut582 uygulama testine karıştırılmaz.

Geri dönüş: yalnız CI/belge commit'lerini geri al; eski otomatik push+PR full
davranışı geri gelir. DB geri dönüşü veya destructive down yoktur. Önceki kabul
kayıtları korunur. main protection değiştirilmez. PR3 OPEN/DRAFT kalır.

## Durum

Uygulama ve gerçek Actions doğrulaması sürüyor. Bu kayıt DONE veya FULL kabul
beyanı değildir. F6 yalnız plan; geliştirme/cutover/staging/merge yetkisi yoktur.
