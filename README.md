# Hostider Automation — V1.0 Master Specification

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

## 27 Ekim 2026 — F6-C sentetik reconciliation teslimi

[Plan](docs/F6_C_PLAN.md), [teknik teslim/istisnalar](docs/F6_C_DELIVERY_REPORT.md),
[ADR-026](docs/adr/ADR-026-synthetic-reconciliation-security.md).
Separate SELECT-only reader, bounded consistent snapshot, bağımsız source/target
identity/hash/owner/mapping/provenance/claim/checkpoint/history karşılaştırması,
currency bazlı Money özeti ve private metadata redaksiyonu geliştirildi.
Son uygulama `c5ee92645a3cda842581fccd86707e76cea4dfde`,
tree `6418118929a977729f6fe12383c689c91f3edad5`.
[FAST40530996504](https://github.com/Boydaksu/hostidercp/actions/runs/40530996504):
PHP8.3/8.4 her sürüm56 suite/699 test/0 hata; eski664 ad eksiksiz, yeni35 PASS.
Yerel35 yeni test PASS; eski664 kaynak ve001–054 değişmez. F6-C bağımsız kabul
PENDING, FULL NOT DISPATCHED; gerçek WHMCS/ledger/provider/dosya NOT VERIFIED.
F6-D/E ve staging/merge/deploy/cutover yoktur. PR3 OPEN/DRAFT; production unchanged.
Aşağıdaki F6-B kabul ve önceki teslimler tarihsel referans olarak korunur.

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

## 27 Ekim 2026 — F6-B B-01 düzeltmesi

Batch commit öncesi tam readiness kontrolü eklendi; tek yeni negatif test rollback
ve committed cursor korumasını doğrular. [B-01 kanıtı](docs/F6_B_B01_REVIEW.md).
Yeni uygulama / FULL aday `d4ead54db3aad6208e1a40e70822da7ad1346e33`,
tree `0057f233dbbeebfae1b3c094ec8eeafdb0fad7a4`.
[FAST40514399931](https://github.com/Boydaksu/hostidercp/actions/runs/40514399931):
PHP8.3/8.4 her sürüm55 suite/664 test/0 hata; eski621+42 korunmuş, yeni1 PASS.
Bağımsız kabul bekler. FULL/F6-C/canlı işlemlere geçilmez; önceki teslimler aşağıdadır.

## 27 Ekim 2026 — F6-B sentetik teknik teslimi

F6-B izole dry-run/load, idempotent mapping/provenance, batch recovery ve1/4/8
bağımsız PHP process testleri tamamlandı; bağımsız teknik kabul bekler.
[Plan](docs/F6_B_PLAN.md), [teslim/kanıt ve açık kapılar](docs/F6_B_DELIVERY_REPORT.md),
[ADR-025](docs/adr/ADR-025-synthetic-isolated-import.md).
Son uygulama ve FULL aday SHA `eb3c2f64f8515dcf3ceff6ca7f41c3b9c702e576`,
tree `7c69d2420591777ec09aee56d57d79cc46783fa1`.
[FAST40505208818](https://github.com/Boydaksu/hostidercp/actions/runs/40505208818)
PHP8.3/8.4: her sürüm55 suite/663 test/0 hata; ayrı6 CI kontrolü PASS.
Eski621 test (F6-A39 dahil),404 korunan kaynak/test ve001–054 korunur.
Yeni42 F6-B testi vardır; gerçek WHMCS/dosya/provider/ödeme/mail işlemi yoktur.
FULL bu aday için çalıştırılmadı; ayrıca açık yetki olmadan dispatch yapılmaz.
F6-B ACCEPTED/FULL DONE/PRODUCTION READY ilan edilmez; F6-C'ye geçilmez.
PR3 OPEN/DRAFT ve bütün canlı GATE'ler açıktır. Aşağıdaki kayıtlar tarihçedir.

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

> **F6-A P1 mesaj türü/görünürlük düzeltmesi — bağımsız yeniden kabul bekler.**
> PUBLIC_REPLY yalnız KNOWN PUBLIC ile geçerli; tutarsızlık MESSAGE_VISIBILITY_CONFLICT
> ile incelemeye gider. INTERNAL_NOTE doğrulanmış source STAFF + KNOWN PRIVATE ister.
> Attachment ilişkileri private mesajı client-visible yapmaz. Native F5-E/Identity/Finance
> ve001–054 değişmedi. [Karşı-örnek ve denetim raporu](docs/F6_A_POST_AUDIT_REVIEW.md).
> Son uygulama `063e846999e4bda513f3563de0c4f13ac8858b97`; FULL dispatch yapılmadı.
> [FAST PHP8.3/8.4](https://github.com/Boydaksu/hostidercp/actions/runs/40470409239):
> her sürüm54 suite/621 test/0 hata; eski613 ad eksiksiz, yeni8 PASS. Eski kaynakta aynı8 test4 FAIL verdi.
> PR3 OPEN/DRAFT; F6-B ve bütün canlı GATE'ler açık kalır.

> **F6-A sentetik sözleşme geliştirmesi — bağımsız teknik kabul bekler.**
> [Plan](docs/F6_A_PLAN.md), [teknik teslim ve açık kapılar](docs/F6_A_DELIVERY_REPORT.md),
> [ADR-024](docs/adr/ADR-024-synthetic-migration-contract.md). Gerçek WHMCS bağlantısı,
> importer, persistent mapping CRUD, dosya aktarımı veya dış servis etkisi yoktur.
> Yeni migration yok;001–054 korunur. F6-B ve bütün canlı GATE'ler açık kalır.
> Son uygulama `8340be6c5a12e551587ab92e72da930a18a7894d`;
> [FAST PHP8.3/8.4](https://github.com/Boydaksu/hostidercp/actions/runs/40465060398)
> her sürüm53 suite/613 test/0 hata: eski582 ad eksiksiz, yeni31 PASS; state3/3.
> FULL bu adayda çalıştırılmadı; PR3 OPEN/DRAFT, merge/deploy/cutover yoktur.

> **27 Ekim 2026 — CI OPTIMIZATION — LIMITED TECHNICAL BASELINE: ACCEPTED.**
> Proje sahibinin bağımsız kabulü: [FULL run40459020759](https://github.com/Boydaksu/hostidercp/actions/runs/40459020759),
> [PHP8.3 job122038976890](https://github.com/Boydaksu/hostidercp/actions/runs/40459020759/job/122038976890)
> ve [PHP8.4 job122038977146](https://github.com/Boydaksu/hostidercp/actions/runs/40459020759/job/122038977146) SUCCESS.
> Her sürüm582/582 uygulama testi, ek6/6 CI kontrolü, Chromium11/11, state3/3,
> Services10/10, Commerce30/30, Session20/20 PASS; PHPStan/audit/lint/pattern temiz.
> Uygulama SHA `7e2092898287e566fc6a487c6540d6f70cec7481`, tree `b4451d7e85cf42ce48b51cc577096a4de6537d0f`;
> workflow SHA `272eb2c3e6a8f6e8cc0b5c4f66a8272f0333c909`. Ayrı SHA'lar beklenen/doğrulanmış davranıştır.
> FAST, docs-only ve manuel exact-SHA FULL kapsamları ayrı kalır. FULL production release,
> deployment veya merge izni vermez. PR3 OPEN/DRAFT; main protection ve bütün canlı GATE'ler korunur.
> **F6-A SYNTHETIC DISCOVERY & CONTRACT DEVELOPMENT: GO**; yalnız sentetik sözleşme/validasyon.
> F6-B, gerçek WHMCS/import/provider, staging ve cutover yetkisi yoktur.
> Aşağıdaki FULL bekleme ve F6 plan-only ifadeleri önceki tarihsel kayıtlardır.

> **CI optimizasyonu ve F6 teknik planlama — FAST doğrulandı, FULL bekliyor.**
> [FAST/FULL ayrımı ve kanıt kaydı](docs/CI_OPTIMIZATION_REPORT.md),
> [F6-A–E planı ve açık kabul kapıları](docs/F6_PLAN.md),
> [ADR-023](docs/adr/ADR-023-ci-exact-source.md).
> F6 yalnız planlanmıştır; uygulama/gerçek aktarım yetkisi yoktur. Önceki sınırlı
> F4/F5 kabulleri korunur. PR3 OPEN/DRAFT; canlı GATE'ler ve G-O03 OPEN.
> Yeni FAST sonucu FULL ACCEPTANCE değildir; FULL yalnız exact SHA ile açıkça tetiklenir.
> [FAST PHP8.3/8.4](https://github.com/Boydaksu/hostidercp/actions/runs/40377794488):
> her sürüm52 suite/582 test/0 hata, state3/3; [docs-only](https://github.com/Boydaksu/hostidercp/actions/runs/40380246812)
> SUCCESS, pahalı matris SKIPPED. FULL için kaynak SHA7e2092898287e566fc6a487c6540d6f70cec7481;
> henüz gerçek FULL run kanıtı yoktur, CI optimizasyonu DONE değildir.

> **F5-E LIMITED MOCK TECHNICAL BASELINE — ACCEPTED.**
> Proje sahibinin son bağımsız teknik kabulü:
> [`e0d460d0aea05f496135d169ecbb64ce94659cc9`](https://github.com/Boydaksu/hostidercp/commit/e0d460d0aea05f496135d169ecbb64ce94659cc9).
> [PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40367106726): **52 suite /582 test /0 hata**;
> önceki579 korundu; Chromium11/11 ve state3/3 PASS. Client author_id P1 **CLOSED**;
> yeni/legacy projection, can_attach ve native attachment authorization PASS.
> 277 korunan dosya hash eşliği ve001–054 migration koruması PASS.
> F5-A/B/C/D kayıtları korunur; A–E yalnız test/mock alt kapsamlarıyla kabul edilmiştir.
> **FULL F5, V1 veya production-ready kabulü değildir.** F5-C dört canlı istisnası,
> auto-close, merge/split, tarihsel SLA raporu, SUP-010/F6, DOMAIN-002 PARTIAL,
> mapping/import CRUD DEFERRED, PROV-005 ve tüm canlı GATE'ler **OPEN**.
> [Kabul ve kapsam kaydı](docs/F5_E_DELIVERY_REPORT.md), [P1 denetim kaydı](docs/F5_E_POST_AUDIT_REVIEW.md),
> [F5 planı](docs/F5_PLAN.md), [ADR-022](docs/adr/ADR-022-support-ui-session-drafts-polling.md).
> PR #3 **OPEN/DRAFT**; merge/staging/production/canlı mail/provider yoktur.
> Kabul kaydı tamamlandıktan sonra durulur; sonraki aşama için ayrı talimat beklenir.
> Aşağıdaki eski teslim ve denetim bekleme açıklamaları tarihsel olarak korunmuştur.


> **F5-E P1 metadata redaksiyonu — bağımsız yeniden kabul bekleniyor.**
> Client UI ve legacy destek detaylarında mesaj author_id alanları kaldırıldı;
> attachment kontrolü güncel Identity/mesaj sahipliğinden hesaplanan can_attach boolean'ını kullanır.
> Native attachment upload yetkisi değişmedi.001–054 korundu; migration gerekmedi.
> Uygulama[`e0d460d0aea05f496135d169ecbb64ce94659cc9`](https://github.com/Boydaksu/hostidercp/commit/e0d460d0aea05f496135d169ecbb64ce94659cc9).
> [PHP8.3/8.4 tam CI SUCCESS](https://github.com/Boydaksu/hostidercp/actions/runs/40367106726):
> her sürüm52 suite /582 test /0 hata; eski579 korundu. Chromium11/0 (eski9 korundu), state3/0.
> [Denetim sonrası rapor](docs/F5_E_POST_AUDIT_REVIEW.md). PR3 OPEN/DRAFT; canlı GATE'ler OPEN.
> Aşağıdaki ilk F5-E teslimi ve önceki kabul kayıtları tarihsel olarak korunmuştur.


> **F5-E TEST/MOCK teknik teslimi — bağımsız teknik kabul bekleniyor.**
> Native `/admin/support` ve `/client/support`, SQL kapsamlı inbox, şifreli oturum
> taslakları, sürüme bağlı gönderim ve A/B/C/D işlem panelleri eklendi.
> Uygulama[`f226220847390ea3f5c69eef90ad968117d7edc7`](https://github.com/Boydaksu/hostidercp/commit/f226220847390ea3f5c69eef90ad968117d7edc7).
> [PHP8.3/8.4 tam CI SUCCESS](https://github.com/Boydaksu/hostidercp/actions/runs/40334349439):
> her sürüm52 suite /579 test /0 hata; eski555 korundu, yeni24 PASS.
> Ek olarak gerçek Chromium9/0, state3/0 ve10/30/20 tekrarları0 hata.
> [Plan](docs/F5_E_PLAN.md), [teslim/doğrulama kaydı](docs/F5_E_DELIVERY_REPORT.md),
> [ADR-022](docs/adr/ADR-022-support-ui-session-drafts-polling.md).
> Şifreli subject araması ve export açıkça unsupported. Yalnız additive054;
> 001–053 ve önceki sınırlı kabuller korunur. Canlı GATE'ler OPEN, PR3 OPEN/DRAFT.
> Aşağıdaki F5-D kabulü ve önceki teslim kayıtları tarihsel olarak korunmuştur.


> **F5-D LIMITED MOCK TECHNICAL BASELINE — ACCEPTED.**
> Kabul edilen uygulama[`3404ddda79bc2017f05cb474b5546e23647910d8`](https://github.com/Boydaksu/hostidercp/commit/3404ddda79bc2017f05cb474b5546e23647910d8).
> PHP8.3/8.4: **51 suite /555 test /0 hata**; eski514 korundu, yeni41 PASS.
> SUP-008/009, CSAT ve sınırlı raporlama yalnız test/mock kapsamında kabul edildi.
> 001–052 ve kritik kaynaklar korundu;053 additive. [Kabul kaydı](docs/F5_D_DELIVERY_REPORT.md),
> [ADR-021](docs/adr/ADR-021-support-automation-incident-csat-report.md).
> Tam V1, canlı scheduler/incident operasyonu veya production kabulü değildir.
> F5-C dört istisnası, auto-close, merge/split, kurum geneli tarihsel SLA rapor doğrulaması,
> F5-E arayüzü, F6 aktarımı, DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED, PROV-005
> ve bütün canlı GATE'ler açık kalır. F4/F5-A/B/C kabulleri ve555 test tabanı korunur.
> PR #3 OPEN/DRAFT; gerçek SMTP/IMAP/provider işlemi, merge, staging veya production yoktur.
> Kabul kaydından sonra durulur; F5-E için ayrı yetki beklenir. Aşağıdaki teslim ve
> denetim bekleme ifadeleri tarihsel kayıtlardır.


> **F5-D test/mock teslimi — bağımsız teknik denetim bekliyor.**
> Rule AST/simulation/causation, incident public/private ayrımı, CSAT ve scoped raporlar:
> [plan](docs/F5_D_PLAN.md), [ADR-021](docs/adr/ADR-021-support-automation-incident-csat-report.md),
> [teslim ve güvenlik/test matrisi](docs/F5_D_DELIVERY_REPORT.md).
> Uygulama[`3404ddda79bc2017f05cb474b5546e23647910d8`](https://github.com/Boydaksu/hostidercp/commit/3404ddda79bc2017f05cb474b5546e23647910d8);
> [PHP8.3/8.4 tam CI](https://github.com/Boydaksu/hostidercp/actions/runs/40298787710):
> **51 suite /555 test /0 hata**; eski514 eksiksiz, yeni41 PASS.001–052 korunur;053 additive.
> 111 korunan dosya ve10 native modül tree eşliği doğrulandı. SUP-008/009 yalnız uygulanan
> test/mock alt kapsamındadır; F5-D DONE/FULL ACCEPTED değildir. Customer/service predicate
> projection UNAVAILABLE; auto-close/merge/split ve SUP-010 açık bağımlılıktır.
> F4/F5-A/F5-B/F5-C kabulleri ve F5-C'nin dört istisnası değişmez. EXTERNAL_MAIL_ALLOWED=false;
> gerçek mail/provider/notification teslimi yoktur. DOMAIN-002 PARTIAL, mapping/import CRUD
> DEFERRED, PROV-005 ve tüm canlı GATE'ler OPEN. PR #3 OPEN/DRAFT; F5-E/F6, merge,
> staging veya production başlatılmaz. Bağımsız teknik denetim istenir ve burada durulur.
> Aşağıdaki kabul kararları geçerlidir; önceki fazların durma/yetki bekleme ifadeleri tarihseldir.

> **F5-C LIMITED MOCK TECHNICAL BASELINE — ACCEPTED WITH EXPLICIT EXCEPTIONS.**
> Proje sahibinin bağımsız teknik kabulü SUP-001–004 için yalnız uygulanan ağsız
> test/mock alt kapsamını kapsar. Yeni bloklayıcı P1 bulunmamıştır.
> [Plan](docs/F5_C_PLAN.md), [ADR-020](docs/adr/ADR-020-support-mail-test-boundaries.md),
> [teslim, güvenlik matrisi ve test kanıtları](docs/F5_C_DELIVERY_REPORT.md).
> Kaynak[`68fa3e5`](https://github.com/Boydaksu/hostidercp/commit/68fa3e51e315efd05fd8ddb34dffc9eabb7edd18);
> [PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40283941945):
> **51 suite /514 test /0 hata**; eski472 eksiksiz, yeni42 PASS.
> 95 korunan dosyanın Git blob eşliği bağımsız denetimde doğrulandı.
> Açık istisnalar: gerçek mailbox alıcı/departman yönlendirmesi ve sender authentication;
> retention süresi sonunda kesin erişim kesilmesi ve scheduler operasyonu canlı kabul bekler.
> Antivirus, gerçek IMAP/SMTP ve genel MIME uyumluluğu bu kabule dahil değildir.
> HELD/ACCEPTED_BY_TRANSPORT(mock) gerçek müşteri teslimi değildir.
> Gerçek SMTP/IMAP, müşteri e-postası, gerçek antivirüs veya DELIVERED sonucu yoktur.
> `EXTERNAL_MAIL_ALLOWED=false`; fixture dışındaki dosyalar QUARANTINED kalır.
> 001–051 korunur; yalnız additive052. F4/F5-A/F5-B sınırlı mock kabulleri değişmez.
> DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED, PROV-005 ve bütün canlı GATE'ler
> OPEN. PR #3 açık/taslak; F5-D/E/F6, merge, staging veya production yapılmaz.
> Kabul kaydından sonra çalışma durur; F5-D için proje sahibinin ayrı talimatı beklenir.
> Aşağıdaki önceki faz yetki ve kabul bekleme ifadeleri tarihsel kayıtlardır.

> **F5-B LIMITED MOCK TECHNICAL BASELINE — ACCEPTED — 26 Ekim 2026.**
> Proje sahibinin son bağımsız kabulü yalnız F5-B test/mock teknik çekirdeğini kapsar.
> Kabul edilen uygulama[`5869483fd0d977ede646cbef02bdf4a9f2b12bc8`](https://github.com/Boydaksu/hostidercp/commit/5869483fd0d977ede646cbef02bdf4a9f2b12bc8);
> [PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40234838426):
> **51 suite /472 test /0 hata**; önceki461 eksiksiz, yeni11 PASS.
> P1-1 staff-reopen FIRST ve P1-2 SLA takvim reddi/ticket koruması **PASS — CLOSED**.
> [Kabul kaydı](docs/F5_B_DELIVERY_REPORT.md), [denetim kaydı](docs/F5_B_POST_AUDIT_REVIEW.md).
> 001–050 ve kritik altyapı korunur;051 additive. F4/F5-A sınırlı mock kabulleri değişmez.
> Gerçek SLA taahhüdü, canlı personel vardiyası, SMTP/IMAP, attachment, notification,
> F5-C/D/E ve production tamamlanmış sayılmaz. DOMAIN-002 PARTIAL, mapping/import CRUD
> DEFERRED, PROV-005 ve bütün canlı GATE'ler OPEN. PR #3 açık/taslak; merge, staging
> veya production yapılmaz. Yeni geliştirme için proje sahibinin sonraki talimatı beklenir.
> Aşağıdaki kabul bekleme ifadeleri son karardan önceki tarihsel teslim kayıtlarıdır.

> **F5-B P1 hedefli test/mock düzeltmesi — bağımsız yeniden kabul bekliyor.**
> Kaynak[`5869483`](https://github.com/Boydaksu/hostidercp/commit/5869483fd0d977ede646cbef02bdf4a9f2b12bc8);
> [gerçek PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40234838426):
> **51 suite /472 test /0 hata**; eski461 + yeni11. Staff-reopen FIRST sonucu ve
> SLA calendar publication/failure isolation düzeltildi. [Denetim raporu](docs/F5_B_POST_AUDIT_REVIEW.md).
> 001–050 korunur, yalnız additive051. F4/F5-A mock kabulü ve canlı GATE'ler değişmez;
> PR #3 açık/taslak, sonraki fazlar ve canlı işlemler kapalıdır. Aşağıdaki ilk F5-B
> teslim kaydı tarihseldir; F5-B bağımsız kabul edilmiş sayılmaz.

> **F5-B test/mock geliştirmesi yetkilendirildi; bağımsız teknik kabul bekliyor.**
> SLA takvimleri ve manuel/RR/ağırlıklı atama kapsamı:
> [plan](docs/F5_B_PLAN.md), [ADR-018](docs/adr/ADR-018-support-calendars-assignment.md),
> [teslim ve doğrulama raporu](docs/F5_B_DELIVERY_REPORT.md).
> Uygulama`4d58b81823deb2b44ce52ef89bae77dce468ef3b`;
> [gerçek PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40226687244):
> **51 suite / 461 test / 0 hata**; eski433 + yeni28. Additive050 dışında
> 001–049 korunur. Ticari SLA/roster değerleri onaylanmış sayılmaz. F5-A/F4 sınırlı
> mock kabulleri ve bütün canlı GATE'ler korunur. PR #3 açık/taslak kalır;
> F5-C/D/E/F6, gerçek mail, merge, staging veya production başlatılmaz.
> Aşağıdaki önceki faz notları tarihsel kabul kayıtlarıdır.

> **F5-A LIMITED MOCK TECHNICAL BASELINE — ACCEPTED.** Proje sahibinin son bağımsız
> kabul kararı yalnız F5-A test/mock çekirdeğini kapsar. Kabul edilen uygulama
> [`d3712eda6b3d9156187727ced85fff04de7457c5`](https://github.com/Boydaksu/hostidercp/commit/d3712eda6b3d9156187727ced85fff04de7457c5);
> [PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40212210626)
> her sürümde **51 suite / 433 test / 0 hata**. Eski 428 test korunur, yeni 5 regresyon PASS.
> P1-1 tekil event UUID/outbox/job/receipt ve P1-2 açık 422 unsupported arama sözleşmesi
> bağımsız denetimde kapatıldı. [Kabul kaydı](docs/F5_A_DELIVERY_REPORT.md),
> [hedefli düzeltme kanıtı](docs/F5_A_POST_AUDIT_REVIEW.md).
> Gerçek mail, SLA, assignment, attachment, rule engine, incident, CSAT ve gelişmiş UI
> tamamlanmış sayılmaz. F4 mock kabulü, 001–049 ve bütün canlı GATE'ler korunur.
> F5-B ayrı yetki bekler; yeni geliştirme, F5-C/D/E/F6, merge, staging veya production yoktur.
> [PR #3](https://github.com/Boydaksu/hostidercp/pull/3) açık ve taslak kalır.

> **F5-A ilk teslim (tarihsel):** Ticket, departman, immutable mesaj/dahili not,
> native Identity kapsamı ve minimal HTTP çekirdeği ayrı `codex/f5-support-center`
> dalındadır. [Plan](docs/F5_PLAN.md), [A sözleşmesi](docs/F5_A_PLAN.md),
> [teslim ve doğrulama durumu](docs/F5_A_DELIVERY_REPORT.md),
> [taslak PR #3](https://github.com/Boydaksu/hostidercp/pull/3).
> Kaynak `a38e8da`: [PHP 8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40208255513)
> her sürümde **51 suite / 428 test / 0 hata**; önceki 394 test korunur, 049 additive.
> Bu ilk teslim notları tarihseldir; güncel sınırlı kabul kararı üsttedir.
> F5-B/C/D/E, F6 ve canlı işlemler için ayrı yetki gerekir.

> **F4 bütünleşik mock kabul paketi:** C/D/E/F teknik mock kabulleri ve 49 suite / 394 test
> [F4_REPORT](docs/F4_REPORT.md) altında birleştirildi. Bağımsız karar: **SINIRLANDIRILMIŞ MOCK
> TEKNİK GO**. Sabit uygulama: `6e25a8b98de6e0c9da5e84df6b78e46cd47854f2`.
> Tam kapsam/live exit kabul edilmedi; F4 DONE/production-ready değildir.
> Mapping/import CRUD NOT ACCEPTED; DOMAIN-002 PARTIAL; bütün canlı GATE'ler OPEN.
> Aşağıdaki faz teslim notları tarihsel kayıttır; güncel kabul sınırı F4_REPORT'tadır.


> **F4-F son denetim düzeltmesi:** Native doğrulanmış kanıt özeti ve Domain no-effect FAILURE
> onayı [F4_F_POST_AUDIT_REVIEW](docs/F4_F_POST_AUDIT_REVIEW.md) ile teslim edildi.
> PHP8.3/8.4 **394/394**; 001–048 korunur, yeni migration yoktur. Bağımsız yeniden kabul bekler.
> Mapping/import CRUD proje sahibinin açık onayıyla ayrı işe ertelendi; uygulanmış/DONE değildir.
> DOMAIN-002 PARTIAL ve canlı GATE'ler OPEN; F5/F6, merge veya production başlatılmaz.

> **F4-F test/mock teslimi; bağımsız kabul bekliyor:** Domain/Cloud müşteri ve personel panelleri,
> native Identity HTTP güvenliği ve FIN-010 ekranları uygulandı. PHP8.3/8.4 **383/383**;
> [teslim raporu](docs/F4_F_DELIVERY_REPORT.md), [plan](docs/F4_F_PLAN.md).
> F4-C/D/E kabul edilen 355 testlik taban ve 001–047 migration geçmişi korunur;
> additive 048; gerçek sağlayıcı GATE'leri açık, DOMAIN-002 PARTIAL kalır.
> F4-F sonrası bağımsız kabul olmadan F5/F6, merge veya staging/production başlatılmaz.

> **F4-E test/mock teslimi; bağımsız kabul bekleniyor:** F4-D güvenlik tabanı bağımsız kabul edildi (325 test, HEAD dc70fa5). Kullanım/sağlayıcı maliyeti, mevcut Ledger ve manual fiyat önerisi kapsamı [F4_E_PLAN](docs/F4_E_PLAN.md) ve [F4_E_DELIVERY_REPORT](docs/F4_E_DELIVERY_REPORT.md) ile belgelenir (355 test). 30 günlük peşin müşteri satışı ayrı kalır; PROV-005 gerçek kabulü ve tüm canlı GATE’ler açıktır. F4-F/F5, merge ve production başlatılmaz.


> **F4-D geliştirme:** F4-C’nin 278 testlik güvenlik tabanı bağımsız kabul edildi; DOMAIN-002 otomatik registrar failover PARTIAL kalır. Kalıcı Voyar Cloud operation/task/mock kapsamı [F4_D_PLAN](docs/F4_D_PLAN.md) ile yürütülür. Canlı GATE’ler açık; F4-E/F, F5 ve production başlatılmaz.

> **F4 geliştirme başladı; tamamlanmadı:** kabul edilen F3 tabanı üzerinde [F4 planı](docs/F4_PLAN.md), [Voyar SDK incelemesi](docs/F4_VOYAR_SDK_REVIEW.md), Domain model/kalıcı registrar mock temeli ve SDK POST replay transport koruması eklendi. [Taslak PR #2](https://github.com/Boydaksu/hostidercp/pull/2), PHP 8.3/8.4 için [251/251 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40071562182). [F4 ilerleme raporu](docs/F4_REPORT.md) eksik Domain lifecycle/failover, Cloud ve panel kapsamını açıkça ayırır. Bütün canlı GATE'ler OPEN; F5 başlamadı. Aşağıdaki önceki faz notları tarihseldir.

> **F2 CI kararlılık kapanışı:** PHP 8.3 built-in server JIT SIGSEGV yeniden üretildi; test server interpreter moduna alındı, lifecycle/transport tanıları ve bounded Windows fixture cleanup eklendi. Önceki 175 + 5 yeni regression ve tekrarlı CI kanıtı: [F2_CI_STABILITY_REPORT](docs/F2_CI_STABILITY_REPORT.md). F3 ve canlı işlemler başlatılmadı.

> **F2 teslim sonrası denetim:** kanıtlı mutabakat kapanışı, daraltılmış onay kilitleri, idempotency replay yetki kontrolü ve güvenli F3 devir sözleşmesi: [F2_POST_DELIVERY_REVIEW](docs/F2_POST_DELIVERY_REVIEW.md). Production ve F3 başlatılmadı; önceki F2 raporu tarihsel teslim kaydıdır.

> **F2 finans ve satış çekirdeği:** katalog, kesin dövizli fiyatlandırma, teklif/sipariş, cüzdan/karma ödeme, proforma/korumalı PDF ve yönetici finans panelleri test ortamında tamamlandı. [F2_REPORT](docs/F2_REPORT.md), [F2_SETUP](docs/F2_SETUP.md), [F2_PLAN](docs/F2_PLAN.md). 164 test ve PHP 8.3/8.4 CI başarılı. Kullanıcı kabulü beklenir; F3 başlamadı. Production kullanımı ve canlı işlemler kapalı; bütün doğrulama GATE'leri OPEN. Aşağıdaki eski faz notları tarihsel kayıtlardır.

> **F1.1 güvenlik kapanışı:** pending MFA ile password+MFA step-up ayrıldı; kritik Identity işlemlerine sunucuya bağlı ayrıntılı onay ve scope delegasyon kontrolü eklendi. [F1_1_REPORT](docs/F1_1_REPORT.md), [F1_1_PLAN](docs/F1_1_PLAN.md), [ADR-004](docs/adr/ADR-004-f1-1-confirmations.md). F2 başlamadı; bütün canlı GATE'ler OPEN.

> **F1 Identity ve paneller:** çalışan admin/client hesapları, MFA, kalıcı roller/kapsamlar, davetler, oturum iptali, API anahtarları ve responsive paneller eklendi. Teslim ve sınırlar: [F1_REPORT](docs/F1_REPORT.md); kurulum, güvenli ilk yönetici ve yapay demo: [F1_SETUP](docs/F1_SETUP.md); plan: [F1_PLAN](docs/F1_PLAN.md). F2 başlatılmadı; bütün canlı GATE'ler OPEN. Aşağıdaki F0/F0.1 notları tarihsel teslim kayıtlarıdır.

> **F0.1 hardening:** kapsamlı sonuç ve açık sınırlar [F0_1_REPORT](docs/F0_1_REPORT.md), güvenlik/session/retention sözleşmeleri [F0_1_OPERATIONS](docs/F0_1_OPERATIONS.md), plan [F0_1_PLAN](docs/F0_1_PLAN.md).

> **Uygulama durumu (F0, 22 Ekim 2026):** PHP çekirdeği, MariaDB migration/outbox/queue, güvenlik sözleşmeleri ve ağsız sağlayıcı mock'ları eklendi. Kurulum: [F0_SETUP](docs/F0_SETUP.md); kapsam: [F0_PLAN](docs/F0_PLAN.md); test ve sınırlar: [F0_REPORT](docs/F0_REPORT.md); gereksinim izi: [F0_TRACEABILITY](docs/F0_TRACEABILITY.md); canlı kapılar: [F0_GATE_TASKS](docs/F0_GATE_TASKS.md). F1 başlamadı. Aşağıdaki şartname iş kurallarının referansıdır. `SHA256SUMS.txt` ilk geliştirici paketinin manifestidir; güncel uygulama manifesti `docs/evidence/F0_SHA256SUMS.txt` içindedir.

**Durum:** Onaylı iş kararlarına dayanan teknik tasarım tabanı; henüz uygulama kodu değildir.  
**Tarih:** 22 Ekim 2026  
**Amaç:** Hostider'ın WHMCS'den kontrollü ayrılması. Sadece kurum içi kullanım.  
**Teknoloji hedefi:** PHP 8.3+, MariaDB 10.11+ tercih, paylaşımlı hosting, 1 dakikalık cron, modüler monolit.  
**Öncelikler:** finansal doğruluk > veri kaybını önleme > güvenlik > operatör deneyimi > performans > gösterişli özellikler.

## Okuma sırası
1. `docs/01_DECISIONS.md` — yedi turdan kesin iş kararları, öncelik ve çakışma çözümü.
2. `docs/02_REQUIREMENTS.md` — kapsam, aktörler, iş gereksinimleri ve V2 sınırları.
3. `docs/03_ARCHITECTURE.md` — modüler yapı, olaylar, kuyruk, güvenlik, dağıtım.
4. `docs/04_DATA_MODEL.md` — ana varlıklar, ilişkiler, veri bütünlüğü.
5. `docs/05_FINANCE_AND_PRICING.md` — döviz, ödeme, cüzdan, kasa ve faturalandırma.
6. `docs/06_WORKFLOWS.md` — durum makineleri, iptal, transfer, domain, yenileme.
7. `docs/07_PROVIDER_CONTRACTS.md` — PayTR, Voyar, cPanel, ResellerClub, DomainNameAPI.
8. `docs/08_SUPPORT_CENTER.md` — destek sisteminin ayrıntılı şartnamesi.
9. `docs/09_API_AND_SCREENS.md` — API ilkeleri, yetki matrisi, sayfa haritası.
10. `docs/10_OPERATIONS.md` — kurulum, staging, yedek, güncelleme, güvenlik.
11. `docs/11_MIGRATION.md` — WHMCS'den aktarım ve geri dönüş.
12. `docs/12_ROADMAP.md` — bağımlılıklar ve aşamalı teslim planı.
13. `docs/13_ACCEPTANCE_TESTS.md` — Gherkin benzeri kabul testleri ve yayın kapıları.
14. `docs/14_OPEN_VALIDATIONS.md` — sağlayıcı bazlı doğrulama listesi; varsayım ≠ gerçek.
15. `AGENTS.md` — geliştirici/AI için davranış sözleşmesi.
16. `sql/001_core_schema_blueprint.sql` — kritik tablolar için *başlangıç* DDL taslağı; tamamlanmış üretim migrasyonu değildir.
17. `contracts/` — örnek sağlayıcı yetenekleri ve hata sözleşmesi.
18. `prompts/` — geliştirmeyi fazlara ayıran başlangıç talimatları.
19. `references/voyar-api-client-user-provided.zip` — kullanıcının gönderdiği özgün Voyar PHP SDK referansı; canlı davranış ve güvenlik kontrolü bekliyor.

## Normatif dil
- **MUST / ZORUNLU:** V1 yayın engelleyici gereksinim.
- **SHOULD / ÖNERİLEN:** gerekçeyle ertelenebilir veya değiştirilebilir.
- **MAY / OPSİYONEL:** zorunlu olmayan geliştirme.
- **GATE / DOĞRULAMA KAPISI:** canlı sağlayıcı API'si/ortam/uyumluluk görülmeden kesin kabul edilemez.

## Kaynak hiyerarşisi
1. Kullanıcının 1–7. turda kesinleştirdiği iş kararları; sonraki karar aynı konuyu değiştiriyorsa en güncel karar.
2. Bu paketteki finansal/güvenlik değişmezleri (iş kararlarını ihlal etmeden uygulama yöntemi).
3. Resmi API belgeleri ve kullanıcıdan gelen Voyar SDK dosyası — entegrasyon davranışlarının teknik kanıtı, canlı kabiliyet garantisi değil.
4. Uygulama sırasında yazılı olarak kabul edilmiş ADR'ler ve test edilmiş özellikler.

## Sahiplik ve kullanış
Bu paket çalışma tasarımıdır; kullanıcı API anahtarlarını, canlı DB dump'ını ve şifrelerini bu dosyalara koymamalıdır. Uygulanan test/mock kapsamı ve gerçek kanıtlar aşağıdaki güncel raporda belirtilir; canlı sağlayıcı onayı açık GATE gerektirir.

## Uygulama durumu — F3 test/mock

Güncel uygulama ve doğrulanmış sınırlar [F3 teslim raporunda](docs/F3_REPORT.md) bulunur. Hizmet motoru ve Identity/Commerce ile bağlı müşteri/yönetici hizmet panelleri test/mock ortamında uygulanmıştır; canlı sağlayıcı onayı ve production kullanımı açık GATE'lere bağlıdır. F4 başlatılmadı.

F3 son denetiminde Runner kuyruk açlığı ve cron batch işleme düzeltildi; yeniden üretim, gerçek CLI ölçümü ve kabul kararları [F3_POST_DELIVERY_REVIEW](docs/F3_POST_DELIVERY_REVIEW.md) içindedir. Migration 038 additive'dir; 001–037 korunur. F4 kullanıcı kabulü bekler, canlı GATE'ler açıktır.
