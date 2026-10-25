# ADR-015 — Native Identity ve kalıcı Domain/Cloud HTTP sınırı

25 Ekim 2026; kullanıcının F4-F talimatı. R1-07, R4-01/02/04/05/07/10,
R5-04/07; SEC-001–004, FIN-010, DOMAIN-001/003, PROV-001/002/006, OPS-001/005.

Yeni kimlik/finans/queue/dispatch motoru kurulmaz. ProviderPanel mevcut Identity cookie,
scope ve customer/service resource registry'sini kullanır. HTML de authentication ister;
API veya sayfa varlığı mutation yetkisi değildir. ProviderPanel impersonation okuması dahil
fail-closed'dur. Dar cloud.power müşteri sahibine yalnız satın alınmış mevcut VM üzerinde
güç, aynı OS reinstall ve beklemeli terminate hakkı verir; services.manage/CREATE/UPGRADE,
finans veya staff reconciliation yetkisi vermez. Native staff services.manage davranışı kalır.

HTTP prepare/execute actor/session/identity-version/customer/service/payload ve güncel canonical
order/bill/refund/service/instance/operation state'ini bağlayan kısa ömürlü onay kullanır.
Mevcut Idempotency sonucu encrypted saklar; replay'de güncel native yetki tekrar kontrol edilir.
Cloud prepare/enqueue/resolve ve Domain resolve yalnız yerel composition için mevcut açık
transaction'a katılabilir; claim/dispatch/inspect/worker sınırları değişmez. Cloud'un mevcut
resource/task/lease/fence ve native confirmation'ı HTTP confirmation'ının altında korunur.
Terminate onay TTL'i policy waiting + 300 saniyedir; waiting uzun diye erken sona ermez.

Provider inspection HTTP'de yapılmaz. Yeni panel_inspections yalnız istek/lease/evidence pointer
metadata'sıdır; mevcut queue/outbox handler'ı ve bağımsız panel:inspect cron bunu işler.
Native Identity, scope ve state worker'da tekrar denetlenir; expired lease REVIEW kalır,
mutation açmaz. Receipt, inspection sonucu ve fiziksel task completion ayrı gerçeklerdir.
Containment native application service'in mevcut dış-resource inspect ve ayrı iki transaction
güvencesini kullanır; HTTP genel transaction/idempotency wrapper'ına alınmaz. Tek kullanımlık
native confirmation duplicate execute'u reddeder. Yalnız STOP/TERMINATE finans istisnası vardır.

FIN-010 maliyet ekranı native cost doğrulamasını kullanır; read model explicit allowlist'tir.
Öneri onayı ve Commerce-confirmed yayın ayrı POST aksiyonlarıdır. Müşteri fiyatı sağlayıcı
maliyeti değildir. UNVERIFIED sıfır, MATCHED_MOCK nakit ödeme veya borcun kapanması değildir.
Raw provider response/contact/EPP/root/VNC/session/credential genel projection'a alınmaz.

048 additive şema ve izin ekler; toplu identity-version değişimi veya session revoke yapmaz.
İlk taslakta bulunan global session revoke otomatik onay denetiminde reddedildi ve kaldırıldı.
Güncel Identity izinleri her request'te DB'den okunur; eski oturumlar cloud.power için de
native customer ownership ve permission kontrolünden geçer. GATE'ler OPEN; DOMAIN-002 PARTIAL.
