# F4-C ikinci bağımsız denetim — yeniden inceleme raporu

24 Ekim 2026. Repository: `Boydaksu/hostidercp`, dal: `codex/f4-domain-cloud`.
Başlangıç HEAD: `72e35a88ca381a5a00e0daf68e798ccb766663d7`.
Kabul edilen önceki taban: `7f875d678e4d580a0ce5ebd42875652b8873b322` (254 test).
Taslak [PR #2](https://github.com/Boydaksu/hostidercp/pull/2), hedef `codex/f3-service-engine`.

**Durum: yerel ve GitHub doğrulamaları başarılı; bağımsız kabul bekleniyor. F4-C DONE değildir.**

Yerel kaynak commit: `c2839dfff1179ca7ed50ac796cf1db0d12a66790`.
Kaynak tree: `5e91e5beed4a8eac7b8ac6268e06d18bc256cb03`.
Uzak kaynak commit: `a52ee336cc1f14ba56d89fc4abf687d92a5f231a`.
İlk uzak kanıt HEAD: `457ee9a5c6cae3b6ecb007cf1ac4dac8a3cedecb`.
Yerel kaynak commit ile uzak kaynak commit aynı `5e91e5beed4a8eac7b8ac6268e06d18bc256cb03` tree’sine;
yerel kanıt commit’i `c6f3522` ile ilk uzak kanıt HEAD aynı
`087bf4ab661491b3c3291279316fc85e4d027719` tree’sine sahiptir. 16 kaynak dosyasının Git blob eşliği
ayrıca doğrulandı. Commit SHA farkı metadata’dan gelir; içerik farkı yoktur.
Önceki bu dosyada yer alan doğrulanmamış 12 PASS ve “düzeltmeler tamamlanmıştır” ifadeleri
ikinci denetim kararıyla geri çekilmiştir. Eski CI fatal hatası bu oturumda yeniden üretildi.
Plan: [F4_C_SECOND_AUDIT_PLAN](F4_C_SECOND_AUDIT_PLAN.md);
karar: [ADR-010](adr/ADR-010-f4-domain-audit-safety.md).

## Bulgular ve kanıt matrisi

PASS aşağıda yalnız test/mock teknik davranışını belirtir; bağımsız kabul veya canlı GATE kapanışı değildir.
Yerel ortam: Windows, PHP 8.4.24 64-bit, MariaDB 10.11.18, loopback izole `hostider_f0_test*`,
APP_ENV=test, HTTP interpreter. Tüm müşteri/sağlayıcı verisi sentetiktir.

| # | Durum | Kaynak ve düzeltme | Gerçek assertion / kanıt |
|---|---|---|---|
| 1 Harness | PASS (yerel + CI) | `tests/domains-operations.php:3` harness → database → fixture sırası | 12 test tanımı ve 12 çalıştırılan senaryo; iki CI job’unda da 12/12; tam sonuç aşağıda |
| 2 Starvation | PASS (yerel + CI) | `src/Domains/DomainRunner.php:40`, `DomainEngine.php:312` | 101 bloke + 1 sağlıklı; ilk çağrı false/100 hold, ikinci çağrı SUCCEEDED/1 fiziksel create; yeni gelen sağlıklı iş de ilerler. `domains-audit.php`: hold rollback ve SKIP LOCKED; 1/4/8 ayrı PHP runner |
| 3 CLI/outbox | PASS (yerel + CI) | `tests/domains-fixture.php:domainCli`, `src/Domains/EventSink.php:consume` | `proc_open([PHP_BINARY,bin/console.php,...])`: bounded dispatch, boolean queue worker drain, domain sonucu ve fiziksel create/call sayısı. Aynı event ve ayrı duplicate queue job; crash-after-effect CLI expire ve replay engeli |
| 4 Kesin no-effect | PASS (yerel + CI) | `src/Domains/AttemptEvidence.php:32`, `DomainReconciliation.php:80` | Call yok, UNKNOWN+no_effect, fingerprint/account/resource uyumsuzluğu failure açmaz; bulunan fiziksel kaynak failure kararını reddeder. Uyumlu definitive call + operator kararı failure olabilir |
| 5 Renewal finans kaynağı | PASS (yerel + CI) | `src/Services/DomainPeriods.php:17`, `DomainEngine.php:164`, `DomainEngine.php:289` | Services.Renewals.request immutable service/customer/generation/order/period/price/bill kaynağı zorunlu. HOSTING ve aynı müşterinin başka geçerli ödenmiş DOMAIN siparişi reddedilir. Claim ve sonuçta refund, manual approval ve binding tekrar kontrol edilir. İki ayrı PHP renewal request tek canonical renewal'a birleşir; 8 runner tek uzatma |
| 6 Renewal mutabakatı | PASS (yerel + CI) | `src/Services/DomainPeriods.php:79`, `DomainReconciliation.php:80` | 1 yıl kayıt + 1 yıl yenileme + ikinci 1 yıl timeout-after-effect; reconciliation eski dönemi değiştirmez, yeni current period ve domain_renewals VERIFIED, toplam tam 3 yıl. İkinci resolve ve runner ikinci uzatma yapmaz; renew call sayısı 2 |
| 7 Failover ticari kanıtı | PASS (güvenli blok); otomatik failover PARTIAL | `src/Domains/DomainEngine.php:494` | Güncel teklif/bakiye/ücret farkı onayı sözleşmesi yok. 3 yıllık ilk request korunur; definitive no-effect sonrası MANUAL_REVIEW, ikinci attempt/create yok. `years=1` alternatif üretimi kaldırıldı. Mock no-effect canlı kanıt sayılmaz |
| 8 Transaction dışı inspection | PASS (yerel + CI) | `src/Domains/DomainReconciliation.php:41` | Snapshot transaction kapanır → registrar evidence/call → kısa fenced kayıt. Registrar evidence() transaction açıksa zaten exception üretir. Generation/active attempt/state/stamp/domain version ve 5 dk TTL; yeni inspection/versiyon eski resolve'u reddeder |
| 9 Kaynak/test/CI bütünlüğü | PASS | 001–042 korunur; yalnız additive 043 | Yerel 41 suite / 278 test / 0 hata; son kaynak üzerinde ayrıca 12+12 / 0 hata. 001–042 byte eşliği doğrulandı. Uzak kaynak/tree/blob eşliği doğrulandı; GitHub PHP 8.3.36 ve 8.4.27 job’ları 278/278, 0 hata; run 40089306734 |
| 10 Faz/canlı sınırları | PASS (kaynak sınırı); canlı NOT TESTED | Test-only guard'lar, mevcut transfer engeli | F3 kabulü korunur. F4-D/F5, main merge, production ve gerçek registrar/WHM/PayTR/Voyar mutation yapılmadı |

## Runner sözleşmesi

`once()` en fazla 100 PENDING adayı inceler ve en fazla bir provider dispatch tamamlar; ayrıca en fazla
100 expired lease'i UNKNOWN yapabilir. Doğrudan `claim()` en fazla 20, belirli ID ile 1 adayı kilitler.
Aday seçimi `runner_checked_at,created_at,id`; hold zamanı ve nedeni aynı transaction'dadır.
Finans nedeniyle bloke hedef `null` döndürür ve hold commit olur; exception ise bütün claim yazıları rollback olur.
Transaction dışında hold temizleme/yazma yoktur. Concurrent claim `FOR UPDATE SKIP LOCKED` kullanır.
İlk tarama yalnız bloke işler görürse cron biter; sonraki cron kalıcı sıradan devam eder. Henüz bakılmamış
NULL zamanlı yeni işler, daha önce kontrol edilmiş bloke işlerin önüne gelir. UNKNOWN/MANUAL_REVIEW seçilmez.
Sürekli kapasite üstü girişte gecikme garantisi yoktur. 20 saniye yeni işe kabul sınırıdır; çalışan I/O'yu
zorla kesen hard timeout değildir. CLI DB lock wait 2 saniyedir.

## Yenileme ve finans sınırı

Yenileme order item'ı quantity=1, aynı product/service/customer, immutable price snapshot/net/tax/total,
ISO currency, payment request/order ve bir sonraki generation period ile eşleşmelidir. İlk hizmetin
unit bağı korunur; yenileme o tek service için yeni quantity=1 satın alımdır. Katalog süre sözleşmesi
MONTHS'tur; yalnız 12 ayın 1–10 tam katı kabul edilir. Eski ANNUAL varsayımı kaldırıldı.
REQUIRES_APPROVAL ve MANUAL activation otomatik dispatch edilmez. Domain için yeni manuel approval
arayüzü bu F4-C düzeltmesinde açılmadı; bu siparişler kapalı kalır.
Provider sonucu için attempt UUID/provider/account/request fingerprint/action/resource/term/no_effect
birlikte doğrulanır. Call effect_term_years kümülatif sayaç kanıtıdır; period artışı satın alınan term'dir.
Funding iptal edilse de doğrulanmış fiziksel etki saklanır; hizmet SUSPENDED olur. Para/belge geçmişi değişmez.

## Migration, geri dönüş ve demo

`043_f4_domain_audit_safety.php`: mock call effect resource/term alanları ve immutable domain renewal binding.
041 ve 042 dahil önceki migration dosyaları değiştirilmedi. Yeni `domains-audit.php` setup'ı gerçek MariaDB'de
001–042 kurar, yalnız 043 upgrade'i uygular, ikinci migrate no-op ve assertReady doğrular.
Yeni alanlar eski call kayıtlarını kesin kanıta dönüştürmez: eski/yetersiz kayıtlar manuel incelemede kalır.

Geri dönüş: cron durdur, UNKNOWN'u yeniden gönderme; doğrulanmış kod+DB+APP_KEY snapshot'ını birlikte
restore et veya forward-fix uygula. DDL implicit commit nedeniyle destructive otomatik down yoktur.
F4-C yerel test DB'si production restore provası değildir.

Demo: 12 operasyon senaryosu ve 12 ek denetim regresyonunu çalıştır; CLI testinde ödenmiş domain attach →
register enqueue → outbox dispatch → queue worker → SUCCEEDED/tek fiziksel create; crash senaryosunda
CLI expire → UNKNOWN/ikinci create yok; ikinci renewal timeout senaryosunda inspect → açık operator
resolve → doğru yeni dönem/tek uzatma. Test isimleri ham çıktıda birebir bulunur.

## Doğrulama durumu ve sınırlar

PHPStan Level 5: 0 hata. Lint/mimari: 263 PHP dosyası / 0 ihlal. Güvenlik örüntü taraması:
273 source/config/test dosyası / 0 bulgu. Bu tarama tam bağımsız güvenlik denetimi değildir.

Yerel tam koşu 41 suite, 278 test, 0 hata: önceki 254 + mevcut 12 operasyon + yeni 12 denetim testi.
Son kaynak değişikliklerinden sonra iki domain suite ayrıca yeniden çalıştı: 12+12, 0 hata.
Önceki tabanın test tanımları değiştirilmedi; yalnız ortak domains-fixture yardımcı dosyası aynı DB için
tek anahtar, gerçek MONTHS döngüsü ve uyumlu Commerce nesneleriyle düzeltildi. Hiçbir test silinmedi,
ignore edilmedi; sözleşmesi yanlış önceki yeni testler gerçek arayüze göre güçlendirildi.

Ham kanıtlar:
- [Tam yerel koşu](evidence/f4/second-audit-local-full.txt)
- [Nihai 12 operasyon testi](evidence/f4/second-audit-operations-final.txt)
- [Nihai 12 denetim regresyonu ve 042→043 upgrade](evidence/f4/second-audit-negative-final.txt)
- [PHPStan/lint/mimari/audit](evidence/f4/second-audit-checks.txt)
- [001–042 checksum ve önceki test kaynağı koruması](evidence/f4/second-audit-preservation.json)
- [Kaynak commit/blob manifesti ve yayın durumu](evidence/f4/second-audit-source.json)
- [CI job/adım sonuçları](evidence/f4/second-audit-ci.json)
- [PHP 8.3 gerçek suite sayımı](evidence/f4/second-audit-ci-83.json)
- [PHP 8.4 gerçek suite sayımı](evidence/f4/second-audit-ci-84.json)

## GitHub CI — doğrulanmış kaynak

[Run 40089306734](https://github.com/Boydaksu/hostidercp/actions/runs/40089306734),
test edilen HEAD `457ee9a5c6cae3b6ecb007cf1ac4dac8a3cedecb`:

| Job | Ortam | Sonuç | Ana test sayımı |
|---|---|---|---|
| [verify (8.3)](https://github.com/Boydaksu/hostidercp/actions/runs/40089306734/job/120858531630) | Ubuntu, PHP 8.3.36, MariaDB 10.11.19 | SUCCESS | 41 suite / 278 test / 0 hata |
| [verify (8.4)](https://github.com/Boydaksu/hostidercp/actions/runs/40089306734/job/120858531833) | Ubuntu, PHP 8.4.27, MariaDB 10.11.19 | SUCCESS | 41 suite / 278 test / 0 hata |

Her job’da 254 önceki test + 12 operasyon + 12 ek denetim testi çalıştı. Sayım indirilen gerçek job
günlüklerinden test dosyası/adım bazında çıkarıldı; 10 Services HTTP, 30 Commerce HTTP ve 20 session
concurrency kararlılık tekrarı ana 278 sayısına eklenmedi. Bu tekrarlar da başarılıdır. Composer locked
audit, PHPStan, lint/mimari, güvenlik örüntü taraması ve iki JS syntax kontrolü başarılıdır.

İlk otomatik onay reddinden sonra kullanıcı aynı dal/PR için açık yükleme ve CI onayı verdi; yayın bu
onayla yapıldı. Önceki başarısız koşular 40086094886/40086092542 başarı kanıtı sayılmadı.
Bu rapor ve kanıt güncellemesi PHP/migration/test/workflow kaynağını değiştirmez; final rapor commit’i
ayrı GitHub CI tetikleyebilir. F4-C bağımsız kabul gelmeden DONE değildir.

Açık GATE’ler: G-R01/G-D01, G-V01–04, G-C01, G-P01–02, G-X01–02, G-O01–04 ve G-T01 OPEN.
Canlı registrar fiyat/bakiye/no-effect sözleşmesi NOT TESTED. Otomatik alternatif registrar dispatch
bilinçli olarak kapalı; yeni ticari onay mekanizması uydurulmadı. F4-C bağımsız kabul bekler;
F4-D ve F5 başlamaz. Main merge, production deployment ve canlı mutation yapılmadı.
