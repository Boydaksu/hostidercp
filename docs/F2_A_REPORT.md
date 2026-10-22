# F2-A finans çekirdeği — çalışma raporu

22 Ekim 2026. F2 devam ediyor; A–G tamamlanmış değildir. FR-PAY/BILL, R3-04/05, R4-08, R7-04; FIN-004/006/007 altyapısı. Plan: F2_PLAN; karar: ADR-005.

Bağımlılıksız exact Decimal/Currency/Money/Rounding, string minor tutarlar; immutable dengeli journal/line; tek para birimli wallet, hesap kilidi altında hareketlerden türetilen bakiye; verified test collection, disjoint unallocated/allocated/wallet/refund funds; orijinal FX fişi; kaynak harcamaya bağlı ve limitli yeni telafi kredisi. Journal + audit + outbox aynı caller-owned transaction'da. Önceki altyapı yeniden yazılmadı.

Migration 006 finans tablolarını, 007 wallet kredi bağlantısını ve immutable UPDATE/DELETE trigger'larını ekler. 001–005 değişmedi. Mutable wallet balance alanı yok. Tablo sahibi yalnız Finance repository/service'tir. Kimlik/currency sahibi Identity entegrasyonu ve HTTP mutation sınırı G alt fazında doğrulanacak; bu servisler doğrudan public endpoint değildir.

Yerel PHP 8.4.24 / MariaDB 10.11.18: önceki 94 test başarılı; yeni 4 exact aritmetik ve 10 gerçek MariaDB testi başarılı. Ayrı PHP süreçlerinde paralel wallet spend/refund/duplicate collection; rollback, scope/impersonation red, storage/JSON roundtrip, mixed allocation, immutable FX, kredi üst sınırı. Son kanıt dosyaları evidence/f2 altındadır. PHPStan level 5, lint/mimari ve pattern taraması başarılı; Composer locked audit advisory bulmadı. PHP 8.3 sonucu uzak CI doğrulanınca kaydedilir; yerel başarıdan türetilmez.

Sınırlar: Collection şu anda yalnız APP_ENV=test factory'sidir; BANK workflow ve PayTR contract E'de. Gerçek PayTR callback, FX kaynağı, iade gönderimi veya provisioning yok. Original refund yalnız unallocated aynı-para tahsilatını refund_payable'a rezerv eder; gerçek para iade edildi anlamına gelmez. Tahsil edilmiş sipariş iadesi ürün politikası ve Billing düzeltmesiyle E/F'te bağlanacak. FX original refund otomatik yapılmaz; reconciliation gerektirir. Bu aşama muhasebe mevzuatı kabulü değildir; bütün GATE'ler OPEN.

Geri dönüş: trafik/cron durdur, doğrulanmış kod+DB+APP_KEY snapshot birlikte restore veya forward-fix. Yeni finans hareketleri silinmez. Testler immutable finans satırlarını temizlemez; yalnız izole hostider_f0_test* veritabanı kullanır. Runtime DDL/TRIGGER yetkisi kaldırılmalıdır. Üretimde migration ve geri yükleme provası yapılmadı.

Demo: izole test DB'de finance-unit.php ve finance.php çalıştır; 1000 TRY test tahsilatını 400 TRY wallet + 600 TRY allocation olarak izle; paralel 800 TRY harcamada tek başarılı sonuç ve 200 TRY bakiye; orijinal hareket değişmeden kredi fişi. Kullanıcı arayüzü demosu G'de yapılacak.
