# F0 teslim raporu — 22 Ekim 2026

## Sonuç ve kapsam
Güncel kickoff A–E doğrultusunda gerçek PHP/MariaDB çekirdeği ve test altyapısı oluşturuldu. F1 başlatılmadı. Üretim kullanımı ve tüm V1 özelliklerinin kabulü anlamına gelmez; canlı GATE'ler OPEN. F0 mimari/schema incelemesi kullanıcıya sunulmuştur, kullanıcı kabulü otomatik varsayılmaz.

Teslim: güvenli bootstrap/config/DI/router/middleware, JSON envelope, PDO transaction, checksum/lock/dirty migration, outbox→queue aktarımı, consumer inbox, idempotency, kısa worker/lease/fencing/retry/dead-letter/UNKNOWN, session/CSRF/ACL/step-up sözleşmeleri, rate limiter, AEAD, audit, validation, staging politikası ve 5 mock sağlayıcı. Detaylı dosya yapısı ve komutlar F0_SETUP.md'de; özellik kimlikleri F0_TRACEABILITY.md'de.

## Gerçek test kanıtı
Ortam: Windows, PHP **8.4.24**, MariaDB **10.11.18**, PDO native prepares/InnoDB. İzole localhost DB, gerçek sağlayıcı veya müşteri yok. Test servisleri yalnız görev için açıldı. İlk sandbox yerel socket bağlantısını engelledi; izole loopback testleri izinli çalıştırmada tekrarlandı ve geçti. Bu ilk hatalar test başarısı olarak sayılmadı.

| Doğrulama | Sonuç | Ham kanıt |
|---|---|---|
| Unit + 5 provider mock contract senaryosu | 19 test / 0 hata | evidence/unit-contract.txt |
| Session cookie/rotation/expiry | 1 test / 0 hata | evidence/session.txt |
| Gerçek MariaDB integration/migration/concurrency | 18 test / 0 hata | evidence/mariadb-integration.txt |
| Gerçek loopback HTTP/API bootstrap/rate/readiness | 3 test / 0 hata | evidence/http.txt |
| PHP lint + token/mimari statik kontroller | 53 dosya / 0 ihlal | evidence/lint-architecture.txt |
| Sır örüntüsü ve bağımlılık taraması | 55 dosya / 0 bulgu, runtime third-party PHP paketi 0 | evidence/dependencies-secrets.txt |
| CLI migrate/dispatch/worker/health | gerçek çalıştırma başarılı | evidence/console-smoke.txt |

Toplam **41 test**, 0 başarısız. Provider contract testleri her mock action üzerinde SUCCESS/DEFINITIVE_FAILURE/UNKNOWN/UNSUPPORTED, replay ve çelişkili fingerprint davranışını sınar. Şema required/additionalProperties/status/date sözleşmesi kontrol edilir; tam JSON Schema engine kullanılmaz. İki process ile yarış testleri gerçek ayrı PDO bağlantıları kullanır. Gerçek bozuk DDL sonrası kalıcı APPLYING, runner'ın ve HTTP readiness'nin engellenmesi sınandı.

