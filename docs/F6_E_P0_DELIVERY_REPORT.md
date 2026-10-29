# F6-E-P0 — offline/sentetik preflight teslimi

28 Ekim 2026. **TARGETED DEVELOPMENT VALIDATED; INDEPENDENT CODE REVIEW PENDING.**
Bu teslim gerçek F6-E/cutover veya üretim kabulü değildir.

## 1. R2 kapanışı ve yeni adayın ayrımı

Proje sahibinin ilettiği bağımsız karar [R2 kabul kaydında](F6_D_R2_ACCEPTANCE_RECORD.md):
**F6-D-R2 LIMITED SYNTHETIC READ-ONLY RECOVERY RECONCILIATION — ACCEPTED WITH EXPLICIT EXCEPTIONS**.
Yalnız eski application53162495902b3b168c2fb8554594e99c23a34d62, treee5a4dc3c8daff38e09ed8f1fb1f8225366a91f47,
evidencecc94f662454cb3f030addd2db0785a09900c0ed2. ADR-030 yöntem kararı ayrı kayıttır.
Eski başarısızlıklar, quality placeholder, tek broad olmaması, historical runtime/ENOSPC ve
bütün recovery istisnaları korunur. Yeni P0 bu kabul/CI istisnasını otomatik devralmaz.

