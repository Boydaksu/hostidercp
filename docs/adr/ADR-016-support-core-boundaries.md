# ADR-016 — F5-A destek gizliliği ve state sınırı

25 Ekim 2026. D4/D5 ve kullanıcı F5-A talimatının teknik normalleştirmesi.
Native customer support.view mevcut hesap rolünü korur; personelde yeni granular
support.ticket.* permissions gerekir. Departman üyeliği native staff'a FK ile
bağlanır; native grant ve role yerine geçmez. Hiçbir super-admin bypass yoktur.
Departman/üyelik yönetimi existing identity.staff.manage + administrative + fresh
MFA ister. Destek permission'ları başka modül mutation'larına taşınmaz.

Requester geçmiş bağı user FK'sidir; aktif membership oluşturma/okuma sırasında
native Identity ile denetlenir. Membership'e kalıcı FK koymak müşteri erişimi
iptalini engellerdi. Native staff kaydı kaldırılınca yalnız support department
üyeliği CASCADE kaldırılır; mesajlar ve actor audit'i kullanıcı kimliğiyle korunur.
Bu sayede native staff ve müşteri yetki iptalleri yeni şema tarafından engellenmez.

Kapalı yeniden açma ve ON_HOLD explicit departman policy bool'larıdır; başlangıçta
false. NEW→WAITING_STAFF/WAITING_CUSTOMER/RESOLVED/CLOSED;
WAITING_STAFF→WAITING_CUSTOMER/RESOLVED/CLOSED;
WAITING_CUSTOMER→WAITING_STAFF/RESOLVED/CLOSED;
RESOLVED→WAITING_STAFF/CLOSED; ON_HOLD→WAITING_STAFF/WAITING_CUSTOMER/RESOLVED/CLOSED.
Aktif NEW/WAITING_* için ON_HOLD yalnız policy=true; CLOSED→WAITING_STAFF yalnız
reopen=true. Public reply hedefi customer için WAITING_STAFF, staff için
WAITING_CUSTOMER; kapalı veya resolved reply önce aynı allowlist/policy ile geçer.
Internal note state'i değiştirmez, version artırır. Custom status/merge/split yoktur.

Ticket customer/requester değişmez. Service link güncel Services application
service sahiplik kontrolüyle kurulur; transfer ticket müşteri sahipliğini değiştirmez.
Eski müşterinin ticket'ındaki link yeni service sahibinin özel verisini göstermez:
projection'da güncel sahipliği uyuşmayan link gizlenir. Departman taşıma iki tarafta
view/manage + üyelik + native scope ister ve version/audit ile atomiktir.

Mesajlar encrypted plain text'tir. JSON plain text sunar; HTML tüketicisi textContent
kullanmalıdır; F5-A HTML renderer/upload/export/search endpoint'i açmaz. Liste
filtreleri yetkili ticket özetiyle sınırlıdır, internal note araması yoktur.
DB CHECK internal_note customer_visible=false sabitler; private notun outbox'ı yoktur.
Public outbox yalnız ID olaylarıdır; mail/SLA consumer sonraki yetkili faza kalır.
İleriki consumer güncel ACL ve server-side mesaj türünü tekrar doğrulamalıdır.

F5-A test/mock dışı kapalıdır; yeni ticari politika veya canlı kabul belirlemez.
