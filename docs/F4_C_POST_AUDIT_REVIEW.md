# F4-C ikinci bağımsız denetim — yeniden inceleme raporu

24 Ekim 2026. Repository: `Boydaksu/hostidercp`, dal: `codex/f4-domain-cloud`.
Başlangıç HEAD: `72e35a88ca381a5a00e0daf68e798ccb766663d7`.
Kabul edilen önceki taban: `7f875d678e4d580a0ce5ebd42875652b8873b322` (254 test).
Taslak [PR #2](https://github.com/Boydaksu/hostidercp/pull/2), hedef `codex/f3-service-engine`.

**Durum: yerel düzeltme ve doğrulama tamam; GitHub CI ve bağımsız kabul bekleniyor. F4-C DONE değildir.**

Yerel kaynak commit: `c2839dfff1179ca7ed50ac796cf1db0d12a66790`.
Kaynak tree: `5e91e5beed4a8eac7b8ac6268e06d18bc256cb03`.
Yeni uzak commit oluşturulmadı; GitHub doğrulanmış HEAD başlangıç SHA’sıdır.
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
| 1 Harness | PASS (ilgili suite) | `tests/domains-operations.php:3` harness → database → fixture sırası | 12 test tanımı ve 12 çalıştırılan senaryo; tam koşu/CI sonucu aşağıda |
| 2 Starvation | PASS (ilgili suite) | `src/Domains/DomainRunner.php:40`, `DomainEngine.php:312` | 101 bloke + 1 sağlıklı; ilk çağrı false/100 hold, ikinci çağrı SUCCEEDED/1 fiziksel create; yeni gelen sağlıklı iş de ilerler. `domains-audit.php`: hold rollback ve SKIP LOCKED; 1/4/8 ayrı PHP runner |
| 3 CLI/outbox | PASS (ilgili suite) | `tests/domains-fixture.php:domainCli`, `src/Domains/EventSink.php:consume` | `proc_open([PHP_BINARY,bin/console.php,...])`: bounded dispatch, boolean queue worker drain, domain sonucu ve fiziksel create/call sayısı. Aynı event ve ayrı duplicate queue job; crash-after-effect CLI expire ve replay engeli |
| 4 Kesin no-effect | PASS (ilgili suite) | `src/Domains/AttemptEvidence.php:32`, `DomainReconciliation.php:80` | Call yok, UNKNOWN+no_effect, fingerprint/account/resource uyumsuzluğu failure açmaz; bulunan fiziksel kaynak failure kararını reddeder. Uyumlu definitive call + operator kararı failure olabilir |
| 5 Renewal finans kaynağı | PASS (ilgili suite) | `src/Services/DomainPeriods.php:14`, `DomainEngine.php:164`, `DomainEngine.php:289` | Services.Renewals.request immutable service/customer/generation/order/period/price/bill kaynağı zorunlu. HOSTING ve aynı müşterinin başka geçerli ödenmiş DOMAIN siparişi reddedilir. Claim ve sonuçta refund, manual approval ve binding tekrar kontrol edilir. İki ayrı PHP renewal request tek canonical renewal'a birleşir; 8 runner tek uzatma |
| 6 Renewal mutabakatı | PASS (ilgili suite) | `src/Services/DomainPeriods.php:76`, `DomainReconciliation.php:80` | 1 yıl kayıt + 1 yıl yenileme + ikinci 1 yıl timeout-after-effect; reconciliation eski dönemi değiştirmez, yeni current period ve domain_renewals VERIFIED, toplam tam 3 yıl. İkinci resolve ve runner ikinci uzatma yapmaz; renew call sayısı 2 |
| 7 Failover ticari kanıtı | PASS (güvenli blok); otomatik failover PARTIAL | `src/Domains/DomainEngine.php:494` | Güncel teklif/bakiye/ücret farkı onayı sözleşmesi yok. 3 yıllık ilk request korunur; definitive no-effect sonrası MANUAL_REVIEW, ikinci attempt/create yok. `years=1` alternatif üretimi kaldırıldı. Mock no-effect canlı kanıt sayılmaz |
| 8 Transaction dışı inspection | PASS (ilgili suite) | `src/Domains/DomainReconciliation.php:41` | Snapshot transaction kapanır → registrar evidence/call → kısa fenced kayıt. Registrar evidence() transaction açıksa zaten exception üretir. Generation/active attempt/state/stamp/domain version ve 5 dk TTL; yeni inspection/versiyon eski resolve'u reddeder |
| 9 Kaynak/test/CI bütünlüğü | PARTIAL | 001–042 korunur; yalnız additive 043 | Yerel 41 suite / 278 test / 0 hata; son kaynak üzerinde ayrıca 12+12 / 0 hata. 001–042 byte eşliği doğrulandı. GitHub PHP 8.3/8.4 NOT TESTED: yayın onayı bekleniyor; uzak SHA/tree eşliği henüz yok |
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

GitHub PHP 8.3/8.4 CI **NOT TESTED**. Önceki başarısız koşular
[40086094886](https://github.com/Boydaksu/hostidercp/actions/runs/40086094886) ve
[40086092542](https://github.com/Boydaksu/hostidercp/actions/runs/40086092542) başarı kanıtı sayılmadı.
GitHub connector create_tree gönderimi otomatik onay denetimince reddedildi: kod/test/migration/belgelerin
Boydaksu/hostidercp deposuna yüklenmesi için açık kullanıcı izni gerektiği bildirildi. Uzak commit/ref
oluşturulmadı; alternatif yöntemle bu ret aşılmadı. Kullanıcı onayı gelirse aynı dal/PR üzerinde yayın,
gerçek uzak commit/tree/blob eşliği, PHP 8.3/8.4 iki başarılı job ve CI bağlantıları tamamlanmalıdır.
Bu eksik nedeniyle F4-C kabul/DONE iddiası yoktur.

Açık GATE’ler: G-R01/G-D01, G-V01–04, G-C01, G-P01–02, G-X01–02, G-O01–04 ve G-T01 OPEN.
Canlı registrar fiyat/bakiye/no-effect sözleşmesi NOT TESTED. Otomatik alternatif registrar dispatch
bilinçli olarak kapalı; yeni ticari onay mekanizması uydurulmadı. F4-C bağımsız kabul bekler;
F4-D ve F5 başlamaz. Main merge, production deployment ve canlı mutation yapılmadı.
