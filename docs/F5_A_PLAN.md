# F5-A — Uygulama sözleşmesi ve doğrulama planı

Kapsam: FR-SUPPORT, FR-ID, SEC-001/002/004, OPS-001/003; SUP-003/004 çekirdek,
SUP-007. Yalnız APP_ENV=test. Native Identity değiştirilmeden kullanılır.
Karar izi: R1-09, R2-01, R2-05/06 temel politika, R3-09, R4-01/02/04/07.

Dosyalar: src/Support/{Access,State,Service,Controller,EventSink}.php, Services içinde salt
okuma sahiplik application service, AuditEvent enum eki, bootstrap route kaydı,
049_support_core.php, yeni DB/HTTP/migration testleri, CI ek adımları ve ADR-016.

Tablolar: support_departments (yönetilebilir üç başlangıç şablonu, reopen/on_hold
politikaları), support_department_members (native staff FK), support_tickets
(UUID, random HD no, immutable customer/requester/provenance, version),
support_messages (immutable type/visibility, author/session, encrypted body),
support_ticket_links (immutable customer snapshot ve service FK), support_events
(append-only audit metadata). Mesaj/içerik log veya outbox payload'ına girmez.

049 additive; 001–048 byte/hash korunur. Mesaj ve event UPDATE/DELETE trigger ile
engellenir; tenant FK ve visibility CHECK savunması bulunur. DDL implicit commit:
APPLYING kalırsa runtime açılmaz; kör replay/down yok, izole tutarlı backup restore
veya operatörün kontrollü forward-fix'i gerekir.

API: /api/v1/{client|admin}/support/{list,detail,departments} GET;
client create/reply POST; admin reply/note/state/move/department/department-new/member POST.
Tüm mutation CSRF, native session/MFA, Idempotency-Key ve field allowlist ister.
Ticket update version zorunlu; stale 409. Yönetim native fresh step-up ve
identity.staff.manage administrative izni ister. Üyelik tek başına izin vermez;
staff permission + native customer/department grant + üyelik birlikte aranır.
Client support.view kendi seçili customer hesabıyla sınırlıdır; impersonation kapalıdır.
internal_note okuma/yazma ayrıca support.ticket.internal_note gerektirir.

State allowlist ADR-016'da. Kapalı ticket için explicit reopen=false varsayılan;
ON_HOLD=false varsayılan; ticari zaman/SLA varsayımı yoktur. Requested priority
başlangıç effective priority olur, override/rule sonraki fazdır.

Idempotency native transaction'ı kapsar; replay güncel ACL'yi tekrar denetler.
Identity user/session → department → ticket kilit sırası; optimistic version ile
paralel yanıtlardan biri 409 alır, açık retry yeni version ile kaydedilir.
Public olaylar yalnız ID içeren mevcut transactional outbox'a yazılır. Internal
note yalnız private event/audit üretir; outbox/notification üretmez. EventSink yalnız
support.held.v1 yerel receipt üretir; mail handler veya notification dispatch yok.

Test: önceki 49 suite/394 test ve 001–048 hash manifesti; yeni en az 20 negatif
DB/HTTP senaryosu, iki gerçek HTTP süreç, migration empty/048 upgrade/replay/
checksum/failed DDL. PHP8.3/8.4 CI, PHPStan, locked audit, lint/pattern scan.
Kanıtlar docs/evidence/f5-a altında; başarısızlıklar PASS sayılmaz.

Geri dönüş: önce izole DB/code/key yedeği; A route kapat/kodu geri al; 049 tablolarını
silme. Yazılmış veri varsa tutarlı restore veya forward-fix; destructive down yok.
Demo: client ticket açar → staff yanıt/not → client yalnız public mesajları görür →
aynı key tek mesaj → stale version 409 → departman üyeliği iptalinde 404.
