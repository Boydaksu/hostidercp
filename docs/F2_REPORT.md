# F2 teslim raporu — 23 Ekim 2026

F2-A–G uygulaması test ortamında tamamlandı. Kullanıcı kabulü beklenir; F3 başlatılmadı. Production kullanımı, gerçek ödeme ve sağlayıcı işlemleri kapalıdır. Açık GATE durumları değiştirilmedi. Bu rapor canlı finans veya resmî muhasebe uygunluğu beyanı değildir.

## Kapsam ve commit izi

| Faz | Özellik kimlikleri / kapsam | Kod commit |
|---|---|---|
| A | FR-PAY/BILL; FIN-004/006/007; kesin Money/Decimal, immutable ledger, wallet, tahsilat/dağıtım/iade | ffc33db |
| B | FR-CATALOG; R3-01/06, R5-01..04/11; kategori, ürün/varyant/seçenek/addon, fiyat/maliyet/promosyon | 11dc916 |
| C | FR-CATALOG/CHECKOUT; FIN-005/010; FX, vergi, promosyon, 15 dakika teklif, renewal policy | 14a6963 |
| D | FR-CHECKOUT; FIN-001/003; sabit proforma, kısmi/karma ödeme, ayrı durumlar ve tek readiness olayı | 81fbf8b |
| E | FR-PAY; FIN-001/002/004/007; test tahsilatı, havale onayı, topup, kapalı PayTR sınırı | f615214 |
| F | FR-BILL; FIN-006/008; korumalı harici PDF, kasa/gider/recurring, ayrı finans raporu | de431c7 |
| G | FR-ID/CATALOG/CHECKOUT/PAY/BILL; SEC-002/004; çalışan paneller, saf plan ve atomik onay, izolasyon | f7b1df7 |

G kapanışında tam kapsam için bağımsız addon, takvim/variant döngüsü, kategori vergi mirası, ödenmiş iade, original-currency kasa kanıtı, hesap/cüzdan para birimi dönüşümü ve yerel fact tüketimi de tamamlandı. Mevcut Identity/Queue/Outbox/Idempotency altyapısı korundu. Koordinasyon application service üzerinden yapılır; transaction açıkken dış API çağrılmaz. Mimari gerekçe: [ADR-006](adr/ADR-006-f2-pure-confirmations.md).

## Test kanıtı

Yerel PHP 8.4.24 + MariaDB 10.11: **164 test, 0 hata**. Önceki 94 test dosyası değiştirilmedi; F2 yeni 70 test ekler. Tam çıktı: [test kanıtı](evidence/f2/f2-g-all-tests.txt). Testler gerçek izole MariaDB, bağımsız PHP süreçleri ve yerel HTTP sunucuları kullanır; skip PASS sayılmaz.

| Paket | Test |
|---|---:|
| Önceki F0–F1.1 | 94 |
| finance-unit / finance | 4 / 10 |
| catalog / pricing / addons | 7 / 9 / 3 |
| orders / payments / billing-cash | 6 / 6 / 4 |
| paid-refunds / commerce / commerce-http | 3 / 5 / 8 |
| currency-change / commerce-events | 2 / 3 |

