# F4-D — P0-1/P0-2/P1 bağımsız yeniden kabul dosyası

24 Ekim 2026. Yalnız test/mock; F4-E/F, F5, main merge ve production kapsam dışıdır.
Taban: uygulama `a7489b938292530cba73e4dd22c625e66328f112`, son rapor HEAD
`b36537907c28dc40fdd793ef5cd7a513711da6e5`, 298 test.
Bu belge önceki F4_D_REPORT içindeki katalogdan serbest/free PID ve OS seçimi kabulünü daraltır.
Nihai bağımsız mock kabul verilmiş sayılmaz; yeniden denetim istenir.

## Kaynak ve doğrulama

Gerçek GitHub uygulama SHA: `3296eca0631e41d49ad78545d49a6ac7fa9ec5f1`.
Yerel commit `fd941b53071e2aca7b2a45540d060fe9404a6f2a` ile aynı
`013ed3112b043026fa5e12762815263b115d8199` tree; 20 değişmiş dosya ve 001–044 hash
eşliği [publication.json](evidence/f4-d-post-audit/publication.json) içindedir.
Yerel metadata farklılığı kaynak farkı değildir. Mevcut taslak [PR #2](https://github.com/Boydaksu/hostidercp/pull/2).
Kaynak CI: [Actions 40110274172](https://github.com/Boydaksu/hostidercp/actions/runs/40110274172).
- [PHP 8.3.36 / job 120920357543](https://github.com/Boydaksu/hostidercp/actions/runs/40110274172/job/120920357543): 45 suite / 313 test / 0 hata, SUCCESS.
- [PHP 8.4.27 / job 120920357495](https://github.com/Boydaksu/hostidercp/actions/runs/40110274172/job/120920357495): 45 suite / 313 test / 0 hata, SUCCESS.

MariaDB 10.11.19. Her iki job'da Composer locked audit, PHPStan, lint/mimari/pattern taraması,
10 service HTTP, 30 Commerce HTTP, 20 session concurrency tekrarı ve JS kontrolleri başarılıdır.
Gerçek checkout `028da04c89346b6b898791d8dc4fac360bbf6daf` fetch edildi; kaynakla aynı
`013ed3112b043026fa5e12762815263b115d8199` tree ve sıfır dosya farkı doğrulandı.
Bu PR test checkout'udur; hedef dala merge yapılmadı.
[CI kanıtı: suite/step/worker ölçümleri](evidence/f4-d-post-audit/ci.json).

## Hedefli sonuçlar

| Bulgu | Test/mock sonucu | Güvence |
|---|---|---|
| P0-1 satın alınmış entitlement | PASS | Immutable fixed offer → server-side quote line → order/item → generation-1 period, service/unit, immutable svc_price ve bill snapshot aynı canonical bundle'a bağlanır. Exact spec/catalog/price zorunludur. |
| P0-2 finansal istisna | PASS | Gerçek Identity admin oturumu, MFA/fresh step-up, services.manage customer+service grant, state/session/identity bağlı confirmation, dış account/resource inspect, terminate policy wait; yalnız STOP/TERMINATE. |
| P1 queue semantiği | PASS | cloud.delivery.v1 inbox receipt teslimi kanıtlar. Queue SUCCEEDED, operation WAITING_TASK kalabilir. Aynı event tekrarında yeni POST yoktur. |
| Ücretli değişiklik ve gerçek maliyet | NOT PROVEN / CLOSED | Katalogda PID veya custom capability olması satın alma hakkı değildir. Ücretli plan/CPU/RAM/disk/IP/OS/location değişimi, gerçek usage ve saatlik Voyar maliyeti kanıtlanmamıştır. |

Gereksinim izi: FR-VOYAR, FR-SERVICE, FR-CHECKOUT, PROV-001/002/004/006,
SEC-004, OPS-005. Karar: [ADR-012](adr/ADR-012-cloud-purchase-and-containment.md);
[ön plan / geri dönüş](F4_D_POST_AUDIT_PLAN.md).

## Satın alma sınırı

CloudOffers.publish yalnız güvenilen test application service çağrısıdır; yeni müşteri veya
admin HTTP endpoint'i açmaz. Tek immutable katalog fiyatına bir exact configuration yayımlanır.
QuoteService istemciden entitlement almaz; fiyat kaynağı üzerinden kendisi ekler. Variant,
opsiyon, addon ve yayımlanmamış yeni fiyat sürümleri fixed Cloud teklifini geçemez.
Fixed custom configuration, ürünün önceden yayımlanmış exact spec'idir; kullanıcı tarafından
arttırılabilir ücretli custom konfigurasyon değildir. Mevcut meşru 9 USD katalog fiyatı,
20% vergiyle unit başına 1080 minor fiyatı korunur; sağlayıcı maliyeti bundan türetilmez.

Attach ve enqueue, claim ve I/O öncesi durable fence canonical satın alma bağını tekrar
kontrol eder. Refund/cancel, başka customer/item/unit veya değişmiş current_price_id reddedilir.
Immutable offer/price/snapshot güncellemesi DB trigger'larıyla engellenir. Eski entitlement'sız
siparişlere sonradan hak eklenmez; normal CREATE kapalı kalır. UPGRADE yalnız satın alınmış aynı
PID'yi, REINSTALL yalnız aynı OS'yi kullanabilir; daha yüksek PID veya farklı config seçilemez.
Bunlar mock SDK mapping testleridir; gerçek yeniden kurulum/plan değişimi ücret iddiası değildir.

## Maliyet/zarar durdurma sınırı

Containment normal funded provisioning kontrolünün yerine genel bir izin açmaz. Gerçek native
admin session referansı IdentityService tarafından doğrulanır; customer/impersonation, eksik
scope, stale step-up ve revoked session reddedilir. Session ve identity version, confirmation
payload'u, instance version, reason ve inspect edilen account/resource/ref/version bağlıdır.
Provider inspect DB transaction dışında yapılır. Ardından state yeniden kilitlenip doğrulanır.

TERMINATE ancak policy waiting sonrasında, açık irreversible acceptance ile kuyruğa alınır.
Confirmation waiting bitiminden itibaren beş dakika geçerlidir; execution ve dispatch için
fresh session yine zorunludur. Confirmation yalnız bir kez tüketilir. Operation + financial
exception audit + consumed confirmation + generation + transactional outbox atomiktir.
Enqueue sonrası worker session/scope/revocation ve persisted dispatch fence'i tekrar kontrol eder.
Kaynak sürümü gönderim anında uyuşmazsa mock provider mutation yapmaz.

UNKNOWN/pending task veya fiziksel belirsizlikte yeni STOP/TERMINATE gönderilmez. Timeout-after-effect
UNKNOWN olur; inspect/task/resource mutabakatı mevcut operation'ı sonuçlandırır, ikinci POST yoktur.
Farklı VM/account/task/operation/fingerprint başarı üretmez. Task ve kalıcı mock resource hedef
state'i eşleşmeden TERMINATED yazılmaz. Paralel terminate isteklerinden biri 409 alır.
STOP power state STOPPED yapar; finansal hizmet durumu ayrı kalır, sağlayıcı faturası durdu iddiası
üretilmez. Mevcut finans kayıtlarına dokunulmadığı hash karşılaştırmasıyla doğrulanır.
Reason ve native session referansı yalnız context-bound AEAD şifreli immutable containment
kaydında tutulur. Normal history/API/customer view bu alanları içermez; mevcut credential/VNC/root
password projection testleri korunur. Audit operation/actor ve FINANCIAL_CONTAINMENT_* türünü taşır.

## Migration / geri dönüş

Yalnız `045_cloud_purchase_containment.php`: catalog_cloud_fixed_offers,
cloud_purchased_entitlements, cloud_safety_confirmations, cloud_containments ve operation'da
immutable financial_exception bayrağı. Önceden yalnız mock Principal'da kullanılan services.manage
izin tanımı gerçek Identity'ye eklenir; Super Admin rolüne tanımlanır fakat customer/service
scope grant'i hâlâ zorunludur. Onay binding'i ve teklif/entitlement/containment immutable trigger'larla
korunur. 001–044 kaynakları değişmez. Gerçek MariaDB'de 044 → yalnız 045 upgrade + replay no-op
ve checksum/readiness kontrolleri çalışır. F3/F4-C uygulama kaynakları yeniden yazılmaz.

Geri dönüş: cron durdurulur; UNKNOWN POST replay yapılmaz. Tutarlı kod/DB/APP_KEY yedeğine restore
veya forward-fix uygulanır; otomatik destructive down migration yoktur. 045 rollback'i kayıtlı
external resource/exception kanıtını silerek yapılamaz. Canlı migration veya production işlemi yoktur.

## Testler ve mevcut 298 testin korunması

Yerel PHP 8.4.24 / MariaDB 10.11.18: **45 suite / 313 test / 0 hata** (298 mevcut + 15 yeni).
Tam koşum eski fixture düzeltmesinden sonra kaldığı suite’ten sürdürülmüştür; temiz CI tüm
45 suite’i baştan çalıştırır. [Yerel sonuçlar ve 1/4/8 ölçümleri](evidence/f4-d-post-audit/local.json).
PHPStan, locked Composer audit, 283 PHP lint/mimari kontrolü, 293 dosya pattern taraması ve
iki JS sözdizimi kontrolü başarılıdır. Pattern taraması tam secret/vulnerability denetimi değildir.
Yeni cloud-audit.php 15 test: yüksek/başka PID/product/OS/location, exact custom CPU/RAM/disk/IP,
customer/item/unit/price bağları, immutable snapshot, refund/cancel/current price drift,
meşru fiyat/config başarısı; tam refund sonrası normal ret; gerçek Identity ile güvenli terminate;
customer/unscoped/stale/revoked ret; confirmation immutability/explicit acceptance; iki bağımsız
PHP process terminate yarışı; timeout → UNKNOWN → reconcile/tek POST; account/resource/task
mismatch; STOP cost-unknown ve pending task teslimi/duplicate receipt.

Eski test tanımları silinmedi veya ignore edilmedi. Cloud fixture satın almadan önce fixed offer
oluşturur; eski custom senaryo kendi exact custom ürününü gerçekten satın alır. Önceki OS2/PID2
serbest seçimi meşru aynı OS1/PID1 SDK mapping ile sınırlandırıldı, yeni negatifler yetki artışını
reddeder. Generic variant-only fiyat testi aynı fiyat assertion'larıyla HOSTING fixture'ına taşındı;
entitlement'sız CLOUD varyantı artık reddedilir. Exact migration listeleri yalnız 045 için güncellendi.

## Başarısızlıklar / düzeltme kayıtları

1. İlk yeni paket 14 testte 7 başarısızlık: F3 mock Principal'ın services.manage izni gerçek Identity
   permission tablosunda yoktu. 045 additive permission/role kaydı eklendi; scope zorunluluğu
   korunarak 14/14, ardından genişletilmiş 15/15 doğrulandı.
2. Yerel eski deneme DB'sindeki yayımlanmamış 045 taslak checksum'u migrator tarafından reddedildi.
   Checksum bypass/backfill yapılmadı; yeni izole DB oluşturuldu.
3. İki yerel koşumu aynı MariaDB instance'ında eşzamanlı çalıştırmak sunucu kapsamlı migration
   advisory lock çatışması üretti. İkisi durduruldu; tek koşuma geçildi. Koruma gevşetilmedi.
4. Generic variant-only fiyat testinin entitlement'sız CLOUD fixture'ı yeni fail-closed sınıra takıldı.
   Fiyatlama davranışı HOSTING fixture ile korundu; CLOUD için yeni negatif eklendi.
5. Kaynak CI ilk koşumunda iki job da SUCCESS; başarısız test/kalite adımı yoktur. Yerel başarısız
   denemeler yukarıda açıklanır; son sonuçlarla örtülmez. Windows revision quoting yüzünden ilk
   yerel/uzak tree karşılaştırması komut hatası verdi ve reset uygulanmadı; git show %T ile
   tekrar karşılaştırıldı, aynı tree doğrulandıktan sonra yalnız yerel commit metadata’sı eşlendi.

## Açık riskler ve durma noktası

Yalnız sentetik mock task/resource/account verisi ve gerçek MariaDB/ayrı PHP süreçleri kanıtlanır.
Gerçek Voyar task response mapper, saatlik maliyet/usage, ücretli değişikliklerin fiyat etkisi,
canlı kaynak silme ve production ölçeği kanıtlanmamıştır. Cloud containment için yeni HTTP veya
operatör arayüzü teslim edilmez; gerçek Identity-backed test application service sınırı doğrulanır.
Finansal istisna canlı sağlayıcıya yetki vermez; tüm effect policy/GATE sınırları korunur.
DOMAIN-002 failover **PARTIAL / MANUAL_REVIEW**, G-V01–04 ve diğer GATE'ler **OPEN** kalır.
F4-E/F, F5, merge ve production'a geçilmedi. Hedefli F4-D düzeltmeleri için bağımsız yeniden kabul istenir.

## Tam kaynak değişiklik listesi

- `.github/workflows/f0.yml`
- `docs/F4_D_POST_AUDIT_PLAN.md`
- `docs/adr/ADR-012-cloud-purchase-and-containment.md`
- `migrations/045_cloud_purchase_containment.php`
- `src/Catalog/CloudOffers.php`
- `src/Cloud/Containment.php`
- `src/Cloud/Engine.php`
- `src/Cloud/Entitlements.php`
- `src/Cloud/EventSink.php`
- `src/Cloud/Store.php`
- `src/Pricing/QuoteService.php`
- `src/Providers/Mock/DurableVoyar.php`
- `src/Services/CloudServices.php`
- `tests/addons.php`
- `tests/cloud-audit.php`
- `tests/cloud-fixture.php`
- `tests/cloud-safety-child.php`
- `tests/cloud-sdk.php`
- `tests/cloud.php`
- `tests/domains-audit.php`

Teslim kanıt commit’i ayrıca bu raporu ve docs/evidence/f4-d-post-audit dosyalarını ekler.