İlk uzak CI [39764325135](https://github.com/Boydaksu/hostidercp/actions/runs/39764325135) PHP 8.3 ve 8.4'te başarılı; bu `d516acab6c62ed26fb445aa7088563794f556037` revizyonunun 36 testini doğrular. Son kod revizyonu `8683bea9189052000622038bc4e160504994e262` için [39765145112](https://github.com/Boydaksu/hostidercp/actions/runs/39765145112) de SUCCESS: partial-DDL/readiness/HTTP dahil 41 test workflow'a dahildir. Ardından yalnız teslim dokümantasyonu güncellenmiştir.

## Kararlar ve migration/rollback
ADR-001: son kullanıcı kickoff'u çekirdek işleri F0'a alır; Composer'sız runtime, hafif modüler monolit, yerel transaction/outbox, unsafe işte tekrar yasağı, MariaDB DDL dirty marker. Ticari iş kuralı değiştirilmedi.

Migration `001_f0_infrastructure.php`: outbox_events, jobs, job_attempts, dead_letters, idempotency_keys, inbox_events, audit_logs, rate_limits; runner schema_migrations'ı sahiplenir. Blueprint doğrudan uygulanmadı. Finans/kimlik/ticket tabloları ileri fazlarda kendi migration'larıyla gelir. F0 için destructive down migration yok. Geri dönüş: cron/erişimi durdur → doğrulanmış snapshot + önceki kodu birlikte geri yükle veya kontrollü forward-fix → checksum/readiness/test → aç. Üretim backup/restore provası bu görevde yapılmadı.

## Sınırlar ve teknik borç
- G-V01..04, G-P01..02, G-C01, G-R01, G-D01, G-X01..02, G-O01..04, G-T01 açık; F0_GATE_TASKS.md her birini kanıt/dependency içeren görev kartına çevirir. Mock başarı canlı yeterlilik değildir.
- Canlı sağlayıcı adapter'ı/ağ transport'u yok. PayTR callback verifier bilinçli olarak gerçek callback kabul etmez. Finans, fatura, hizmet lifecycle ve API mutation route'ları F0 değildir.
- Tam Identity UI/DB, TOTP, recovery, session revocation registry/logout-all, API key lifecycle, audited impersonation akışı F1'de; F0 policy sözleşmeleri bunların yerine geçmez. Rollerin kalıcı yönetimi ve gerçek entity ACL testleri de F1 bağımlılığıdır.
- Token/mimari statik kontrol tam tip analizi/PHPStan yerine geçmez; ileride tip analizi genişletilmeli. Pattern scan kapsamlı güvenlik denetimi değildir. Runtime üçüncü taraf paket yok; PHP/MariaDB ve CI actions güvenliği ayrı bakım gerektirir.
- Voyar SDK yalnız referans olarak incelendi, bootstrap'a dahil edilmedi. SDK auth hatasında POST'u tekrar gönderebilir; SSL kapatma seçeneği ve PII log kapsamı canlı adapter öncesi düzeltilmeli. G-V02/G-V03 kapalı değildir.
- Production shared-hosting HTTPS/FPM/cron limits/permissions henüz sınanmadı (G-O01). Web installer, imzalı updater, backup/restore ve cron heartbeat alarmları ileriki fazdadır. Web cron endpoint'i açılmadı; yalnız CLI/hosting panel cron'u.
- Uzun handler heartbeat ve provider operation düzeyinde kalıcı in-flight lock/reconciliation F3/F4'te; F0'daki tek çalışan handler yerel core.probe'dur. Queue fencing harici sağlayıcı yan etkisini geri alamaz.
- Audit uygulama API'si append-only'dir; DB hesabının audit UPDATE/DELETE yetkileri işletmeci tarafından kaldırılır. OS/webserver logları ve anahtar/dizin izinleri deployment doğrulamasına tabidir.

## F1'e hazırlık ve bağımlılıklar
Çekirdek üzerinden F1 geliştirilebilir; otomatik geçiş yapılmadı. Önce bu rapor/ADR/şema kabulü, ardından Identity tabloları, TOTP/recovery, server-side session registry, gerçek auth middleware ve endpoint/kayıt/departman kapsam testleri planlanmalı. Üretim/canlı provider açılması için ilgili GATE'ler ve tüm V1 release blocker'ları ayrıca kapanmalı.

## Geliştirme günlüğü ve GitHub
1. Tüm teknik paket, sözleşmeler, karar matrisi ve Voyar SDK belgeleri/kritik HTTP/auth/transport kodu incelendi.
2. Plan ve ADR yazıldı; plan GitHub'a `559db0a` ile kaydedildi. Uzak başlangıç README'si proje şartname README'siyle genişletildi.
3. Çekirdek ve 36 ilk test `d516aca` ile gönderildi; PHP 8.3/8.4 CI başarılı.
4. HTTP, gerçek yarım DDL/readiness, ek kontroller, kurulum/izlenebilirlik/GATE görevleri ve 41 test kanıtı teslim revizyonuna eklendi.

Kullanıcının kalıcı talebi gereği tamamlanan her anlamlı değişiklik grubu Boydaksu/hostidercp deposuna gönderilir. Secrets, çalışma DB'si, test anahtarları ve yerel loglar Git dışındadır. Kaynak/test/migration manifesti evidence/F0_SHA256SUMS.txt içindedir; test kanıtları bu kaynak sürümlerini karşılar.