PHPStan level 5 hatasız; 162 PHP dosyası mimari/lint kontrolünde, 168 kaynak/config/test dosyası sır taramasında bulgusuz; Composer audit advisory yok; JS sözdizimi başarılı. [Kontrol çıktısı](evidence/f2/f2-g-checks.txt). Pattern taraması tam güvenlik denetimi değildir. A–G fazlarının PHP 8.3 ve 8.4 GitHub CI sonuçları başarılıdır; faz bazında `evidence/f2/f2-*-ci.json` dosyalarına kaydedildi. Son kod `f7b1df77c4f6fa000a1e3e71bbfb1d9f9a0afe13`: [Actions 39961837770](https://github.com/Boydaksu/hostidercp/actions/runs/39961837770), her iki job completed/success; 164 test, PHPStan, Composer audit ve kaynak kontrolleri geçti. [Makine kanıtı](evidence/f2/f2-g-ci.json).

## Zorunlu 15 finansal regresyon

| # | Senaryo | Gerçek doğrulama |
|---|---|---|
| 1 | Tekrarlanan bildirim tek tahsilat | payments duplicate fixture; finance paralel dış referans |
| 2 | Paralel wallet harcaması negatif olamaz | finance bağımsız PHP süreçleri, DB kilidi |
| 3 | Kısmi ödeme doğru kapanır | orders kısmi state ve kalan tutar |
| 4 | Wallet + dış ödeme sabit talebe bağlanır | orders karma funding; commerce-http banka→wallet→order |
| 5 | Kur değişimi eski belgeyi değiştirmez | orders/pricing; currency-change tarihsel paid bill |
| 6 | 15 dakika teklif süresi | pricing sınır ve expired submit |
| 7 | Paralel promosyon limiti | catalog bağımsız süreçler, kilitli redemption |
| 8 | İade tahsilat limitini aşamaz | finance ve paid-refunds paralel talep/rollback |
| 9 | Tek ödeme tek hazır olayı | orders paralel funding; payments replay; commerce-events hiçbir provider işi yok |
| 10 | Impersonation mali işlem yapamaz | finance/orders/commerce servis retleri; bütün Commerce HTTP finans erişimleri kapalı |
| 11 | Müşteriler arası izolasyon | orders ve commerce-http fatura/sipariş/PDF/customer override retleri |
| 12 | Kesilen transaction tutarlı kalır | orders funding rollback; paid-refunds credit/wallet/reserve rollback; confirmation atomikliği |
| 13 | Sahte veya uyuşmayan callback para oluşturmaz | payments imza/tutar/currency/customer/attempt testleri; commerce-http gerçek PayTR ret |
| 14 | Eksik/güvenilmez/eskimiş kur fail-closed | pricing sentetik FX ve bağımsız fiyat önceliği |
| 15 | Kasa/gelir/wallet mükerrer sayılmaz | billing-cash rapor ayrımı; finance source cash + target clearing; wallet hareketleri |

## Demo ve kabul

Sentetik müşteriyle gerçek tarayıcı akışı: katalog→yapılandırma→teklif→ayrıntılı açık onay→sipariş→wallet ödeme onayı→READY_FOR_PROVISIONING. Sipariş 155,88 TRY ile kapandı, 300,00 TRY test bakiyesinden 144,12 TRY kaldı. Hizmet kurulmadı. Masaüstü [teklif](evidence/f2/client-confirmation.jpg), [ödeme onayı](evidence/f2/client-payment-confirmation.jpg), [hazır sipariş](evidence/f2/client-order-ready.jpg) ve [mobil cüzdan](evidence/f2/client-wallet-mobile.jpg) kanıtları kayıtlıdır. Mobil görünümde sayfa yatay taşması görülmedi. Yönetici iş akışları ve izinleri gerçek HTTP testleriyle doğrulandı; bütün yönetici formlarının manuel tarayıcı kabulü iddia edilmez.

Kabul kontrolü: çalışan katalog ve dövizli fiyat; teklif/sipariş; sabit ödeme talebi; doğrulanmış test tahsilatı; wallet/kısmi/karma ödeme; proforma/PDF geçmişi; yönetici ledger/kasa; sağlayıcıdan bağımsız hazırlık durumu test edildi. Çalışan uygulama teslimidir; yalnız SQL/sözleşme taslağı değildir. Tekrar demo ve kurulum için [F2_SETUP](F2_SETUP.md).

## Migration ve geri dönüş

Eski 001–005 dosyaları değişmedi; sıralı geliştirmede uygulanmış önceki migration dosyaları yeniden yazılmadı. Yeni migration'lar:

| No | Eklenen yapı |
|---|---|
| 006–007 | Money/ledger/wallet/payment/allocation/refund/FX ve DB guard |
| 008 | Ticari katalog, sürümlü fiyat ve promosyon |
| 009 | FX, immutable quote ve fiyat snapshot |
| 010 | Order, proforma ve ayrı durum/event |
| 011 | Attempt, bank notice, test receipt |
| 012 | Korumalı PDF metadata, kasa/gider/recurring |
| 013 | Commerce onayları, granular permission; eski oturumları iptal eder |
| 014–016 | Paid refund/credit note, standalone addon, source journal/şifreli kanıt |
| 017–019 | Tek wallet dönüşümü/DB guard, tarihsel FX iadesi, held fact receipt |

Destructive down yok. Trafik/cron durdurularak kod+DB+APP_KEY+korumalı PDF dosyalarının birlikte doğrulanmış yedeği alınmalı. Geri dönüş tutarlı snapshot restore veya forward-fix'tir; yeni finans hareketleri varsa önce mutabakat gerekir. Eski para kayıtları silinmez, telafi yeni kayıtla yapılır. Bu çalışma production backup/restore provası yapmadı; operasyon kapıları kapanmadı.

## Sınırlar ve sonraki bağımlılıklar

- G-P01/P02: gerçek PayTR create/callback/refund kapalı; test HMAC protokolü PayTR sözleşmesi değildir. Dış iade talebi yalnız refund_payable rezervidir, gönderilmiş iade değildir.
- G-X01: kur kaynağı doğrulanmadı; sentetik FX yalnız testte çalışır. Test tazelik eşiği 3600 saniye ticari kaynak onayı yerine geçmez.
- G-X02: proforma resmî fatura değildir. PDF harici düzenlenmiş belgedir; e-belge üretimi yok. Gelir/kasa raporu iç finans raporudur.
- İade ürünün açık staff_approval politikasıyla çalışır; none reddedilir. Hizmet süresine bağlı prorated iade F3 servis verisi/politikası olmadan açılmaz.
- RenewalPricePolicy hesaplama ve bildirim kararını üretir; gerçek hizmet yenileme scheduler'ı F3 bağımlılığıdır. Bildirimler held kalır; gerçek mail teslimi iddia edilmez.
- PDF kontrolü tam sanitization/antivirus değildir; webroot dışı depolama, özel POSIX izinleri, sahiplik, checksum ve attachment sandbox uygulanır. Windows dağıtımında dizin ACL operatör sorumluluğudur. Terk edilmiş dosya retention kararı G-O04'e bağlıdır.
- Production Commerce mutation gate kapalıdır. Canlı ekonomik işlem, gerçek müşteri tahsilatı veya domain/sunucu işlemi yapılmadı. F3 provider handler yoktur; readiness fact HELD_FOR_F3 kalır.
- Conservative confirmation kilitleri ve sayfalama başlangıç ölçeğindedir; yüksek hacim yük testi ve production operasyon kabulü yapılmadı.

F2 sonrasında kullanıcı onayı beklenir. F3'e otomatik geçilmez.
