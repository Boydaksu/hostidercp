# F5-E — native destek panelleri ve bütünleşik test/mock planı

Başlangıç eb7731d6b955ff2473f4322c7e0e3b8f29e4a0bb; kabul edilmiş D uygulaması
3404ddda79bc2017f05cb474b5546e23647910d8;51 suite/555 test;001–053 frozen.
Yalnız F5-E test/mock. SUP-001–009, SEC-001/002/004, OPS-001/003/005;
D4/R2-01–09 ve ADR-016–021 sınırları korunur. Yeni Identity/Queue/Audit yoktur.

## Ekran / API / yetki / durum matrisi

| Ekran | API / kanonik işlem | Sunucu yetkisi / durum |
|---|---|---|
| /admin/support | native HTML guard + support-ui status/list/detail/poll | active staff, MFA, no impersonation, gerçek dept membership/current view grants |
| /client/support | aynı native client session/account ve own support.view | customer override yasak; private note/roster/review/root/report endpoint yok |
| Inbox | SQL ACL-before-filter/pagination; dept/customer/state/mine/unassigned/risk |50+has_more, stable id order; q her değerde422 unsupported |
| Conversation | bounded public/note-aware message projection, current version | customer SQL public filter; note ayrı composer, permission tekrar |
| Compose | outer native Idempotency + Support canonical compose seam | current ACL/MFA, admin fresh step-up, optimistic version, draft exact revision/body, atomic clear |
| Operational panes | mevcut Work/Mail/Advanced API'leri | her pane kendi native izin/confirmation modelini korur; reddedilen alanda açık hata |
| Draft/filter | yeni054 şifreli server store | user+native session+customer+ticket+reply/note; current ACL, version, expiry; localStorage yok |
| Presence | bounded staff-only heartbeat/count | current own session + ticket view;120s approximate, kesin online/typing iddiası yok |
| Settings/editor | native Work policy/roster, D rule/incident/CSAT/report, C mail | current native management/fresh confirmation; AST builder allowlist, literal templates |

Ana düzen mevcut identity.css/support.css değişkenleri ve native Response/CSP ile
üç sütun: inbox, conversation/composer, operation. Dar ekranda tek sütun; labels,
focus, keyboard, textContent/createElement; dinamik innerHTML/eval yoktur.
Mevcut /admin ve /client navigasyonuna link eklenir; ayrı authentication kurulmaz.

## Dosya ve migration planı

src/Support/UI/{Controller,Access/Views,Commands,Panel,StateStore} ve public/assets/
support.js/support-state.js/support.css. Native bootstrap route; Identity Panel link.
Support.Service'e outer transaction compose seam: aynı fields/authorize/create/reply/
state/event/SLA/mail işlemleri, ikinci state machine yok. Diğer modüller salt okunur
application boundary üzerinden; finance/provider mutation UI alanı yoktur.
Work/Mail/Advanced mevcut endpoint'leri korunur; gerekirse sadece current-authorized
editor/roster/projection metadata endpoint'i eklenir. Rule AST bilgisi ancak native
automation.manage + tüm ilgili action permissions ve fresh MFA ile okunur.

054+ additive: encrypted session-scoped drafts, bounded saved filters ve approximate
presence.001–053 byte/hash değişmez. DraftTTL1800s teknik limit, body16000char,
user/session başına bounded records; saved filters max10, label/spec encrypted.
Metadata/owner bağları FK ve unique/CHECK; native Idempotency sonucuna PII konmaz.
Draft süresi ve presence teknik UI süreleridir; SLA/CSAT ticari değerleri seed edilmez.

## Poll / draft / conflict sözleşmesi

Tek in-flight polling;15s min interval, hidden page/session revoke'da durur. Her
seçim/permission context değişiminde generation ve request sequence; eski response
yeni ticket veya yeni version'ı geri alamaz. Current authorization her poll'da tekrar.
Scope fingerprint değişince cached özel veriler temizlenir; private body session
veya permission kaybında DOM/RAM'den çıkar. Same version permission değişimini gizlemez.
Message page50; full subject/body search yok. History/timeline SQL public allowlist;
client actor/session/internal metadata görmez. Auxiliary panes ayrı current-authorized
requests ile yenilenir; eski response aynı generation kontrolüne tabidir.

