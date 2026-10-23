# F3-E devam kaydı — tamamlanmadı

029 politika/onay + operation-kind genişletme; 030 renewal/preference/held notice; 031 immutable cancellation wait; 032 encrypted immutable action evidence. 001–028 değiştirilmedi.

Uygulanan: nullable global/category/product/service politika mirası (kritik karar eksikse kapalı), scoped fresh manual approval, yeni F2 quote/order/bill ile renewable period, explicit wallet opt-in ve tek debit, ödeme sonrası ayrı provider uzatma/inspect, suspend/unsuspend, period sonu + explicit wait cancellation, iptal geri alma ve deadline+explicit safeguard sonrası terminate. Unknown/lease expiry kör retry yapmaz. Provider hatası finans geçmişini geri yazmaz. Optional HostingRenewal yalnız sentetik mock; gerçek WHM renewal endpoint'i iddia edilmez.

Yerel 6/6 lifecycle MariaDB testi ve PHPStan başarılı. Timestamp testi ilk koşuda MariaDB prepared string parametresini kabul etmedi; yalnız izole test oturumunda doğrulanmış integer SET timestamp literal ile düzeltildi. İşletim sistemi saati değiştirilmedi. Eski C/D regresyonları ayrıca tekrar kontrol edilir.

İkinci dilim: canonical Runner CREATE/RENEW/SUSPEND/UNSUSPEND/TERMINATE dispatch, doğrulanmış local outbox consumer, frozen request seçimine bağlı pool mapping; bounded grace scheduler ve DB advisory overlap lock. Scheduler yalnız HELD notice ve policy-authorized suspend job yazar, provider çağırmaz. Paid fakat provider uzatması bekleyen renewal kör suspend edilmez. Dört bağımsız gerçek worker tek fiziksel create/tek verified resource üretti. Hosting 7/7, lifecycle 8/8; PHPStan başarılı. 80a1ff1 üzerindeki tam yerel paket 203/203 (180 eski + 23 yeni); sonraki dört test ayrıca geçti, tam güncel toplam finalde tekrar ölçülecek.

Üçüncü dilim: 033 transfer/current commercial price ve yeni service izinleri. Identity resource scope kaydı ve reassignment Identity API'sine aittir; Services diğer modülün tablosuna yazmaz. Gerçek Identity kullanıcılarıyla sender+recipient fresh onay, token HMAC/expiry, service version/policy HMAC, revoked sender, explicit debt scope ve old-finance izolasyonu 3/3 testte doğrulandı. Yeni owner commercial price kopyası alır, tarihsel period/bill/order/ledger owner'ı değişmez; auto-wallet kapatılır. Farklı hedef para birimi inceleme ister. ADR-009 test politikasını canlı G-T01 kararından ayırır. 033 izin güncellemesi mevcut oturumları revoke eder; yeniden giriş gerekir.

Eksik işler: karmaşık protected-price/ONCE ayrıştırması ve required-notice delivery proof (şimdilik fail-closed); paket değişikliği/proration; renewal/action UNKNOWN operator resolution genişlemesi; imported senaryoların tam kabulü; F3-F HTTP/UI/confirmation/idempotency; gerçek hacim performansı ve final CI/kabul raporu. Bunlar bitmeden F3-E veya F3 bütünü DONE değildir. F4 ve canlı GATE'ler kapalıdır.

Geri dönüş tutarlı DB+kod+APP_KEY snapshot veya forward-fix. Güncel migration sayıları ilk plan tahminlerinden ayrıştı; final raporda gerçek liste esas alınacak.
