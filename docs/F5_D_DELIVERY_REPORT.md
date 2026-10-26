# F5-D — otomasyon, incident, CSAT ve raporlama test/mock teslimi

**Doğrulama sürüyor; bağımsız teknik kabul verilmiş değildir.**
Başlangıç03d18666397d8dd63c5f23aa9818daf4548e41b4. Kabul edilen F5-C
68fa3e51e315efd05fd8ddb34dffc9eabb7edd18:51 suite/514 test. Uygulama GitHub SHA/tree,
tam PHP8.3/8.4 CI ve ham job kanıtları gerçek execution tamamlanınca eklenecek.
F5-D DONE/FULL ACCEPTED değildir. [Plan](F5_D_PLAN.md),
[ADR-021](adr/ADR-021-support-automation-incident-csat-report.md).

## Gereksinim ve değişiklik sözleşmesi

SUP-008/009 ve R2-05/07/08/09 bu test/mock alt kapsamındadır; SUP-003/004/007,
SEC-001/004, OPS-001/005 korunur. R2-06 auto-close DEFERRED; mevcut explicit reopen
policy değişmez. Ticket merge/split açık gereksinim; SUP-010 WHMCS archive F6 bağımlılığı.
F5-E operatör UI/CSV/export ve F6 bu teslimde yoktur.

Yeni Automation AST/Engine ve Advanced Common/Commands/Operations/Incidents/Csat/
Reports/Views/Controller; native bootstrap/bin bağlantıları. Support.Service ve
Work.Commands'a yalnız outer transaction içinde eski canonical authorize/validate/
state/message/event/SLA/assignment yoluna delegasyon seam'i eklenir. Identity,
Queue, Outbox, Worker, Audit, state machine ve F5-A EventSink yeniden yazılmadı.
Advanced domain history native AuditEvent::SupportChanged ile birlikte yazılır;
ikinci audit/queue sistemi değildir.053 additive;001–052 değişmez.

## Rule AST, olay ve yetki matrisi

v1 JSON AST: all/any/not, typed eq/in; unknown field/operator/node/action422.
Serbest SQL/PHP/eval, dynamic class/method ve shell ifadesi yoktur. Note template
yalnız bounded literal plain text; değişken interpolation desteklenmez.

| Trigger | Native immutable kaynak |
|---|---|
| ticket_created | support_events.CREATED |
| customer_replied | support_events.CUSTOMER_REPLY |
| staff_replied | support_events.STAFF_REPLY; note hariç |
| state_changed | support_events.STATE; Advanced PRIORITY/TAG idari state değişimi |
| assigned | support_work_events.ASSIGNED/UNASSIGNED |
| sla_risk | support_work_events.SLA_RISK/SLA_BREACH |
| incident_updated | immutable incident public update + aynı dept/customer ticket link |

`support:automation` yalnız yayın sonrası native kaynakları bounded discovery ile
stage eder; geçmiş eventlere sonradan oluşturulmuş rule uygulanmaz. Stage anında
revision UUID listesi snapshot olur; daha sonra head değişmişse STALE_RULE/SKIPPED.
Henüz stage edilmemiş eski kaynak için değişmiş/devre dışı current head geçmişe
yürütülmez. Ticket version discovery/consume arasında değişmişse STALE_TICKET/SKIPPED;
kanonik mesaj/snapshot kaybolmaz, otomasyon uygulanmış sayılmaz. Bu davranış açık
konservatif concurrency politikasıdır; kaçırılan eventleri yeni version'a gizlice
rebasing yapmaz. Aynı event içindeki kurallar ordering+rule UUID sırasında güncel
önceki kural etkilerini görür. Source version immutable olarak ayrıca korunur.

