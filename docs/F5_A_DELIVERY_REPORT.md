# F5-A — Destek çekirdeği test/mock teslim raporu

25 Ekim 2026. **Bağımsız kabul bekler; DONE veya production-ready değildir.**
Başlangıç `5d944dcb9abbf5e0f447272d8273346349aab3c8`; dal `codex/f5-support-center`.
Yalnız F5-A uygulandı. Kaynak/CI kanıtı bu raporun son doğrulama bölümüne eklenir.

## Kapsam ve dosya envanteri

FR-SUPPORT, FR-ID, SEC-001/002/004, OPS-001/003; SUP-003/004 çekirdek ve SUP-007.

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
`departments`. Liste en fazla 50 aday üzerinden yetkili projection; total/count/başkasına
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

Hedef 51 suite / 428 test: önceki 49/394 + yeni HTTP30/migration4.
Son temiz yerel regresyon ve PHP8.3/8.4 CI sonuçları henüz bu belgeye bağlanmadı;
tam PASS iddiası ancak evidence/f5-a içindeki gerçek sonuçlarla geçerlidir.
Yerel runtime PHP8.4.24 / MariaDB10.11.18; yerel PHP8.3 mevcut değildir.

İlk ara regresyon sırasında 049 şeması geliştirilirken değiştirilmesi Domain CLI
testinde başarısızlık oluşturdu; checksum güvenliği aşılmadı. Kaynak sabitlendikten
sonra temiz şemada tekrar yapılır. Ayrı HTTP suite'i aynı MariaDB server'daki global
migration lock ile çakışınca startup reddedildi; test atlanmadı, bağımsız suite'ler
aynı server'da eşzamanlı migration çalıştırılmıyor. Assertion gevşetme/skip yoktur.

## Açık kapsam, risk ve durma noktası

SUP-007 çekirdek testi; SUP-003/004 API/departman çekirdeği kanıtlı alt kapsamdır.
SUP-001/002, SUP-005/006, SUP-008/009/010 **OPEN / bu teslimde PASS değil**.
F5-B SLA/assignment, F5-C mail/ek, F5-D rule/incident/CSAT/report, F5-E UI ve
bütünleşik kabul ayrı yetki ister; SUP-010 ayrıca F6 import'a bağlıdır.

Liste candidate pagination yetkisiz boş sayfalar döndürebilir; yetkisiz metadata
ve toplam vermez. Detail mesaj geçmişi henüz cursor pagination kullanmaz; büyük
konuşmalarda kaynak bütçesi F5-E öncesinde ölçülmelidir. Departman row lock'ları
başlangıç 2–5 personel ölçeğinde tutarlılığı önceler; production kapasite sözü değildir.
Public event consumer yalnız HELD receipt'tir; mail/notification teslimi yoktur.
Immutable DB trigger nedeniyle bakım/retention/import ayrı tasarım ve yetki ister.

EXTERNAL_MAIL_ALLOWED=false; SMTP/IMAP hesabı, gerçek müşteri maili veya PayTR/WHM/
Voyar/registrar IO yoktur. DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED,
PROV-005 ve tüm canlı GATE'ler OPEN. F4 DONE değildir. F4 dalı ve taslak PR #2
korunur. F5-A test/mock tesliminde durulur; merge, staging, production veya F6 yoktur.
