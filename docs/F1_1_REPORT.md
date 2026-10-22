# F1.1 — Security closure

İncelenen kaynak bd92174; başlangıç main 5f4d50b. F1 işlevsel kapsamı kullanıcı tarafından kabul edildi. Bu kapanış yalnız FR-ID, R4-01..07, SEC-001/002/004 ve D5 güvenlik politikasını düzeltir. F2 başlatılmadı, gerçek müşteri veya canlı sağlayıcı işlemi yapılmadı. Bütün canlı GATE'ler OPEN.

## Kök neden ve düzeltme

1. `verifyMfa()` aktif/pending ayrımı yapmıyor, ortak `activate()` hem oturumu etkinleştiriyor hem `verified_at` yazıyordu. Dolayısıyla yalnız yeni TOTP bilen aktif oturum parola olmadan kritik işlem tazeliğini yenileyebiliyordu. `verifyMfa` artık aktif oturumu409 `MFA_CHALLENGE_NOT_PENDING` ile reddeder; kodu tüketmez ve DB zamanını değiştirmez. Activation `verified_at=NULL` bırakır. Giriş, MFA kurulumu ve trusted-device oturumu kritik tazelik sağlamaz; renewal da sağlamaz. Ayrı step-up parola ve etkin MFA için TOTP veya kullanılmamış recovery kodu ister. Impersonation bitişinin mevcut parola+MFA sınırı korunur.
2. Kritik formlar yalnız bazı ekranlarda yerel onay gösteriyor, API'de aktör/işlem/hedef/sürüm bağı yoktu. `CriticalActions` coordinator ve 004 migration ile server-side prepared/accepted/consumed onay eklendi. On beş yüksek etkili Identity komutu güncel step-up + açık onay ister. Preview gerçek yetki/validation kodunu DB savepoint içinde çalıştırıp bütün yazıları geri alır; yalnız yetkili aktöre mevcut hedef/kapsam, istenen değişiklik ve etki döner. Uygulama actor/session/version/action/payload/state/expiry kontrolleri ve target kilitleriyle yapılır. Eski hedef/sürüm veya değiştirilmiş payload409, eksik/false onay403. Tek kullanımlık tüketim + business write + audit atomiktir. Panel checkbox ve açık “Onayla ve uygula” sunar; vazgeç işlemi mutation yapmaz. İkinci personel onayı eklenmedi.
3. `assignRole()` grant müşteri alanını customer assignment'a bağlamıyor ve actor'ın gerçek resource kapsamıyla delegasyonu denetlemiyordu. Artık customer eşitliği, grant tipleri, permission subset ve aktörün customer/service/department grant sınırları kontrol edilir; authoritative DB sahipliği korunur. `memberScope` department-only delegasyonu da aktör kapsamından geçer. Staff/admin flag'i sınır aşımına izin vermez.

Kaynak: `src/Identity/{IdentityService,Controller,CriticalActions}.php`, `public/assets/identity.js`, `src/Core/Security/AuditEvent.php`, `migrations/004_f1_1_confirmations.php`. Karar ayrıntısı [ADR-004](adr/ADR-004-f1-1-confirmations.md), çalışma/geri dönüş planı [F1_1_PLAN](F1_1_PLAN.md).

## Yeni negatif regresyonlar

`tests/identity-security.php` sekiz test:

- Aktif MFA session + yeni geçerli TOTP → direct MFA409; verified_at değişmez; role mutation reddedilir. Yanlış parola step-up reddedilir; doğru parola+TOTP sonrası kritik işlem olur; kod replay'i reddedilir.
- Pending login recovery ile aktif olur ancak step-up açmaz; staff trusted-device MFA'yı atlayamaz.
- Customer mismatch, actor dışı customer/service/department delegasyonu reddedilir; sahip olduğu somut hizmete grant başarılıdır.
- Hazırlık mutation yapmaz; actor/session/payload, expiry ve role-version değişimi eski onayı reddeder.
- Hata onay tüketimi/business/audit'i birlikte geri alır; başarı bir kez commit eder, ikinci kullanım409 olur.
- İki gerçek PHP process aynı confirmation için yarışır; yalnız biri200 ve tek rol kaydı, diğeri409 olur.
- Müşteri trusted-device login ve renewal kritik freshness sağlamaz; password+factor step-up zorunludur.
- Müşteri MFA politikası sürümü, hedef personel kaydı ve üyelik değişimi eski onayı409 ile reddeder.

`tests/identity-http.php` ek negatif: gerçek HTTP endpoint'inde aktif MFA+renew bypass reddi, doğru step-up sonrası bile onaysız role-save reddi, açık false consent reddi, prepared consent sonrası başarı, aynı idempotency key replay'inde tek sonuç ve farklı key ile consumed confirmation409. Ek HTTP testi bütün15 kritik endpoint'in onaysız doğrudan çağrısını reddettiğini doğrular. Önceki dört HTTP testi yeni açık onay protokolünü tamamlayarak aynı beklentileri korur.

