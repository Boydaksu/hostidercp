# 10 — Güvenlik, kurulum, yedekleme, güncelleme, izleme

## Çalışma ortamları
- **Production:** gerçek sağlayıcı erişimi, gerçek para, gerçek e-posta.
- **Staging:** ayrı DB, dosya, cache, token key, session secret, gönderici alan adı. Test provider credentials, PayTR test mode; dış bildirimler test alıcıları dışında kapalı. Canlı veri kopyası kullanılırsa PII maskeleme.
- Her ortama `APP_ENV`, `PROVISIONING_ALLOWED`, `PAYMENT_LIVE_ALLOWED`, `EXTERNAL_MAIL_ALLOWED` fail-closed policy; yalnız APP_ENV set etmek yeterli değil.
- Config web kökü dışı; tüm dosya/anahtar path permission kontrolü, document root yalnız `public/`.

## Kurulum
1. Host check: PHP version/ext (pdo_mysql,curl,json,openssl,sodium,mbstring,xml,zip; opsiyonel intl/imap), MariaDB, write permissions, cron, HTTPS.
2. DB bağlantısı/test ve least-privileged kullanıcı; doğru charset/collation.
3. APP secret ve encryption key güvenli üretim; ilk admin TOTP zorunlu; config immutable.
4. Schema migration, seed başlangıç departman/roller/para birimi; installer disable/lock.
5. SMTP/cron/provider sandbox smoke test; bütün credential'lar maskeli.

## Güncelleme
- Versiyonlu release ZIP + hash + imza (güvenilir signing key/webroot dışı), yetkili admin step-up auth.
- `preflight`: PHP/DB/ext/available disk, DB schema version, eksik paket, worker drain, bakım modu.
- Yedek oluşturma + tamamlandığını **doğrulama**; mümkünse farklı volume/kopya.
- Atomik symlink/rename yeni kod yayını; DB migration expand/contract; bağımlı worker ve cron kilitlenir.
- Post-deploy test: auth, API, queue, PayTR webhook route, provider read-only, mail SMTP mock, backups, admin dashboard.
- Başarısız migration/uyumlulukta otomatik rollback vaat edilmez; `restore plan` ve point-in-time recovery gerekir.

## Yedek politika
- Gecelik şifreli tam DB + uploads/support ekleri + resmi faturalar + konfigürasyon manifest (secrets yedeklemenin yöntemine bağlı), veritabanı ve dosya tutarlı snapshot.
- Saatlik DB yedeği veya MariaDB binlog/PITR **yalnız hosting izinleri ve binlog erişimi doğrulandığında**. Gecelik tam yedek varsa en fazla 24 saate yakın kayıp riski teknik olarak azaltılmalı.
- Yerel + kullanıcı seçimli S3/Google Drive vb.; sıkıştırma sonra authenticated encryption, checksum, sürüm, storage object id, retention labels.
- 14 günlük, 8 haftalık, 12 aylık plan; günlük/haftalık/aylık kopyalar arasında referans bağlantıları ve depolama kotası kontrolü.
- Yedek şifreleme anahtarı sadece aynı disk üstünde kalmamalı; anahtar yitirilirse backup işe yaramaz.
- Periyodik şifre bütünlük doğrulaması + izole restore (veritabanı+ekler+anahtar erişimi+uygulama açılması). Test sonuçlarını audit'e yaz.
- RPO/RTO gerçek provayla ölçülür, iş sahibince kabul edilmeden rakamsal taahhüt verilmez.

## İzleme ve alarmlar
- Cron heartbeat son 2+ planlı çalışma kaçırdıysa alarm; exact threshold yapılandırılabilir.
- Sırada bekleyen job ve oldest age, dead-letter, provider `UNKNOWN`, fiyat hesaplama FX stale, WHM health, Voyar wallet/limit risk, registrar düşük bakiye, PayTR callback gecikmesi.
- Günlük backup yaşı ve son restore test sonucu; diskte kapasite ve DB backup failure.
- Panel, e-posta, harici webhook; alarmlar dedupe ve cooldown ile; webhook imzalı/secret; güvenli payload.

## Güvenlik kontrolleri
- Content Security Policy, session rotation, CSRF, brute-force throttling, TOTP replay prevention, recovery codes hashed and single-use.
- Kişisel bilgiler için veri erişim/işlem gizlilik tasarımı; KVKK ve saklama yükümlülükleri ayrıca hukuki inceleme ister.
- API anahtarları public prefix + hashed secret, scopes/expiry/rotate/revoke, IP bound opsiyon, son kullanım audit.
- Provider credentials encryption ve key rotation, gizli dosya indirmeleri signed/bounded, XSS+CSRF+SSRF savunması.
- Kötüye kullanım için bulk ops ve fiyat değiştirme ayrı audit+step-up+rate limit.

