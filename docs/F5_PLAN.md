# F5 — Gelişmiş Destek Merkezi planı

Güncel karar: **F5-D LIMITED MOCK TECHNICAL BASELINE — ACCEPTED**.
Uygulama3404ddda79bc2017f05cb474b5546e23647910d8; PHP8.3/8.4:51 suite/555 test/0 hata.
Eski514 eksiksiz, yeni41 PASS; SUP-008/009, CSAT ve sınırlı raporlama test/mock kabulü.
001–052 korunur,053 additive. [Kabul kaydı](F5_D_DELIVERY_REPORT.md) ve ADR-021 esastır.
Tam V1/canlı scheduler/incident operasyonu/production kabulü yoktur. F5-C dört istisnası,
auto-close, merge/split, kurum geneli tarihsel SLA rapor doğrulaması, F5-E arayüzü,
F6 aktarımı ve tüm canlı GATE'ler açık; DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED,
PROV-005 OPEN. Önceki kabuller ve555 test tabanı korunur. Yeni geliştirme yoktur;
PR3 OPEN/DRAFT. Merge/staging/production/gerçek SMTP/IMAP/provider yoktur.
Kabul kaydından sonra durulur; F5-E için ayrı yetki beklenir. Aşağıdaki önceki
teslim/yetki/denetim bekleme ifadeleri tarihsel olarak korunur.

26 Ekim 2026 güncel teslim: yalnız **F5-D TEST/MOCK — bağımsız teknik denetim bekler**.
Uygulama3404ddda79bc2017f05cb474b5546e23647910d8; PHP8.3/8.4 tam CI51 suite/555 test/0 hata.
Eski514 ad korunur,41 yeni PASS.001–052 korunur,053 additive;111 dosya/10 modül tree eşliği.
[F5_D_PLAN](F5_D_PLAN.md), [ADR-021](adr/ADR-021-support-automation-incident-csat-report.md),
[F5_D_DELIVERY_REPORT](F5_D_DELIVERY_REPORT.md) kaynak/CI/matris/sınırları kaydeder.
SUP-008/009 yalnız uygulanan test/mock alt kapsamındadır; F5-D DONE/FULL ACCEPTED değildir.
R2-05/07/08/09 kural/incident/CSAT/report subset; customer/service predicate UNAVAILABLE.
R2-06 auto-close DEFERRED, merge/split açık, SUP-010 F6 bağımlılığıdır. F4/F5-A/B/C
kabulleri ve F5-C dört istisnası korunur. PR3 OPEN/DRAFT; gerçek mail/provider,
F5-E/F6, merge/staging/production yoktur. Teslimden sonra bağımsız denetim istenir ve durulur.
Aşağıdaki önceki yetki/durma ifadeleri tarihsel kayıttır; kabul tabanları geçerlidir.

26 Ekim 2026 güncel karar: **F5-C LIMITED MOCK TECHNICAL BASELINE — ACCEPTED WITH
EXPLICIT EXCEPTIONS**. Kabul edilen uygulama68fa3e51e315efd05fd8ddb34dffc9eabb7edd18;
PHP8.3/8.4:51 suite /514 test /0 hata. Eski472 + yeni42 PASS; yeni bloklayıcı P1 yok.
95 korunan dosyanın Git blob eşliği doğrulandı;001–051 korunur,052 additive.
SUP-001–004 yalnız uygulanan ağsız test/mock alt kapsamında kabul edilmiştir.
[F5_C_PLAN](F5_C_PLAN.md), [ADR-020](adr/ADR-020-support-mail-test-boundaries.md).
[F5_C_DELIVERY_REPORT](F5_C_DELIVERY_REPORT.md) test/mock kaynak ve doğrulama
kanıtlarını ve açık istisnaları kaydeder. Gerçek mailbox alıcı/departman yönlendirmesi
ve sender authentication; retention sonunda kesin erişim kesilmesi ve scheduler
operasyonu canlı kabul bekler. Antivirus, gerçek IMAP/SMTP ve genel MIME uyumluluğu
kabule dahil değildir; HELD/ACCEPTED_BY_TRANSPORT(mock) gerçek müşteri teslimi değildir.
F4/F5-A/F5-B kabul tabanları ve514 testlik F5-C tabanı korunur. Yeni geliştirme yoktur;
PR #3 OPEN/DRAFT kalır. Kabul kaydından sonra durulur, F5-D için proje sahibinin
ayrı talimatı beklenir.
Gerçek mail/SMTP/IMAP, F5-D/E/F6, merge ve ortam yayını yoktur. Aşağıdaki önceki
yetki/kabul bekleme durumları tarihsel kayıtlardır.