Toplam **94 test = önceki84 + yeni10**. Önceki test adı veya başarısız beklenti kaldırılmadı. Root fixture artık açık step-up yaparken bir recovery kodu tüketir; mevcut recovery testi bu fixture tüketimi + testteki tüketim için tam2 kullanılmış kodu denetler. Bu testte one-use/replay ve staff bypass kontrolleri korunur. Native/session/integration suite'leri değişmeden kalır. Baseline test deklarasyonları ve 001/002/003 blob hash'leri teslim kanıtında karşılaştırılır.

## Çalıştırma, migration ve geri dönüş

[F1_SETUP](F1_SETUP.md) komutları geçerlidir. Güncellemede yedek+bakım ardından `php bin/console.php migrate`; 004 `identity_confirmations` tablosunu ekler; 005 eski MFA yolundan gelmiş olabilecek verified_at değerlerini temizleyerek yeniden step-up zorunlu kılar, eski migration'lar değişmez. Test sırasına `php tests/identity-security.php` eklendi. PHPStan level5 ve açık `composer audit --working-dir=tools/analysis --locked` CI'da korunur. Yerel testler izole hostider_f0_test DB üzerinde; PHP8.3/8.4 matrix MariaDB10.11 ile çalışır.

Rollback otomatik drop değildir. Kod+DB+APP_KEY yedeği birlikte restore veya forward-fix; mevcut kullanıcı oturumlarının güvenlik iptalleri geri açılmaz. Test/demo bootstrap sabit parola veya üretim kullanıcı seed'i eklemez. Geçmiş F1 raporu tarihsel kaynak sonucudur; bu rapor son güvenlik davranışını belirtir.

## API protokolü

1. `/api/v1/{admin|client}/identity/step-up` — parola ve MFA varsa yeni TOTP/recovery. Giriş MFA'sı bunun yerine geçmez.
2. POST `confirmation-prepare`: `{action:"role-save",payload:{...}}`, normal CSRF+Idempotency-Key ile. Yanıt id/current/proposed/effect/expires_in içerir. Prepare401/403/422 dönerse onay üretmez.
3. Özgün işlem endpoint'i, aynı payload + `confirmation:{id:"...",accepted:true}` ile gönderilir. Hedef değiştiyse yeni preview ve yeniden açık onay gerekir. Ağ tekrarında aynı idempotency key kullanılır; farklı içerik409'dur. Hassas one-time response replay'i eskisi gibi409'dur.

Onay kaydı server-side HMAC fingerprint taşır; e-posta, reason, ham komut veya secret tutmaz. Prepared/current ekranı yalnız yetkili kullanıcıya gösterilir; audit yalnız typed `critical.prepared` / `critical.confirmed` ve opaque UUID taşır. Onay bir yetki belgesi değildir; gerçek komut execute sırasında authorization'ı tekrar yapar.

## Kalan gerçek riskler ve F2 hazırlığı

- Bu kapanış güvenlik regresyonlarını düzeltir; production yayını veya bağımsız penetrasyon testi değildir. F1_REPORT'taki hosting/TLS/ACL, key rotation, yedek/restore, retention, pagination ve kapasite sınırları sürer. Yeni confirmation kayıtları da retention kararına dahil edilmelidir; beş dakika sonra kullanılamaz, otomatik silme politikası uydurulmadı.
- Preview savepoint yaklaşımı yalnız mevcut DB-only Identity komutları için geçerlidir. F2'de harici etkili komutlar buna eklenemez; side-effect-free plan/validation ve kendi authoritative transaction sınırları gereklidir. Trusted in-process IdentityService çağrıları HTTP onay gateway'inin yerine dış API olarak açılmamalıdır.
- Snapshot ilgili üyelik/rol/resource satırlarını kilitler; yoğun yönetim trafiğinde contention/deadlock rollback olabilir. Geniş ölçekli yük garantisi verilmez. Mutlak beş dakika TTL ve güncel step-up birlikte zorunludur.
- API, explicit prepare+accepted intent'ini doğrular; insanın ekranı gerçekten okuduğunu kanıtlamaz. İkinci kişi veya yeni kurtarma politikası eklenmedi.
- Scope devri artık actor'ın explicit kaynak sınırını aşamaz. İlk grant'i kurmak için güvenilir operasyon yetkisi gerekir; sırf Super Admin etiketiyle müşterilere otomatik erişim geri getirilmedi.

Test/CI kanıtları `docs/evidence/f1-1/` altındadır. Nihai commit ve CI sonuçları doğrulandıktan sonra aşağıya kaydedilir. F2 yalnız kullanıcının sonraki açık onayıyla başlayabilir; burada durulur.

Yapay ayrı DB ile gerçek tarayıcıda login → password step-up → account-update preview → ayrıntılı etki/hedef/sürüm → başlangıçta devre dışı onay butonu → vazgeç akışı doğrulandı. Hiçbir müşteri güvenlik politikası uygulanmadı. Onay ekranının görüntüsü [critical-confirmation.png](evidence/f1-1/critical-confirmation.png). Bu incelemede MFA policy checkbox'ının false değerini de geçerli girdi olarak bırakabilmesi düzeltildi; browser required checkbox artık politikayı zorla true'ya yönlendirmiyor. İsteğe bağlı MFA false iken preview ve iptal akışı gerçek ekranda görüldü.