| Bağ | Kontrol |
|---|---|
| Queue | event_uuid/ticket ID allowlist, own published outbox aggregate/type/canonical payload, native job/type/correlation/fingerprint |
| Source | source kind/UUID/native event kind/ticket/customer/version; HTTP source veya causation kabul etmez |
| Chain | immutable parent, causation/correlation, chain rule UUID listesi; max depth4/repeated UUID LOOP_BLOCKED |
| Revision | immutable spec/owner/session/identity version; current head kontrolü; event snapshot |
| Execution | event+revision unique; action(event,revision,index) unique; immutable execution/action result |
| Actor | SYSTEM_RULE execution ve support-rule audit aktörü, native canonical sponsor/executor ayrı |
| Yetki | publish + her action current native customer/department grant, gerçek membership, session/MFA/fresh step-up |

Rule publish açık confirmation ile sınırlı delegation'dır. Yönetim izni tek başına
assign/manage/internal_note/incident yetkisi vermez. Sponsor oturumu veya300s fresh
step-up bitince SKIPPED (AUTHORIZATION_UNAVAILABLE); renewal/impersonation/sınırsız system privilege yoktur.
Bu kısa ömürlü test delegation canlı unattended scheduler kabulü değildir.

Predicate kaynakları: department/effective priority/channel, current tags/assignee,
immutable SLA policy calendar ve stored current cycle status/alerts. Mail kanalı
immutable F5-C provenance ile TEST_ADAPTER'dır. Customer group ve yetkili service
type/status projection bu alt kapsamda UNAVAILABLE; NOT da UNAVAILABLE kalır.
Başka modülün private tabloları predicate için taranmaz. OR/AND üç değerli mantık;
eksik veri true sayılmaz. Şifreli subject/body/internal_note koşulu veya indeksi yoktur.

Assign native Eligibility/Assignments; state/note native Support; priority/tag
aynı Support bounded context'inde yeni canonical Operations+Store.bump yoludur.
Incident link current ticket/manage+incident grant ister. Note staff_reply değildir.
Staff notification ID-only HELD kaydı ve ayrı native support.advanced.held receipt'tir;
gerçek recipient teslimi veya müşteri mail'i değildir. Finance/service/domain/cloud/
provider mutation AST allowlist dışında422; ticari değer/ücret uydurulmaz.

## Atomicity, simulation ve kaynak bütçesi

Simulation aynı predicate/preflight ile redakte result/action eligibility döndürür;
native last_seen dahil authentication transaction'ı rollback edilir. Ticket/message/
note/notification/audit/outbox/finance değişikliği yoktur. Execute güncel canonical
precondition'ları tekrar denetler; simulation rezervasyon veya gelecek garanti değildir.
Her rule tüm aksiyonlarıyla savepoint atomic'tir. Http/domain precondition hatası
rule rollback + FAILED/SKIPPED immutable sonuç; önceki bağımsız rule etkisi korunur.
SQL/storage/deadlock hatası tüm event transaction'ını rollback eder; başarı receipt'i
yoktur. Native worker attempt hatayı kaydeder; explicit replay ancak tekil receipt
ve canonical kaynak doğrulamasıyla güvenlidir. Tamamlanmış domain failure otomatik
başarılı retry sayılmaz. MATCH/NO_MATCH/UNAVAILABLE/APPLIED/SKIPPED/FAILED/LOOP_BLOCKED
domain history + native audit vardır; action-level SKIPPED mevcut aynı tag/priority'yi
yeniden değiştirmez. Queue SUCCEEDED yalnız eventin yerel tüketildiğini belirtir.

AST depth4/node32/branch8/value-list8/action4; customer+department başına16 rule;
event başına en fazla64 action. Chain4, discovery batch25/20s/64MiB yeni işe kabul;
consume5s yeni rule/action admission ve DB lock wait2s. Başlamış bir DB statement için
hard realtime garanti değildir. Tag32/ticket, incident link1000/incident, literal
note2000 char, CSAT comment2000 char. HTTP native64KiB/JSON32 depth. Current reports
366 günlük UTC aralığı; her paged dataset50 satır, offset≤1000000. Sponsor TTL ve
bounded limits açık fail-closed sonuçtur; sessiz truncate veya sahte başarı yoktur.

