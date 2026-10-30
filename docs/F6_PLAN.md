> 30 Ekim 2026 — WHMCS migration/discovery DEFERRED; G-O03 OPEN. F5-F opt-in support auto-close geliştirmesi ayrı kapsamdır; gerçek kaynak/cutover yetkisi verilmez.

# F6 — Aktarım ve operasyon teknik planı

## 28 Ekim 2026 — R2 sınırlı kabul / F6-E-P0 offline geliştirme

[R2 kabul kaydı](F6_D_R2_ACCEPTANCE_RECORD.md): LIMITED SYNTHETIC READ-ONLY RECOVERY
RECONCILIATION — ACCEPTED WITH EXPLICIT EXCEPTIONS; exact application53162495902b3b168c2fb8554594e99c23a34d62,
evidencecc94f662454cb3f030addd2db0785a09900c0ed2. ADR-030 yöntem onayı ayrı; FAST NOT_RUN_EXTERNAL_BILLING.
Yalnız [F6-E-P0 planı](F6_E_P0_PLAN.md) kapsamında offline/sentetik evaluator ve negatif test geliştirme GO.
Gerçek F6-E/cutover/WHMCS/provider/payment/mail/FULL/merge/staging/production yetkisi yoktur.
Yeni aday için R2'nin CI istisnası/teknik kabulü otomatik geçerli değildir. Native recovery BLOCKED;
original bytes/issuer/custody/offsite/PITR/RPO/RTO açık; genel F6-D PARTIAL.
Aşağıdaki eski faz yetki/PENDING metinleri tarihçedir; P0 yeni bağımsız kod incelemesi bekler.


## 28 Ekim 2026 — R2 read-only recovery teslimi / CI engeli

