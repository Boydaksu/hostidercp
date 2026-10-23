# F2 test kurulumu ve demo

Ön koşullar: F1 kurulumuna göre PHP 8.3/8.4, pdo_mysql/sodium/mbstring/fileinfo, MariaDB 10.11; kalıcı APP_KEY; yalnız `public/` webroot. Node uygulama runtime'ı değildir; CI yalnız JS sözdizimini kontrol eder. Önce [F1_SETUP](F1_SETUP.md) ve [F0_SETUP](F0_SETUP.md) izlenir. Gizli değerleri terminal çıktısına veya Git'e yazmayın.

1. İzole test DB kullanın; test paketleri yalnız `APP_ENV=test` ve `hostider_f0_test*` DB adlarını kabul eder. Gerçek müşteri DB'sinde çalıştırmayın.
2. `.env.example` alanlarını yerel ortamda yapılandırın. Testler `TEST_DB_DSN`, `TEST_DB_USER`, `TEST_DB_PASSWORD` kullanır; web uygulaması mevcut APP/DB config alanlarını kullanır.
3. `php bin/console.php migrate` ile yeni migration'ları uygulayın. 013 mevcut oturumları iptal eder; tekrar giriş gerekir. Şema ledger guard trigger'larını destekleyen DB kullanıcısıyla kurulmalıdır.
4. F1 güvenli yönetici kurulumu ve MFA ile giriş yapın. Gerekli catalog/finance/billing yetkilerini ve hedef müşteriye açık kapsam grant'ini verin; staff bayrağı tek başına müşteri erişimi sağlamaz.
5. `/admin/commerce` katalog/configuration/kasa/ledger ekranlarını açın. Testte kategori, vergi, döngü, ürün/varyant/seçenek/addon ve bağımsız fiyat oluşturun. SOURCE_FX için yalnız sentetik test kuru kullanılır; gerçek kur kaynağı kapalıdır. Fiyat yayımlama maliyet önerisinden ayrı açık onay ister.
6. `/client/commerce` üzerinde katalogdan ürünü yapılandırıp teklif alın. Onay planındaki müşteri/para/tutar/etkiyi kontrol edin, checkbox ile kesinleştirin. Değişen fiyat/bakiye veya 5 dakika onay süresi tekrar plan gerektirir; teklif 15 dakikadır.
7. Test banka topup isteği ve havale bildirimi oluşturun. Yönetici fresh step-up ve açık müşteri kapsamında bildirimi onaylasın. Bu yalnız test kaydıdır; gerçek bankaya talimat gönderilmez. Müşteri wallet ile kısmi/tam ödeme yapabilir. Tam ödeme READY_FOR_PROVISIONING veya ürün politikasına göre REQUIRES_APPROVAL olur; hizmet oluşturulmaz.
8. Fatura/proforma geçmişini ve yetkili staff PDF yükleme/owner download akışını kontrol edin. Dosya root'u `storage` altında webroot dışında kalmalı; Linux dizin 0700/dosya 0600 olmalı. Resmî belge numarası ve taraf bilgileri korunur; test PDF gerçek vergi belgesi değildir.

Tekrar doğrulama: `.github/workflows/f0.yml` içindeki bütün test komutlarını sırayla çalıştırın. Her suite kendi fixtures/migration hazırlığını yapar. Ardından `php tools/check.php`, `php tools/audit.php`, PHPStan ve `composer audit --working-dir=tools/analysis --locked` çalıştırılır. Composer yalnız analysis araçları içindir; runtime üçüncü taraf PHP bağımlılığı yoktur.

Outbox/worker mevcut console komutlarını kullanır. F2 fact receipt yerel olarak tüketilir; readiness HELD_FOR_F3 ve mail MAIL_HELD kalır. Gerçek callback veya sağlayıcı açmak demo adımı değildir. Production gate'i test için kaldırmayın. Yedek/geri dönüş ve açık sınırlar [F2_REPORT](F2_REPORT.md) içinde yer alır.
