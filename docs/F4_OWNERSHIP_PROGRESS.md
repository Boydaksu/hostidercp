# F4 sahiplik ara düzeltmesi — tamamlanmamış kapsam

Genel Services transferi artık ortak start/accept application lock'ında yalnız HOSTING kabul eder. DOMAIN/CLOUD, kendi atomik modül transferleri tamamlanmadan MODULE_TRANSFER_WORKFLOW_REQUIRED ile reddedilir. 039 trigger değiştirilmedi; domain customer kimliği immutable kalır. Bu, Domain sahiplik transferinin uygulandığı anlamına gelmez. G-T01/G-R01/G-D01 OPEN.

Registry.attach artık Services/RegistrationEligibility üzerinden order→item→service→billing-state kilitleri altında canonical ödeme/köken/müşteri/product/snapshot/unit/refund/cancel ve QUEUED/AUTO koşullarını tekrar denetler. Salt eski materialization sonucuna güvenmez. Henüz register/renew dispatch yoktur; claim sırasında ödeme değişimi, renewal funding ve atomik modül transferi sonraki zorunlu işlerdir. Manual ürünler şimdilik attach'te fail-closed kalır; onay sonrası manual dispatch politikası henüz tamamlanmadı.

Yeni domains-boundaries suite gerçek MariaDB'de 3/3 geçti: ortak transfer application lock'ında Domain/Cloud retleri, unpaid/partial/refund/cancel kaynakları, pending approval ve değişmiş müşteri. Transfer testi Reflection ile start/accept'in ortak private lock'ını doğrudan çağırır; tam public start/accept ve HTTP concurrency testi olarak sunulmaz. Mevcut 251 test dosyaları ve 001–040 migration'ları değiştirilmedi. Yeni migration yoktur; rollback kod snapshot/forward-fix.

Kaynak GitHub'a **8ecf20f7e9a350f1edc36f4d9801695b3b836df7** olarak yayımlandı (yerel 6d6609c; doğrulanmış aynı Git tree `924186cb571ffaa69d7c5794b97a25d228c87126`). [Taslak PR #2](https://github.com/Boydaksu/hostidercp/pull/2), kabul edilen F3 dalını hedefler; main'e merge yapılmadı.

[GitHub Actions 40073013081](https://github.com/Boydaksu/hostidercp/actions/runs/40073013081): PHP 8.3 ve 8.4 için ayrı ayrı **254/254 test**, sıfır hata ve iki job success. Önceki 251 + yeni 3 test; Composer locked audit, PHPStan, lint/mimari, güvenlik taraması, HTTP/session kararlılık tekrarları ve JS kontrolleri başarılıdır. Doğrudan job loglarından sayım ve step kanıtı [ci-ownership.json](evidence/f4/ci-ownership.json) içindedir. Önceki 251 CI bu değişikliğin kanıtı olarak kullanılmadı. Sonraki rapor commit'i yalnız dokümantasyon/kanıt içerir.

Domain lifecycle/failover, atomik sahiplik/contact-period modeli, kalıcı Cloud/task/cost, HTTP/paneller ve performans hâlâ eksiktir. F4 kabulü/DONE yok; F5/production/gerçek provider/mail/ödeme işlemi yoktur. İnceleme bu ara güvenlik düzeltmesi içindir; tam F4 teslimi değildir.
