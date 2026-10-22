# F1 — Identity ve temel paneller teslim raporu

22 Ekim 2026. Kullanıcının F1-A..F kickoff yetkisiyle, mevcut F0.1 üzerine uygulandı. Kaynak referansı `4ec37db43da2634cfebc117b8bd0cd629c0a00e7`; başlangıç main `c1645a90773e41db5d73e673fbc824bf007fdf46`. F2 başlatılmadı. Bu rapor uygulama ve test teslimidir; üretim yayını veya bağımsız güvenlik denetimi onayı değildir.

Gereksinim izi: FR-ID, R4-01..07, R1-07, R6-01; SEC-001..004; OPS-001/003/005. Detaylı kapsam ve geri dönüş [F1_PLAN](F1_PLAN.md), teknik karar [ADR-003](adr/ADR-003-f1-identity.md), kurulum/demo [F1_SETUP](F1_SETUP.md).

## Tamamlanan bileşenler

| Faz | Gerçek uygulama | Kanıt |
|---|---|---|
| A | User/Customer ayrımı, çoklu membership, staff, roller/permissions/assignment/grant; 21 Identity tablosu, FK ve unique sınırları | 003 migration, schema/ownership/JSON testleri |
| B | Argon2id giriş/kayıt, doğrulama/reset/parola değişimi, tek kullanımlık token, aktif oturumlar/tek veya tümünü iptal, yenileme, şüpheli cihaz olayı | Identity ve gerçek HTTP testleri |
| C | Staff zorunlu TOTP, müşteri politikası, recovery single-use, sınırlı trusted device, kritik step-up | RFC vektörü/replay, staff bypass reddi, MFA açınca eski oturum iptali |
| D | DB'den güncel permission/grant, authoritative hizmet sahipliği, güvenli davet/alt kullanıcı grant, yetki değişiminde version iptali, denetimli impersonation | Cross-customer/departman/hizmet, role escalation, gerçek iki process revoke/write yarışı |
| E | Hash-only API credential, scope/owner/version/expiry/revoked/last use, tek gösterim, daraltma/iptal, denial audit | Servis ve Bearer HTTP testleri |
| F | Ayrı admin/client girişleri, responsive HTML/CSS ve fetch; kullanıcı/müşteri detayları, roller/staff/davet/oturum/olay/key/account ekranları | Gerçek hesaplı HTTP ve tarayıcı ekran görüntüleri |

Kaynaklar: `src/Identity/{IdentityService,Repository,Passwords,Totp,PersistentSessionGuard,Controller,Panel}.php`, `public/assets/identity.{css,js}`, `bin/identity.php`, `bin/identity-demo.php`, `migrations/003_f1_identity.php`, `bootstrap/app.php`. Core değişiklikleri: Request nested object tür koruması ve query; AccessGrant dar scope→genel işlem engeli; AuditEvent vocabulary; Response HTML güvenlik başlıkları; Migrator require scope izolasyonu. Sonuncusu migration içi yerel değişkenin runner migration adıyla çakışmasını giderir; eski SQL veya checksum değiştirilmez.

## Veri modeli ve güvenlik sınırları

Identity kendi tablolarını sahiplenir: users/credentials; customers/memberships; staff/roles/permissions/role_permissions/assignments/grants; resource_scopes; tokens; mfa/recovery; sessions/devices; api_credentials; impersonations; security_events; notifications; settings. Altı özelleştirilebilir başlangıç rolü ve 18 permission vardır. Migration hiçbir kullanıcı veya varsayılan parola oluşturmaz.

Her işlem güncel DB user/session/version ve scope üzerinden doğrulanır. Genel staff veya administrative flag bütün müşterilere erişim sağlamaz; müşteri/hizmet/departman/işlem ayrı grant boyutlarıdır. İstemciden principal kabul edilmez. Hizmetin gerçek müşteri ve departmanı registry'den kilitli okunur; body'deki eşleşen UUID tek başına kanıt sayılmaz. Henüz bulunmayan billing/orders/support işlevleri yazılmadı; yalnız scope izin sözleşmesi vardır.

Revocation fence: mutation transaction içinde önce user, sonra session kilitlenir; güncel izin ve identity_version tekrar doğrulanır; business write ile audit birlikte commit/rollback olur. İptal eden işlem aynı user kilidine katılır. Önceden kilit almış işlem commit edebilir; iptal commit'inden sonra eski session mutation yapamaz. Bu sınır iki ayrı PHP process ve MariaDB üzerinde test edildi. DB/user kilidi request boyunca yalnız sınırlı uygulama işleminde tutulur; dış I/O yoktur. Native PHP session dosya kilidi DB veya ağ işlemi boyunca tutulmaz.

