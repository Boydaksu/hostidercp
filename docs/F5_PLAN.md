# F5 — Gelişmiş Destek Merkezi planı

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
