# F4-E — Cloud finans ve maliyet motoru test/mock teslimi

24 Ekim 2026. Dal `codex/f4-domain-cloud`. F4-D bağımsız kabul edildi; taban
`dc70fa50e969ab1822723fdf9df4c89b657dacb4`, 325 test, migration 001–045.
**Yalnız F4-E test/mock teslimi; bağımsız kabul bekler. Tam F4 DONE değildir.**
FIN-010, R5-04, R7-02/03/06 ve PROV-005 yalnız mock izlenebilirlik kapsamındadır.
F4-F/F5, merge, production ve gerçek sağlayıcı işlemi yapılmadı.

## Kaynak / CI / regresyon kimliği

Gerçek GitHub uygulama SHA: `f0af03a816a523a4e822cdf86c96261efb80194b`.
Yerel source metadata SHA `a4bfb37417b10167f6bcce67c7a09a044942e733` ile aynı
`cefc14af198947a38b50ef780f4125275bd1fe55` tree doğrulandı. 26 source dosyası ve
001–045 hash eşliği [yayın manifestinde](evidence/f4-e/publication.json).
[Uygulama commit'i](https://github.com/Boydaksu/hostidercp/commit/f0af03a816a523a4e822cdf86c96261efb80194b),
[mevcut taslak PR #2](https://github.com/Boydaksu/hostidercp/pull/2).

Doğrulanmış kaynak branch CI: [40119824445](https://github.com/Boydaksu/hostidercp/actions/runs/40119824445).
- [PHP 8.3.36 / job 120948736264](https://github.com/Boydaksu/hostidercp/actions/runs/40119824445/job/120948736264): **48 suite / 354 test / 0 hata**, SUCCESS.
- [PHP 8.4.27 / job 120948736453](https://github.com/Boydaksu/hostidercp/actions/runs/40119824445/job/120948736453): **48 suite / 354 test / 0 hata**, SUCCESS.

MariaDB 10.11.19; checkout doğrudan kaynak SHA. Composer locked audit, PHPStan, lint,
pattern scan, 10 service HTTP / 30 Commerce HTTP / 20 session concurrency tekrarı ve JS
kontrolleri iki job'da başarılıdır. [CI suite/worker/fairness/ham log API kanıtı](evidence/f4-e/ci.json).
PR koşumu da [40119825512](https://github.com/Boydaksu/hostidercp/actions/runs/40119825512) ile
PHP8.3 job120948738828 ve PHP8.4 job120948738651'de ayrı ayrı 48/354/0 SUCCESS oldu.
Gerçek PR checkout `f8c18dd87030cf8bb2923d2b39a90a64b07aee3f` fetch edildi; kaynakla aynı
`cefc14af198947a38b50ef780f4125275bd1fe55` tree ve sıfır dosya farkı doğrulandı.
Bu test checkout'udur; hedef dala merge yapılmadı.

Yerel PHP 8.4.24 / MariaDB 10.11.18 temiz schema: **48 suite / 354 test / 0 hata**.
325 önceki + 24 yeni functional + 5 yeni worker testi. [Tam suite sayımı](evidence/f4-e/local.json),
[allowlist ham cost/worker çıktısı](evidence/f4-e/local-cost-tests.txt).
PHPStan; Composer locked audit (0 advisory); 299 PHP lint/mimari kontrolü; 309 dosya pattern
scan başarılıdır. Pattern taraması tam secret/vulnerability denetimi değildir.
Eski test silme/ignore/gevşetme yoktur. Yalnız cloud.php ve domains-audit.php exact migration
beklentilerine 046/047 eklenmiştir; eski 325 davranış ve F4-D karşı-örnekler aynen çalışır.

## Finans ve Cloud mimarisi

Müşteri **30 DAYS prepaid** satış bedeli, provider saatlik maliyeti, tahsilat ve belge dört ayrı
gerçektir. CloudOffers yeni fiyat/config için DAYS/30 kontrolü yapar; calendar MONTH veya başka
süre serbestçe satın alınamaz. Immutable mevcut order/item/quote/bill/svc_price ve period korunur.
CloudServices.costTerms yalnız bu modülün kendi period snapshot'ını okuyan application gateway'dir.

Provider maliyeti yeni bir muhasebe değildir: Finance.ProviderCosts, mevcut Ledger.post'u
kullanır. Pozitif gross maliyet **DEBIT provider_cost / CREDIT provider_payable** olarak aynı
currency'de dengeli ve tekil accrual olur. Yeni provider_payable mevcut Ledger hesap listesine
eklenir; cash/payment/receivable/customer wallet/revenue journal'ı oluşturulmaz. Supplier tax
recoverability kanıtlanmadığından mock gross expense modeli kullanılır; net/tax ayrı tutulur.
Sıfır tutarlı kanıtlı hesapta cost kaydı olabilir, pozitif tutar isteyen Ledger'a sıfır journal
uydurulmaz. Eksik kanıtta ise cost kaydı yoktur; bu durum **UNVERIFIED**, sıfır maliyet değildir.

Cost record + Ledger/Audit + finance.posted ve cloud-cost.recorded outbox aynı transaction'dır.
Unique account/usage reference, work ID, meter ID ve sequence; immutable input HMAC; work row
FOR UPDATE SKIP LOCKED ve instance/resource lock çift accrual/overlap'ı engeller. Bütün iş yerel
SQL/hesaptır; provider metering fixture uygulama transaction'ı dışında import edilir. İçeride
harici API/POST yoktur. Local crash/DDL insert fault tüm accrual/cost/outbox'u geri alır.
F4-D operasyon lease/fence/UNKNOWN/containment mekanizmaları değiştirilmez.

Local cost/proposal transaction'ları yalnız sonraki transaction için READ COMMITTED kullanır;
F3/F4-D session isolation değişmez. 047 generated admissible + ordered admission/proposal
indeksleriyle FOR UPDATE LIMIT 1 sorgularındaki locking filesort kaldırılır. Her işin explicit
resource/instance lock ve unique/idempotency kontrolü devam eder. Yalnız Database.transaction
rollback'inden sonra 1205/1213 için en fazla üç local SQL denemesi vardır; unknown commit,
connection loss veya provider POST tekrar edilmez. Forced 1213 testi Ledger/outbox'un ilk
attempt'inin geri alındığını ve tek kayıt kaldığını doğrular. SQL infrastructure hatası business
REVIEW'a çevrilmez; rollback/retry veya güvenli CLI failure olur. Child testleri hata olsa da
bütün süreçleri drain eder; yalnız güvenli driver code ve retry sayacı kaydedilir.

## Usage kanıtı ve durum makineleri

CloudMetering, **sentetik normalized fixture** sınıfıdır; gerçek Voyar response mapper değildir.
Gerçek provider tarife/usage alanı uydurulmaz. Account, instance, retained CREATE task/resource
referansı, exact spec hash, explicit start/end/observation, usage reference ve sequence birlikte
aranır. Config test dışındaysa yeni modül kapalıdır. Credential/VNC/root password alanı normalize
şemada yoktur; unknown alanlar reddedilir. Envelopes ve hesaplar context-bound AEAD + HMAC ile,
tarifeler immutable policy + HMAC ile saklanır. Normal müşteri API/projection veya yeni HTTP
endpoint'i açılmaz; mevcut admin-only katalog cost proposal görünümü supplier secret içermez.

`cloud_effects.observed_at` **faturalandırılabilir başlangıç/bitiş değildir**. Hesap yalnız
provider fixture'daki interval'dan çıkar; effect saati usage boundary'ye çevrilmez.
Strict UTC second resolution, start<end<=observed<=trusted clock, tariff valid range ve freshness
kontrol edilir. Sequence boşluğu UNVERIFIED; eski sequence/overlap, stale veya yanlış kaynak
REVIEW olur. Ayrı aralıklar desteklenir; eksik aralık sıfır usage olarak doldurulmaz.

| Gerçek | State / kural |
|---|---|
| Cost work | PENDING → UNVERIFIED (missing meter/task/resource/tariff/FX veya sequence gap) → kanıt gelirse tekrar doğrula |
| Hatalı kaynak/aralık | REVIEW; maliyet/journal yok, kör otomatik düzeltme yok |
| Tam sentetik kanıt | COSTED_MOCK + immutable cost + native ledger accrual; gerçek Voyar PASS değildir |
| Provider invoice | UNVERIFIED / REVIEW / MATCHED_MOCK, append-only reconciliation basis |
| Eksik cost sonrası invoice | İlk UNVERIFIED kaydı değişmez; yeni kanıtla yeni reconciliation eklenir |
| Provisional invoice | Eşlik gösterebilir; final binding oluşturmaz ve sonraki final kanıtı engellemez |
| Final invoice | Tam declared coverage + native currency + exact total eşliği; aynı cost ikinci final invoice'a bağlanamaz |
| Price proposal | Mevcut catalog_cost_proposals + MOCK evidence link → ayrı native admin approval → ayrı Commerce confirmed publication |

Invoice farkı, yanlış account/VM/currency/coverage/stale observation veya duplicate invoice
REVIEW olur; cost veya journal overwrite/otomatik ek para hareketi yapılmaz. Invoice match
**ödeme/settlement veya provider_payable sıfırlama değildir**. Nihai fatura/kullanım kanıtı
olmadan maliyet bitişi varsayılmaz. STOPPED ve TERMINATED ne sıfır cost ne de son faturanın
sıfırlandığı anlamına gelir. Geçmiş kullanım/fatura VM sonlandıktan sonra da doğrulanabilir.

## Exact hesap ve açık sentetik politikalar

Finance.Decimal, Unsigned, Money, Currency, FxSnapshot ve Pricing.TaxCalculator yeniden
kullanılır; para float'a dönüştürülmez. Native Money storage overflow/ISO exponent kontrolleri
korunur. Kullanım saniyesi integer; quantum'a yukarı yuvarlama ve minimum_seconds/minimum_minor
her usage interval için açık policy'dir. Saatlik rate ve izinli hourly addon toplamı billed
seconds ile çarpılıp **doğrudan nihai currency exponent'ine** bölünür; ara double rounding yoktur.
Net rounding HALF_UP / HALF_EVEN / TOWARD_ZERO snapshot'tadır. Minimum önce usage'a uygulanır.

Sabit fee ayrı izinli minor-unit/quantity tablosudur, hourly addon değildir. Usage ve fee
vergisi ayrı component'te HALF_UP hesaplanır; net/tax/fee/toplam ayrı snapshot'larda kalır.
Bu policy gerçek Voyar veya vergi sözleşmesi kabulü değildir. Gerçek minimum/rounding/addon/fee/
stop-billing/final-invoice kuralları GATE kanıtı olmadan production politikası sayılamaz.

FX yalnız immutable source/target/rate/spread/reference/observed/rounding snapshot'ıyla
raporlama dönüşümüdür. FX için native pricing.manage onayı da aranır; stale FX UNVERIFIED kalır.
Ledger native currency'yi korur; raporlama FX'i gerçek currency exchange veya cash hareketi
üretmez. Provider invoice native currency'de karşılaştırılır; farklı currency sessiz çevrilmez.

## FIN-010: öneri ≠ onay ≠ yayın

Maliyet artışı/marj düşüşü mevcut fiyatı değiştirmez. Öneri worker'ı immutable doğrulanmış mock
cost ve 30 günlük mevcut kaynak katalog fiyatını kullanır; **ticari varsayılan marj yoktur**.
`--margin-rate` açıkça verilmelidir. Testlerde %20 sentetik fixture'dır, Hostider ticari kararı
veya gerçek kârlılık vaadi değildir. 30 günlük forecast bir tahmin snapshot'ıdır; supplier bill
veya müşteri charge değildir. FX uyuşmazlığı ve one-off fee için publishable amount üretilmez;
REVIEW gerekir. Sabit fee aylık tekrarlayan katalog fiyatına sessizce dağıtılmaz.

Mevcut CatalogService.cost-propose kullanılır; paralel proposal sistemi kurulmaz. Her yeni link
DB CHECK ile auto_publish_allowed=FALSE ve provenance=MOCK'tur. Müşteri satış fiyatı değişmeden
öneri kalır. Native Identity admin session + MFA/fresh step-up + finance.approve/pricing.manage
ile açık manual mock approval gerekir. Bu da fiyatı değiştirmez. Yayın için ayrı Commerce
state/plan/payload/session bağlı tek kullanımlık confirmation ve catalog.manage gerekir.
Base price değişimi, stale evidence, session/identity drift, false acceptance veya replay reddedilir.

Yayın yalnız test ortamında yeni immutable catalog_price oluşturur ve aynı satın alınabilir
fixed Cloud config'i yeni price'a kopyalar. Geçmiş paid bill/order/svc_price/unit entitlement
eski price'a bağlı kalır. Gerçek maliyet kanıtı olmayan mock öneriler **otomatik gerçek yayın**
sayılmaz. F4-F öncesi yeni controller/panel veya müşteri seçilebilir ücretli custom açılmaz.

## İnvariant → test kanıtı

| İnvariant | Yeni regression kanıtı |
|---|---|
| Tek usage / tek cost / dengeli Ledger / atomic outbox | cloud-costs duplicate; cloud-cost-races 1/4/8 |
| Partial hour / quantum / minimum / currency rounding | partial hours + HALF_UP/HALF_EVEN; 1800 sec; minimum floor |
| Multi interval / sıra / overlap / boş aralık | adjacent intervals, sequence gap recovery, older sequence, empty/reverse/future/stale negatives |
| Başka account/VM/task/spec/tariff kullanılamaz | source mix loop ve unapproved tariff UNVERIFIED |
| Effect observation usage değildir | ayrı usage start/end ile exact 3600 seconds |
| UNKNOWN kanıt yokken cost yok | timeout-after-effect CREATE; independent reconcile sonrası tek metering accrual |
| STOP maliyeti durdurmaz | STOPPED resource için ayrı provider interval ve pozitif cost |
| Termination final bill değildir | TERMINATED sonrası gecikmiş final invoice; önce binding yok |
| Invoice exact / mutable overwrite yok | amount/currency/resource/stale/coverage negatives; UNVERIFIED→yeni MATCHED append |
| Provisional final değildir | provisional MATCHED zero final binding; ayrı final invoice ile tek binding |
| FX/decimal/currency kesinliği | USD/TRY 43×1.02; JPY/KWD exponents; 9007199254740991 minor |
| Addon / fee / tax ayrı | izinli ipv4; fazla/unknown addon; setup fee; unknown fee ret |
| Tarifeyi staff onaylar | gerçek client session/stale step-up/non-test/LIVE/secret field negatives |
| Historical finans kayıtları değişmez | paid order/item/bill/price/payment/wallet hash; immutable UPDATE/DELETE SQL triggers |
| Öneri/onay/yayın ayrı | current price 9 kalır; ayrı approval; confirmation sonrası sentetik 180; eski bill/price aynı |
| Review öneri publishable değildir | FX mismatch, one-off fee, missing cost, changed catalog/price ve replay negatives |
| Hata journal bırakmaz | cost insert fault rollback; 1213 known local rollback/retry: tek Ledger/outbox/cost |
| Worker race/fairness | parallel overlap, SKIP LOCKED, iki cron+101 blocked, consumed receipt sonrası ayrı proposal CLI |
| 30 günlük prepaid | MONTHS cycle Cloud offer reddi; DAYS/30 period korunur |

## Cron, demo ve performans sınırı

Her worker 100 admission / 20 saniye / 64 MiB yeni iş sınırı kullanır; in-flight SQL için hard
wall timeout iddiası yoktur. checked_at ve proposal_checked_at fairness, SKIP LOCKED ve aynı
kaynak lock'ları overlap'te korunur. Source facts silinmez; queue receipt cost/proposal completion
olmadığından bağımsız cron kalıcı kayıtları yeniden keşfeder.

İzole test DB ve test APP_KEY ile demonstrasyon:

```text
php tests/cloud-costs.php
php tests/cloud-cost-races.php
php bin/console.php cloud-costs:work --max-operations=100 --max-seconds=20
php bin/console.php cloud-costs:propose --margin-rate=0.2 --max-operations=100 --max-seconds=20
```

Son komuttaki 0.2 yalnız açık mock demo girdisidir; varsayılan ticari marj değildir. Bu komut
öneri üretir, fiyat yayımlamaz. Manual approval/Commerce confirmation/yayın demo'su ilk suite'in
real Identity testinde çalışır; gerçek VM/PayTR/registrar/SMTP çağrısı yoktur.

Yerel tam koşum ölçümleri (bir cost fixture/batch; ayrı PHP süreçleri):

| Süreç | Sample | Work wall µs | Submit wall µs | p50 attempt µs | p95 attempt µs | Cost/journal/fact |
|---|---:|---:|---:|---:|---:|---|
| 1 | 1 | 346635 | 326121 | 11292 | 11292 | 1/1/1 |
| 4 | 4 | 422245 | 440764 | 750 | 11478 | 1/1/1 |
| 8 | 8 | 559959 | 576116 | 740 | 15297 | 1/1/1 |

Bu p50/p95 **admission attempt** dağılımıdır, SKIP LOCKED/no-op dahil; successful transaction
latency veya production p95 değildir. Work wall process launch içerir; throughput fixture başına
1 cost / wall süredir (2.884879 / 2.368293 / 1.785845 costs/sec). İki overlap cron 101 bloke iş
ve bir sağlıklı kullanımda 53+53 checked attempt, tek sağlıklı cost, 581009 µs wall üretti.
Bazı blocked check'lerin overlap'te tekrarı para/effect tekrarı değildir. Ölçümler hosting SLA,
kapasite veya gerçek provider gecikmesi kanıtı değildir. CI Linux ölçümleri ayrıca ci.json'dadır.
Ek izole worker suite DB sayaçları [worker-db-telemetry.json](evidence/f4-e/worker-db-telemetry.json)
içinde; sayaç kapsamı tüm beş race testi, tek PHP/cost işlemi veya production değildir.
Son izole profile: 0 InnoDB deadlock, 6 row-lock wait ve 10 ms toplam ek lock zamanı;
global tarihsel max sayaç suite latency olarak sunulmaz. Profile 5/5 PASS ve 18938 ms
wall içerir; [ham profile](evidence/f4-e/worker-profile.txt).

## Migration / geri dönüş / ilk hatalar

046_cloud_metering_costs.php dokuz gerekli tablo ekler: cloud_meter_tariffs,
mock_cloud_meter_events, cloud_cost_work, cloud_usage_costs, cloud_cost_reconciliations,
cloud_cost_invoice_bindings, catalog_cloud_cost_links/approvals/publications. Mevcut
catalog_cost_proposals ve finance_journals/lines yeniden kullanılır. Immutable UPDATE/DELETE
ve source-binding trigger'ları, unique ref/sequence/meter/journal ve FK'ler eklenir.
001–045 byte/hash eşliği, 046 ilk yayımlanan checksum eşliği ve gerçek MariaDB 045→046+047 upgrade/replay no-op doğrulandı. 047 yalnız generated admissible metadata ve iki ordered admission indeksi ekler; yeni finansal tablo eklemez.

Rollback: iki cost/proposal cron durdurulur; provider POST replay yok. Tutarlı DB/kod/APP_KEY
restore veya forward-fix; immutable evidence/journal silinmez, destructive down migration yoktur.
Maliyet veya invoice discrepancy otomatik journal correction/settlement üretmez; ayrı yetkili
Finance düzeltme/reversal iş akışı ve yeni kanıt gerekir. F4-D safety sınırları kapanmaz.

- İlk yeni suite 21/21 fail: work fingerprint kontrolüne eklenen satırda kapanış parantezi
  eksikti. Lint/PHPStan bunu yakaladı; syntax düzeltildi, ignore uygulanmadı.
- Sonraki 21 testte 6 failure: fixture'ın Cloud catalog policy ve cost policy'si aynı array
  anahtarını paylaşıyordu. cost_policy adıyla ayrıldı; native money/authorization koruması
  gevşetilmedi. 21/21 + 5/5 worker geçti.
- Explicit margin guard, fixed fee ve provisional/final ayrımıyla ilk tam yerel koşum 48/353/0
  oldu. Ancak ilk GitHub source `ba9acafb3ac128a97e2a6d1e4a678638e2c87bef` için iki sürüm
  cron overlap'te başarısız oldu: [run 40117833498](https://github.com/Boydaksu/hostidercp/actions/runs/40117833498).
  PHP8.3 job120942755740: child exit 1; PHP8.4 job120942755621: aynı hata ve ardından
  drain edilmeyen child'ın sonraki receipt testine etkisi. İlk child hata kodu eski fixture'da
  ayrıntılandırılmadığından bu ilk exit'e kesin driver code atfedilmez.
- READ COMMITTED sonrası yerel karşı-örnek ikinci cron'un boş dönebileceğini ve sağlıklı işin
  PENDING kalabildiğini gösterdi. EXPLAIN iki statuslu ORDER BY'nin Using filesort ürettiğini
  doğruladı. [Plan karşılaştırması](evidence/f4-e/admission-plan.json). 046 değiştirilmedi;
  047 ile equality-prefix generated flag ve ordered FORCE INDEX eklendi. Yeni planda filesort
  yok; iki overlap cron ilerledi. Known rollback 1213 testi eklendi; functional 24/24 ve
  worker5/5 geçti. Hiçbir maliyet/idempotency veya 325 eski assertion gevşetilmedi.
- Düzeltilmiş source f0af03a için branch ve PR matrix işleri iki sürümde SUCCESS oldu.
  Önceki CI failure'ları retry ile gizlenmedi; yeni source/047 fix sonrası tam koşum yapıldı.
  [İlk başarısız CI sayımı/FAIL satırları](evidence/f4-e/failed-ci.json) korunur. Final local
  48/354/0 ve fixed branch CI 48/354/0 ayrı kaynak kimliğiyle raporlanır.

## Açık GATE'ler ve F4-F bağımlılıkları

**PROV-005 REAL: NOT PROVEN / OPEN.** Gerçek iki saatlik Voyar ondemand VM, gerçek tarife,
usage süresi, oluşan maliyet, termination etkisi ve provider invoice/usage kanıtı elde edilmedi.
Bu faz gerçek Voyar saatlik fiyatı, STOP/TERMINATE cost cutoff veya son faturanın sıfırlandığını
PASS saymaz. **G-V01–04 ve diğer canlı GATE'ler OPEN; DOMAIN-002 PARTIAL/MANUAL_REVIEW.**

F4-F için: real SDK metering/invoice mapper ve versioned source doğrulaması; gerçek tariff,
minimum/rounding/addon/fee/tax/FX sözleşmesi; imported VM için bağımsız task/resource billing
provenance (mevcut CREATE proof yoksa UNVERIFIED); review/overlap/gap/stale discrepancy ekranı;
admin-approved margin/forecast policy; scoped HTTP/CSRF/step-up/confirmation/projection; supplier
invoice correction/reversal ve settlement entegrasyonu gerekir. Bunlar bu fazda yapılmış
sayılmaz. Kullanımın desteklenmeyen belge/policy revizyonu eski envelope'u overwrite etmez;
yeni evidence/reference ve ayrı review gerekir. UNVERIFIED tutar dashboard'da sıfır gider gibi
sunulmamalıdır. Live/production maliyet kabulü ve compliance bu mock testlerle kapanmaz.

**F4-E mock tesliminde duruldu; F4-F/F5/merge/production başlatılmadı. Bağımsız kabul istenir.**

## Tam source dosya listesi

- `.github/workflows/f0.yml`
- `README.md`
- `bin/console.php`
- `docs/F4_E_PLAN.md`
- `docs/F4_PLAN.md`
- `docs/adr/ADR-014-cloud-metering-ledger-proposals.md`
- `migrations/046_cloud_metering_costs.php`
- `migrations/047_cloud_cost_admission.php`
- `src/Catalog/CloudCostProposals.php`
- `src/Catalog/CloudOffers.php`
- `src/CloudCosts/Calculator.php`
- `src/CloudCosts/Engine.php`
- `src/CloudCosts/EventSink.php`
- `src/CloudCosts/LocalTransactions.php`
- `src/CloudCosts/Store.php`
- `src/CloudCosts/Tariffs.php`
- `src/Finance/Ledger.php`
- `src/Finance/ProviderCosts.php`
- `src/Providers/Mock/CloudMetering.php`
- `src/Services/CloudServices.php`
- `tests/cloud-cost-child.php`
- `tests/cloud-cost-fixture.php`
- `tests/cloud-cost-races.php`
- `tests/cloud-costs.php`
- `tests/cloud.php`
- `tests/domains-audit.php`

Son teslim commit’i rapor ve kanıt ekler; source/test/migration değişmez.
