# F0.1 güvenlik ve operasyon sözleşmeleri

## Yetki sınırı — FR-ID, R4-01..07, SEC-001/002/004
`staff` bütün müşterilere erişim sağlamaz. Normal müşteri isteğinde doğrulanmış `customerId` hedef müşteriyle eşleşir. Personelin farklı müşteriye erişmesi yalnız **aynı permission** için sunucu tarafında verilmiş `AccessGrant` ile mümkündür. İstekte customer/service/department boyutları varsa aynı grant bunların tamamını kapsamalıdır; ayrıca temel işlem izni ve departman üyeliği gerekir. Hizmet isteğinde customer zorunludur; customer/service ilişkisi endpoint/application service tarafından repository'den doğrulanır, kullanıcı parametresinden sahiplik türetilmez.

`customers: ['*']` yalnız güvenilir Identity yöneticisinin o permission için açıkça verdiği genel kapsamı ifade eder. staff veya administrative bayrağından üretilmez. `administrative` ayrı bir güven sınırıdır; kritik işlemde ayrıca `privileged` step-up kontrolü gerekir. Administrator olmak müşteri/service/department kontrollerini kaldırmaz.

Impersonation gerçek actor'ı korur. Etkin müşteri, ayrı effectivePermissions ve grant birlikte kontrol edilir; admin permissions aktarılmaz. Başka müşteriye geçiş ve privileged/administrative işlem engellenir. Gerçek hedef kullanıcının izinleriyle kesişimi, kısa TTL, sebep, kayıt/sona erme audit'i ve yasak işlem kataloğu F1'in Identity servisinde uygulanacaktır. F0.1 request verisinden principal oluşturan endpoint açmaz.

## Kısa session kilitleri
`Session::start('admin'|'client', $guard)` bir `SessionSnapshot(data, version, reference)` döndürür ve **kilidi kapatır**. Snapshot değişmez bir okuma görünümüdür; `$_SESSION`'a sonradan yazmak kalıcı değildir. İş/API/network işlemleri kilit kapandıktan sonra yapılır.

`Session::mutate($context, $transform, $expectedVersion, $guard)` güncel veriyi native kilit altında yeniden okur. Callback yalnız session içi kısa veri dönüşümüdür ve yeni array döndürür; network, uzun DB transaction veya iş akışı burada çalıştırılmaz. Eski snapshot'ın yerine yazma için expectedVersion zorunlu kullanım kuralıdır: eski sürüm 409 alır. Yalnız atomik fresh-state transform (ör. sayaç artırma) expectedVersion olmadan yapılabilir. Callback hata verirse yazım abort edilir; açık kilit bırakılmaz.

`rotate($context, $guard)` yalnız login/step-up/identity kontrollü refresh sınırında kullanılır; okuma istekleri cookie rotate etmez. Eski SID silinmez, retired tombstone yazılır, yeni SID ve CSRF verilir. Kimlik oluştururken **önce rotate**, ardından yeni SID üzerinde identity state kaydet; kullanıcı değiştirme işlemi önceki principal verisini yeni transform'da temizlemelidir. F1 bu akışı tek yetkili servis üzerinden yürütecek, F0.1 login endpoint'i yoktur.

`end($context)` eski SID'ye tombstone bırakır ve cookie'yi temizler. Retired/expired/strict-mode tarafından tanınmayan SID 401 alır; yeni kimlik üretmez. Cookie parse/issue açıkça yönetilir: mevcut SID ile okumalar veya reddedilmiş stale istekler Set-Cookie göndermediği için geç gelen cevap yeni login cookie'sini ezmez. Cookie yalnız yeni anonymous session, explicit rotation ve logout'ta yazılır. Secure/HttpOnly/SameSite=Strict ve __Host- korunur; context içeride de bağlanır.

Yeni session formatı `_context`, `_version`, `data`, opaque `_reference` gerektirir. Eski F0 session'ları güvenli biçimde retired olur; dağıtımda yeniden giriş gerekir. Tombstone native session store'da kalır, normal GC ile kaldırılır; sonradan aynı SID yeniden verilirse strict-mode kimliksiz yeni SID üretmeye çalışsa bile supplied SID eşleşmediği için 401 olur ve cookie yazılmaz.

`SessionGuard` F1'in server-side revocation/identity-version doğrulaması için sözleşmedir; F0.1 yalnız reddi ve rollback'i test eder, registry oluşturmaz. F1'de guard giriş/okuma/yazma/rotation sırasında kullanılmalı. Ayrı business transaction başlatan uzun istekte **iş commit'inden önce authoritative revocation/version yeniden kontrolü ve gerekli DB kilidi** gerekir; eski snapshot tek başına yetki kanıtı olamaz. Native PHP file-session handler'ı process kilidi sağlamalı; farklı session handler/çok sunucu için aynı davranış ayrıca kanıtlanmalıdır (G-O01).

## Audit
`AuditEvent` sabit sözlüğü login success/failure, MFA challenge/success/failure/enrollment/recovery, permission/role, API key lifecycle/denied, session ve impersonation olaylarını destekler. Bunlar event sözleşmesidir; ilgili auth özellikleri uygulanmış değildir. Başarısız giriş actor'ı `anonymous`, subject'i opaque attempt ID olabilir; e-posta/IP/token/şifre/error message alınmaz. Event/actor/subject/effective-customer ve UUID trace/cause doğrulanır, serbest metadata parametresi yoktur.

