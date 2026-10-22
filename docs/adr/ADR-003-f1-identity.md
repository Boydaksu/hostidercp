# ADR-003 — Kalıcı Identity ve güvenlik sınırları

2026-10-22; kullanıcı F1 kickoff'una dayalı teknik karar. Modüler monolit korunur.

Identity kendi tablolarını sahiplenir. Users müşteri hesabı değildir; membership ve role assignment ayrı. Permission/grant'ler DB'den çözülür; request'ten principal kabul edilmez. F1'de iş modülü tabloları yok: authoritative `identity_resource_scopes` registry, gelecekteki modüllerin application service sınırından kaydedeceği hizmet→müşteri→departman ilişkisini tutar. Public yazma endpoint'i yoktur; üretim hizmeti seed edilmez. İlişki yoksa erişim reddedilir. İş modülleri geldiğinde sahiplik kaydı aynı business transaction içinde güncellenmelidir.

Parola Argon2id (64 MiB, time 3, thread 1); tek kullanımlık token/API key/recovery random secret HMAC-SHA256 özeti; TOTP seed ve gönderilmesi gereken notification token'ı context-bound sodium AEAD ile şifreli. Seed yeniden gösterilmez; recovery kodu yalnız oluşturulduğunda bir defa gösterilir. Trusted devices müşteri MFA kolaylığıdır, staff MFA veya privileged step-up atlamaz. MFA ilk etkinleştirmesi diğer oturumları, cihazları ve eski API credential version'larını iptal eder; yalnız kurulumu doğrulayan oturum yeni version ile devam eder.

Native session cookie yalnız opak registry reference taşır; registry güncel identity_version, expiry, revoked ve context kontrol eder. DB mutation içinde user→session satır kilidiyle revocation ve güncel izinler yeniden doğrulanır; işlem ile audit birlikte commit edilir. Session guard bir ön kontroldür, commit fence'in yerine geçmez.

MFA'sız staff yalnız enrollment/challenge/logout endpointlerine erişir. Impersonation aynı admin session içinde ayrı effective bağlam, kısa TTL, encrypted sebep kaydı, original actor audit; yalnız read izni allowlist'i ve target membership kesişimi. Çıkışta fresh step-up istenir, müşteri bağlamından admin işine doğrudan atlama yok.

Request::json üst seviye array API'sini korur, nested stdClass nesnelerini list'e dönüştürmez. Cookie API için CSRF, mutation için idempotency key ve server-derived fingerprint kapsamı uygulanır; password/token sonuçları idempotency cache'e plaintext yazılmaz. Identity işlevleri staging'de yapay veriyle çalışır; dış mail/ödeme/provisioning fail-closed kalır.

HTTP idempotency outer transaction'ına Identity servisi açıkça katılır; Core Database nested transaction yasağı korunur. Rate-limit transaction'dan önce kalıcı olarak işlenir. Hassas bir defalık yanıtlar context-bound şifrelenir; aynı key ile replay yeni secret üretmez ve secret'ı yeniden göstermez (409). İletişim kesilirse yeni enrollment/recovery veya revoke+new-key akışı gerekir.
