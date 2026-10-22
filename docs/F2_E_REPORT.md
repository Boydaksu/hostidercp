# F2-E tahsilat iş akışları — çalışma raporu

FR-PAY, R1-01, R3-04/05, R7-04; FIN-001/002/003/007 temel senaryoları. Migration 011 ayrı payment attempts, banka bildirimleri ve immutable test callback receipts ekler. Attempt/notification finansal hareket değildir. Personel onayı + scoped finance.approve + güncel step-up olmadan bankadan tahsilat veya wallet credit olmaz. Üretim finans release gate açıkken banka kabulü kapalıdır.

APP_ENV=test için Hostider'a özgü sentetik fixture protokolü HMAC ile doğrulanır; **PayTR imza sözleşmesi değildir**. Tam attempt/customer/minor/currency bağlamı, status ve kalıcı external reference fingerprint kontrol edilir. Duplicate fixture aynı sonucu döndürür; farklı tutar/para/müşteri/attempt/sahte imza para oluşturmaz. Failure ödeme yaratmaz. İptal edilmiş veya ödenmiş siparişe geç gelen doğrulanmış tahsilat unallocated kalır; reconciliation kaydı oluşur, sipariş yeniden açılmaz. Wallet topup yalnız doğrulanmış tahsilattan aktarılır.

PaytrBoundary createSession/callback/refund her ortamda kapalıdır; G-P01/P02 OPEN. Gerçek alan/hash/currency davranışı uydurulmadı. BANK ve TEST tahsilatlar ayrı yöntemdir. PaymentService kilit sırası order/request→wallet→attempt/notice→collection; finansal yazı ve audit aynı transaction'da. Orijinal yöntem iadesi Finance'de yalnız refund_payable talebidir; dış iade gönderimi yok. Wallet kredisi kaynak debit'e bağlı yeni hareket ve limit kontrolüdür.

6 MariaDB testi: bekleyen topup/bank notification ve yetkili onay/replay, üç duplicate fixture→tek collection/readiness, bad signature/amount/currency/customer/attempt, failure, late cancellation reconciliation, staging/production fixture red ve kapalı PayTR sınırı. Gerçek HTTP callback reddi G'de eklenecek. Banka ekstresi/dekontu ve merchant yeteneğinin canlı teyidi yapılmadı. Geçerli fixture testte bile web public endpoint'i olarak sunulmayacak.

Geri dönüş: doğrulanmış kod+DB+APP_KEY snapshot birlikte veya forward-fix. Önceki migration'lar değişmez; 011 destructive down içermez. Finansı geri almak eski hareketi silmez; politikaya bağlı yeni telafi kaydı gerekir. Bütün GATE'ler OPEN; gerçek ekonomik işlem yapılmadı.
