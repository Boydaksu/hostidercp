# F4-F — Domain / Cloud panel ve HTTP güvenlik planı

25 Ekim 2026. Kullanıcı F4-C/D/E mock tabanlarını kabul etti. Başlangıç HEAD
`7135e7c37dea1d7889686cf06333736706a81a27`; uygulama `9cc105c6ed4d00099f5892a894d2b772ec52ba52`;
48 suite / 355 test. Bu belge uygulama kodundan önce yazılmıştır.

## Kapsam ve gereksinim izi

FR-ID, FR-DOMAIN, FR-VOYAR; SEC-001–004, DOMAIN-001/003, PROV-001/002/006,
FIN-010, OPS-001/005; R1-05/06/07, R4-01/02/04/05/07/10, R5-04/07, R7-06/07.
Mevcut Identity, Commerce, Services, Domains, Cloud, CloudCosts, Catalog ve Finance
application service'leri kullanılır. Yeni kimlik/finans/sağlayıcı dispatch mekanizması yoktur.

| Ekran | Okuma / aksiyon | Yetki ve sınır |
|---|---|---|
| /client/domains | Domain durumu, nameserver, expiry, dönem/yenileme, bekleyen işlem | services.view + native customer/service ownership; renewal mevcut Services paneli |
| /client/cloud | Fiziksel VM / finansal hizmet ayrımı, satın alınan fixed spec, güç/reinstall/terminate | services.view; cloud.power yalnız kendi hizmeti; capability + entitlement + fresh step-up + confirmation |
| /admin/domains | Tenant seçimi, attempt/lease/UNKNOWN/inspection, resolve | scoped services.view; services.reconcile + services.manage, staff MFA/fresh session |
| /admin/cloud | Task/resource/provenance, blocked/stale, finans istisnası, containment | scoped services.view; scoped services.manage/reconcile; native Containment confirmation ve waiting |
| /admin/cloud-costs | COSTED_MOCK/UNVERIFIED/REVIEW/MATCHED_MOCK, invoice difference, currency/FX, fiyat önerileri | service-scoped finance.view; approval finance.approve + pricing.manage; publication ayrıca catalog.manage + Commerce confirmation |

## Endpoint ve güvenlik kararı

`/api/v1/{client|admin}/provider-panel/{status,list,detail}` GET; admin maliyet detayı
ayrı izinle. `prepare`/`execute` POST: native cookie/session, CSRF, Idempotency-Key,
strict field allowlist, actor/session/identity-version/customer/service/payload/current-state
bound tek kullanımlık confirmation. GET mutasyon yapmaz. Replay güncel yetkiyi tekrar doğrular.
Provider read inspection durable iş olarak mevcut queue/outbox üzerinden bağımsız cron'da
yapılır; HTTP transaction içinde provider I/O yapılmaz. UNKNOWN için ikinci POST/failover yoktur.
Containment native application service üzerinden ayrı POST prepare/execute akışıdır;
resource inspect transaction dışında, finans istisnası ve irreversible waiting korunur.
Cloud power için dar `cloud.power` izni eklenir; müşteri CREATE/UPGRADE veya admin reconcile
yetkisi kazanmaz. Yalnız satın alınmış aynı OS ile reinstall; ücretli/custom upgrade kapalıdır.
Credential, EPP, contact, raw provider cevapları ve encrypted context projection'a alınmaz.
Dinamik HTML textContent ile oluşturulur; CSP/no-store mevcut Response katmanındadır.

## Dosya ve migration

`src/ProviderPanel/` controller, scoped read model, commands/confirmations ve inspection handler;
mevcut Cloud application service'inde yalnız composable local transaction girişleri ve dar güç
yetkisi; bootstrap, mevcut queue/CLI bağlantısı; panel HTML ve hafif JS/CSS; HTTP test fixture'ları.
048+ yalnız additive permission/confirmation/inspection metadata; 001–047 hash'leri korunur.

## Test, demo ve geri dönüş

355 eski test korunur. Gerçek MariaDB/native cookie HTTP: customer IDOR/scoped staff,
MFA/freshness/revoke/identity version/impersonation, CSRF/method, replay/çift submit,
stale confirmation/payload/status, secret/PII allowlist, pending task ve UNKNOWN, native
containment waiting/duplicate, FIN-010 approval/publication ayrımı. Gerçek bağımsız HTTP
istek yarışları ve önceki 1/4/8 PHP worker/session concurrency; PHP8.3/8.4 CI, PHPStan,
Composer audit, lint/mimari. Yapay müşteri/admin HTTP çıktıları teslim kanıtına kaydedilir.
Geri dönüş: yeni panel/inspection cron durdurulur; mevcut operation'lar silinmez veya yeniden
dispatch edilmez. Tutarlı kod/DB/APP_KEY snapshot restore veya forward-fix; otomatik DROP yoktur.

## Açık GATE ve durma noktası

APP_ENV=test ve mock dışında işlemler kapalıdır. G-V01–04, PROV-005 gerçek maliyet,
G-R01/G-D01, G-C01 ve diğer canlı kapılar OPEN; DOMAIN-002 PARTIAL/MANUAL_REVIEW.
Transfer, EPP/credential retrieval, ücretli custom/plan değişimi desteklenmiyor.
F4-F mock teslim raporu sonrası durulur; F5/F6, main merge, staging/production yoktur.
