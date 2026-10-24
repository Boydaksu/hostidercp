# F4-D Voyar Cloud — bağımsız denetim teslim raporu

24 Ekim 2026; dal `codex/f4-domain-cloud`; önceki kabul edilen HEAD
`73468c0427e5f23553a7a8b534f7713011fafcc7` (278 test).
**F4-D yalnız test/mock. Yerel ve PHP 8.3/8.4 CI doğrulamaları başarılı; F4-D mock bağımsız kabul bekliyor. Tam F4 DONE değildir.**
F4-C güvenlik tabanı kabul edildi; DOMAIN-002 otomatik registrar failover **PARTIAL** ve
MANUAL_REVIEW korumasında kalır. F4-E/F ve F5 başlatılmadı; main merge/production yoktur.
Plan: [F4_D_PLAN](F4_D_PLAN.md); karar: [ADR-011](adr/ADR-011-cloud-mock-task-fencing.md).

## Kaynak kimliği

Gerçek uzak kaynak commit: `a7489b938292530cba73e4dd22c625e66328f112`.
Yerel kaynak commit: `6ed1dc72112c4fbfe14b9c78c409e927aef26a77`.
İkisi aynı `19f8869781751ce3ae6a8363490cb39d323c9148` tree’sine sahiptir; 24 dosyanın blob eşliği
ayrıca doğrulandı. SHA farkı commit metadata’sıdır. [Yayın/source manifesti](evidence/f4-d/publication.json).
Mevcut taslak [PR #2](https://github.com/Boydaksu/hostidercp/pull/2) aynı F3 dalını hedefler.

## Değişen kapsam / gereksinimler

FR-VOYAR, R1-06, R5-07, PROV-001/002/004/006, OPS-001/005, SEC-004.

| Konu | Durum | Uygulama | Test / kanıt |
|---|---|---|---|
| Kalıcı VM ve canonical kaynak | PASS (test/mock) | Catalog ürün bağı, allowlist OS/location/plan/custom spec; paid CLOUD service/unit ve immutable snapshot | cloud.php canonical source, custom bounds, secret fields |
| Tek dispatch | PASS (test/mock) | Operation/generation/lease altında cloud_dispatches I/O öncesi commit; VoyarMutationFence callback | cloud-races.php 8 yeni süreç; cloud-sdk.php özgün SDK auth replay |
| Task polling | PASS (test/mock) | ACCEPTED başarı değildir; WAITING_TASK/POLLING ve ayrı task/resource gözlemi | cloud.php pending task; cloud-races.php duplicate task / stale poll lease |
| VM başarı kanıtı | PASS (test/mock) | Account/operation/fingerprint/task/resource/public ref/version/config ve hedef state eşliği | cloud.php alien status/account/resource/fingerprint/config negatives |
| UNKNOWN | PASS (test/mock) | Timeout-before/after, body/auth loss, crash-after-effect ve expiry; kör mutation replay yok | cloud.php dört belirsizlik; cloud-races.php polling crash / durable fence |
| Operator mutabakatı | PASS (test/mock) | Provider inspect transaction dışında; şifreli immutable observation, generation/lease/version/TTL; fresh scoped staff + server-bound confirmation | cloud.php pending/absent ret ve tek reconciliation; cloud-races.php stale confirmation |
| Power / reinstall | PASS (test/mock) | SDK start/stop/restart/reinstall yöntemleriyle eşleşen sentetik task akışı | cloud.php power/reinstall; cloud-sdk.php gerçek SDK yöntem/yol testi |
| Plan değişimi | PASS (test/mock) | Katalogda explicit ücretsiz fixture hedefi; custom upgrade/downgrade/ek ücret kapalı | cloud.php UPGRADE success + unsupported/financial review |
| Terminate | PASS (test/mock) | Scoped staff/fresh step-up, payload/version bağlı açık onay + policy bekleme; task+terminated resource | cloud.php erken dispatch ret / final verified termination |
| Imported VM | PASS (test/mock) | Bağımsız inspect ile mevcut kaynak bağı; imported service yeni CREATE alamaz | cloud.php imported VM; mevcut F3 import davranışı korunur |
| F4-E provenance | PASS (test/mock) | Immutable tekil operation/task, opaque provider task/resource ref, account/resource version/MOCK origin | cloud.php usage provenance; cloud-races.php duplicate completion |
| CLI / outbox / cron | PASS (test/mock) | cloud:work/cloud:expire; gerçek outbox event kontrolü; bounded queue/worker | cloud-races.php gerçek ayrı PHP console, 101 blocked canonical units |
| Ortam / secret | PASS (test/mock) | Test-only model/provider; strict input/projection allowlist; SDK yalnız test zip:// + enjekte transport | cloud.php projection/IDOR/non-test guards; önceki SDK ve HTTP testleri |

Canlı SDK task/response mapper ve gerçek saatlik maliyet: **NOT TESTED**, G-V01–04 OPEN.
Ek ücretli/custom plan değişimi ve genel downgrade dispatch: **kapalı**, başarı iddiası yoktur.
DOMAIN-002 otomatik registrar failover: **PARTIAL** (F4-C istisnası korunur).

Kaynak indeksi: `src/Cloud/Engine.php:17` canonical attach, `:58` enqueue,
`:80` SKIP LOCKED claim, `:98` durable send fence, `:109` response binding,
`:135` transaction dışı observation, `:146` operator resolve, `:150` task/resource acceptance.
`src/Providers/VoyarMutationFence.php:19` admission callback I/O öncesinde çağrılır.

## Durum makineleri ve işlem sınırları

cloud_operations: PENDING → RUNNING → WAITING_TASK → POLLING → SUCCEEDED.
POLLING pending task için WAITING_TASK’a döner. RUNNING/POLLING lease kaybı UNKNOWN olur.
UNKNOWN/MANUAL_REVIEW yeni mutation adayına girmez. Operator yalnız doğrulanmış task ve dış kaynak
kanıtını mevcut operation’a bağlayabilir; resource yokluğu no-effect veya success sayılmaz.
Task status yalnız mock sözleşmesinin MOCK_SUCCEEDED değeriyle ve doğru fiziksel state/config ile
birlikte kabul edilir. Provider’ın herhangi bir bilinmeyen başarı string’i normalize edilmiş başarı sayılmaz.

İş+history+audit+outbox aynı kısa transaction’dadır. Provider mutation/advance/observe/inspect uygulama
transaction’ının dışındadır. Dispatch admission, response kabulü ve poll completion ayrı kısa
transaction’lardır. Send izni tüketildikten sonra crash olsa bile yeni süreç aynı POST’u gönderemez.
Polled task sonucu unique operation ve task anahtarlarıyla bir kez uygulanır.

Claim en fazla 100 aday, SKIP LOCKED ile bir mutation veya poll claim eder. Worker en fazla 100
adım/20 saniye yeni işe kabul, 64 MiB veya PHP memory_limit altında 8 MiB rezerv kullanır. CLI DB
lock wait 2 saniyedir. Bu hard I/O timeout değildir; canlı transport/rate-limit sözleşmesi kapalıdır.
İlk 100 bloke unit sonraki cron’u engellemez; NULL kontrol zamanlı yeni işler öncelikle görülebilir.
Kapasite üstü sürekli girişte gecikme garantisi verilmez.

Power STOPPED ile uygulama hizmetinin finansal ACTIVE durumu ayrıdır; stop, maliyet kesimi değildir.
Funding dispatch sonrası geri alınırsa fiziksel VM kanıtı korunur; service SUSPENDED olur.
Para/journal/fatura kayıtları provider sonucu nedeniyle geriye yazılmaz. Ek ücretli upgrade,
Cloud renewal/extend yeni finans kaynağı ve gerçek fiyat onayı olmadan çalıştırılmaz.

## Kaynak değişiklikleri

- `migrations/044_f4_cloud_operations.php`: 12 yeni tablo, unique keys, immutable binding/evidence trigger’ları.
- `src/Cloud/{Catalog,Store,Engine,Worker,EventSink}.php`: model, doğrulama, confirmation, operation, polling, reconciliation, cron.
- `src/Providers/Contract/AsyncComputeProvider.php`: mevcut ComputeProvider’ı değiştirmeyen internal async sınır.
- `src/Providers/Mock/DurableVoyar.php`: ayrı kalıcı sentetik VM/task/call dünyası.
- `src/Providers/VoyarMutationFence.php`: opsiyonel kalıcı admission callback; önceki üç SDK testi korunur.
- `src/Services/CloudServices.php`: Services tabloları application gateway üzerinden yazılır.
- `bin/console.php`: cloud:work/cloud:expire ve Cloud event sink kaydı.
- `tests/cloud.php`, `cloud-races.php`, `cloud-sdk.php`, `cloud-fixture.php`, `cloud-child.php`: 20 yeni test tanımı ve bağımsız süreç fixture’ları.
- `.github/workflows/f0.yml`: üç yeni suite; mevcut kalite ve HTTP/session tekrarları korunur.
- `tests/domains-audit.php`: yalnız exact upgrade expectation’a 044 eklenir (043+044); eski 278 test tanımı/assertion korunur, ignore/silme yoktur.
- README, F4 planı, F4-C kabul notu, SDK incelemesi, ADR-011, F4-D plan/rapor/kanıtları.

## Migration / geri dönüş

001–043 byte/hash eşliği ve özgün SDK ZIP hash’i [preservation](evidence/f4-d/preservation.json) içindedir.
cloud.php setup gerçek MariaDB’de önce yalnız 001–043 kurar, sonra yalnız 044 uygulanmasını, tekrar
migrate no-op ve assertReady’i doğrular. 044 yayımlanmadan önce bu geliştirme oturumunda revize edildi;
yayımlanmış 001–043 değiştirilmedi.

Geri dönüş: cron’u durdur, UNKNOWN mutation’ı yeniden gönderme; tutarlı kod/DB/APP_KEY snapshot restore
veya forward-fix. DDL implicit commit nedeniyle destructive otomatik down yoktur. Başarılı task/resource,
operation ve provenance kayıtları silinmez. İzole test upgrade’i production restore provası değildir.

## Test, ortam ve önceki başarısızlıklar

Yerel: Windows, PHP 8.4.24 64-bit, MariaDB 10.11.18, loopback izole hostider_f0_test* şemaları,
APP_ENV=test, HTTP interpreter. Gerçek müşteri/provider/payment/SMTP verisi veya ağ mutation’ı yoktur.
Yeni 12 model + 7 race/CLI + 1 SDK suite testi vardır; ana tam sayım beklenen 278+20=298’dir.
Tam yerel koşu: **44 suite / 298 test / 0 hata**. PHPStan Level 5: 0 hata; 277 PHP dosyası / 0 mimari ihlal, 287 kaynak/config/test dosyası / 0 güvenlik örüntü bulgusu. Composer locked audit: no security vulnerability advisories. Örüntü taraması bağımsız güvenlik denetiminin yerine geçmez. PHP 8.3/8.4 [CI 40093933860](https://github.com/Boydaksu/hostidercp/actions/runs/40093933860): iki job SUCCESS, ayrı ayrı 44 suite / 298 test / 0 hata. Son kaynak için tam sayım indirilen gerçek job günlüklerinden çıkarıldı.

Ham kanıt: [yerel tam koşu](evidence/f4-d/local-full.txt), [son model/upgrade](evidence/f4-d/model-final.txt), [kalite kontrolleri](evidence/f4-d/checks.txt) ve [son kaynak kontrolleri](evidence/f4-d/checks-final.txt), [Composer audit](evidence/f4-d/composer-audit.txt).

İlk import fixture’ı mevcut hizmetin immutable imported flag’ini UPDATE etmeyi denedi; trigger haklı
olarak reddetti. Fixture baştan imported hizmet oluşturacak şekilde düzeltildi, trigger korunur.
İlk CLI testi önceki senaryoların 100’den fazla outbox olayı arkasında hedefi varsayıyordu; test ayrı
şemada canonical akışı bounded dispatch/drain ile çalıştırır. Worker sınırı veya assertion gevşetilmedi.
İlk 12 model, 1/4/8 yarışlar ve SDK gerçek auth replay testi sonraki yerel koşularda geçti.
Son iç kontrolde mock provider’ın gönderilen payload HMAC’ini yeniden hesaplaması da eklendi; değiştirilmiş request fiziksel etki/call oluşturmadan reddedilir. Bu final kaynak için 12 model ve 1 SDK testi yeniden geçti; eski ara CI final düzeltmenin kanıtı sayılmaz.

1/4/8 ölçümü her seviyede bir VM için bağımsız PHP process yarışıdır; ham MEASURE wall_us, create/effect
sayısı race çıktısına yazılır. Her seviye tek dalga olduğu için p50/p95 veya production throughput/SLA
iddiası yapılmaz. Uzun süreli production performans kabulü G-O01 açık kalır.

## GitHub Actions ve gerçek checkout kanıtı

Kaynak HEAD: `a7489b938292530cba73e4dd22c625e66328f112`.
PR CI’ın gerçekten checkout ettiği GitHub test commit’i:
`bb4a8db65a9e1b8ac66823d7dfa9d923bf246e33` (PR başlığının F3 tabanı ile otomatik test birleşimi;
PR merge edilmedi). Bu commit fetch edilip kaynakla `git diff --quiet` ve tüm tree eşliği doğrulandı:
`19f8869781751ce3ae6a8363490cb39d323c9148`. CI başka bir kaynağın sonucu diye sunulmaz.

| Job | Ortam | Sonuç | Ana test sayımı |
|---|---|---|---|
| [verify (8.3)](https://github.com/Boydaksu/hostidercp/actions/runs/40093933860/job/120872143793) | Ubuntu, PHP 8.3.36, MariaDB 10.11.19 | SUCCESS | 44 suite / 298 test / 0 hata |
| [verify (8.4)](https://github.com/Boydaksu/hostidercp/actions/runs/40093933860/job/120872144451) | Ubuntu, PHP 8.4.27, MariaDB 10.11.19 | SUCCESS | 44 suite / 298 test / 0 hata |

Önceki 278 test korunur, yeni 20 test eklenir. 10 Services HTTP / 30 Commerce HTTP / 20 session
concurrency kararlılık tekrarı ana 298 sayısına dahil edilmez; bu tekrarlar da başarılıdır. Composer
locked audit, PHPStan, lint/mimari, güvenlik örüntü taraması ve iki JS syntax kontrolü başarılıdır.
[Job/adım durumları](evidence/f4-d/ci.json), [8.3 suite sayımı ve race ölçümü](evidence/f4-d/ci-83.json),
[8.4 suite sayımı ve race ölçümü](evidence/f4-d/ci-84.json) ham günlüklerden çıkarılan kanıttır.

Son rapor/kanıt commit’i yalnız docs değiştirir. Kaynak kodu/migration/test/workflow değişmez;
GitHub belge commit’i için ayrıca otomatik CI başlatabilir. Kullanıcı yalnız F4-D yükleme ve CI’a
onay verdi; main merge veya canlı mutation için bu onay kullanılmadı.

## SDK / canlı sınırlar ve sonraki bağımlılıklar

Gerçek SDK create/task cevap DTO’su ve final state string mapping’i doğrulanmadı; canlı mapper yoktur.
SDK yöntem/URL kanıtı [F4_VOYAR_SDK_REVIEW](F4_VOYAR_SDK_REVIEW.md) içindedir. Sentetik MOCK_* task
sonuçları gerçek Voyar sonucu diye sunulmaz. Unknown discovery’nin operation korelasyonu mock dünyasına
aittir; canlı arama/task eşlemesi G-V03 kanıtı olmadan açılmaz.

Katalog/custom limitler açık Hostider mock policy’sidir. Gerçek stok, kapasite, quote, bakiye ve ücret
farkı doğrulanmış değildir. Ek ücretli upgrade, custom upgrade, downgrade, EXTEND ve addon/GPU mutation
kapalıdır. Root password/VNC/credential public method veya projection yoktur. Yeni HTTP/panel F4-F
ve gerçek WHMCS imported veri aktarımı F6 başlatılmadı; imported test sentetik mevcut kaynak içindir.

cloud_effects observed_at yerel gözlem zamanıdır; gerçek VM kullanım başlangıç/bitişi veya saatlik
ücret zamanı değildir. F4-E yalnız gerçek ölçüm/usage refs ve idempotency/interval kanıtları eklendikten
sonra ledger üretebilir. Bu faz ledger, saatlik ücret, fiyat önerisi veya bildirim gönderimi eklemez.

G-V01–04, G-R01/G-D01, G-C01, G-P01–02, G-X01–02, G-O01–04, G-T01 OPEN.
DOMAIN-002 otomatik registrar failover PARTIAL kalır. F4-D mock yeniden denetiminde durulur;
F4-E/F, F5, merge ve production’a otomatik geçilmez.