Cookie admin/client ayrıdır; Secure/HttpOnly/SameSite, CSRF, native ID rotation ve registry context bağlaması uygulanır. Registry pending MFA oturumu 10 dakika, aktif oturum 8 saat; step-up 5 dakika. Trusted device 30 gün, API key en çok 30 gün, impersonation 10 dakika. Yenileme native session ID'yi döndürür; mutlak registry süresini uzatmaz. Password/role/membership/staff/security policy değişimi gerekli kullanıcı version'larını, oturumları ve cihazları iptal eder; API key eski version ile geçmez. MFA etkinleştirme yalnız enrollment'ı tamamlayan oturumu korur.

Parola Argon2id 64 MiB/time3/thread1; min12/max1024 byte. Token/recovery/device/API secret HMAC-SHA256; TOTP ve gönderim için zorunlu notification secret sodium AEAD ile şifreli. Login/reset/kayıt hesap varlığını genel yanıtla gizler; rate bucket HMAC'li ve transaction dışı kalıcıdır. Cihaz/network fingerprint değişimi şüpheli olay + HELD bildirim üretir; coğrafi/IP istihbaratı veya risk motoru iddiası yoktur.

Staff her girişte MFA challenge/enrollment gerektirir; trusted device atlayamaz. Müşterinin MFA kaldırması kendi staff veya herhangi bir bağlı mandatory customer politikası varsa reddedilir. Recovery ve yeni cihaz için taze doğrulama gerekir. Destek oturumunda gerçek personel actor korunur, müşteri ayrı effective context'tir; yalnız account/members/services/support read allowlist kesişimi, target üyelik ve açık staff grant geçerlidir. Finansal, yıkıcı, role/API/session yönetimi kapalıdır. Süre dolması admin yetkisini otomatik geri getirmez; parola + yeni TOTP/recovery ile açık bitirme zorunludur. Sebep şifrelidir; audit'e serbest metin gitmez.

Mail outbox/job yalnız opak notification UUID taşır; gövde şifrelidir ve HELD kalır. Canlı mail handler'ı eklenmedi. Security events/audit typed event ve opak referans içerir; email, parola, token, seed veya impersonation sebebi log'a yazılmaz. Başarısız HTTP güvenlik olayları business rollback dışından güvenli olarak kaydedilir.

## API ve arayüz

HTML: `/admin`, `/client`. JSON: `/api/v1/{admin|client}/identity/`. GET bootstrap CSRF döndürür; status pending/active aşamasını döndürür. Görünümler me, users/user, customers/account, roles, staff, members/member-roles/invitations, sessions/devices/keys, events'tir. Her görünüm ayrıca permission/context kontrol eder.

POST işlemleri login/register/token-request/token-complete, logout/logout-all/session-revoke/renew, step-up/enroll/enroll-confirm/mfa/password/mfa-disable/recovery/device-create/device-revoke, switch/account-update/customer-create, user-save/role-save/role-assign/role-remove/staff-save/invite/member-remove/member-scope, impersonate/impersonation-end, key-create/key-change. Strict alan/type validation, CSRF ve 16–128 karakter Idempotency-Key zorunludur. Role/grant yalnız yetkili server-side yönetim akışından değişir; grant girdisi iç JSON object olmalıdır.

Hassas idempotency yanıtı şifrelenir; tekrar gösterim 409'dur. Diğer replay aynı sonucu verir; farklı mantıksal payload409. JSON nested object/list korunur ve reordered object tek mantıksal payload sayılır. Önceki response kaydını tekrar okumadan persistent session guard doğrulanır. `/api/v1/integration/identity` Bearer örneği gerçek hash/version/scope/expiry doğrular; müşteri sorgusu account.view, global actor sorgusu identity.users.view ister. Henüz iş modülü API'si sunulmaz.

Paneller gerçek DB/fetch akışlarıdır; henüz bulunmayan finans/hosting/domain/cloud sayfaları yoktur. Formlar yeniden doğrulama, MFA enrollment/recovery ve secret tek gösterim akışlarını kullanır. CSP self-only, textContent ile server data rendering, no-store/nosniff/referrer/HSTS uygulanır. Screenshot'lar yalnız sentetik müşteri verisi taşır: [masaüstü](evidence/f1/client-panel.png), [mobil](evidence/f1/client-mobile.png).

## Test ve CI

Önceki 64 test adı/beklentisi korunur. `tests/harness.php` yalnız test cleanup'ın finally içinde çalışabilmesi için returning executeTests ekler; runTests aynı exit davranışını sürdürür. Eski integration ve concurrency suites CI'da kalır. Yeni `tests/identity.php` 16 ve `tests/identity-http.php` 4 test: toplam **84 test**. Son tam yerel sonuç ve doğrulamalar [evidence/f1](evidence/f1/) altında kaydedilir. Yerel runtime PHP 8.4.24, MariaDB 10.11.18; PHP 8.3 sonucu yalnız GitHub matrix'ten raporlanır.

