# ADR-013 — Cloud containment: güncel service state ve fiziksel UNKNOWN ayrımı

24 Ekim 2026; son hedefli bağımsız denetim talimatı. ADR-012'nin finansal snapshot kullanımı
bu kararla daraltılır. financial_context immutable tarihsel audittir; güncel yetkilendirme değildir.

Financial containment sırasında Cloud operation/VM UNKNOWN olabilir. Bu durum müşteri hizmetinin
bağımsız SUSPENDED/cancellation durumunu ezmez. Mevcut doğrulanmış VM'nin ücretli müşteri hizmeti
ACTIVE kalabilir; bu, VM'nin power state'inin bilindiği veya maliyetinin durduğu anlamına gelmez.

Başarılı task/resource mutabakatında Engine canonical order/bill/refund/cancel/entitlement'ı tekrar
kilitli transaction'da doğrular. Service status FOR UPDATE okunur. SUSPENDED, CANCEL_SCHEDULED,
TERMINATING ve TERMINATED bağımsız engelleri korunur. Geçersiz finans veya eski UNKNOWN için
ACTIVE üretilmez; SUSPENDED uygulanır. Güncel ACTIVE + geçerli kaynak ACTIVE kalır. Legacy UNKNOWN
sahibi için snapshot'tan otomatik geri dönüş yoktur; ayrı yetkili değerlendirme gerekir.
Doğrulanmış fiziksel TERMINATE mevcut proof gate'leriyle TERMINATED olur; finans geçmişi değişmez.

P1: Cloud receipt başarılıyken PENDING/WAITING_TASK, Domain receipt başarılıyken PENDING olabilir.
Bağımsız cron bu durable işi aynı claim/fence/source kontrolleriyle sürdürür. Domain'in mevcut
UNKNOWN/MANUAL_REVIEW attention davranışı korunur; sağlıklı PENDING bununla karıştırılmaz.
Yeni migration yok; 001–045 korunur. Mock dışında provider erişimi ve F4-E/F/F5 açılmaz.
