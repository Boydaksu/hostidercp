# F5-A — Destek çekirdeği test/mock teslim raporu

## Son bağımsız kabul kararı — 25 Ekim 2026

**F5-A LIMITED MOCK TECHNICAL BASELINE — ACCEPTED**

Proje sahibinin son bağımsız kabul kararı yalnız F5-A test/mock çekirdeğini kabul eder.
Kabul edilen uygulama:
[`d3712eda6b3d9156187727ced85fff04de7457c5`](https://github.com/Boydaksu/hostidercp/commit/d3712eda6b3d9156187727ced85fff04de7457c5).
Her iki gerçek CI işi **51 suite / 433 test / 0 hata**:
[PHP8.3](https://github.com/Boydaksu/hostidercp/actions/runs/40212210626/job/121225660648),
[PHP8.4](https://github.com/Boydaksu/hostidercp/actions/runs/40212210626/job/121225660244).
Eski 428 test adı korunmuş, beş yeni regresyon başarıyla eklenmiştir.

- **P1-1 kapalı / PASS:** support.changed event UUID, outbox/job bağları ve tekil receipt.
- **P1-2 kapalı / PASS:** yanıltıcı subject araması kaldırılmıştır; açık HTTP 422
  SUPPORT_SEARCH_UNSUPPORTED sözleşmesi kabul edilmiştir.

Bu karar gerçek mail, SLA, assignment, attachment, rule engine, incident, CSAT veya
gelişmiş UI kabulü değildir; bu özellikler tamamlanmış sayılmaz. F5-A genel V1 DONE
veya production-ready ilan edilmez. Ayrıntılı düzeltme ve eski/yeni test kanıtları
[F5_A_POST_AUDIT_REVIEW](F5_A_POST_AUDIT_REVIEW.md) içinde korunur; oradaki yeniden
kabul bekleme ifadeleri bu son karardan önceki tarihsel teslim durumudur.

F4'ün kabul edilmiş sınırlı mock tabanı değişmez. 001–049 migration geçmişi korunur;
yeni migration yoktur. DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED, PROV-005
ve bütün canlı GATE'ler OPEN kalır. F5-B için ayrı yetkilendirme beklenir; yeni
geliştirme, F5-C/D/E/F6, gerçek SMTP/IMAP, merge, staging veya production başlatılmaz.
[PR #3](https://github.com/Boydaksu/hostidercp/pull/3) açık, taslak ve merge edilmemiş kalır.

Kabul kaydı kapsamı/planı: yalnız README ve bu rapora karar eklenir; kaynak/test/CI
workflow/migration değişikliği yapılmaz. Doğrulama belge diff'i, kabul edilen kaynak
ve migration eşliği, mevcut CI kanıtı ve PR'ın açık/taslak durumudur. Yeni bir test
veya canlı kabul iddiası yoktur. Geri dönüş yalnız bu belge kaydı commit'inin geri
alınmasıdır; uygulama verisi veya immutable geçmiş değiştirilmez. Çalışma durur.

## Tarihsel ilk teslim

> Bu rapor tarihsel ilk teslimdir. P1 event receipt ve q sözleşmesi için güncel
> [hedefli denetim düzeltmesi](F5_A_POST_AUDIT_REVIEW.md) ve ADR-017 esas alınır.

25 Ekim 2026. **İlk teslimin tarihsel durumu: bağımsız kabul bekliyordu; DONE veya production-ready değildi.**
Başlangıç `5d944dcb9abbf5e0f447272d8273346349aab3c8`; dal `codex/f5-support-center`.
Yalnız F5-A uygulandı. [Uygulama SHA a38e8da89bef9fa0476210a8ad021d250606c81a](https://github.com/Boydaksu/hostidercp/commit/a38e8da89bef9fa0476210a8ad021d250606c81a).
Bu SHA sonrası teslim kanıtı güncellemesi yalnız README/docs dosyalarıdır; runtime,
migration ve test kaynakları aynı kalır. [Taslak PR #3](https://github.com/Boydaksu/hostidercp/pull/3).

## Kapsam ve dosya envanteri

FR-SUPPORT, FR-ID, SEC-001/002/004, OPS-001/003; SUP-003/004 çekirdek ve SUP-007.
Karar izi: R1-09, R2-01, R2-05/06 temel politika, R3-09, R4-01/02/04/07.

| Dosya | Değişiklik |
|---|---|
| src/Support/Access.php | Native Identity, customer/departman/üyelik/permission birlikte kontrolü; yetkisizde tek 404 |
| src/Support/State.php | Kanonik allowlist; explicit departman reopen/hold politikası |
| src/Support/Service.php | Ticket/mesaj/departman/üyelik application service; transaction/version/idempotency ve projection |
| src/Support/Controller.php | Native client/admin session, CSRF, rate-limit, request allowlist, JSON response |
| src/Support/EventSink.php | Mevcut Outbox/Queue üzerinde yalnız support.held.v1 yerel receipt |
| src/Services/SupportLinks.php | Mevcut service repository ve native sahiplik kontrolü üzerinden salt okuma |
| migrations/049_support_core.php | Altı InnoDB tablo, FK/index/CHECK/immutable triggers ve başlangıç departmanları/izinleri |
| bootstrap/app.php, bin/console.php | Route ve yerel held event handler kaydı |
| src/Core/Security/AuditEvent.php | support.changed kontrollü audit sözlüğü |
| tests/support-fixture.php, support-http-child.php | Yapay hesaplar; bağımsız HTTP client process |
| tests/support-http.php | 30 gerçek cookie/session/HTTP/MariaDB testi |
| tests/support-migrations.php | 4 gerçek migration testi |
| .github/workflows/f0.yml | Önceki 49 suite'e 2 yeni suite eklenmesi; PHP8.3/8.4 |
| tests/cloud.php, cloud-costs.php, domains-audit.php | Yalnız kesin expected migration listesine 049 eklendi; assertion/test kaldırılmadı |
| F5_PLAN, F5_A_PLAN, ADR-016, bu rapor, evidence/f5-a | Plan, teknik karar, teslim ve kanıt |

001–048 değişmez. Önceki 394 testin isimleri ve assertion'ları korunur; üç migration
assertion'ı yeni additive sürümü de doğrular. Native kimlik/rol/finans/provider motoru yeniden yazılmadı.

## State ve kalıcı köken

Ticket opaque UUID ve random 96-bit `HD-` numarası taşır. customer/requester,
opened_by/session/provenance/channel/created_at immutable; requested/effective priority
ilk açılışta aynı explicit LOW/NORMAL/HIGH/CRITICAL değeridir. Rule/priority override yoktur.
UTC timestamp ve version saklanır. Mesajlar encrypted plain text; yazar, session,
identity version ve TEST_PANEL kökeni korunur. Mesaj/event/link UPDATE/DELETE reddedilir.

NEW→WAITING_STAFF/WAITING_CUSTOMER/RESOLVED/CLOSED;
WAITING_STAFF→WAITING_CUSTOMER/RESOLVED/CLOSED;
WAITING_CUSTOMER→WAITING_STAFF/RESOLVED/CLOSED;
RESOLVED→WAITING_STAFF/CLOSED;
ON_HOLD→WAITING_STAFF/WAITING_CUSTOMER/RESOLVED/CLOSED.
NEW/WAITING_*→ON_HOLD yalnız allow_hold; CLOSED→WAITING_STAFF yalnız allow_reopen.
İki policy başlangıçta false; süre veya SLA ticari değeri belirlenmez.
Customer reply WAITING_STAFF, staff reply WAITING_CUSTOMER yapar; kapalı yeniden
açılma önce policy kontrolünden geçer. Internal note yalnız version artırır.

Ticket müşteri sahipliği hizmet transferiyle değişmez. Service link açılırken
Services.SupportLinks.owned güncel Identity registry + canonical service sahibini
doğrular. Eski ticket'taki link güncel sahiplik uyuşmazsa projection'dan çıkarılır.
Yeni malik eski konuşmaları alamaz. Ticket üzerinden service/finance mutation yoktur.

## Native permission matrisi ve izolasyon

| İşlem | Native izin ve ek koşul |
|---|---|
| Müşteri list/detail/create/reply | Seçili session customer membership + o hesaba ait support.view; ticket.customer aynı |
| Personel list/detail | support.ticket.view + native customer/department grant + aynı departmanda üyelik + MFA |
| Personel public reply | Yukarıdakiler + support.ticket.reply için aynı kapsam |
| Dahili not okuma/yazma | Yukarıdakiler + support.ticket.internal_note için aynı kapsam |
| State/move | Yukarıdakiler + support.ticket.manage; move iki departmanın üyelik/grant'ını ister |
| Departman/üyelik yönetimi | identity.staff.manage + administrative native staff + MFA ve son 300 saniye fresh step-up |

Super Admin ve Destek rollerine dört granular izin eklenir; otomatik üyelik/grant
verilmez. Super Admin bypass yoktur. Müşteri eski support.view kullanır; farklı
hesap rolleri birleştirilmez. Impersonation bütün F5-A read/mutation yollarında
reddedilir. Replay de güncel ACL/session/identity version kontrolü yapar.
Native staff statüsü kaldırılırsa departman üyeliği CASCADE kalkar; tarihsel mesaj
yazarı user FK ile kalır. Requester membership kaldırılabilir; erişim sonraki istekte kesilir.

support.ticket.assign/merge, support.sla.override, support.incident.manage,
support.mail_review, support.report.view yalnız sonraki faz planıdır; bu fazda
permission/endpoint yeteneği olarak açılmaz. Docs/08'deki kısaltılmış SLA/incident
isimleri için kullanıcı kickoff'undaki tam isimler esas alınır; uygulaması yoktur.
Destek izinleri finance/WHM/Voyar/registrar mutation hakkı vermez.

## Internal note gizliliği

Mesaj tipi sunucuda route/actor'dan seçilir. İstemciden type/customer_visible/author/
customer/ticket bağlama alanı kabul edilmez. SQL CHECK private type ile visibility
eşitliğini ayrıca zorlar; composite ticket/customer FK çapraz müşteriyi engeller.
Client projection private satırı SQL seviyesinde dışlar; decrypt edilmez. İzni olmayan
personelin detail'i de private satırı içermez. Liste araması yalnız yetkili subject/number
üzerindedir, mesaj aramaz. Dahili not için public outbox/notification yoktur.
Yerel EventSink de internal_note veya forged payload kabul etmez.
Attachment, export, mail ingestion/dispatch endpoint'i yoktur; bu genişletmeler için
SUP-003'ün bütünü PASS sayılmaz. F5-C/E bu filtreleri korumak zorundadır.

## HTTP sözleşmesi ve yapay örnekler

GET `/api/v1/{client|admin}/support/list?offset=0&q=...`, `detail?id=<uuid>`,
`departments`. Liste native grant/üyelik filtresi LIMIT/OFFSET öncesinde uygulanmış en fazla 50 yetkili projection; total/count/başkasına
ait subject/customer metadata yoktur. Offset 0..1000000, q en çok 191 karakter.
Admin departments yönetim izni ister; client yalnız enabled departman id/name görür.

POST client `create`, `reply`; admin `reply`, `note`, `state`, `move`, `department`,
`department-new`, `member`. Cookie, X-CSRF-Token ve Idempotency-Key zorunludur.

```json
{"department_id":"<department-uuid>","subject":"Yapay destek talebi","body":"Salt metin\nİkinci satır","priority":"NORMAL","service_ids":[]}
```

Create data: `{id,number,version:1,message_id}`. Reply/note:
`{id:"<ticket-uuid>",version:1,body:"Yapay yanıt"}` → `{id,message_id,version:2}`.
State: `{id,version,status:"RESOLVED"}`; move: `{id,version,department_id}`.
Department update `{id,version,name,enabled,allow_reopen,allow_hold}`;
department-new aynı politika alanları, id/version olmadan;
member `{department_id,user_id,enabled,version:<department-version>}`.
Unknown alan 422, yanlış method 405, native auth 401/MFA 403, yetkisiz ticket 404,
stale state/version/idempotency payload 409. Response mevcut `{data,meta,errors}`.
Create response cached yalnız ID/no/version taşır; subject/body PII cache'e girmez.

JSON application/json, no-store, nosniff ve mevcut CSP ile çıkar. HTML/binary
upload/download renderer yoktur; içerik plain text'tir ve sonraki UI textContent
kullanmalıdır. Yapay gerçek örnek: [http-examples](evidence/f5-a/http-examples.json).

## Transaction, yarış ve migration

Native Idempotency outer transaction'ına Identity.locked katılır: user/session
fence → department lock → ticket lock. Move department ID'lerini sıralı kilitler.
Mesaj + version/state + immutable support_event + Audit + public Outbox tek commit'tir.
Hata hepsini rollback eder; request payload HMAC'ı native key ile bağlanır.
Stale optimistic version 409; yanıt kaybolmuş gibi başarılı görünmez. Aynı key aynı
session/action için tek mesajdır; key farklı payload ile conflict. Farklı aktörler
aynı key'i paylaşarak birbirinin cevabını alamaz.

İki farklı personel, iki HTTP server, iki bağımsız client process eski version ile
yarışır: bir 200/bir 409; yeni version ile açık retry sonrası iki yanıt da kalıcıdır.
1/4/8 bağımsız process aynı session/key ile tek mesaj sonucu paylaşır. Queue receipt
SUCCEEDED yalnız yerel olay tüketimidir; mail gönderildi veya SLA karşılandı değildir.

049 altı tablo ekler. Runtime schema checksum/APPLIED kontrolü sürer. MariaDB DDL
implicit commit nedeniyle başarısızlıkta kısmi şema + APPLYING kalır, runtime/replay
fail-closed olur. Otomatik destructive down yoktur. Tutarlı kod/DB/APP_KEY yedeği
restore edilir veya incelemeli forward-fix yapılır; mesaj/audit geçmişi silinmez.
Bu teslim production restore provası değildir.

## Doğrulama ve başarısızlık kaydı

Yerel toplam **51 suite / 428 PASS**: önceki 49/394 + yeni HTTP30/migration4.
Eski 49 suite ve migration suite temiz batch'te; HTTP suite EventSink ve pagination
gizlilik düzeltmeleri ardından yeniden çalıştırıldı. [Yerel özet](evidence/f5-a/local.json),
[PASS satırları](evidence/f5-a/local-tests.txt), [migration kanıtı](evidence/f5-a/migrations.json),
[001–048 / eski test koruması](evidence/f5-a/preservation.json).
Son kaynak için gerçek CI **SUCCESS**:

| Runtime | Suite / test / hata | Gerçek job |
|---|---|---|
| PHP 8.3.36 / MariaDB 10.11.19 | 51 / 428 / 0 | [121213753537](https://github.com/Boydaksu/hostidercp/actions/runs/40208255513/job/121213753537) |
| PHP 8.4.27 / MariaDB 10.11.19 | 51 / 428 / 0 | [121213753410](https://github.com/Boydaksu/hostidercp/actions/runs/40208255513/job/121213753410) |

[CI kanıtı](evidence/f5-a/ci.json): gerçek ham günlüklerden her sürümde 428 PASS ve
51 suite özeti sayıldı. Kabul edilen F4 job 121174901258'deki **394 test adının tamamı**
her iki son job'da bulundu; eksik 0, yeni 34. PHPStan 0 hata, locked Composer audit
0 advisory, 322 PHP lint dosyası 0 ihlal, 334 dosya pattern scan 0 bulgu.
Services HTTP 10, Commerce HTTP 30, session concurrency 20 tekrarın tamamı hatasız;
üç mevcut JS syntax kontrolü başarılıdır. Eski Domain/Cloud/finance/identity/commerce
ve 1/4/8 worker regresyonları aynı CI içinde korunur.

PR test checkout `045de363b49643bf5505ec2f10792fad8a968226` kaynakla aynı
`bb8be164b74b82f29445100df762fb934898847f` tree'yi taşır; bu GitHub'ın sanal test
checkout'udur, gerçek branch merge yapılmadı. Yerel ve GitHub kaynak tree eşliği
ayrıca doğrulandı. Belge/kanıt follow-up'ı test edilen runtime'ı değiştirmez.
Yerel runtime PHP8.4.24 / MariaDB10.11.18; yerel PHP8.3 mevcut değildir.

İlk ara regresyon sırasında 049 şeması geliştirilirken değiştirilmesi Domain CLI
testinde başarısızlık oluşturdu; checksum güvenliği aşılmadı. Kaynak sabitlendikten
sonra temiz şemadaki 49 eski suite'in tamamı PASS oldu. Ayrı HTTP suite'i aynı MariaDB server'daki global
migration lock ile çakışınca startup reddedildi; test atlanmadı, bağımsız suite'ler
aynı server'da eşzamanlı migration çalıştırılmıyor. Assertion gevşetme/skip yoktur.

Yeni native Queue testi EventSink'in alan sırasına bağımlılığını yakaladı: Queue
canonical JSON anahtarlarını sıraladığı için public receipt FAILED oluyordu.
Field allowlist artık sıralanmış alan kümesini doğrular; ek alanlar hâlâ reddedilir.
Son HTTP30 testi bu düzeltmeyle PASS; önceki başarısız koşu PASS diye sayılmadı.
İlk GitHub PR koşusu 40207426955 iki sürümde bu Queue testinde başarısızdı;
düzeltme sonrası yukarıdaki son kaynak CI'ı başarılıdır. Hiçbir eski veya yeni test
silinmedi, assertion gevşetilmedi, skip/ignore kullanılmadı.

### Zorunlu negatif senaryo eşlemesi

Aşağıdaki adlar `tests/support-http.php` içindeki bağımsız testlerdir; ham PASS
satırları local-tests.txt ve son CI job loglarında bulunur.

| No | Senaryo | Test adı / kanıt |
|---|---|---|
| 1 | Başka müşteri ticket okuma | another customer cannot read reply or enumerate ticket |
| 2 | Yetkisiz departman başlık/arama/varlık | staff role without department membership; membership role customer grant (pagination öncesi filtre dahil) |
| 3 | Çapraz müşteri mesaj bağlama | client cannot forge message kind; database visibility CHECK and composite customer FK |
| 4 | Müşteri internal_note oluşturma | client cannot forge message kind visibility author or other customer binding |
| 5 | Dahili not müşteri response/notification | internal note never enters client response search or outbox notifications; queue held receipt testi |
| 6 | Not permission iptali | note permission revoked next request also blocks cached idempotency replay |
| 7 | Impersonation | impersonation does not confer privileged support permission |
| 8 | Başka müşterinin service link'i | service link uses canonical ownership and transfer never opens old conversations |
| 9 | Service transfer eski konuşma gizliliği | Aynı service link/transfer testi; yeni malik HTTP 404 |
| 10 | CSRF | CSRF missing wrong method and missing idempotency key cannot mutate |
| 11 | Yanlış method | native HTTP authentication MFA and method gates; CSRF testi |
| 12 | Aynı key tek ticket/mesaj | create and message retry same key; 1 4 8 independent HTTP processes |
| 13 | Aynı key farklı payload | create and message retry same key are single durable effects different payload conflicts |
| 14 | İki farklı personel paralel yanıt | two different staff in independent HTTP processes conflict and explicit retry preserves both |
| 15 | Stale version | stale state version returns explicit 409 without message or audit loss |
| 16 | Session/identity_version iptali | identity version invalidation; session revocation; native staff removal; requester membership revocation |
| 17 | Atomic rollback | failed audit insert rolls back message state event outbox and idempotency |
| 18 | HTML/XSS | HTML XSS text is JSON data under no-store CSP and no HTML renderer |
| 19 | Closed yanlış reopen policy | closed reopen and hold obey explicit department policy allowlist; explicit department reopen policy testi |
| 20 | Departman move izolasyonu | department move requires both scopes and invalidates old staff access |

SUP-003: private satır SQL filtresi + sunucu türü/CHECK + outbox/held receipt negatif
testleri; upload/export/mail endpoint'i olmadığı için bu alt alanlar ACCEPTED değildir.
SUP-004: role/grant/member/customer kesişimi, başlık/arama/list pagination ve move
testleri çekirdeği kanıtlar; attachment kapsamı F5-C'ye kalır.
SUP-007: iki farklı native staff, iki HTTP server ve iki client process 200/409;
fresh version ile retry sonrası iki yanıt korunur, 1/4/8 process dedupe tek mesajdır.

## Açık kapsam, risk ve durma noktası

SUP-007 çekirdek testi; SUP-003/004 API/departman çekirdeği kanıtlı alt kapsamdır.
SUP-001/002, SUP-005/006, SUP-008/009/010 **OPEN / bu teslimde PASS değil**.
F5-B SLA/assignment, F5-C mail/ek, F5-D rule/incident/CSAT/report, F5-E UI ve
bütünleşik kabul ayrı yetki ister; SUP-010 ayrıca F6 import'a bağlıdır.

Liste native grant/üyelik SQL filtresini pagination öncesinde uygular ve her satırı
yeniden yetkilendirir; yetkisiz kayıtlar sayfa boşluğu da oluşturmaz. Detail mesaj geçmişi henüz cursor pagination kullanmaz; büyük
konuşmalarda kaynak bütçesi F5-E öncesinde ölçülmelidir. Departman row lock'ları
başlangıç 2–5 personel ölçeğinde tutarlılığı önceler; production kapasite sözü değildir.
Public event consumer yalnız HELD receipt'tir; mail/notification teslimi yoktur.
Immutable DB trigger nedeniyle bakım/retention/import ayrı tasarım ve yetki ister.

EXTERNAL_MAIL_ALLOWED=false; SMTP/IMAP hesabı, gerçek müşteri maili veya PayTR/WHM/
Voyar/registrar IO yoktur. DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED,
PROV-005 ve tüm canlı GATE'ler OPEN. F4 DONE değildir. F4 dalı ve taslak PR #2
korunur. F5-A test/mock tesliminde durulur; merge, staging, production veya F6 yoktur.
