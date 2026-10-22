# F0 kurulum ve çalışma

Bu sürüm çalışan altyapı çekirdeğidir; ticari otomasyon veya üretim sürümü değildir. Tam giriş, TOTP enrollment/recovery, kullanıcı tabloları ve paneller F1 kapsamındadır. Tek public route `GET /api/v1/health`; commerce/callback endpointleri kapalıdır.

## Gereksinimler ve dosya yapısı
64 bit PHP 8.3+, MariaDB 10.11+, PDO MySQL, sodium, JSON, mbstring, session; HTTP testleri için curl. Dağıtımda Composer/Node/Redis/Docker/SSH zorunlu değil. CI'daki MariaDB container yalnız test altyapısıdır.

```text
bootstrap/             autoload ve uygulama bileşimi
src/Core/              HTTP, configuration, DB, queue, security
src/Providers/Contract/ sağlayıcıdan bağımsız dahili sözleşmeler
src/Providers/Mock/     beş sağlayıcı için ağsız senaryolar
migrations/            yalnız F0 altyapı şeması
public/                tek document root
bin/                   kısa süreli CLI komutları
tests/                 unit, mock contract, session, MariaDB, HTTP
tools/                 lint ve mimari kuralları
docs/adr/              mimari kararlar
storage/               Git dışında yerel çalışma verisi
references/            değiştirilmeyen kullanıcı SDK arşivi
```

## Ortam
`.env.example` gerekli alanları belgeler; uygulama `.env` dosyasını kendiliğinden okumaz. Hosting paneli/FPM veya webroot dışındaki korumalı yapılandırma ile ortam değişkenlerini aktar. Değerleri loglama veya Git'e ekleme. Test/staging/production DB, dosya, anahtar ve cookie hostları ayrı olmalı; örnek değerler erişim bilgisi değildir.

Anahtar üretimi: `php bin/console.php key:generate`. Çıktıyı yalnız gizli yapılandırmaya koy. APP_KEY base64 kodlu 32 byte, libsodium anahtarıdır; key ID `v1`, ciphertext context binding vardır. Anahtar rotasyonunda eski anahtarları koru, sürümlü decrypt/encrypt migration'ını ayrıca planla; otomatik keyring rotasyonu F0'da yok.

Document root **yalnız `public/`** olmalı; HTTPS zorunlu. Apache rewrite için `public/.htaccess` mevcut; sunucu izinlerini doğrula. Kaynak, storage, SQL, SDK arşivi ve anahtarlar webden servis edilemez. PHP display_errors kapalı; uygulama hata logunda yalnız event/trace/sınıf bulunur. PHP-FPM/Apache erişim loglarını da URL/query, cookie ve authorization/PII yazmayacak şekilde işletmeci yapılandırır.

## Migration ve cron
1. Yetkileri sınırlı ayrı DB oluştur. DDL yetkisi yalnız kontrollü deployment hesabında; uygulama hesabına gerekli DML izinlerini ver. Audit tablosu için UPDATE/DELETE yetkisi verme.
2. Doğrulanmış yedek al; cron'u durdur, bakım erişimini sunucu düzeyinde sınırla. F0'da installer/update/backup UI yok.
3. `php bin/console.php migrate`
4. `php bin/console.php health:check`
5. Her dakika önce `php bin/console.php outbox:dispatch`, ardından `php bin/console.php queue:work --once`. Dispatcher en çok 100 olay/20 saniye, worker en çok bir iş işler. Handler süresi lease'den kısa olmalıdır; mevcut tek handler `core.probe` ağsızdır. Gerçek handler eklenmeden timeout/heartbeat politikası ayrıca doğrulanmalı.

Mutasyon uygulama servisleri `Idempotency::run` içinde yalnız yerel DB yazımı ve Outbox::append yapar. Provider/network dispatch bu callback içinde yapılamaz. Çağrı kapsamına HTTP method+route ve doğrulanmış principal bağlanır. F0'da mutation route yoktur. Yanıt önbelleğine sır veya müşteri PII konmaz; idempotency retention keyfi silinmez. Finans ve provider operasyon referansları sonraki fazlarda ayrıca kalıcı unique kayıt tutar.

Queue `retry_safe` varsayılan false. Bu bayrak yalnız uygulama içi allowlist servis tarafından yerel/idempotent işlerde true olur; müşteri payload'ından alınmaz. Expired unsafe lease/exception `UNKNOWN`; manuel reconciliation gerekir. `FAILED` dead-letter'dır. F0'da otomatik UNKNOWN→PENDING komutu yok. SQL ile kör tekrar kuyruğa alma yasak. Fencing token yerel sonucu korur; sağlayıcının dış yan etkisini durdurduğu varsayılmaz.

## Test komutları
```text
php tools/check.php
php tests/run.php
php tests/session.php
php tests/integration.php
php tests/http.php
```

DB/HTTP testleri `APP_ENV=test`, `TEST_DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=hostider_f0_test;charset=utf8mb4`, `TEST_DB_USER`, `TEST_DB_PASSWORD` ister. DB önceden oluşturulur; testler yalnız `hostider_f0_test*` ismine izin verir ve bu DB'nin F0 tablolarındaki verileri siler. Production bilgileri bu test ortamına aktarılmaz. HTTP testi yalnız loopback 33318 portunu kullanır; staging config ile çalışır, canlı adapter yoktur. Unit/session testleri DB istemez. CI PHP 8.3/8.4 ve MariaDB 10.11 matrisi sağlar; CI sonucu ayrıca kontrol edilir.

## Demo ve geri dönüş
Demo: migrate → test suite → health:check; integration senaryosunda yerel iş+outbox rollback'i, dispatcher dedupe, iki PHP sürecinin aynı job'a yarışması ve unsafe timeout'un UNKNOWN kalması gözlemlenir. Test çıktısı her doğrulamayı adlandırır; gerçek müşteri/sağlayıcı verisi kullanılmaz.

DDL MariaDB'de implicit commit yapar. Migration APPLYING kalırsa uygulamayı/cron'u açma. Şemayı ve DDL kanıtını incele; doğrulanmış snapshot restore veya yeni forward-fix migration uygula. Şemayı incelemeden APPLYING→APPLIED değiştirme. Otomatik down/DROP yok; kod ve DB birlikte önceki doğrulanmış sürüme alınır. Sonradan oluşmuş işler varsa restore öncesi mutabakat gerekir. İlk boş test DB'sini silip yeniden oluşturmak yalnız izolasyonu doğrulanmış test ortamında uygundur.