R1 **LIMITED SYNTHETIC COLD VERIFICATION ACCEPTED WITH EXPLICIT EXCEPTIONS**;
kabul docs `1bcad5f93f4d75b4af336180fe71b46d1a4c1374`.
R2 development **PARTIAL; INDEPENDENT ACCEPTANCE PENDING; FAST BLOCKED_EXTERNAL**.
Application `f0db545b5b5fd0e569b07e33efb85e245be16b40`, tree
`6482e0d546e9bcafbeeb7872052eda99a97d8d26`.
Yeni24 yerel R2 ve ilgili25 R1 test PASS; eski756 testin129 dosyası blob-equal.
Actual restored target, source kaybı/Vault expiry sonrası SELECT-only fresh process'te
doğrulandı. BLOCKED_IDENTITY_BOUND **PARTIAL yalnız read-only**; runtime verilmedi.
BLOCKED_ISSUER_RECOVERY PARTIAL, ORIGINAL_ARCHIVE_BYTES OPEN; executable-object
inventory NOT_VERIFIED_SELECT_ONLY, gerçek offsite/PITR/key custody NOT VERIFIED.
[FAST40637188575](https://github.com/Boydaksu/hostidercp/actions/runs/40637188575):
attempt1 ve açık kullanıcı izniyle aynı SHA attempt2 GitHub Billing & plans engeliyle
job başlamadan FAILURE. PHP8.3/8.4 **NOT RUN**;59 suite/780 yalnız beklenen registry
toplamıdır, başarılı CI sonucu değildir. Yeni tekrar başlatılmadı.
Migration001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmez.
Bütün gerçek GATE'ler OPEN; PR3 OPEN/DRAFT; main protected=false/rulesets=[] gözlendi,
ayarlar değiştirilmedi. FULL NOT DISPATCHED; F6-E NOT AUTHORIZED; production UNCHANGED.
[R2 planı](F6_D_R2_PLAN.md), [R2 teslimi](F6_D_R2_DELIVERY_REPORT.md), [CI kanıtı](evidence/f6-d-r2/ci.json).
Aşağıdaki eski bekleme/kabul kayıtları tarihçedir.

## 28 Ekim 2026 — F6-D-R1 bağımsız sınırlı kabulü

**F6-D-R1 LIMITED SYNTHETIC COLD VERIFICATION — ACCEPTED WITH EXPLICIT EXCEPTIONS.**
Application `3773351c01164dc3e58a7c4b869ef980a5b7cf79`, tree
`80beecd299c60ed669e4d0c52c76751ee020b692`; evidence docs
`2a3b4d1d8ffd9a06cbabe66d10de694645971198`.
[FAST40566808738](https://github.com/Boydaksu/hostidercp/actions/runs/40566808738):
PHP8.3/8.4 her sürüm58 suite/756 test/0 hata; eski731+25 ve57 suite korunmuş;
125 eski test dosyası blob-equal, PHPStan0, lint443/0, pattern465/0, locked audits temiz.
Kabul versioned Ed25519/pinned key/trusted operator policy, exact backup/source,
ciphertext SHA/XChaCha20-Poly1305/original issuer record digest, source schema loss
sonrasında bağımsız salt-okunur verification, expired Vault enrollment reddi ve
immutable evidence/negatif regresyon kapsamıdır.
F6-D genel PARTIAL. BLOCKED_IDENTITY_BOUND OPEN; BLOCKED_ISSUER_RECOVERY PARTIAL;
BLOCKED_ORIGINAL_ARCHIVE_BYTES OPEN. Native application restore BLOCKED;
production cold restore/offsite/key custody/RPO/RTO/PITR NOT VERIFIED.
Bu kabul yalnız altı belgeyi değiştirir; application/test/fixture/CI/migration aynı,
başarılı756 test tekrar çalıştırılmaz. Rollback yalnız kabul belge commit revert.
R2 yalnız izole sentetik read-only recovery authority geliştirme GO; runtime
activation/F6-E/FULL/staging/production/merge/deploy/cutover yetkisi yoktur.
PR3 OPEN/DRAFT; bütün gerçek GATE'ler korunur. Aşağıdaki eski statüler tarihsel kayıttır.

## 27 Ekim 2026 — F6-D-R1 sentetik recovery hardening teslimi

F6-D **LIMITED SYNTHETIC ENCRYPTED BACKUP & RECOVERY ESCROW — ACCEPTED WITH EXPLICIT
EXCEPTIONS**, kabul docs `113334dd2c37556ed20a83b6e84eeeab3dcfc42b`.
F6-D genel **PARTIAL**. R1 geliştirme **PARTIAL; INDEPENDENT ACCEPTANCE PENDING**.
Application `3773351c01164dc3e58a7c4b869ef980a5b7cf79`, tree
`80beecd299c60ed669e4d0c52c76751ee020b692`.
[FAST40566808738](https://github.com/Boydaksu/hostidercp/actions/runs/40566808738): PHP8.3/8.4 her sürüm58 suite/756 test/0 hata; eski731 ad ve57 suite sayımı korunmuş, yeni25 PASS.
Yeni25 hedefli R1 ve32 ilgili F6-D test PASS; eski731 testin125 dosyası blob-equal.
Bağımsız pinned Ed25519 attestation, exact cipher/issuer/source binding, fresh operator
policy ve gerçek source-schema-loss/expired-Vault/fresh-process cold verification
uygulandı. Orijinal artifacts/MAC/history/provenance/backup ID değişmez.
**BLOCKED_ISSUER_RECOVERY PARTIAL**: yalnız önceden enrolled paketin read-only
verification'ı; gerçek custody/offsite/key-policy operasyonu NOT VERIFIED.
**BLOCKED_IDENTITY_BOUND OPEN; native recovery BLOCKED**; Sandbox/Inspector/marker
koşulları gevşetilmedi. **BLOCKED_ORIGINAL_ARCHIVE_BYTES OPEN**.
Migration001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmez.
G-O01–04 ve bütün gerçek vendor/finance/support/cutover kapıları açık;
PR3 OPEN/DRAFT, main protected=false/rulesets=[] gözlendi ve değiştirilmedi.
FULL NOT DISPATCHED; F6-E NOT AUTHORIZED; production UNCHANGED.
[Plan](F6_D_R1_PLAN.md), [teslim ve sınırlar](F6_D_R1_DELIVERY_REPORT.md), [recovery matrix](evidence/f6-d-r1/recovery-matrix.json).
Aşağıdaki önceki teslim/bekleme ifadeleri tarihsel kayıttır.

## 27 Ekim 2026 — F6-D sınırlı bağımsız kabulü

**F6-D LIMITED SYNTHETIC ENCRYPTED BACKUP & RECOVERY ESCROW — ACCEPTED WITH EXPLICIT EXCEPTIONS.**
Kabul edilen application `71e916a6e8e6ceb1eef9f02b5e3c6ee379064eaa`, tree
`0c5f1729089dafb07dec2e374e1079efa75fcbba`; evidence docs
`f16c306d7a6670ad512fbb224a824af1e844eac6`.
[FAST40555003177](https://github.com/Boydaksu/hostidercp/actions/runs/40555003177):
PHP8.3/8.4 her sürüm57 suite/731 test/0 hata, eski699+32 korunmuş;
PHPStan0, lint435/0, pattern457/0, migrations001–054 değişmez.
Kabul sentetik read-only/bounded DB snapshot, private file inventory, gerçek
XChaCha20-Poly1305, deterministic identity, atomic immutable publication/replay,
authenticated owned escrow restore/INSERT-only writer/metadata witness,
checksum/provenance/finance eşliği, crash/rollback/retry,1/4/8 process,
retention dry-run ve native Queue/Worker test/mock güvenliği içindir.
Genel F6-D **PARTIAL**. Açık istisnalar: **BLOCKED_IDENTITY_BOUND,
BLOCKED_ISSUER_RECOVERY, BLOCKED_ORIGINAL_ARCHIVE_BYTES**, gerçek offsite/PITR/binlog,
RPO/RTO/hosting kapasitesi, WHMCS/support/PDF restore ve key custody/rotation.
Bu işlem yalnız dört kabul belgesini değiştirir; source/test/fixture/CI/migration
değişmez, başarılı731 test tekrar çalıştırılmaz. Rollback yalnız docs commit revert.
F6-D-R1 recovery hardening yalnız sentetik GO. F6-E/FULL/production restore/staging/
merge/deployment/cutover yetkisi yoktur. Main protected=false/rulesets=[] gözlemi,
PR3 OPEN/DRAFT ve bütün gerçek GATE'ler korunur. Aşağıdaki bekleme kayıtları tarihseldir.

## 27 Ekim 2026 — F6-D sentetik operasyon teslimi

F6-A/B/C **ACCEPTED — LIMITED SYNTHETIC**. F6-C kabul kaydı
`90aaaaa8a8c8c743119b2c8f08e71cc58445a534` docs-only'dir.
F6-D **SYNTHETIC DEVELOPMENT PARTIAL; INDEPENDENT ACCEPTANCE PENDING**.
Application `71e916a6e8e6ceb1eef9f02b5e3c6ee379064eaa`, tree
`0c5f1729089dafb07dec2e374e1079efa75fcbba`.
[FAST40555003177](https://github.com/Boydaksu/hostidercp/actions/runs/40555003177): PHP8.3/8.4 her sürüm57 suite/731 test/0 hata; eski699 ve56 suite sayıları korunmuş, yeni32 PASS.
Yeni32 hedefli test PASS; eski699 testin119 dosyası blob-equal; migrations001–054
subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmez.
Native Crypto ile encrypted/authenticated snapshot, private derived sample files,
izole escrow SQL restore,1/4/8 child process, crash/replay, native Queue/Worker,
dry-run14/8/12 retention ve sanitized status geliştirildi.
Native application activation **BLOCKED_IDENTITY_BOUND**; original archive bytes
ve kalıcı issuer/key cold recovery doğrulanmadı. Bu teslim gerçek operasyon kabulü değildir.
G-O01/02/03/04 ve tüm vendor/finance/support/cutover kapıları açık;
main protected=false/rulesets=[] gözlendi, değiştirilmedi. PR3 OPEN/DRAFT.
FULL NOT DISPATCHED; PRODUCTION BACKUP/RESTORE NOT VERIFIED; F6-E NOT AUTHORIZED;
production UNCHANGED. Bağımsız incelemeye teslim ve DUR.
[Plan](F6_D_PLAN.md), [teslim/kapılar](F6_D_DELIVERY_REPORT.md), [kanıt](evidence/f6-d/source-proof.json).
Aşağıdaki önceki teslim durumları tarihsel kayıttır.

## 27 Ekim 2026 — F6-C proje sahibi kabul kararı

**F6-C LIMITED SYNTHETIC RECONCILIATION & SECURITY TECHNICAL BASELINE — ACCEPTED.**
Application `c5ee92645a3cda842581fccd86707e76cea4dfde`, tree
`6418118929a977729f6fe12383c689c91f3edad5`; ayrı evidence commit'i
`2e5761921874cfef2ae25327434655a260064ff8`.
[FAST40530996504](https://github.com/Boydaksu/hostidercp/actions/runs/40530996504):
PHP8.3/8.4 her sürüm56 suite/699 test/0 hata; eski664+35 korunmuş; PHPStan0,
lint423/0, pattern445/0. Evidence-binding negatif düzeltmesi doğrulanmıştır.
Kabul yalnız sentetik source/target count, mapping/provenance/claim, checkpoint/HMAC,
SELECT-only Inspector, currency metadata, provider ownership, support privacy,
redaksiyon, evidence fingerprint ve bounded fail-closed sorgular içindir.
Gerçek WHMCS/ledger/provider/arşiv/PDF bytes, backup/restore operasyonu, FULL,
production readiness, merge/deploy/cutover kabul edilmez. F6-D yalnız SYNTHETIC
OPERATIONAL READINESS DEVELOPMENT GO; F6-E ve gerçek operasyon yetkisi yoktur.
Bu docs-only kabul dört belgeyi değiştirir; application/test/fixture/CI/migration
aynıdır. Başarılı FAST tekrar edilmedi. Rollback yalnız bu belge commit revert'üdür.
PR3 OPEN/DRAFT; main protected=false/rulesets=[] gözlendi, ayarlar değiştirilmedi.
Aşağıdaki eski teslim/bekleme ifadeleri tarihsel kayıttır.

## 27 Ekim 2026 — F6-C güncel teknik durum

F6-B LIMITED SYNTHETIC TECHNICAL BASELINE ACCEPTED, B-01 CLOSED.
F6-C SYNTHETIC RECONCILIATION & SECURITY geliştirildi; [plan](F6_C_PLAN.md),
[teslim/kanıt/istisnalar](F6_C_DELIVERY_REPORT.md), ADR-026 kapsamı geçerlidir.
Son uygulama `c5ee92645a3cda842581fccd86707e76cea4dfde`,
tree `6418118929a977729f6fe12383c689c91f3edad5`.
[FAST40530996504](https://github.com/Boydaksu/hostidercp/actions/runs/40530996504):
PHP8.3/8.4 her sürüm56 suite/699 test/0 hata; eski664 ve55 suite korunur, yeni35 PASS.
Yerel35 yeni test PASS; native664 ve001–054 korunur. F6-C bağımsız kabul PENDING;
FULL NOT DISPATCHED, gerçek G-O03/SUP-010/PROV-004 ve bütün vendor/operasyon
kapıları açıktır. F6-D/E NOT AUTHORIZED, PR3 OPEN/DRAFT, production unchanged.
Aşağıdaki F6-B ve önceki yetki/bekleme kayıtları tarihçedir.

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

## 27 Ekim 2026 — F6-B B-01 güncellemesi

[B-01](F6_B_B01_REVIEW.md) commit öncesi readiness düzeltmesi bağımsız yeniden
kabul bekler. Yeni uygulama / FULL aday `d4ead54db3aad6208e1a40e70822da7ad1346e33`,
tree `0057f233dbbeebfae1b3c094ec8eeafdb0fad7a4`; tek yeni negatif test eklendi,
mevcut42 F6-B testi korunur.
[FAST40514399931](https://github.com/Boydaksu/hostidercp/actions/runs/40514399931):
PHP8.3/8.4 her sürüm55 suite/664 test/0 hata; FULL/F6-C/canlı yetkisi yoktur.
Aşağıdaki eb3c2f6 ve663-test kaydı ilk teslimin tarihsel referansıdır.

## 27 Ekim 2026 — güncel F6-B teslim durumu

F6-A yalnız kabul edilmiş sentetik sözleşme tabanıdır. F6-B izole dry-run/load
geliştirmesi ve42 regresyon teslim edildi; bağımsız teknik kabul bekler.
[F6_B_PLAN](F6_B_PLAN.md), [teslim/kanıt](F6_B_DELIVERY_REPORT.md),
[ADR-025](adr/ADR-025-synthetic-isolated-import.md) güncel uygulama sınırını gösterir.
Son uygulama / FULL aday SHA `eb3c2f64f8515dcf3ceff6ca7f41c3b9c702e576`,
tree `7c69d2420591777ec09aee56d57d79cc46783fa1`.
[FAST40505208818](https://github.com/Boydaksu/hostidercp/actions/runs/40505208818)
PHP8.3/8.4: her sürüm55 suite/663 test/0 hata; ayrı6 CI kontrolü PASS.
Eski621 testin içinde F6-A39 vardır;001–054 ve native uygulamalar korunur.
Yeni FULL dispatch yapılmadı. F6-B ACCEPTED/FULL DONE değildir.
F6-C gerçek mutabakat, F6-D operasyon, F6-E cutover ve bütün canlı GATE'ler açıktır;
PR3 OPEN/DRAFT, main protection değişmez. Bu teslimden sonra DURULUR.
Aşağıdaki yetki/bekleme ifadeleri önceki aşamaların tarihsel kaydıdır.

## 27 Ekim 2026 — F6-A sınırlı bağımsız kabulü ve F6-B yetkisi

**F6-A LIMITED MOCK TECHNICAL BASELINE — ACCEPTED.** Proje sahibinin bağımsız
kararı yalnız sentetik contract, identity, snapshot, provenance, mapping evidence
ve validation kapsamını kabul eder. Message kind/visibility P1 **CLOSED**.
Kabul edilen uygulama `063e846999e4bda513f3563de0c4f13ac8858b97`,
tree `fb238efc3aa70cbbd022652d192d9e89c089f211`.
[FULL40475881946](https://github.com/Boydaksu/hostidercp/actions/runs/40475881946):
PHP8.3/8.4 her sürüm54 suite/621 uygulama testi/0 hata; ayrı6 CI kontrolü,
Chromium11/11, state3/3, Services HTTP10/10, Commerce HTTP30/30 ve
session concurrency20/20 PASS.001–054 korunur.

Gerçek WHMCS sürüm/şema keşfi, G-O03, gerçek SUP-010 ve PROV-004 kabul edilmedi.
Diğer vendor/operasyon/live kapıları, DOMAIN-002 PARTIAL/MANUAL_REVIEW,
production mapping/import CRUD DEFERRED, PROV-005 ve F5-C istisnaları korunur.
**F6-B SYNTHETIC ISOLATED IMPORT & DRY-RUN DEVELOPMENT — GO**; yalnız ayrı geçici
sentetik test şeması ve dry-run/load geliştirmesi yetkilidir. F6-C, gerçek veri,
provider/ödeme/mail, staging, merge/deploy/cutover izni değildir. PR3 OPEN/DRAFT;
main protection değişmez. Aşağıdaki bekleme ve yetki ifadeleri tarihsel kayıttır.

Bu kabul iş paketi yalnız bu altı belgeyi değiştirir; uygulama/test/CI/migration
kaynakları değişmez. Kontrol belge diff'i ve korunan kaynak eşliği; migration yok,
geri dönüş yalnız kabul belge commit'inin revert'üdür. Sonraki F6-B planı ayrı yazılır.

## 27 Ekim 2026 — yalnız F6-A geliştirme yetkisi

Proje sahibi F6-A SYNTHETIC DISCOVERY & CONTRACT DEVELOPMENT için GO verdi.
Güncel kapsam [F6_A_PLAN](F6_A_PLAN.md), karar ADR-024, teknik teslim
[F6_A_DELIVERY_REPORT](F6_A_DELIVERY_REPORT.md) ile izlenir. Bu yetki sentetik
sözleşme/fixture/validasyon içindir; F6-B/import/staging/merge/cutover izni değildir.
G-O03/SUP-010/gerçek PROV-004 ve tüm canlı GATE'ler açık kalır. Aşağıdaki
F6-A NOT STARTED/yetki bekleme ifadeleri önceki planın tarihsel kaydıdır.

## Yetki ve mevcut taban

Bu belge yalnız plan teslimidir. F6-A kodu dahil hiçbir F6 uygulaması, gerçek WHMCS
erişimi, aktarım, staging veya cutover yetkilendirilmedi. Her alt faz için ayrıca proje
sahibi talimatı ve bağımsız kabul gerekir. SUP-010 **PLANLANDI / NOT PASS**.

Başlangıç belge HEAD'i `fb8a217619196b650d82a89a16b3d2680736cf2d`;
kabul edilmiş F5-E uygulaması `e0d460d0aea05f496135d169ecbb64ce94659cc9`.
F4 ve F5-A/B/C/D/E yalnız sınırlı test/mock kabulleriyle korunur. PHP8.3/8.4
52 suite/582 test, Chromium11, state3 ve10/30/20 tekrar tabanı korunacaktır.
001–054 migration dosyaları immutable; bu plan migration eklemez.
Gelecekte gerekirse yalnız055+ additive, ayrıca tasarım ve yetki sonrasında.

Dayanaklar: [aktarım](11_MIGRATION.md), [yol haritası](12_ROADMAP.md),
[kabul](13_ACCEPTANCE_TESTS.md), [açık kapılar](14_OPEN_VALIDATIONS.md),
[operasyon](10_OPERATIONS.md), [ertelenmiş CRUD](F4_F_MAPPING_IMPORT_SCOPE.md),
[F3](F3_REPORT.md), [F3 operasyon sınırları](F3_POST_DELIVERY_REVIEW.md),
[F4 kabul](F4_REPORT.md), [F5 plan/kabulleri](F5_PLAN.md),
[F5-C istisnaları](F5_C_DELIVERY_REPORT.md), [F5-E kabul](F5_E_DELIVERY_REPORT.md).

## Alt faz bağımlılık ve kabul matrisi

| Faz | Bağımlılık ve önerilen dosya kapsamı | Ayrı kabul kapısı / teslim | Şimdiki durum |
|---|---|---|---|
| F6-A discovery/sözleşme | Ayrı geliştirme yetkisi; docs/F6_A_PLAN ve sözleşme/sentetik fixture tasarımı; gerçek source adaptörü yok | Sürüm/şema belirsizlikleri, extract/mapping/provenance/idempotency ve güvenlik sözleşmesi bağımsız incelenir | PLAN ONLY, NOT STARTED |
| F6-B izole import/dry-run | A test/mock sözleşme kabulü; native application service uygunluğu; ayrı sentetik test şeması | Tekrar/yarış/yarım batch recovery; ikinci provider CREATE sıfır; tüm entity fixture eşlemeleri | PLAN ONLY, NOT STARTED |
| F6-C mutabakat/güvenlik | B izole başarı ve manifest; gerçek kaynak doğrulaması ayrı G-O03 kapısı | Açıklanamayan para/sahiplik/dosya farkı sıfır; SUP-010 ve PROV-004 sentetik kanıtı ayrı etiketli | PLAN ONLY, NOT PASS |
| F6-D operasyon hazırlığı | A–C test/mock incelemesi; backup/key/runtime olanaklarının envanteri | İzole restore, migration failure, overlap/DLQ ve rollback/forward-fix provası | PLAN ONLY; staging izni değil |
| F6-E canlı geçiş kapıları | A–D kabulü, G-O03 ve tüm ilgili vendor/operasyon kapıları; ayrıca açık canlı yetki | İmzalı cutover/fence/delta/callback/single-actor kontrol listesi | PLAN ONLY; canlı işlem yok |

Önerilen kod konumları bağlayıcı şema kararı değildir: ancak ayrı yetkiden sonra
`src/Migration/`, `tests/migration-*`, `docs/F6_*` değerlendirilir. Native Core,
Identity, Finance, Services, Support ve transactional outbox sözleşmeleri korunur.
Controller'dan başka modül tablolarına yazma veya ikinci Identity/Queue motoru yoktur.
Her uygulama fazı önce kapsam/dosya/migration/test/rollback planını güncellemelidir.

## F6-A — Discovery ve sözleşmeler

Gerçek WHMCS sürümü, kurulu modüller, custom field'lar, şema/kolasyon, dosya
dizinleri, zaman dilimi ve parola formatı henüz doğrulanmadı. Kaynak tablo/alan adı,
hash algoritması veya provider API cevabı uydurulmayacak. **G-O03 OPEN** kalır.
Şimdilik yalnız sentetik, açıkça etiketlenmiş veri sözleşmesi tasarlanır.

İleride ayrıca yetkilendirilecek read-only discovery çıktısı: sürüm/schema fingerprint,
extract zamanı ve watermark, source snapshot SHA-256, entity sayımları, dosya manifest'i,
kaynak timezone/encoding, ilişki ve bilinmeyen alan listesi. Gerçek bağlantı/dump bu
talimatta alınmaz. Extract tutarlı snapshot olmalı; değişen kaynakla sessiz karma veri yoktur.

Kaynak kimliği: source-system-instance + doğrulanmış entity türü + değişmez source ID.
Mapping ve provenance source snapshot/run/transform sürümü/hedef ID/karar kanıtını bağlar.
Aynı source ID + aynı içerik replay'i yeni kayıt/para/provider işi üretmez; değişmiş
içerik sessiz overwrite yerine delta/çatışma incelemesine gider. Mapping anahtarının
tekilliği, transaction sınırı ve kesilen batch checkpoint'i sözleşmede tanımlanır.

Sınıflandırma: PII, finansal tarihçe, credential/hash, private note, müşteri görünür
metin, dosya, operasyon/audit. Sentetik fixture dışında gerçek PII kullanılmaz.
Kaynak credential/token/session taşınmaz; loglarda PII/hash/secret yoktur. Kaynak
staff kimliği otomatik hedef admin/rol/MFA yetkisi sağlamaz. Güncel native Identity,
department/customer scope, MFA/fresh step-up ve impersonation sınırları korunur.
Parola doğrulayıcı yalnız gerçek format ayrıca doğrulanırsa tasarlanabilir; aksi
reset gerekir. Hash uyumu veya rehash-on-login şu anda uygulanmış sayılmaz.

Kabul: eksik/çatışan source ID, farklı snapshot, bilinmeyen hash ve ownership için
fail-closed sentetik sözleşme testleri; sıfır gerçek bağlantı. G-O03 kapanışı değildir.

## F6-B — Sentetik izole import/dry-run

Her prova ayrı, private, geçici test şeması ve web dışı dosya alanıyla tasarlanır.
APP_ENV tek başına yeterli değildir: provisioning/payment/external mail fail-closed,
gerçek endpoint/credential yok, mock transport ve egress izin listesi gerekir.
Dry-run kalıcı hedef entity veya provider işi üretmez; öneri ve hata manifest'i verir.
Load için validate → mapping claim → native service → provenance/checkpoint aynı
transaction sınırında tasarlanır; dış I/O açık transaction'a alınmaz.

| Entity | Korunacak ilişki/kanıt | Engelleyici belirsizlik |
|---|---|---|
| Customer/user | Source ID, üyelik, aktif/pasif durum, doğrulanmış ownership | Çakışan kimlik/e-posta; eski staff yetkisinin mirası yasak |
| Order/service | Order ≠ payment ≠ service ≠ provider operation; dönem/sahiplik/origin | Ödeme veya aktif status bilinmiyorsa MANUAL_REVIEW |
| cPanel/Voyar/domain | Mevcut external resource + account/provider + hedef owner eşliği, imported origin | Duplicate resource, yanlış hesap, UNKNOWN/in-flight: MANUAL_REVIEW; CREATE yasak |
| Wallet | Hesap para birimi, immutable kaynak fişleri ve bakiye mutabakatı | Tutar/para/kur belirsizliği; float ve tahmini FX yok |
| Payment/allocation/refund | Özgün dış referans, tutar, currency, tarih, kısmi dağıtım | Tahsilat kanıtı yoksa paid/provisioning varsayılmaz |
| Official document/proforma | Ayrı belge türleri, eski numara, immutable tutar/tarih, SHA-256 | Eksik PDF/yanlış sahip; yeni resmi belge numarası üretilmez |
| Ticket/message | Orijinal ID, kronolojik sıra, timezone/tarih, yazar ilişkisi ve visibility | Belirsiz author/private flag: müşteri görünürlüğüne açma |
| Attachment/file | Owner/ticket/message bağları, boyut/MIME/özel storage, SHA-256 | Eksik dosya, traversal/symlink, bozuk hash, belirsiz erişim |

Imported cPanel/Voyar/domain load, replay, queue replay ve worker yarışları
**sıfır ikinci CREATE** sağlamalıdır. Bir mapping eklemek paid order keşfini veya
notification dispatch'ini tetiklememelidir. Bu sözleşme gelecekte native imported
origin guard ve uygulama servisleriyle doğrulanır; CRUD ayrı yetki bekler.

Kabul: aynı snapshot iki load, kesilmiş batch resume, farklı içerikle aynı ID,
1/4/8 bağımsız süreçte tek target/mapping; stale/revoke/IDOR/CSRF/idempotency.
Kaynak/finans geçmişi değişmez. Test/mock sonucu gerçek aktarım sayılmaz.

## F6-C — Mutabakat ve güvenlik

Manifest alanları: entity/source_count/target_count/unmatched_count,
currency bazında original/debit/credit/allocation/refund totals, snapshot/run/transform
provenance, owner/resource mismatch, SHA-256 mismatch, reviewer ve zaman.
Toplamlar para birimleri arasında toplanmaz; minor-unit integer/kesin decimal ve
açık yuvarlama kullanılır. Bilinmeyen tarihsel kur ayrı legacy_unknown kanıtıyla
**MANUAL_REVIEW** olur; yeni kurla geçmiş yazılmaz. Bilinmeyen payment/provider
status/ownership de MANUAL_REVIEW; iş sahibi kanıtsız paid/active kabul edemez.

| Kabul ID | Planlanan izole kanıt | Canlı/gerçek kabul için ek kapı | Şimdiki sonuç |
|---|---|---|---|
| SUP-010 | Ticket/mesaj sayımı, aynı orijinal sıra/tarih; ek SHA-256; private note customer API/export/notification/attachment'ta görünmez; native staff scope | Gerçek kaynak şeması/dosyaları G-O03; gerçek arşiv mutabakatı | NOT PASS |
| PROV-004 | Imported cPanel/Voyar/domain replay/yarış sonrası tek mapping, provider CREATE sayısı0; native yönetim yetkileri | Read-only gerçek provider identity/resource doğrulaması ayrıca yetkili | F3 mock guard korunur; F6 import NOT PASS |
| OPS-002 | Tutarlı DB+attachment+official PDF manifest'i; authenticated encrypted copy; ayrı anahtar erişimi; izole restore checksum | Gerçek dış hedef/hosting, RPO/RTO provası ve sahibin kabulü | F6 NOT PASS |
| OPS-003 | Preflight/backup doğrulaması; bozuk DDL APPLYING/readiness ret; restore veya incelenmiş forward-fix | Gerçek update/runtime ve geri dönüş provası | F6 NOT PASS |
| OPS-004 | Sentetik kaynak/hedef delta/count/currency reconciliation; çift actor/fence/callback senaryoları | WHMCS fence, final delta ve gerçek cutover için açık yetki | NOT PASS |

Açıklanamayan finans farkı0, eksik aktif service mapping0, eksik resmi PDF/ek0,
checksum/ownership farkı0 planlanan kapılardır; bugün ölçülmüş başarı değildir.
Kaynak yazar UUID'si müşteriye dönmez; yeni/legacy client projection redaksiyonu
ve can_attach server authorization sınırı korunur. Import edilmiş message order
aynı timestamp durumunda doğrulanmış source sequence/ID ile deterministik olmalıdır;
bilinmeyen timezone sessiz dönüştürülmez.

## F6-D — Operasyon hazırlığı

Tutarlı DB/dosya/manifest snapshot'ı, authenticated encryption, web dışı anahtar
yönetimi ve ayrı izole restore planlanır. Gece yedek + dış şifreli kopya,
14 günlük/8 haftalık/12 aylık retention master kararı korunur. PITR/saatlik erişim
G-O02 doğrulanmadan RPO sözü verilmez. Restore sonrası auth, finans, özel dosyalar,
source mapping ve queue/outbox tekilliği doğrulanır.

Scheduler overlap ve worker claim/lease/fence bağımsız süreçlerle sınanır; stale lease
ve UNKNOWN kör tekrar edilmez. DLQ/poison batch, bounded time/memory/batch, disk dolması,
DB kesintisi, clock/timezone ve şifreli dosya/anahtar kaybı için kontrollü duruş
tasarlanır. Heartbeat, oldest job, UNKNOWN, reconcile farkı, backup yaşı ve son restore
izlenir; alarmlar test alıcıları/mock ile, PII'siz ve dedupe'lıdır.

Rollback: yalnız izole synthetic DB/files aynı tutarlı snapshot'a döner. Immutable
finans geçmişi silerek düzeltme yoktur. Gerçek cutover sonrası yeni ödeme/provider
etkisi varsa eski DB'ye kör restore güvenli sayılmaz: delta reverse-import ve state
reconciliation veya incelenmiş forward-fix gerekir. Owner ve karar penceresi ayrıca
belirlenir; destructive down otomatik değildir. Bu faz staging izni değildir.

## F6-E — Canlı kapılar ve kontrol listesi (uygulanmayacak)

- [ ] G-O03 gerçek WHMCS sürümü/şeması/hash/dosya ilişkileri bağımsız doğrulandı.
- [ ] Kaynak/hedef kapsam, maintenance penceresi, sorumlu/reviewer ve rollback eşiği onaylı.
- [ ] Restore provası ve anahtar erişimi; DB/dosya snapshot bütünlüğü; kapasite/cron runtime kanıtı.
- [ ] WHMCS cron, provisioning, billing/notification mutation teknik fence'i doğrulandı; eski erişim read-only.
- [ ] In-flight create/renew/transfer/terminate, UNKNOWN ve kısmi ödeme ayrı reconcile edildi.
- [ ] Final delta watermark/snapshot ve mapping tekilliği; sayım/tutar/hash/owner farkı0.
- [ ] PayTR callback routing, eski dış referanslar, retry/idempotency ve geri dönüş rotası doğrulandı.
- [ ] Tek aktif automation actor ve fencing kanıtı; çift sağlayıcı işlemi/bildirim yok.
- [ ] Gerçek mailbox/retention/AV/provider/finans kapıları kapandı; notification dispatch ayrı açık onaylı.
- [ ] WHMCS arşiv erişimi, lisans/saklama ve güvenli dosya indirmeleri doğrulandı.
- [ ] main protection ve bağımsız release incelemesi tamamlandı; proje sahibi canlı cutover'ı açıkça yetkilendirdi.

Hiçbir kutu bu belgeyle işaretlenmez. Şu anda gerçek kullanıcı verisi/credential,
PayTR/WHM/Voyar/registrar/SMTP/IMAP çağrısı, gerçek restore/cutover veya deploy yoktur.

## Açık bağımlılıklar ve durma noktası

DOMAIN-002 **PARTIAL/MANUAL_REVIEW**; mapping/import CRUD **DEFERRED** ve ayrı
yetki gerektirir. PROV-005, G-V01–04/G-P01–02/G-C01/G-R01/G-D01/G-X01–02,
G-O01–04 ve G-T01 dahil canlı kapılar OPEN kalır. Özellikle G-O03 OPEN;
SUP-010 henüz PASS değildir. F5-C'nin dört canlı istisnası (gerçek routing/sender
auth; kesin retention cutoff/scheduler; AV/IMAP/SMTP/genel MIME; mock HELD/ACCEPTED'in
teslim olmaması), auto-close, merge/split ve tarihsel SLA rapor doğrulaması açıktır.

Plan kabulü uygulama kabulü değildir. PR3 OPEN/DRAFT; main protection mevcut kapalı
durumuyla korunur. CI/F6 planı bağımsız incelemeye sunulduktan sonra DURULUR;
F6-A kodu, gerçek extract/import, merge/staging/production için yeni talimat beklenir.
