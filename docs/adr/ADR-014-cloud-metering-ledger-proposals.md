# ADR-014 — Cloud metering, mevcut Ledger ve manual fiyat önerisi

Kullanıcının F4-E talimatı (24 Ekim 2026) F4-D kabulünü verir. 30 günlük prepaid müşteri satışı,
provider ondemand maliyeti, tahsilat ve belge ayrı gerçeklerdir. F4-E yalnız test/mock.
Ledger'a provider_payable hesabı eklenir; provider_cost debit/payable credit accrual mevcut
Finance uygulama servisiyle tekil ve dengeli yapılır; nakit ödeme veya tahsilat değildir.
FX yalnız raporlama dönüşümüdür, exchange settlement/journal değildir. Provider tax recoverability
kanıtsız olduğundan sentetik politika gross expense'tir; net/tax ayrı snapshot saklanır.

cloud_effects.observed_at billing boundary değildir. Provider usage/task/resource/account,
sequence, explicit interval ve immutable authorized MOCK tariff gerekir. Quantum/minimum per
interval, precision/tax/addon/FX açık policy'dir; gerçek Voyar sözleşmesi sayılmaz. UNKNOWN eksik
kanıtken maliyet üretilmez; bağımsız kanıt varsa STOP/termination/refund maliyeti gizlemez.
Invoice eşliği cost'u tekrar post etmez; final usage/invoice yoksa cost kapanışı varsayılmaz.

Yerel cost hesap/ledger transaction'ı resource lock ile seri, work SKIP LOCKED ve fairness ile
bounded çalışır. Dış I/O yok; crash rollback güvenlidir. Provider mutation fence'leri değişmez.
Mevcut catalog_cost_proposals kullanılır; auto_publish_allowed false. Ayrı native admin approval,
Commerce state-bound confirmation ve publication gerekir; mock öneri otomatik canlı fiyat olamaz.
Bu faz yeni ticari fiyat veya gerçek provider maliyeti belirlemez. GATE'ler ve PROV-005 açık kalır.

Fiyat önerisi için marj parametresi açıkça verilmelidir; ticari varsayılan marj atanmaz.
Testlerdeki %20 yalnız açık sentetik fixture değeridir. CLI --margin-rate gerektirir;
kanonik teklif snapshot’ına yazılır ve yönetici onayı/yayını yerine geçmez.

Tek seferlik fee, saatlik addon ve vergi ayrı bileşendir. Fee içeren maliyetin marj önerisi
ONE_OFF_FEES_REQUIRE_REVIEW kalır; tekrarlayan katalog fiyatına sessiz yayılmaz. Vergi her
bileşende HALF_UP, net kullanım policy rounding ile hesaplanır; gerçek vergi sözleşmesi değildir.
Provisional invoice eşliği final kullanım/fatura bağı değildir. Final kanıt ayrıca eşleştirilir;
invoice match bir nakit settlement veya payable borcunun sıfırlanması sayılmaz.

İlk Linux CI cron yarışında child failure görüldü. Cost/proposal local transaction'ları
sonraki tek transaction için READ COMMITTED kullanır; F3/F4-D session isolation değişmez.
Instance lock ve unique/idempotency sınırları korunur. Yalnız Database.transaction'ın
rollback ettiği 1205/1213 için en fazla üç local deneme vardır. Network/unknown commit
veya provider POST bu mekanizmayla tekrarlanmaz. Child testleri hata olsa da tüm süreçleri
drainer; güvenli driver code ve retry sayaçları ham kanıtta tutulur.

EXPLAIN karşı-örneği: status IN iki değer + global checked_at sırası Using filesort üretir.
FOR UPDATE LIMIT 1 aday taramasını tek satıra indirmiyordu; iki cron'dan biri boş dönebiliyordu.
047'de türetilmiş admissible=TRUE equality-prefix ve sıralı FORCE INDEX ile filesort kaldırılır;
proposal için de status/proposal_checked_at sırası indekslenir. Finans kaydı veya 046 değişmez.

25 Ekim son sınır: hourly engine immutable VM spec type=ondemand ister; prepaid provider VM
onaylı mock tarife olsa bile REVIEW olur, cost/journal üretmez. Bu müşteri 30 DAYS prepaid
satışından farklıdır. Eski cost okumalarında da mode kontrolü vardır; migration değişmez.
