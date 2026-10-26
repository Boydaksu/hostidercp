# F5-D — otomasyon, incident, CSAT ve raporlama test/mock planı

Başlangıç03d18666397d8dd63c5f23aa9818daf4548e41b4; kabul edilen F5-C
68fa3e51e315efd05fd8ddb34dffc9eabb7edd18,51 suite/514 test. Yalnız test/mock;
PR3 OPEN/DRAFT. SUP-008/009, SUP-003/004/007, SEC-001/004, OPS-001/005;
D4 ve R2-05/06/07/08/09 izlenir. Önceki kabul ve F5-C'nin dört istisnası korunur.

## Kapsam ve dosyalar

src/Support/Automation: bounded v1 JSON AST, native event adapter, simulation,
immutable revision/causation/execution/action sonuçları ve native Outbox consumer.
src/Support/Advanced: scoped commands, incident, CSAT, canonical ticket operations,
SQL ACL aggregation ve native HTTP controller. Support.Service ve Work.Commands'a
outer transaction içinde mevcut private canonical validation/apply yolunu kullanan
dar delegation seam eklenir. Identity/Queue/Audit veya state machine kopyalanmaz.
Bootstrap/bin native bağlantıları; yeni test fixture/child/HTTP/regresyon dosyaları.

## Veri ve migration

Yalnız additive053+: immutable rules/revisions ve current heads; immutable source
envelopes/executions/action outcomes/chain; confirmation; tag/priority history;
central support_incidents, public updates, private encrypted root cause ve tenant
FK'li ticket links; explicit department CSAT policy, rating head/revisions.
FK/unique/check/immutable trigger ve canonical JSON.001–052 byte/hash korunur.
Kaynak event UUID, causation_id, correlation_id, rule_revision, execution_id ve
source_ticket_version ayrı saklanır. Notification ID-only HELD/mock; SMTP yoktur.

## Yetki ve aktörler

Native permissions support.ticket.manage/assign/internal_note, support.incident.manage,
support.report.view ve ayrı support.automation.manage. Staff role tek başına yetki
vermez: current customer/department grant + gerçek department membership + native
session/MFA/fresh step-up. Admin mutation prepare/execute, state/payload/session/
identity-bound confirmation, optimistic version ve Idempotency-Key kullanır.
Rule publish açık, sınırlı delegation onayıdır; worker aynı sponsor native oturumunu,
güncel grant/freshness ve her aksiyonun iznini yeniden doğrular. Oturum/step-up
süresi biterse aksiyon SKIPPED (AUTHORIZATION_UNAVAILABLE); sistem sınırsız yetki edinmez. Sistem execution
aktörü ile native canonical executor/sponsor ayrı immutable provenance'dır;
hiçbir rule aksiyonu staff_reply üretmez. Otomatik onay veya target impersonation yok.

## AST/event/action sözleşmesi

v1 trigger allowlist: ticket_created/customer_replied/staff_replied/state_changed/
assigned/sla_risk/incident_updated. Native CREATED/CUSTOMER_REPLY/STAFF_REPLY/STATE,
work ASSIGNED ve SLA_RISK/BREACH ile immutable incident update adapter eşlenir.
Server source UUID/ticket/customer/version/outbox/job/fingerprint birlikte doğrulanır;
istemci event/causation belirleyemez. Stable priority+rule UUID sırası.
Predicate all/any/not ve typed eq/in; department/channel/priority/tag/local business
hours/assigned/SLA desteklenir. Native müşteri grubu veya yetkili hizmet projeksiyonu
yoksa UNAVAILABLE; asla true fallback veya başka modül tablo taraması yoktur.
Assign/state/priority/tag/internal-note constant template/staff HELD/incident-link
allowlist; finance/provider/customer mail/service mutation açıkça yasaktır.