Güncel yetki: F5-A bağımsız sınırlı mock kabulünden sonra yalnız F5-B test/mock
geliştirmesi. [F5_B_PLAN](F5_B_PLAN.md), [ADR-018](adr/ADR-018-support-calendars-assignment.md)
ve [F5_B_DELIVERY_REPORT](F5_B_DELIVERY_REPORT.md) güncel kapsam/kanıt/sınırları verir.
SUP-005/006 bağımsız teknik kabul bekler. F5-C/D/E/F6, gerçek mail, merge veya
ortam yayını yetkili değildir. Aşağıdaki başlangıç planı tarihsel olarak korunur.

25 Ekim 2026. Yetki: yalnız F5-A test/mock. Başlangıç HEAD
`5d944dcb9abbf5e0f447272d8273346349aab3c8`, dal `codex/f5-support-center`.
F4/PR #2 değiştirilmez; merge, staging, production, SMTP/IMAP ve provider IO yoktur.

Karar izi: R1-09 ve R2-01/R3-09 A çekirdeğinde; R2-02/03 B'de,
R2-04 mail C'de, R2-05 öncelik A temel + D override, R2-06 A explicit reopen +
B auto-close, R2-07/08/09 D'de; R1-08/SUP-010 F6 aktarımına bağlıdır.

| Alt faz | Kapsam / dosyalar | Bağımlılık | Kabul izi | Migration / test / geri dönüş |
|---|---|---|---|---|
| A | src/Support, native HTTP, departman/ticket/mesaj/audit; bu teslim | Identity, Services, Audit, Idempotency, Outbox | SUP-003/004 çekirdek; SUP-007 | 049+ additive; gerçek MariaDB/HTTP ve paralel süreç; backup restore veya forward-fix |
| B | SLA takvim/tatil/vardiya ve assignment | A; açık takvim/atama politikaları | SUP-005/006 | Ayrı additive şema; DST, tatil, concurrency; tutarlı restore |
| C | Mail adapters, dedupe, review, SMTP outbox, güvenli ekler | A; mail/storage güvenlik kabulü | SUP-001/002; SUP-003/004 ek/mail genişletmesi | Ayrı additive şema; ağsız contract ve attachment negatif testleri; restore |
| D | JSON AST kuralları, incident, CSAT, rapor | A/B/C olay sözleşmeleri | SUP-008/009 | Ayrı additive şema; loop/privacy/rapor testleri; restore |
| E | Operatör inbox, müşteri portalı, polling/draft, bütünleşik kabul | A–D; F6 import sözleşmesi | SUP-001–009 bütünleşik; SUP-010 F6 bağımlılığı | Ayrı plan; E2E; restore |

SUP-010 WHMCS arşiv/ek checksum kabulü F6 aktarımına bağlıdır; E yalnız sözleşmeyi
hazırlar. A'da SUP-001/002/005/006/008/009/010 PASS ilan edilmez. SUP-003/004
ek/export/mail alt kapsamları kapalı olduğundan tüm madde için koşulsuz PASS yoktur.
DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED, PROV-005 ve tüm canlı GATE'ler OPEN.

Her alt faz ayrı yetki ve bağımsız kabul ister. A sonunda durulur.
