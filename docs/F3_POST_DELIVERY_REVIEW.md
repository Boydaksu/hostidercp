# F3 son denetim — 24 Ekim 2026

Dal `codex/f3-service-engine`; denetim tabanı `4c6c98e911a40ba56940a9f07cc5ca8d5684e3f8`. Yalnız F3 test/mock kapsamı. F4, production, gerçek WHM, ödeme ve dış bildirim başlatılmadı. Bu raporun CI/commit bölümü doğrulama tamamlandığında güncellenir.

## Kök neden ve yeniden üretim

Runner her çağrıda `created_at,id LIMIT 100` ile aynı PENDING kayıtları seçiyordu. Kapasite veya kaynak ödeme kontrolünde dispatch öncesi ret transaction'ı geri alıyor ve PENDING kalıyordu. Migration 037 yalnız Scheduler hizmet kontrolünü döndürüyordu; Runner operasyon sırasını değiştirmiyordu. Worker'ın doğrudan adsız CREATE taramasında da benzer ilk-100 sınırı vardı.

Gerçek MariaDB 10.11.18/PHP 8.4.24 üzerinde 101 eski, mappingsiz/kapasitesiz PENDING ve arkalarında sağlıklı ödenmiş hizmet oluşturuldu. Beş Runner çağrısı sonunda beklenen ACTIVE yerine QUEUED görüldü: [başarısız assertion](evidence/f3/review-starvation-before.txt). İlk fixture denemesinde ayrı test anahtarlarının karışması nedeniyle ciphertext authentication hatası alınmıştı; aynı uygulama anahtarı ve izole DB ile düzeltilen fixture üzerinde yukarıdaki gerçek starvation yeniden üretildi. Fixture hatası starvation kanıtı sayılmaz.

## Düzeltme

- Additive 038 migration: `runner_checked_at`, allowlist hata için `runner_hold_code`, `(status,runner_checked_at,created_at,id)` indeks. 001–037 dosyaları değiştirilmez.
- Runner ve doğrudan Worker taraması en az yakın zamanda değerlendirilenden başlar; aday kontrol zamanı dispatch öncesi commit edilir. Bu alan lease veya işlem başarısı değildir. Geçici kapasite hatası işi silmez, başka generation oluşturmaz veya terminale taşımaz. Sonraki cron sıradan devam eder.
- Staff görünümünde son kontrol ve blokaj kodu bulunur; müşteri allowlist'i korunur. Kaynak ödeme değişimi güncel claim kontrolünde hâlâ reddedilir.
- `services:work` varsayılan 100 işlem/20 saniye kabul bütçeli batch olur; `--once` korunur. Daha düşük sınırlar açık seçeneklerle seçilir. Bellek rezervi ve 2 saniyelik CLI InnoDB lock wait yeni iş kabulünü sınırlar. Süre her aday öncesi kontrol edilir; in-flight çağrıya hard deadline iddiası yoktur.
- Mevcut operation/service claim ve lease/fence, transaction dışı provider etkisi ve UNKNOWN mutasyonunu yeniden göndermeme kuralları korunur. Overlap çalışan iki CLI desteklenir; Scheduler named lock'ı farklı amaçla korunur.

## Regresyonlar ve demo

`tests/services-runner.php` bağımsız PHP CLI süreçleri ve kendi izole gerçek MariaDB şemasıyla beş senaryo ekler:

1. 101 bloke kaydın arkasındaki sağlıklı iş işler; 101 kayıt PENDING ve güvenli hata koduyla görünür kalır. Kapasite eklendikten sonra gerçek CLI çağrıları aynı 101 operasyonu tamamlar; her hizmet için bir fiziksel mock hesap oluşur.
2. Ödeme kaynağı değişmiş PENDING görünür kalır; timeout-after-create UNKNOWN üç CLI çağrısında ikinci create almaz. Arkadaki sağlıklı iş tamamlanır.
3. İki eşzamanlı CLI, ayrı 5 işlem sınırıyla yarışır; sonraki cron kalan işi tamamlar. 12 operasyon için 12 STARTED attempt, 12 create çağrısı ve 12 fiziksel hesap doğrulanır.
4. Süresi geçmiş kabul bütçesi claim yapmaz; geçersiz limitler ve açık transaction ret edilir.
5. 16 kaynak için doğrudan Worker ve gerçek `bin/console.php services:work` süreleri ayrı ölçülür; CLI açılışı ve schema readiness maliyeti CLI ölçümüne dahildir, fixture hazırlığı hariçtir.