Simulation aynı değerlendirme ve action preflight yoluyla salt redakte sonuç;
native last_seen dahil hiçbir DB mutation/outbox/message yok (native authentication
transaction'ı rollback edilir). Max AST depth4/node32/action4/rule16/event;
chain depth4, chain'de aynı rule UUID tekrarı LOOP_BLOCKED (revision değişimi bypass
değildir); batch25/20s/64MiB yeni işe başlama bütçesi. Event+revision+action unique.
Her event native inbox receipt ile tek transaction; rule savepoint tüm aksiyonlarını
atomik tutar, failure sonrası güvenli FAILED audit. SQL/deadlock hataları güvenli
rollback/retry politikasında; hatalı başarı yok. Stale source version açık SKIPPED;
concurrent farklı eventler ticket lock/version ile seri. Closed/resolved mutation
varsayılan engelli. Notes state/SLA/mail değiştirmez.

Priority requested/effective ayrı; reason/sponsor/system/revision/old/new/version
immutable history. Eski SLA cycle/clock/history snapshot'ı yeniden hesaplanmaz;
assignment weight mevcut policy weight'ine açıkça uyarlanır. Yeni ticari priority
ve SLA değerleri yoktur. R2-06 auto-close tamamlanmış değildir, bu fazda DEFERRED;
mevcut explicit reopen policy korunur. Merge/split ve SUP-010 F6 açık bağımlılıktır.

## Incident, CSAT ve rapor

Incident state INVESTIGATING/IDENTIFIED/MONITORING/RESOLVED; explicit transitions,
encrypted private root cause; public title/description/update ayrı allowlist ve
staff confirmation. Müşteri yalnız kendi linked ticket ACL'si üzerinden incident'ın
kendi public alanlarını görür; diğer ticket/customer/service/message/note/file IDs
yoktur. Incident update/resolution ticket state veya SLA response değiştirmez.
CSAT yalnız current native owner/member, RESOLVED, explicit configurable window,
1–5 integer ve encrypted plain comment; immutable revisions/version/idempotency.
Default ticari pencere seed edilmez. HTML JSON plain text; UI textContent zorunlu.
Reports UTC half-open date bounds + IANA zone, SQL view AND report ACL, bounded
pagination, deterministic aggregation. Canonical clocks/history/policy revision,
pay/payda/null/UNAVAILABLE tanımı; atanmış süre çalışma saati değildir: NOT_AVAILABLE.
CSV/export ve görsel UI yoktur. Customer group/service predicate unavailable kalırsa
raporda açıkça belirtilir; veri veya ticari metrik uydurulmaz.

## Test, kanıt ve geri dönüş

514 eski test adı karşılaştırması;26 negatif senaryo, gerçek MariaDB/native HTTP,
1/4/8 bağımsız worker; SUP-008/009 karşı-örnek ve kaynak satırı eşlemesi. Empty/
052→053/replay/checksum/failed-DDL; transaction rollback ve old SLA history eşliği.
PHP8.3/8.4 full CI, PHPStan0/locked audit/lint/pattern; Services10/Commerce30/session20
tekrarları korunur. Ham CI job API linkleri ve redakte allowlist sonuç kanıtı;
credential içeren tam log repository'ye konmaz. Gerçek çalışmayan test PASS sayılmaz.
Rollback tutarlı code/DB/key/storage snapshot restore veya reviewed forward-fix;
destructive down yoktur. Rule kapatma current head revision ile; geçmiş silinmez.

Riskler: sponsor-session TTL (bilinçli fail-closed test delegation), async stale
event, bounded MIME/mail ve retention/AV canlı istisnaları, aggregate denominator,
çoklu tenant incident public approval, encrypted storage backup. DOMAIN-002 PARTIAL,
mapping/import CRUD DEFERRED, PROV-005/tüm canlı GATE OPEN; EXTERNAL_MAIL_ALLOWED=false.
Teslim raporu gerçek GitHub SHA/tree/CI ile yazılır, bağımsız denetim istenir ve durulur.
F5-D DONE/FULL ACCEPTED, F5-E/F6, merge, staging, production veya gerçek mail yoktur.
