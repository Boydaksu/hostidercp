# F3 test/mock geliştirme planı — 23 Ekim 2026

Onaylı taban ada37dc52b324e34380ba836ae7794cebb174ec4, 180 test. Kullanıcı F3-A–F test/mock geliştirmesini yetkilendirdi. F4/production/canlı provider mutation kapsam dışı. Mevcut 001–022 migration, Identity/Finance/Queue altyapısı korunur.

## Sıralı alt fazlar

| Faz | Gereksinim / P0 | Dosyalar / migration / test |
|---|---|---|
| A | FR-SERVICE; service/period/resource/config/price/ownership; ayrı state machine | src/Services model/repository; 023 additive şema; state/immutable/çoklu unit DB testleri |
| B | FR-SERVICE/OPS; PROV-003/004, OPS-005 | canonical discovery, order-item/unit claim, unique operation/generation, outbox, lease/fence; 024; bağımsız PHP süreç/queue kaybı/lease testleri |
| C | FR-HOSTING; OPS-001 | Providers/Hosting mock dünya + kapalı WHM sınırı; pool/mapping/kapasite/health/secret; 025; contract + seçim testleri |
| D | PROV-001/002/006 | immutable attempts/results/evidence; UNKNOWN inspect ve scoped personel mutabakatı; 026; found/not-found/stale/rollback testleri |
| E | FR-SERVICE; SVC-001/002/003 | politika mirası, onay, billing service üzerinden renewal order/period, wallet opt-in, grace/notification, cancel/termination safeguard, dual-owner transfer; 027; lifecycle DB/concurrency testleri |
| F | SEC-004, OPS-001/005 | Identity tabanlı service HTTP controller, pure plan/confirmation, admin/client assets; 028 izin/onay; gerçek HTTP/IDOR/impersonation/replay testleri ve browser demo |

Her fazdan sonra ilgili test/lint/PHPStan, teslim notu ve küçük commit; bir sonraki faz test geçmeden başlamaz. Mevcut 180 suite finalde ve PHP 8.3/8.4 CI'da korunur. Test HTTP sunucusu interpreter modunda kalır. Gerçek hosting JIT/cron testi G-O01'e bağlıdır.

## Güvenlik ve tehdit sınırları

- Hazır sipariş canonical order/bill/immutable item'dan yeniden keşfedilir. Queue başarı olayı tek kaynak değildir. Her item quantity unit'i ayrı hizmettir; unit/generation kuralı ADR-008'de açıklanır.
- Mock discovery yalnız test kökenli yeni siparişleri kabul eder; staging/LEGACY_UNKNOWN/production kökeni otomatik yükseltilmez. Live yol kapalıdır. Imported service hiçbir create operasyonuna giremez.
- Order→bill→item→service/operation kilitleri; narrow indeks ve bounded batches. Provider çağrısı açık DB transaction dışında. Lease kaybı UNKNOWN, kör create/failover yok. Fiziksel exactly-once ancak vendor kanıtıyla değerlendirilebilir; mock kalıcı dünya/çağrı sayımıyla test edilir.
- Secret AEAD, allowlist audit; client görünümü resource/server/credential/özel attempt verisini içermez. Devir sonrası eski period/bill/order verisi yeni malike açılmaz.
- Staff scope/MFA/fresh step-up, client sahipliği, CSRF, idempotency replay yetkisi ve tek kullanımlık server-state confirmation. Impersonation mutation yasak. Termination explicit etki + yapılandırılmış bekleme + provider kanıtı ister.
- WHM HTTPS/TLS/host doğrulama/ACL sözleşmesi korunur; G-C01 açıkken gerçek HTTP transport çağrısı yok. Gerçek endpoint/cevap uydurulmaz.
- Sayısal grace/transfer TTL/termination bekleme/fiyat bildirimi ticari varsayılan olarak uydurulmaz; test politikaları açık yapılandırılır. Belirsiz yenileme/transfer davranışı fail-closed; G-T01 canlı transferi kapatır.

## Performans ve kabul

Gerçek MariaDB: 1/4/8 process claim p50/p95, kilit bekleme/deadlock, cron overlap, queue wait ve worker throughput; pool seçimi, client list ve admin operation HTTP p95. Temsilî hacim ve örnek sayısı raporlanır; SLA önerisi karar yerine geçmez. Bir ödemeyle tek unit resource, multi-item/unit, imported, manual, refund/cancel, provider failure'da ledger değişmezliği ve UNKNOWN kanıtı P0 kabulidir.

## Geri dönüş / çıkış kapıları

Yeni DDL additive, eski migration checksum'ları değişmez. Destructive down yok. Kod+DB+APP_KEY+korumalı dosyalar birlikte doğrulanmış snapshot restore veya forward-fix; gerçek finans varsa mutabakat gerekir. F6 restore kabulü bu fazda yapılmış sayılmaz.

F3_REPORT gerçekten çalışan/mock-only/canlı doğrulanmamış alanları, test/commit/CI/performance ve migration kanıtlarını ayrı verir. G-C01/G-O01/G-T01 ve diğer açık GATE'ler kapanmaz. Main ruleset salt okunur kontrol edilir; ilk F3 merge öncesi repository sahibine PR review + verify(8.3/8.4) önerilir. Dal üzerinde geliştirme ve incelemeye hazır PR; kullanıcı kabulü olmadan F4 veya canlı çalışma yok.