Priority requested değişmez. Native effective LOW/NORMAL/HIGH/CRITICAL, explicit
reason, actor/sponsor/execution, old/new/version/policy revision/weight effect ve UTC
history saklanır. Mevcut SLA clock/target/completion/history geriye dönük değişmez;
sonraki native NEXT clock aynı policy snapshot'ında yeni effective priority'yi,
yeni cycle ise canonical yeni policy seçimini kullanır. Current assignment weight
aynı policy weights ile uyarlanır; eligibility native sonraki tick'te tekrar denenir.

## Incident public/private matrisi

| Veri / işlem | Yetki / projection |
|---|---|
| Create/public update/private RCA | native fresh confirmed support.incident.manage + explicit wildcard customer grant + gerçek dept membership |
| Ticket link | same current dept, ticket view/manage + incident.manage scoped customer grant |
| Client public | current own ticket ACL + immutable link + current same dept; SQL yalnız incident public alanları |
| Private root/affected | encrypted, yalnız authorized admin projection; root revisions immutable encrypted |
| Public response | incident own title/description/status/times/explicit public updates; diğer ticket/customer/message/note/file/service/fatura ID veya count yok |

INVESTIGATING→IDENTIFIED/RESOLVED; IDENTIFIED→MONITORING/RESOLVED;
MONITORING→IDENTIFIED/RESOLVED; same-state public update mümkün; RESOLVED terminal.
Incident resolved ticket'ları kapatmaz; incident_updated rule da RESOLVED/CLOSED
action'ını kabul etmez. Update gerçek staff response değildir, FIRST/NEXT kapanmaz.
Private RCA otomatik özetlenmez. Public plain text staff tarafından açıkça onaylanır;
JSON application/json/no-store/nosniff/CSP ve content_format=PLAIN_TEXT, UI textContent
zorunludur. Son50 public update projection'ı; immutable tam DB history korunur.

## CSAT / rapor veri ve hesaplama matrisi

CSAT yalnız current native client membership/own ticket, RESOLVED ve current explicit
department window policy.1–31536000 saniye teknik üst sınır; default gün/süre seed
edilmez. Başlangıç latest canonical RESOLVED transition DB UTC timestamp'ıdır.
Integer1–5; update window içinde revision+ticket version/idempotency. Immutable score/
encrypted comment/policy/window/user snapshot. Reopen oy ile tetiklenmez; eski rating'in
resolution event'i sonraki resolve ile uyuşmazsa409 (yeni survey cycle bu alt kapsamda
yok). Client başka customer rating'ini okuyamaz/yazamaz. Staff comment read ayrıca
scoped report.view ister. Genel report comment içermez.

| Rapor | Kanonik kaynak / pay-payda |
|---|---|
| Backlog/status/priority | SQL view AND report ACL; created-at [from,to) cohort; current status NEW/WAITING_*/ON_HOLD açık |
| FIRST/NEXT/RESOLUTION | canonical cycles/clocks + immutable policy ID/revision; recorded consumed_us sum/count, status grupları |
| SLA | UNAVAILABLE/UNCONFIGURED availability ayrı; RUNNING/PAUSED/COMPLETED/CANCELLED ve recorded RISK/BREACH ayrı |
| Success | pay COMPLETED without recorded BREACH; payda COMPLETED OR recorded BREACH; UNAVAILABLE tamamen hariç; running unbreached henüz ölçülmemiş |
| Assignee/load | current assignee + configured weight sum; actual_work_hours=NOT_AVAILABLE |
| Incident | yalnız yetkili cohort'a aynı dept'te bağlı incident DISTINCT count/status; private içerik yok |
| CSAT | yalnız current rating revision; score sum/count ve1–5 dağılım; average numerator/denominator, count0→NO_RATING |