GitHub başlangıç HEAD546dda93f1ba49387cd9702c3693c7b2dcac3d63. Yerel detached worktreecc94f662
tabanındadır; önceki API yayımlanmış üç belge yerelde untracked kopyadır. Hiç reset/clean/checkout yapılmadı.
Yeni kod uncommitted; yeni application SHA üretilmedi. Candidate source manifest SHA-256:
`ec9dcfe23c2b00559170be9ff353ce33b3bf82353e7ab8c16f16b505b3dc4c0c`.
[478 dosyalı gerçek kaynak manifest'i](evidence/f6-e-p0/candidate-source-manifest.json) üç test/kalite koşusunda aynıdır.
Bu hash Git commit SHA değildir. Eski376 src/test ve54 migration dosyası raw Git blob düzeyinde
aynıdır; [preservation](evidence/f6-e-p0/preservation.json). Production migration subtreec0729c92dd143c017fdec32421bf67fe6b51e05b korunur.

## 2. Çalışan bileşen ve entegrasyon

OPS-004, OPS-001/002/003, SEC-004, FIN-001/003/006, SUP-003/004/010 ve D6 sentetik alt kapsam.
[Plan](F6_E_P0_PLAN.md), [ADR-031](adr/ADR-031-offline-synthetic-cutover-preflight.md).

- Yeni Preflight, mevcut final F6-C Inspector ve R2 RecoveryInspector'ı doğrudan yeniden çağırır.
- Fixture/Plan, checkpoint/HMAC/mapping/provenance/count/finance/currency/ownership/privacy ve
  actual restored-root davranışları mevcut mekanizmalardır; ikinci motor veya native tablo yazımı yoktur.
- Source önce/sonra fingerprint'i, source observed root, recovery observed root, verified logical root,
  kaynak snapshot/roots ve backup kimliği bağlanır. Bozuk/eksik/belirsiz kanıt BLOCKED olur.
- Typed final reader/context girişi generic JSON veya caller-rehashed Report PASS'ını yetki saymaz.
- Test/synthetic flag'leri, kısa lease ve purpose her değerlendirmede kontrol edilir; cache yoktur.
- Named Simulation gözlemleri yalnız sentetiktir. Çift/eksik actor, UNKNOWN callback, in-flight
  provider, incomplete delta, missing gate inventory ve forged real-gate claims reddedilir.
- Olumlu sonuç yalnız SYNTHETIC_PREFLIGHT_COMPLETE; daima cutover_allowed=false/effects_allowed=false.
- Rapor yalnız safe code, gate ID ve hash içerir; credential/body/customer/schema/path sızdırmaz.
- DB/root kurulumu test harness'ına aittir; Preflight yalnız reader'lara sahiptir. Session/advisory lock
  işlemleri persistent target write değildir. Global cross-DB atomik snapshot/final live delta ispatı yoktur.

## 3. Gerçek GATE listesi — hepsi açık/bloke

G-O03; G-O01/02/04; G-V01/02/03/04; G-P01/02; G-C01; G-R01; G-D01; G-X01/02; G-T01;
FINANCE_RECONCILIATION; SUP-010; OPS-002/003/004; SINGLE_ACTIVE_ACTOR; CALLBACK_ROUTING; FINAL_DELTA;
NATIVE_RUNTIME_RECOVERY; ORIGINAL_ARCHIVE_BYTES; ISSUER_RECOVERY; OFFSITE_KEY_CUSTODY;
PITR_RPO_RTO; LIVE_CUTOVER_AUTHORIZATION.

Toplam30 gate kategorisi:28 OPEN, NATIVE_RUNTIME_RECOVERY ve LIVE_CUTOVER_AUTHORIZATION BLOCKED.
Fonksiyonel büyük harfli isimler P0 rapor kategorileridir; doğrulanmış yeni dış GATE numarası değildir.
Sıradan JSON true/CLOSED flag'i hiçbirini kapatamaz. SYNTHETIC_PREFLIGHT_COMPLETE bu gate'leri kapatmaz.
Real enrollment, canlı fence/actor/callback routing ve gerçek delta kanıt adapter'ları **BLOCKED / NOT IMPLEMENTED**;
kullanıcı kaynak şeması/API alanı veya operasyonu uydurulmadı.

## 4. Gerçek test sonuçları

| Suite | PHP8.3.36 | PHP8.4.27 |
| --- | --- | --- |
| Yeni migration-cutover-preflight |27/0|27/0|
| Mevcut migration-reconciliation |35/0|35/0|
| migration-recovery-integrity |2/0|2/0|
| migration-recovery-target |24/0|24/0|
| migration-recovery (R1) |25/0|25/0|
| migration-contract |31/0|31/0|
| finance-unit |4/0|4/0|
| identity-security |8/0|8/0|
| hardening-unit |7/0|7/0|
| Toplam |**9 suite /163 test /0 failure**|**9 suite /163 test /0 failure**|

Yeni27 test ilk çalıştırıldı; sonrasında136 ilgili regresyon yürütüldü.
Negatif assertion'lar gerçek sentetik DB'de snapshot, checkpoint, mapping/provenance,
count/history, finance/currency, attachment owner, support visibility ve restored hash tahrifatı yapar.
Ayrıca iki ayrı kendi içinde PASS DB'nin farklı logical root'larla birleştirilmesini reddeder.
Stale lease/marker, önceki olumlu rapordan sonra tahrifat, environment/purpose eskalasyonu,
sahte JSON/Report, invalid simulation ve redaksiyon/gerçek reader bağlantı kesintisi sınanır.
Olumlu test tüm gerçek gate durumlarını tek tek doğrular. Tekrar/no-write testi source ve
restored DB fingerprint'lerinin değişmediğini ve deterministik report'u kontrol eder.

Bu **hedefli** koşudur;61 paket/809 test yalnız güncellenmiş registry beklentisidir, full PASS değildir.
Yeni suite FAST registry ve FULL listesine birlikte eklenmiştir; workflow çalıştırılmamıştır.
Core/Identity/Finance veya accepted Migration kodu değişmediği için broad/FULL/maraton tekrarı yapılmadı.

## 5. Kalite ve korunan runner başarısızlığı

Her sürümde PHPStan0, lint456/0, pattern478/0 ve belge encoding/pattern kontrolü exit0.
Yeni bağımlılık/lockfile yok; Composer/npm audit yeniden çalıştırılmadı, güncel audit PASS iddia edilmez.

İlk PHP8.3 koşusunda tüm163 uygulama testi geçti; runner belge adımı255 ile durdu çünkü
ephemeral kopyada Git index yoktu. Bu koşunun status FAILED kaydı korunur.
İlk PHPStan JSON çıktısı newline olmadan exit marker'a birleşti; o ilk metadata'dan PHPStan PASS türetilmedi.
Yalnız runner düzeltildi: geçici kopyada git init + docs index, commit yok; çıktı separator'ı düzeltildi.
Aynı478 kaynak hash'iyle yalnız kalite kontrolü tekrarlandı ve dört quality exit0 kaydedildi.
PHP8.3 uygulama testleri gereksiz tekrarlanmadı. PHP8.4 hedefli+quality tek koşuda tamamlandı.
Bu ayrım [summary](evidence/f6-e-p0/summary.json) ve ayrı log/result dosyalarında korunur.

Ortam: mevcut pinned PHP8.3/8.4 image'ları, MariaDB10.11.18 image ID
sha256:de61fed4a40d3842f3ee09944ba52792156cfd9adf489b2cc670fc6ded28df8d,
dedicated internal ağ, host portu yok,4GiB tmpfs DB; kaynak host bind-readonly ve ephemeral çalışma kopyası.
Mevcut migration'lar yalnız test harness'ının izole sentetik DB'lerinde çalıştı; yeni production migration yok.
Kaynak manifest'i container kopyasında başta/sonda ve host'ta karşılaştırıldı.
Yeni container/ağlar yalnız tam görev kimliği+ownership label doğrulanarak temizlendi.
Raw hata/credential çıktılarına log izni verilmedi; filtreli test isimleri ve kodlar kaydedildi.
Runner review kopyasındaki sabit sahte test password alanı redakte edilmiştir; yürütülen runner hash'i ayrı kayıttadır.

## 6. Kanıtlar, diff ve dosya grupları

- [Kaynak diff'i](evidence/f6-e-p0/source.diff): yalnız iki yeni PHP sınıfı, yeni test ve suite registration.
- [R2 kapanış diff'i](evidence/f6-e-p0/r2-closure.diff): owner yöntemi ve iletilen ayrı teknik kabul kaydı.
- [Tam değişen dosya listesi](evidence/f6-e-p0/changed-files.json): önceki yayımlanmış üç yerel kopya ayrı gösterilir.
- [Kanıt hash manifest'i](evidence/f6-e-p0/manifest.json): bu görevdeki yeni kanıt dosyaları.
- [Kaynak koruma kanıtı](evidence/f6-e-p0/preservation.json) ve [makine özeti](evidence/f6-e-p0/summary.json).

R2 kapanış diff'i önceki görevden kalan commit edilmemiş owner-method ekleriyle bu görevin
teknik kabul eklerini birlikte gösterir; önceki üç yayımlanmış belge kopyası bu diff'e dahil değildir.

Kapanış grubu ADR-030, R2 plan/delivery, yeni acceptance record; geliştirme grubu F6_PLAN P0 yetkisi,
P0 plan/ADR-031, src/Migration/Cutover/Preflight.php, Simulation.php, tests/migration-cutover-preflight.php,
tools/ci/suites.json, .github/workflows/f0.yml, bu rapor ve yeni evidence dizinidir.
Önceki üç hazırlık belgesi byte/hash korunur. ADR-023/028/029, docs/01_DECISIONS, README ve
eski evidence değiştirilmez. Ana dizindeki docs/F6_B_B01_REVIEW.md kullanıcı değişikliği SHA-256
fe09203507b7ad8d97200aa4641b52ad103072804ad66ad41f804d47209a8aa7 korunur.

## 7. Demo, sınırlar ve sonraki bağımlılık

Demo: yeni suite'in ilk testi mevcut targetFixture→typed Inspector/RecoveryContext/RecoveryInspector
bileşiminden report üretir; SYNTHETIC_PREFLIGHT_COMPLETE,30 gerçek gate OPEN/BLOCKED,
cutover_allowed=false ve zero native/provider writes assertion'larını çalıştırır.
Aynı test zincirinde restored payload_hash tahrifatı eski olumlu sonuç yerine BLOCKED üretir.
Hiçbir demo gerçek müşteri veya canlı sistem içermez.

F6-D genel PARTIAL; native runtime recovery BLOCKED; original archive bytes OPEN;
issuer recovery PARTIAL/OPEN; offsite/key custody/PITR/RPO/RTO NOT VERIFIED;
executable inventory NOT_VERIFIED_SELECT_ONLY. FULL, merge, staging, production ve
gerçek F6-E cutover yetkisizdir. P0 için bağımsız kod kabulü henüz verilmemiştir.

## 8. Rollback / yeni SHA doğrulaması / durma

Kullanıcı dosyası, accepted R2 kaynak/kanıtları ve immutable facts korunarak yalnız bu görevdeki
yeni preflight/test/registry ekleri ve tarihli kapanış belgeleri kontrollü geri alma kapsamındadır.
Otomatik reset/clean/force checkout/revert yoktur. Test cleanup gerçek rollback değildir.

Yeni application commit SHA yoktur; otomatik push veya merge yapılmaz.
Sonraki en küçük adım kaynak/kanıt diff'inin bağımsız kod incelemesidir. Ayrı Git talimatı gelirse
mantıksal kapanış ve yeni uygulama adayını ayrı tutacak commit planı uygulanır; yeni SHA/tree ve
migration subtree doğrulanıp mevcut manifest'le bağlanır. Sonra o yeni SHA için gereken dar
bağımsız doğrulama belirlenir. Eski ADR-030 istisnası yeni SHA'ya otomatik uygulanmaz.
Bu teslimden sonra yeni faz veya gerçek cutover başlatılmaz.