Audit çağrısı DB transaction ister. İş mutation'ı ve audit aynı transaction içinde commit/rollback olur. Reddedilmiş isteğin güvenlik olayı, başarısız business transaction rollback'inden sonra üst katmanda ayrı kısa transaction ile yazılabilir; aynı transaction içindeki audit'in rollback sonrası kaldığı varsayılmaz. DB hesabının audit UPDATE/DELETE yetkileri kaldırılmalıdır; external immutable archive/tamper evidence gelecekteki operasyon çalışmasıdır.

## Mock ve canlı ayrımı
Production `EffectPolicy` mock dispatch'i reddeder; tüm beş adapter'da policy dispatch öncesindedir. `ProviderResult` zorunlu readonly `environment=mock` provenance taşır; normalized JSON schema bunu zorunlu tutar. `ResultAcceptance` production'da bu sonuçları iş durumuna çevirmeyi reddeder; canlı GATE'ler açık olduğu için başka bir live sonuç tipi de yoktur. Sonraki provider application service'leri state yazmadan önce bu kabul sınırını kullanmak zorundadır. Ağ bağlantısı/gerçek API endpoint'i eklenmedi.

## Rate-limit retention
002 migration `expires_at` ekler. Yeni/güncellenen bucket'ın expiry'si gerçek pencere sonudur (window_start+window); aktif pencere silinmez. Eski NULL expiry'li bucket'lar süreleri bilinmediği için korunur; tekrar kullanıldıklarında expiry kazanırlar. `php bin/console.php retention:rates` en çok 100 expired bucket siler; çekirdek API batch üst sınırı 1000'dir. Cron'da bir dakikada bir veya operatörün seçtiği daha seyrek aralıkta çalıştırılabilir. Veri hacmi yüksekse backlog ölçülür; pencere güvenliği pahasına toplu TRUNCATE yoktur. Pencere üst sınırı 86400 teknik kaynak sınırıdır; yanlış konfigürasyon fail-closed reddedilir.

## İş retention ve izlenebilirlik
F0.1 job satırlarını silmez. `SUCCEEDED` payload minimization **varsayılan kapalıdır**; işletmeci yedek/restore gereksinimini inceleyip açık yaş seçerse `php bin/console.php retention:job-payloads <age-seconds>` en çok 100 eski başarılı payload'ı `{}` yapar. En az 86400 saniye teknik safeguard'dır, bir günlük otomatik silme politikası değildir. Dedupe key + canonical SHA-256 fingerprint + status + correlation + timestamps + job_attempts korunur. Aynı mantıksal job replay'i yeni dispatch yaratmaz, farklı payload conflict olur. Legacy job fingerprint'i payload temizlenmeden canonical veriden hesaplanır.

`FAILED`, `UNKNOWN`, `PENDING`, `RUNNING`, dead_letters, audit, outbox, inbox ve idempotency bu cleaner tarafından değiştirilmez. `php bin/console.php queue:attention` en çok 100 FAILED/UNKNOWN işin ID/status/attempts/correlation/safe-code/timestamp özetini döndürür; payload veya sır içermez. Attempt lease token ve final outcome DB'de ilişkilidir. UNKNOWN otomatik retry/failover edilmez. Personel inceleme UI, reconciliation karar/audit akışı, pagination ve alarm/cooldown sonraki fazlardadır.

Sınırsız tombstone/attempt/audit/UNKNOWN büyümesi disk riski taşır. G-O04 kapanmadan yasal/business retention süresi uydurulmaz. Job payload yalnız opaque referanslar taşımalı; müşteri PII/credential/raw vendor response kuyruk veya log'a konmamalı. Arşivleme/kalıcı iş operasyon kimlikleri olmadan tombstone silinemez.

## Migration, rollout ve geri dönüş
001 değişmez. 002 yalnız nullable/index alanları ekler; mevcut rate/job verisini silmez ve canlı provider açmaz. MariaDB DDL implicit commit yapar; APPLYING kalırsa trafik/cron açılmaz. Testler hem 001→002 upgrade/replay hem boş şema kurulumunu CI'da çalıştırır. Backup ve önceki kod/DB snapshot birlikte restore veya kontrollü forward-fix gerekir; otomatik down yoktur. Payload redaksiyonu opt-in ve geri dönüşü yalnız doğrulanmış yedekle mümkündür. Dağıtımda eski session cookie'leri yeniden girişe yönlendirilir.

## Geliştirme araçları
`composer install --working-dir=tools/analysis --no-interaction --prefer-dist --no-plugins --no-scripts`

`php tools/analysis/vendor/phpstan/phpstan/phpstan.phar analyse --no-progress --memory-limit=512M`

PHPStan 2.3.1 composer.lock ile sabitlenmiştir; level 5, PHP 8.3 hedefi, src/bootstrap/bin/public tamamı analiz edilir. Baseline veya error ignore eklenmedi. Vendor uygulama autoloader'ına dahil değildir; production Composer/Node gerektirmez. 512 MB yalnız geliştirme analiz sınırıdır. [PHPStan resmi kurulum](https://phpstan.org/user-guide/getting-started), [rule levels](https://phpstan.org/user-guide/rule-levels). Level 6+ collection shape/mixed analizi ve tam güvenlik denetimi sonraki kalite çalışmasıdır.
