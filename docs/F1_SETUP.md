# F1 kurulum, bootstrap ve demo

F0_SETUP ve F0_1_OPERATIONS geçerlidir. Document root yalnız `public/`; PHP 8.3+ 64-bit, PDO MySQL, sodium, mbstring, Argon2id desteği ve MariaDB 10.11 gerekir. Canlı GATE'ler OPEN; dış mail/ödeme/provisioning flag'leri false kalır. Runtime Composer bağımlılığı yoktur; `tools/analysis/vendor` dağıtıma konulmaz.

## Kurulum ve ilk yönetici

1. Trafiği/cron'u durdurun, dosya+DB yedeği alın ve restore prosedürünü doğrulayın. F0 ortam değişkenlerini process/hosting secret store üzerinden yükleyin; APP_KEY rastgele 32 byte base64 olup kaynak depoya veya web root'a yazılmaz. Testteki geçici key production'da kullanılmaz. DB UTC'dir; sunucu saati NTP ile doğru tutulmalıdır.
2. `php bin/console.php migrate` çalıştırın. 001/002 değişmez; 003 Identity tablolarını ve rol şablonlarını oluşturur. MariaDB DDL rollback değildir; yarım migration APPLYING durumunda fail-closed kalır.
3. İlk yöneticinin `{ "email": "...", "name": "...", "password": "..." }` JSON girdisini web root dışında, yalnız operatörün okuyabildiği geçici bir dosyada hazırlayın. Parola rastgele, benzersiz ve en az 12 karakter olmalı. Shell geçmişine/parola argümanına yazmayın.

POSIX örneği (dosya izinleri 0600, gerçek parola örneğe dahil edilmez):

```sh
php bin/identity.php bootstrap < /private/initial-admin.json
```

PowerShell'de stdin yönlendirmesi için güvenli process stdin veya özel dosyayı okuyan pipeline kullanılabilir; dosyayı konsola yazdırmayın. Çıktı yalnız admin UUID ve MFA zorunluluğunu içerir. Başarılı işlemden sonra özel girdiyi güvenli şekilde kaldırın. Bootstrap DB kilidi altında tek kullanımlıktır; web bootstrap endpoint'i yalnız CSRF başlatır, yönetici oluşturmaz.

4. HTTPS üzerinden `/admin` açın, oluşturduğunuz hesapla giriş yapın. İlk girişte parola ile TOTP kurulumu başlatılır. Gizli anahtarı authenticator'a girin, yeni kodla doğrulayın, sekiz tek kullanımlık recovery kodunu güvenli saklayın. İlk TOTP kodu ikinci kez kullanılamaz; aynı 30 saniyede tekrar giriş/step-up için yeni kodu bekleyin veya recovery kodu kullanın.
5. İlk yönetici global yönetim izinlerine sahiptir; müşterilerin tümüne otomatik veri erişimi yoktur. Roller ekranından gereken işlem için açık müşteri/hizmet/departman grant'i verin. Kendi rolünü kaldırma ve kendi staff statüsünü değiştirme engellenir. Hesap güvenlik politikası, parola veya yetki değişimi ilgili oturumları iptal edebilir; yeniden giriş yapın.

`/client` ayrı cookie/CSRF ve DB context kullanır. Hesap kaydı e-posta doğrulaması gerektirir. Doğrulama/reset/davet bildirimi HELD kalır; canlı mail açılmadığı için gerçek müşteriye self-service teslim akışı henüz açılmamalıdır. HTTP üzerinden token veya mail gövdesi listeleme endpoint'i yoktur.

## Güvenli yapay demo

Demo yalnız `APP_ENV=test` ve **tam olarak** `dbname=hostider_f0_test_demo` kabul eder. Gerçek DB adını değiştirerek demo çalıştırmayın. DB/ortam yalnız yapay veriye ayrılır, APP_KEY bu demo süresince sabit tutulur. Önce bu boş DB'yi oluşturup ortamı ona bağlayın:

```sh
php bin/console.php migrate
php bin/identity-demo.php
```

Komut normal bootstrap ve registration servislerini kullanır; iki `example.invalid` hesap ve rastgele 40 karakter parolalar üretir. Müşteri doğrulaması yalnız bu izole demo içinde tüketilir. Yönetici MFA'sı panelde kurulmak zorundadır. Sabit kullanıcı parolası veya seed yönetici yoktur. Zaten kullanıcı bulunan DB'de veya mevcut çıktı dosyası varken çalışmaz. Dış mail/ödeme/provisioning yapılmaz.

Kimlik bilgileri yalnız ignored `storage/identity-demo.json` dosyasına exclusive create ile yazılır; stdout/log'da gösterilmez. POSIX 0600 umask uygulanır. Windows'ta çalışma dizini ACL'sini yalnız operatöre sınırlandırın; umask ACL yerine geçmez. Dosyayı source control'a eklemeyin, demo sonrası silin. PHP preview/test router'ı production'a taşımayın; gerçek web sunucusu+TLS kullanın.

Demo kabul adımları:

1. `/admin` → gerçek giriş → zorunlu TOTP enrollment → recovery kodlarını bir defa alın.
2. Users/customer/roles/staff/security screens → gerçek DB kayıtlarını görüntüleyin. Müşteri listesi açık grant verilene kadar boş kalabilir.
3. `/client` → demo müşteriyle giriş → hesap bilgisi ve aktif oturumları görün. Güvenlik ekranında yeniden doğrulayın, sonra hesap adını değiştirin.
4. TOTP kurulumu, ikinci tarayıcı oturumu iptali, tek kullanımlık API key, rol/grant ve alt kullanıcı daveti akışlarını HTTP/integration testleriyle doğrulayın. Mail kutusu yoktur; token'ları normal panelde açığa çıkarmayın.
5. Test DB ve özel demo dosyasını kaldırın; production kullanıcıları veya ayarlarıyla birleştirmeyin.

## Test komutları

`APP_ENV=test`, `TEST_DB_DSN` (izole `hostider_f0_test` öneki), `TEST_DB_USER`, `TEST_DB_PASSWORD` tanımlayın. Testler bu DB'deki kayıtları temizler; production DSN kabul edilmez.

```sh
composer install --working-dir=tools/analysis --no-interaction --prefer-dist --no-plugins --no-scripts
composer audit --working-dir=tools/analysis --locked
php tools/analysis/vendor/phpstan/phpstan/phpstan.phar analyse --no-progress --memory-limit=512M
php tools/check.php
php tools/audit.php
php tests/run.php
php tests/hardening-unit.php
php tests/session.php
php tests/session-concurrency.php
php tests/session-http.php
php tests/integration.php
php tests/identity.php
php tests/identity-http.php
php tests/http.php
node --check public/assets/identity.js
```

Node yalnız geliştirici syntax kontrolü içindir; runtime/worker veya panel build zorunluluğu yoktur. HTTP testleri loopback PHP sunucusunu açıp kapatır; test router yalnız test ortamında HTTPS simülasyonu yapar. Gerçek tarayıcı görsel incelemesi 1280×900 ve 390×844 boyutlarında yapılmıştır.

## Geri dönüş

Trafiği/cron'u durdurun. Kod ile DB+APP_KEY snapshot'ını birlikte restore edin veya forward-fix yapın. Otomatik down/drop yoktur; Identity verisi varken eski uygulama koduna yalnız kod rollback'i güvenli kabul edilmez. Secret key kaybı TOTP/notification/idempotency şifrelerini kurtarılamaz kılar. Anahtar yedeği DB yedeğinden ayrı, şifreli tutulur; key rotation runbook'u üretim öncesi hazırlanmalıdır.