Tarih zorunlu UTC ISO Z ve half-open; ayrıca geçerli IANA zone belirtilir. IANA metadata
UTC filtreyi sessiz local güne dönüştürmez. Native transaction snapshot/as_of UTC;
deterministik ORDER BY. Paged clocks/policy groups, assignment ve ticket dataset'leri
aynı açık offset/limit penceresini kullanır; rates grup başınadır, ilk50 grup tüm grup
sonucu gibi sunulmaz. Tüm cohort count/distribution SQL'de tam yetkili havuzdandır.
Stored SLA risk/consumed time raporudur; çalışmamış cron adına live breach tahmini veya
otomatik başarılı SLA iddiası yoktur. Null clock=NO_CANONICAL_CLOCK; unavailable excluded.

## API, demo ve test matrisi

Native `/api/v1/admin/support-advanced/{prepare,execute,simulate}` POST;
`{report,rules,executions,incident,csat}` GET. Client `{incident,csat}` GET ve csat POST.
Mutation native CSRF/session/Idempotency-Key/current grants/allowlist. Admin prepare/
execute state/payload/session/identity-bound300s confirmation ve optimistic version.
Rules view predicate/template değerlerini redakte eder, execution view current ticket
automation grant ile ID/result/reason/causation gösterir. Customer report/rule route yok.

Sentetik demo: tests/support-d.php native users/grants/MFA ile sample ticket ve explicit
rule publish, side-effect-free simulate, native source→support:automation→outbox:dispatch
→queue:work; loop cut/receipt. Incident iki müşteri link + explicit public update;
customer only public view. CSAT explicit fixture window, RESOLVED, own client vote;
scoped cohort report. Gerçek müşteri/veri/credential/mailbox/provider yoktur.

26 zorunlu negatif senaryo: AST/type/depth/action deny; real A→B→A/A→A/depth4;
exact unpublished/forged/job fingerprint/replay;1/4/8 worker ve concurrent two-rule;
stale revision/ticket/confirmation; simulation/no writes; current grant/session revoke;
closed/invalid state; priority old SLA history; note privacy/no customer mail;
incident tenant/private RCA isolation/no fake response/no auto close; CSAT ownership/
score/window/replay/revision/rollback; report SQL ACL/UNAVAILABLE/pagination; atomic
rule/incident/CSAT publication failure; old514 regression/migration. SUP-008/009
karşı-örnek/test/PASS/source-line eşlemesi gerçek kanıt tamamlanınca aşağıya eklenecek.

## Migration, geri dönüş ve açık sınırlar

053 additive immutable rule/source/execution/domain events/confirmation/tags/held,
central incidents/public/private history, explicit CSAT policy/rating revisions.
001–052 byte/hash korunur; checksum/empty/052→053/replay/failed-DDL eski testleri
genişletilir, ad veya assertion silinmez. Native Identity/Queue/Security/HTTP ve F5-C
Mail/Attachment dosyaları korunur. Tutarlı code/DB/key/storage snapshot restore veya
reviewed forward-fix; destructive down yoktur. Rule disable current immutable revision
yayınlar; history silinmez. Bu çalışma production restore provası değildir.

F4/F5-A/F5-B/F5-C kabulleri ve F5-C dört açık istisnası değişmez:
1 gerçek mailbox alıcı/dept routing ve sender auth;2 retention sonunda kesin erişim
kesilmesi/scheduler;3 AV/gerçek IMAP/SMTP/genel MIME;4 HELD/ACCEPTED(mock) gerçek teslim
değildir. EXTERNAL_MAIL_ALLOWED=false. DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED,
PROV-005 ve bütün canlı GATE OPEN. PR3 OPEN/DRAFT. F5-E/F6/gerçek mail/provider,
merge/staging/production yoktur. Gerçek test/mock kanıt tesliminden sonra bağımsız
teknik denetim istenir ve durulur; kapsamın tamamı veya canlı kabul ilan edilmez.