Draft türü immutable key parçasıdır; note→reply sessiz conversion yoktur. Draft save
versioned, server encrypted ve1800s; base ticket version korunur. Ticket değişirse
uyarı/409; kullanıcı güncel konuşmayı incelemeden otomatik POST/rebase yoktur.
Send native Idempotency transaction'ında exact draft revision/body ile canonical
message + history/outbox + draft clear atomiktir. Cached replay current ACL recheck
yapar; duplicate message veya başka revision draft silme yoktur. Network uncertainty
manual aynı payload/key tekrarına izin verir; otomatik retry yoktur.

## F5-A/B/C/D bütünleşik kapsam

Client create/service choice/public reply/files/public incident/RESOLVED CSAT;
staff reply/note/state/move/eligible manual assignment ve policy/roster settings.
Calendar/targets/risk/weights explicit değerler; policy revision ve UNAVAILABLE uyarısı.
Automation visual typed v1 builder + simulate + confirmed publish + execution results;
customer/service predicates disabled UNAVAILABLE. Incident public/private ayrı alanlar.
Mail review/unknown/duplicate/proof ve mock outbound/attachment quarantine gösterimi;
gerçek sender authentication veya mail/AV yoktur. Reports UTC created-at cohort,
stored snapshot, pay/payda ve NOT_AVAILABLE actual work hours; enterprise historical
validation açık. Export açılmaz; explicit unsupported ve server-side fail-closed.

## Test ve kanıt matrisi

555 eski test adı korunur. Yeni gerçek MariaDB/native HTTP: HTML/MFA/impersonation,
scope/filter/pagination/public timeline, native compose409/idempotency/draft cleanup,
note draft isolation/expiry/revocation, saved filters, polling scope/version, presence,
attachment/mail/SLA/assignment/automation/incident/CSAT/report end-to-end18 senaryo.
1/4/8 bağımsız PHP process; önceki Services10/Commerce30/session20. JS syntax ve
deterministic DOM/state tests; mümkünse gerçek browser E2E, yoksa açık ayrı gap.
Browser bağımlılığı eklenirse mevcut CI/lock ile pinli, gerekçeli ve reproducible;
browser başarısızlığı HTTP/DOM sonucu gibi gizlenmez. PHP8.3/8.4 gerçek CI, PHPStan0,
locked audit/lint/pattern, empty/053-upgrade/replay/failed-DDL/checksum koruması.

## Açık bağımlılık, risk, geri dönüş ve durma

F5-C dört istisnası OPEN: canlı mailbox routing/auth; retention sonunda kesin access
cutoff/scheduler; AV/IMAP/SMTP/genel MIME; HELD/ACCEPTED mock gerçek teslim değildir.
R2-06 auto-close DEFERRED; merge/split kabul dışı; SUP-010 F6; kurum geneli tarihsel
SLA doğrulaması açık. DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED, PROV-005 ve
tüm canlı GATE OPEN. EXTERNAL_MAIL_ALLOWED=false. F5-F/F6 ve live yetkisi yoktur.
Riskler: async response inversion, stale typed drafts, note projection/cross-session
cache, scope revoke, multi-action confirmation ve browser fixture repeatability.
Rollback consistent code/DB/key/private-storage snapshot restore veya reviewed
forward-fix; destructive down yoktur. Yeni tablolar olmadan eski code restore
planı snapshot'tan yapılır; immutable audit/history silinmez. Teslim SHA/tree/gerçek
CI ve browser kanıt sınırı raporlanır; bağımsız denetim istenir ve durulur.
F5-E DONE/FULL ACCEPTED/production-ready ilan edilmez, PR3 OPEN/DRAFT kalır.