## Ortam güvencesi
- 1 dk cron olanağı doğrulandı; **gerçek uygulama timeout, CPU, memory, disk quota** ve döngülerin üst üste binmesi staging load-testinde kontrol edilir.
- Paylaşımlı hosting PHP/cron yetersiz çıkarsa hedef uygulama tasarımı değiştirilmeden harici queue runner veya VPS kullanılabilir; V1'in başlangıç şartı değil.
# F3 test/mock işletim eki

`php bin/console.php services:discover`, `services:work`, `services:expire`, `services:schedule` aynı test DB ve APP_KEY ile çalışır. Canlı effect gate'leri kapalı kalır. Canonical operation kayıtları işin kaynağıdır; UNKNOWN mutation yeniden gönderilmez. Cron overlap named lock ile reddedilir. Keşif ve scheduler sınırlı batch kullanır; yoğunlukta aynı kayıtları tekrar seçmemek için kontrol zamanı tutulur.

023–037 migration ve geri dönüş etkileri, gerçek test/performans kanıtı ve açık hosting/JIT kapıları için `F3_REPORT.md` okunmalıdır. Bu komutlar production cron veya restore kabulü anlamına gelmez.

## F3 son denetim: hizmet worker cron'u

Migration 038 sonrası test/mock ortamında dakikada bir `php bin/console.php services:work --max-operations=100 --max-seconds=20` çalıştırılabilir. Seçenekler verilmezse aynı sınırlı batch kullanılır. `--once` eski tek başarılı operasyon/inceleme davranışını korur; yoğun kuyruk için dakikada tek `--once` yeterli throughput sağlamaz. `services:discover`, `services:expire` ve `services:schedule` ayrı sınırlı görevlerdir; worker yeni siparişi keşfetmez veya süresi dolmuş lease'i tekrar create'e açmaz.

Batch en çok 100 tamamlanan dispatch/inspection ve 1–20 saniye yeni işe kabul bütçesi kullanır. Her tarama en çok 100 adaydır; her adaydan önce süre kontrol edilir. 64 MiB kullanımında veya PHP memory_limit altında 8 MiB rezerv kalmadığında yeni tarama başlatılmaz. CLI InnoDB lock wait 2 saniyedir. Bunlar çalışan provider/DB çağrısını zorla kesen bir hard timeout değildir; bir çağrının tamamlanması bütçeyi aşabilir. Gerçek WHM transport kapalıdır. Canlı adapter açılmadan bağlantı/toplam istek/inspect timeout'ları, DB sorgu süreleri ve CLI dış timeout payı G-C01/G-O01 altında ölçülmelidir. PHP memory_limit ve hosting CPU/duvar saati sınırları açıkça yapılandırılmalı; süreç zorla kesilirse RUNNING lease `services:expire` ile UNKNOWN olur, otomatik create tekrarı yapılmaz.

Operasyonlar `runner_checked_at,created_at,id` ile döner. Kontrol zamanı claim öncesinde kalıcılaşır; süreç kesilmesi işi silmez. Bloke işlem PENDING kalır; `runner_hold_code` ve son kontrol zamanı yalnız staff hizmet ayrıntısındaki operations listesinde görünür. Geçici kapasite geri geldiğinde aynı operation yeniden değerlendirilir. Eski/yeni sağlıklı işlerin ilerlemesi düzenli cron, sonlu/işlenebilir kuyruk ve yeterli kapasiteye bağlıdır; aşırı giriş hızında gecikme garantisi verilmez. Bir taramada yalnız bloke işler görülürse batch sonlanır, sonraki cron kalıcı sıradan devam eder.

Worker overlap toptan reddedilmez: iki CLI aynı anda çalışabilir; kalıcı operation claim, lease_token, service pointer, server kapasite kilidi ve immutable attempt/resource tekilliği provider etkisini fence eder. Scheduler/discovery kendi named lock davranışını korur. Provider etkileri kısa claim transaction'ından sonra yapılır. UNKNOWN aday seçimine girmez; yalnız mevcut kanıtlı reconciliation yolu kullanılır. Eski PENDING yaşı, hold code dağılımı, UNKNOWN/RUNNING lease yaşı ve cron çıkış kodları izlenmelidir; UNKNOWN veya finans durumu değişmiş kayıtlar insan incelemesi ister.

Gerçek CLI ile doğrudan Worker ölçümleri ayrı olarak `F3_POST_DELIVERY_REVIEW.md` içinde verilir. Yerel mock hızı production kapasite taahhüdü değildir. Şema rollback'i otomatik DROP değildir; cron durdurulup tutarlı kod/DB/APP_KEY yedeği restore edilir veya forward-fix yapılır. 001–037 korunur.
