# F2 uygulama planı — 22 Ekim 2026

Başlangıç: yerel ve uzak main `776f6d32c522629ebd630212593ce6b712ae4315`; doğrulanmış uygulama `37c01f95cef5f798e41fd3d8847db72af4a74afc`. Kullanıcının F2 kickoff ve devam talimatı geçerlidir. F3 kapsam dışıdır. Durum: IN PROGRESS; alt fazların kabulü test kanıtından sonra yazılır.

## Sıralı kapsam ve dosyalar

| Alt faz | Gereksinim / karar | Uygulama ve dosyalar | Doğrulama |
|---|---|---|---|
| A | FR-PAY/BILL, R3-04/05, R4-08, R7-04; FIN-004/006/007 | `src/Finance/`: kesin değer nesneleri, ledger, wallet, tahsilat/dağıtım/iade/FX/mutabakat repository ve servisleri; migration 006 | string JSON/DB roundtrip, dengeli append-only fişler, negatif bakiye/duplicate/refund limit, ayrı PHP süreçleri |
| B | FR-CATALOG, R3-01/06, R5-01..04/11 | `src/Catalog/`, ayrı expand migration: kategori/ürün/varyant/seçenek/addon/döngü/vergi/fiyat yayını/promosyon/mapping | yayın sürümleri, kaynak maliyet ayrımı, eşzamanlı promosyon limiti |
| C | FR-CATALOG/CHECKOUT, R3-02/03/06, R7-01..03; FIN-005/010 | `src/Pricing/`: FX policy, resolver, tax/promotion, quote, immutable snapshot, renewal policy | sentetik USDTRY, bağımsız TRY önceliği, expiry sınırı, vergi/yuvarlama, fail-closed FX |
| D | FR-CHECKOUT, R3-05/07, R7-02; FIN-001/003 | `src/Orders/`, `src/Billing/`, ayrı migration: order/payment request yaşam döngüleri | kısmi/karma ödeme, sabit belge, tek hazırlık olayı, transaction rollback |
| E | FR-PAY, R1-01, R3-04/05, R7-04; FIN-001/002/004/007 | Finance application services, kapalı PayTR sınırı, test-only doğrulanmış fixture | duplicate/mismatch/late/wrong-order/fake bildirim; gerçek callback hiçbir ortamda açılmaz |
| F | FR-BILL, R1-02/09; FIN-006/008 | `src/Billing/`: proforma, webroot dışı PDF, cash/expense/recurring/report | sahiplik, MIME/hash/path, gelir vs nakit vs yükümlülük, snapshot geçmişi |
| G | FR-ID/CATALOG/CHECKOUT/PAY/BILL, R1-07, R4-04/05/10; SEC-002/004 | mevcut paneli genişleten controller/view/assets, Identity izinleri; yan etkisiz plan ve atomik onay | gerçek HTTP, müşteri izolasyonu, impersonation red, stale/used confirmation, tarayıcı demo |

## Migration ve geri dönüş

001–005 değişmez. Yeni modüller additive apply-once/checksum migration kullanır; eski Identity/Queue/Outbox/Idempotency/Migration yeniden yazılmaz. Finans hareketleri UPDATE/DELETE ile değiştirilmez; telafi yeni ters kayıttır. Modüller birbirlerinin tablolarına yazmaz; application service aynı transaction'a katılır. Ağ transaction içinde çağrılmaz. Savepoint ile gerçek komut finansal preview olarak çalıştırılmaz.

Üretimde uygulanmadan önce dosya+DB+APP_KEY yedeği ve restore doğrulaması, cron/trafik durdurma, migration/readiness kontrolü gerekir. Destructive down yok. Geri dönüş doğrulanmış snapshot ve kodun birlikte restore edilmesi veya forward-fix'tir. Yeni tahsilat varsa snapshot öncesi mutabakat şarttır.

## Test ve kanıt

Önceki 94 test korunur; PHP 8.3/8.4 MariaDB 10.11 CI, PHPStan level 5, lint/mimari, Composer audit ve sır taraması. Her alt faz anlamlı commit ve gerçek kanıtla kaydedilir. Yeni suites CI'a eklenir. Test atlanması PASS sayılmaz. F2 raporu migration, rollback, başarısızlık/sınır, demo ve kabul matrisini içerir; tüm A–G ve 15 regresyon tamamlanmadan F2 DONE yazılmaz.

G-P01/P02, G-X01/X02 ve diğer tüm canlı GATE'ler OPEN kalır. Sentetik veri canlı ticari yeterlilik değildir. Gerçek PayTR/kur/sağlayıcı çağrısı, gerçek müşteriden tahsilat, gerçek provisioning ve production kullanımı etkinleştirilmez.
