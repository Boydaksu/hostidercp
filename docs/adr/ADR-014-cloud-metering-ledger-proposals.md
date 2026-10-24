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