PHPStan level5, PHP80300 hedefi ve sıfır ignore; lint/architecture/security pattern taraması; açık Composer locked audit adımı; runtime üçüncü taraf PHP bağımlılığı sıfır. Node yalnız JavaScript syntax kontrolü. CI her push'ta PHP8.3/8.4 ve MariaDB10.11 ile dokuz suite çalıştırır. Kesin run/commit sonuçları `evidence/f1/ci.json` dosyasında teslim sırasında kaydedilir; tahmini başarı raporlanmaz.

**Doğrulanmış son kaynak:** `bd92174a276164ad218688331aeb725ee2e47521`. [GitHub Actions 39776719991](https://github.com/Boydaksu/hostidercp/actions/runs/39776719991): `verify (8.3)` ve `verify (8.4)` tamamlandı, ikisi de **success**. Her matrix job içinde Composer audit, PHPStan, önceki MariaDB/session concurrency suite'leri ve yeni Identity/HTTP suite'leri başarılıdır. Yerelde **84/84**, CI'da her PHP sürümünde **84/84**; eski migration blob hash'leri aynı ve önceki test deklarasyonları korunmuş ([kanıt](evidence/f1/baseline-preservation.txt)).

Anlamlı gruplar main'e ayrı gönderildi: A `6c30c91`, B/C `37cb47c`, D/E `bcd50f4`, F `b95b620`, final MFA/kanıt/demo `bd92174`. Bu beş grubun Actions sonucu success'tir. Bu raporun son CI kayıt güncellemesi yalnız dokümantasyondur; yukarıdaki kaynak SHA'sı doğrulanan uygulamayı belirtir.

## Açık sınırlar ve F2 öncesi borçlar

- Bütün [canlı GATE görevleri](F0_GATE_TASKS.md) OPEN. Gerçek mail/PayTR/Voyar/cPanel/registrar bağlantısı veya production müşteri işlemi yapılmadı. Mail GATE kapanmadan gerçek müşteri signup/reset/invite gönderimi kullanıma açılmaz.
- TLS/hosting ACL, yedek/restore provası, APP_KEY yedeği/rotation, cron/monitoring ve operatör erişimi deployment sorumluluğudur; izole test bunları production'da doğrulamaz. Bağımsız penetrasyon testi ve gerçek hosting yük/Argon2 kapasite ölçümü yayın öncesi gerekir.
- Identity büyük servisinin modül içi küçük servislerine ayrılması, görünüm listelemelerine pagination/search ve kullanıcı dostu permission/event adları F2 öncesi teknik borçtur. Listeler şu anda en yeni 100 kayıtla sınırlıdır; büyük kurulumlarda tam yönetim arayüzü sayılmaz.
- Security event/notification/token/session/device/API kayıtları için otomatik retention henüz yoktur; F0.1 rate-limit ve succeeded-job politikaları korunur. Identity kanıtını sessiz silmemek için süreler uydurulmadı; veri saklama/anahtar imha ve arşiv kararı üretim öncesi onaylanmalıdır.
- Hesap kayıt varsayılan para birimi TRY; ayrı müşteri oluşturma TRY/USD/EUR destekler. F1 para hareketi ve fiyatlandırma geliştirmez. E-posta değiştirme, hesap silme, MFA kaybında operatör kurtarma ve WebAuthn/SSO yoktur; otomatik güvensiz kurtarma eklenmedi.
- Yetki değişiminde user lock commit fence sağlar; çok kullanıcı değiştiren operasyonlar deadlock'ta rollback olabilir. Kullanıcı güvenli yeniden denemeyi yeni sonuçla yapar; harici işlem tekrarı yapılmaz. Bu teslim geniş ölçekli contention/load testi değildir.
- Kayıp one-time response tekrar gösterilmez; enrollment/recovery yeniden üretilebilir, kayıp API key iptal edilip yenisi oluşturulur. Kullanıcıların recovery kodlarını gerçekten güvenli sakladığı doğrulanamaz.
- Resource registry'ye gelecekteki service/domain/support/billing modülleri authoritative sahipliği kendi application service işlemleriyle bağlamalıdır. Sahte üretim hizmeti/veri oluşturulmadı. Genel permission'lar iş modüllerindeki ayrı create/edit/delete/transfer/critical izinlerinin yerine geçmez.

F1 çalışan Identity ve panel temeli incelemeye hazırdır. Üretim yayını yukarıdaki operasyon kapılarına bağlıdır. F2 iş modüllerine geçmek için kullanıcı kabulü gerekir; burada durulmuştur.
