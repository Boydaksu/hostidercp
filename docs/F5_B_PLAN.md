# F5-B — SLA takvimleri ve personel atama planı

25 Ekim 2026. Yetki yalnız test/mock F5-B. Dal codex/f5-support-center; taban
563b79e5ab201fb73ba38e37d2a6b86506df6e9b, kabul edilmiş F5-A uygulama d3712ed.
51 suite / 433 test ve migration001–049 korunur. F5-C/D/E/F6 veya canlı işlem yoktur.
İz: FR-SUPPORT, R2-02/03, R3-09, SUP-005/006, SEC-001/002/004, OPS-001/005.

## Kapsam ve dosyalar

src/Support/Work altında Time, Calendar, Policy, Store, Clocks, Eligibility,
Assignments, Engine, Commands, Controller ve HeldSink; additive050; Support.Service
transaction içi olay hook'u; bootstrap/bin bağlantısı. Yeni testler mevcut Support
HTTP test girişinden çalışır; CI workflow değişmez. F5-A ACL/private note/idempotency
ve exact support.changed receipt sözleşmesi korunur; iki gerçek kaynak modülü yeniden yazılmaz.

## Veri ve teknik sözleşme

050: immutable versioned SLA/assignment policies ve roster snapshots; department
heads/cursor; ticket work projection, SLA cycles/clocks/history/alerts; immutable
assignment/work events; server-bound confirmations. Ticket customer composite FK;
native user/session/department bağlantıları, unique clock/source event ve alert-level
tekilliği. Eski migration'lar byte/hash korunur; destructive down yoktur.

IANA calendar weekly intervals + date exceptions; exception günü tüm günü override
eder (önceki geceden gelen aralık dahil). UTC sabit-offset segmentleriyle duvar saati
aralıklarının preimage union'u alınır: spring gap yoktur, fall fold'daki iki gerçek
UTC aralığı ayrı zamanlardır; her UTC mikrosaniye yalnız bir kez sayılır. Süre integer
microseconds; hedefler integer minutes. Açık bounds: 3660 local gün hesap ufku,
7 weekday, günde en çok8 interval, request64KiB, cycle başına en çok100 pending NEXT.
NEXT kapasitesi aşılırsa message transaction bütünü409 SLA_CLOCK_CAPACITY ile geri
alınır; mesaj kabul edilmiş gibi gösterilmez. UTC tarih aralığı2000–2099'dur.
Limits dışında fail-closed; sonsuz tarama yok.

Her policy bütün öncelikler için first/next/resolution hedefi, metric bazında explicit
pause states ve risk yüzdesi ile assignment mode/weight ister; ticari default yoktur.
Yeni ticket o andaki policy'ye bağlanır. Eski/no-policy ticket otomatik backfill edilmez;
explicit enroll yalnız yeni cycle başlatır, geçmişi yeniden hesaplamaz. Department
move eski cycle'ı koruyup kapatır ve yeni department policy ile yeni cycle açar.
Gerçek staff_reply first/next clock'ları kapatır; internal_note/held receipt/system
ack hiçbir yanıtı karşılamaz. Her sonraki customer_reply ayrı next clock açar;
önceki bekleyen clock silinmez veya deadline ileri itilmez. RESOLVED/CLOSED response
clock'larını yanıtsız iptal eder; resolution ayrı tamamlanır. Reopen yeni cycle'dır.

Roster kaynağı bu fazda native admin tarafından yönetilen versioned manuel kayıttır:
shift calendar, explicit availability, UTC leave intervals, priority allowlist ve
expires_at. Harici HR/izin kaynağı varsayılmaz. Eksik/expired/off-shift/leave/priority
uyuşmazlığı eligible değildir. Aktif native staff + MFA enrollment + güncel view/reply
customer/department grant + membership gerekir. Assigner ayrıca scoped ticket.assign
ve fresh native MFA ister. Destek izinleri provider/finance yetkisi vermez.

Atama department row lock ile seri; round-robin eligible user UUID sırası/cursor;
least-load aynı departmandaki açık ticket'ların immutable weight snapshot toplamı,
eşitlikte user UUID. Manuel atama/unassign auto override eder; explicit auto komutu
override'ı kaldırır. Ineligible assignee cron'da çıkarılır; uygun kimse yoksa ticket
unassigned kalır. Native staff user fence'leri department'tan önce NOWAIT alınır;
busy durum API409, cron sonraki bounded çalışmaya kalır. F5-A olay hook'u aday seçmez.

## HTTP / cron / güvenlik

GET /api/v1/admin/support-work/{detail,unassigned,settings}; yalnız native current ACL.
POST prepare/execute: {action,payload,confirmation?}. action policy-publish, roster-set,
assign, auto, enroll. CSRF, native session/MFA, fresh step-up, Idempotency-Key, field
allowlist, ticket/head version ve server-bound confirmation zorunlu. Prepare sadece
nonce/review/audit; execute actor/session/version/payload/state/acceptance'ı tekrar bağlar.
Özetler policy/clock/assignment ve kalan business minutes içerir; private note/body,
başka departman veya adayın izin detayları açılmaz. Customer route açılmaz.

support:work cron: test-only, max25 ticket/20s/64MiB admission; fair checked_at sırası,
ticket FOR UPDATE ve unique clock alert/work event. Aynı job/cron tekrarları yeni
assignment veya alert yaratmaz. Bounded backlog ilerlemesi ve1/4/8 PHP process test edilir.
Yeni held event consumer exact event UUID + native outbox/job bağlarını doğrular;
SMTP/IMAP/customer notification/provider dispatch yoktur.

## Test matrisi / kabul

SUP-005: Fri17:30+120 business min→Mon10:30; holiday shift; forward/back DST;
overnight/half-day/interval overlap union; boundary microseconds; horizon limit;
ack/note gerçek reply değil; actual staff reply closes; pause allowlist/resume;
customer next clock; immutable policy/history; risk/breach duplicate yok.
SUP-006: manual/RR/weighted deterministic; native active/MFA/role/customer/department;
shift/leave/availability/expiry/priority; no eligible queue; stale409; parallel staff
andcron; atomic rollback; customer/move isolation; native revocation next request.
Gerçek MariaDB, native HTTP/cookie/CSRF/confirmation ve1/4/8 bağımsız PHP süreç.
Eski433 test isimleri korunur; sadece explicit migration beklentileri050 ile genişler.
Empty001→050,049→050, replay/no-op, checksum/drift/failed DDL; PHP8.3/8.4 CI,
PHPStan0, locked audit, lint/architecture/security scan ve mevcut HTTP stability.

## Geri dönüş / teslim

Tutarlı code/DB/key backup restore veya forward-fix.050 tabloları/drop history silinmez;
runtime rollback'te cron/route kapatılır, yeni policy snapshot'ları eskiye çevrilmez.
F5_B_DELIVERY_REPORT: gerçek SHA/CI, SUP-005/006 matrisi, demo, bounds ve ticari
belirsizlikler. Independent technical acceptance istenir ve durulur. F5-A/F4 limited
mock kabulü, DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED, PROV-005 ve bütün GATE'ler OPEN.