[İlk düzeltme sonrası aynı assertion](evidence/f3/review-starvation-after.txt), [genişletilmiş test sonucu](evidence/f3/review-runner-after.txt), [tam suite](evidence/f3/review-all-tests.txt), [ham ölçüm](evidence/f3/review-throughput.json). Otomatik demo komutu `php tests/services-runner.php`; yalnız `APP_ENV=test` ve `hostider_f0_test*` DSN kabul eder, geçici test şemasını kapatır. Production verisine veya sağlayıcıya erişmez.

Gereksinim izi: PROV-001/002 → UNKNOWN/no-retry ve reconciliation paketleri; PROV-003/004 → engine/model; PROV-006 → lifecycle; SVC-001 → transfer; SVC-002 → lifecycle yarışları; SVC-003 → package; SEC-004 → gerçek HTTP/IDOR/confirmation/replay; OPS-005 → eski claim/lease testleri ve yeni overlap/cron devam testi. Önceki test dosyaları değiştirilmez.

## CI, ölçüm ve kalan riskler

Yerel PHP 8.4.24 / MariaDB 10.11.18 tam suite **237/237**: önceki 232 ve yeni 5 test geçti. PHPStan, lint/mimari kontrol, Composer locked audit ve güvenlik örüntü taraması başarılı: [kontroller](evidence/f3/review-checks.txt). Eski testler ve 001–037 için taban karşılaştırması sıfır değişikliktir. PHP 8.3/8.4 CI SHA/URL sonuçları tamamlanınca kesinleştirilir. Önceki yeşil CI yeni kodun kanıtı olarak kullanılmaz.

| Ölçüm, 16 kaynak | Süre | İşlem/sn |
|---|---|---|
| Doğrudan Worker, tek PHP süreç | 306,84 ms | 52,14 |
| Gerçek services:work CLI, bir çağrı | 671,04 ms | 23,84 |

Fixture süreç peak memory: 4 MiB; bu değer ayrı CLI çocuğunun peak bellek ölçümü değildir. İşlem sayısı/tekillik DB'den ayrıca doğrulanır; sayısal throughput assertion'ı kullanılmaz.

Yerel ölçüm son ham JSON'a göre raporlanır. Örneklem küçük ve ağsız mock'tur; gerçek hosting CPU, bellek, DB contention, provider rate limit ve timeout'ları ölçülmedi. Bir dakikada sayısal üretim kapasitesi veya sonlu gecikme garantisi verilmez. Sürekli geliş hızı işleme kapasitesini aşarsa backlog büyür. Her cron'un yeni işe kabul süresi sınırlıdır; in-flight provider/DB çağrısını güvenli biçimde zorla kesmek vaat edilmez. Gerçek ortam sınırları G-O01/G-C01'de açıktır.

## Migration, geri dönüş ve kabul sınırı

Migration 038 yalnız operasyon metadata/index ekler; immutable iş/finans kayıtları veya 001–037 geçmişi değişmez. Boş DB migrate/replay ve eski suite'ler doğrulanır. Dağıtımdan önce worker durdurma, tutarlı kod+DB+APP_KEY+korumalı dosya yedeği gerekir. Otomatik destructive down yok; doğrulanmış snapshot restore veya forward-fix uygulanır. Yalnız eski kodu geri almak starvation'ı geri getirir. Üretim backup/restore provası yapılmış sayılmaz.

Main salt okunur denetimde hâlâ `ada37dc52b324e34380ba836ae7794cebb174ec4`, `protected=false`. İlk merge öncesinde repository sahibi PR incelemesi ve zorunlu **verify (8.3)** / **verify (8.4)** kurallarını etkinleştirmelidir. Repository ayarları değiştirilmedi; merge yapılmadı.

G-C01 (gerçek WHM ACL/endpoint/TLS/timeout/inspect), G-O01 (gerçek hosting cron/runtime/deployment/restore), G-T01 (canlı transfer politikası) açık. Ayrıca G-V01..04, G-P01..02, G-R01, G-D01, G-X01..02, G-O02..04 açık; [tam kapı listesi](14_OPEN_VALIDATIONS.md) değişmez. Gerçek WHM staging kabulü yapılmadı. HELD bildirim gerçek SMTP teslimi değildir; F4/F5/F6 ve V1 release blocker'ları tamamlanmış sayılmaz.

Nihai altı kabul kararı yeni CI sonucu alındığında aşağıda kesinleştirilir. F4 kodlaması kullanıcı kabulüne kadar başlamaz.
