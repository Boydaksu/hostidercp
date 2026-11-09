# F6-B B-01 — commit öncesi readiness düzeltmesi

## 27 Ekim 2026 — proje sahibi F6-B kabul kararı

**F6-B LIMITED SYNTHETIC ISOLATED IMPORT & DRY-RUN TECHNICAL BASELINE — ACCEPTED.**
**B-01 CLOSED.** Kabul edilen uygulama `d4ead54db3aad6208e1a40e70822da7ad1346e33`,
tree `0057f233dbbeebfae1b3c094ec8eeafdb0fad7a4`; ayrı kanıt belge commit'i
`dadef0b3c2d36e44dcbf2236227d160071945566`.
[FAST40514399931](https://github.com/Boydaksu/hostidercp/actions/runs/40514399931):
PHP8.3/8.4 her sürüm55 suite/664 test/0 hata; eski621+42 ve yeni1 B-01 PASS.
001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` korunur.

Kabul yalnız sentetik source validation, deterministic dry-run, isolated load,
mapping/provenance, idempotency/replay, transaction/checkpoint/recovery, sandbox/grant
izolasyonu, provider CREATE-zero, sentetik finance/support privacy ve commit öncesi
migration readiness içindir. Gerçek WHMCS, payment/ledger mutabakatı, provider resource,
support dosyaları, mailbox/AV/retention, FULL ve production-ready kabulü değildir.
F6-C SYNTHETIC RECONCILIATION & SECURITY DEVELOPMENT — GO; gerçek bağlantı,
production mapping CRUD, F6-D/E, merge/staging/deploy/cutover yetkisi yoktur.

Bu kabul commit'i yalnız dört belgeyi değiştirir; uygulama/test/fixture/CI/migration
aynıdır. Önceki CI tekrar çalıştırılmadı. Rollback yalnız belge commit revert'üdür.
PR3 OPEN/DRAFT. Son kontrolde main protected=false ve rulesets=[] görüldü;
ayarlar değiştirilmedi. Aşağıdaki teslim/bekleme kayıtları tarihçe olarak korunur.

27 Ekim 2026. CONDITIONAL bağımsız inceleme düzeltmesi; yeniden kabul bekler.
Sabit uygulama referansı: eb3c2f64f8515dcf3ceff6ca7f41c3b9c702e576.
İzlenen gereksinim: B-01 ve F6_B_PLAN commit öncesi fence/native migration readiness.

## Hedefli plan

- Kapsam: batch transaction commit'inden önce Sandbox::assertReady; mevcut
  assertBound kontrolü korunur. Yeni özellik/refactor/entegrasyon yoktur.
- Dosyalar: Loader.php, tek negatif readiness testi ve mevcut suite'e test kaydı.
  Önceki42 F6-B ve621 test gövdeleri değiştirilmez.
- Migration:001–054 ve ayrı sentetik DDL değişmez. Yalnız geçici test schema'sında
  test trigger'ı, batch entity INSERT'inde migration durumunu APPLYING yapar.
- Test: önce aynı negatif test eski kaynakta FAIL; sonra düzeltilmiş kaynakta PASS.
  Commit edilmiş prefix/cursor/history ve target/mapping/provenance/claim sayıları
  rollback sonrası korunur; trigger kaldırıldıktan sonra aynı checkpoint devam eder.
  Yerelde yalnız yeni negatif test; son adayda tek gerçek PHP8.3/8.4 FAST turu.
- Geri dönüş: yalnız B-01 uygulama commit revert; geçici schema/trigger fixture
  teardown'ı. Native DB down/restore veya production migration yoktur.
- Kanıtlar ayrı docs-only commit ile kaydedilir. FULL/Chromium/10-30-20 yoktur.
  PR3 OPEN/DRAFT, main protection ve bütün production GATE'leri korunur.

## Karşı-örnek ve düzeltme

Yeni test önce1 kayıtlık prefix'i commit eder. Ayrı setup hesabıyla yalnız ephemeral
schema'da AFTER INSERT trigger'ı kurulur; sonraki batch'in entity INSERT'i aynı
transaction içinde schema_migrations durumunu APPLYING yapar. Başlangıç readiness
kontrolü geçmiştir; mevcut assertBound bu migration bozulmasını denetlemez.

Eski eb3c2f6 uygulaması aynı negatif testte FAILED yerine APPLYING döndü:
[önce](evidence/f6-b-b01/before.txt),1 test/1 FAIL. Bu başarı olarak sayılmaz.
Değişiklik mevcut assertBound'u korur; başarılı entity batch dönüşünden önce ayrıca
assertReady çağırır. REVIEW_REQUIRED ile run/history yazılan batch dönüşü de aynı
tam readiness kontrolünden geçer. assertReady'nin grant/table/native migration
kontrolleri değiştirilmedi; readiness hatası native transaction rollback'ine gider.

[Sonra](evidence/f6-b-b01/after.txt): yalnız yeni negatif test1/1 PASS.
Run satırının tamamı (cursor/version/checkpoint dahil), history, migration geçmişi
ve bütün teknik tablo sayıları başarısız batch öncesiyle eşittir; cursor1'de kalır. Trigger
kaldırıldıktan sonra aynı checkpoint ile26 kayıt APPLIED olur. Testteki DDL yalnız
kurulum/teardown bağlantısındadır; loader DDL/UPDATE migration yetkisi kazanmaz.
Yeni fault enum, callback veya production test hook eklenmedi.

Yeni negatif senaryo migration durumunu bozar; ayrı grant/table mid-batch fault
senaryoları çalıştırılmış gibi raporlanmaz. Bu alanların kontrolleri assertReady'nin
mevcut tam denetimidir. Eski42 F6-B testi yerelde tekrar koşturulmadı.

## Kaynak ve koruma

- Uygulama SHA / FULL aday: `d4ead54db3aad6208e1a40e70822da7ad1346e33`.
- Uygulama tree: `0057f233dbbeebfae1b3c094ec8eeafdb0fad7a4`.
- Değişen uygulama dosyaları yalnız Loader.php, migration-import.php test kaydı ve
  yeni migration-readiness.php'dir. CI workflow/registry ve DDL değişmez.
- [Koruma kanıtı](evidence/f6-b-b01/preservation.json): eski42 test metni yeni
  require satırı çıkarıldığında aynıdır; eski621 tabanının404 dosyası değişmez.
  001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` korunur.
- Geri dönüş yalnız bu uygulama commit'inin revert'ü ve owned fixture teardown'ıdır.
  F6-A kabulü ve önceki F6-B kanıtları değiştirilmez.

## FAST / durma sınırı

[40514399931](https://github.com/Boydaksu/hostidercp/actions/runs/40514399931)
son uygulama SHA'sında başlatılan tek FAST turudur; SUCCESS.
Her sürümde55 suite,664 test/0 hata: eski621 + mevcut42 F6-B + yalnız1 B-01 testi.

| Job | Doğrulanmış sonuç |
|---|---|
| [PHP8.3 —122224529530](https://github.com/Boydaksu/hostidercp/actions/runs/40514399931/job/122224529530) |55 suite/664 test/0 hata;621+42 eski ad eksiksiz; yeni1 PASS|
| [PHP8.4 —122224529624](https://github.com/Boydaksu/hostidercp/actions/runs/40514399931/job/122224529624) |55 suite/664 test/0 hata;621+42 eski ad eksiksiz; yeni1 PASS|
| Scope/security —122224422348 | broad55 suite; ayrı6 CI helper/0 hata |
| FAST result —122231928242 | SUCCESS |

[CI kanıtı](evidence/f6-b-b01/ci.json) raw job referanslarını, suite sayılarını,
eski621 ve42 adının ayrı multiset karşılaştırmalarını ve yalnız1 yeni test adını
içerir. İki job'da PHPStan0, lint415/0, pattern437/0, Composer locked audit temiz,
native JS syntax ve3 state kontrolü PASS; browser lock audit temizdir. Bunlar
Chromium/stability maratonu değildir. [Yerel kayıt](evidence/f6-b-b01/local.json)
PHP8.4.24, eski kaynakta1 FAIL ve düzeltilmiş kaynakta1 PASS sonucunu bağlar.

Yerelde yalnız bu yeni testin önce/sonra koşuları ve değişen PHP dosyalarının lint'i
çalıştırıldı. PHPStan/lint/audit ve gerekli tüm suite seçimini normal FAST yürütür.
FULL, Chromium,10/30/20 tekrarları dispatch edilmedi; F6-C'ye geçilmez.
PR3 OPEN/DRAFT, main protection ve bütün vendor/production GATE'leri korunur.
F6-B bağımsız teknik kabulü proje sahibinin yeniden kararını bekler.
