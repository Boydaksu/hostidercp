# F6-G-Q1 Identity throttle incelemesi

8 Kasım 2026. İnceleme tabanı `4922bcf8060a6d5009359e9d83bb66eb98904631`, tree `3e1516da3c35aef95c2c7b9e69fb58682a3a6fd8`.

## Tarihsel hata ve kontrollü karşı örnek

`hostider-f6g-a0739457e950-83` koşusunda Identity 15 PASS / 1 FAIL, `hostider-f6g-0e77f261f28e-83` koşusunda 16 PASS / 0 FAIL. İkisi aynı `d4ee5cd5fdd3eaa52c017200ebe109408b69972547dfffbb74b867158e9c4054` kaynak manifestine bağlı. İlk log F1-B bootstrap/login throttling testini gösteriyor. Assertion mesajı ayıklandığı için tarihsel assertion ve tetikleyici geri çıkarılamıyor. **ROOT_CAUSE_UNVERIFIED.**

Eski test, farklı ağ anahtarlarından aynı hesaba 10 başarısız girişin ardından 11. denemede HTTP 429 bekliyor. İzole MariaDB oturumunda ilk 10 çağrı pencerenin son saniyesine ve 11. çağrı sonraki pencerenin ilk saniyesine yerleştirildiğinde, `raises(HttpError::class, ...)` içindeki “Expected exception” assertion'ı yeniden üretildi. Normal kimlik doğrulama reddi döndü. Bu, sınırda eski test beklentisinin bozulabileceğini kanıtlıyor. Tarihsel koşunun o sınırı geçtiğini kanıtlayan zaman damgası yok. Mevcut Identity testi değiştirilmedi.

## Kanıtlanan ayrı yarış ve düzeltme

Servis login'i önce ağ bucket'ını 30, ardından normalize hesap bucket'ını 10 deneme ile sınırlar. `IdentityService::throttle` ikisine de açıkça 300 saniye verir. 60 saniye genel limiter varsayılanıdır. HTTP controller kendi 300 saniyelik ağ ve action/hesap kontrollerini dış idempotency transaction'ı öncesinde yapar. Servis mevcut transaction içindeki çağrıda bu kontrolleri tekrar uygulamaz.

Limiter HMAC bucket anahtarı, upsert ve `SELECT ... FOR UPDATE` ile sayacı transaction içinde günceller. Önceki kod DB zamanını bucket kilidinden önce örnekliyordu. Testte A eski pencere zamanını okuduktan sonra bucket upsert öncesinde durduruldu. B sonraki pencerede 10 hakkı tüketti. A bırakılınca eski pencereyi 1 hit ile geri yazdı ve bir ek güncel isteğe izin açtı. MariaDB'deki bu sıra, başlangıç kaynaklarında `CROSS_WINDOW_RESET_REPRODUCED` ile başarısız oldu. Test yalnız sıralamayı ve oturum zamanını kontrol eder. Sayaçlar ve kilitler gerçek MariaDB'dedir.

Düzeltme, provisional bucket satırını aynı transaction içinde oluşturup kilitler ve pencereyi kilit alındıktan sonra DB zamanından hesaplar. Aynı yarışta geciken istek reddedildi, yeni pencerenin 10 hit'i korundu ve sonraki istek de reddedildi. Ağ/hesap limitleri, 300 saniye, HMAC, işlem sınırları ve HTTP davranışı korunur. Yedi test ayrıca 60 saniye sınırının etkisizliğini, tam 300 saniye sıfırlamasını, ayrı bucket davranışını, rollback'i, iç içe transaction reddini ve aynı pencerede son hakkın dört yarışmacıdan yalnız birine verilmesini doğrular.

## Doğrulama ve kapsam

PHP 8.3.36 ve 8.4.27, her biri 94 PASS / 0 FAIL. Dokuz suite yürütmesi içinde mevcut Identity suite'i iki kez (16/0 + 16/0), yeni suite 7/0. Identity güvenliği, HTTP, session, session concurrency, hardening ve DB entegrasyonu dahil. Testler ayrı ağda yerel Docker, geçici MariaDB ve non-root PHP ile yürütüldü. Geçmişteki 83 ilk inceleme koşusundaki iki yeni test hatası, HttpError mesajı yerine sabit errorCode alanı doğrulanarak giderildi ve kayıtta tutuldu. Düzeltme öncesi yarış hatası ve iki son koşu [results.json](results.json) içinde. Eski Identity tekrarlarında zaman sabitlemesi uygulanmadı. GitHub Actions başlatılmadı.

549 dosyalık [kaynak manifesti](source-manifest.json) SHA-256 `e0e6deaf32df2204f736873bee8ca584f1eaf15de37f8a1bfe91923d2dd6eb25`. F6-G manifestindeki 548 dosyadan yalnız Core RateLimiter değişti. Yeni kaynak dosyası Q1 testidir. Migration 001–057 tree `01b51a655655cb4c7483d6b5937555504264ca3f`, eski kanıtlar ve kullanıcının yerel inceleme düzenlemesi korundu. Şema değişikliği yok. Geri dönüş bu commit'in ayrı yetkiyle geri alınmasıdır ve belgelenen yarış riskini yeniden açar.

Tarihsel F1-B nedeni açık kaldığı için genel Identity throttle kararlılığı UNVERIFIED. Kanıtlanan pencere yarışı bu kapsamda düzeltildi. Gerçek hosting uyumluluğu NOT VERIFIED. Production BLOCKED.
