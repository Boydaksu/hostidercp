# F4 sahiplik ara düzeltmesi — tamamlanmamış kapsam

Genel Services transferi artık ortak start/accept application lock'ında yalnız HOSTING kabul eder. DOMAIN/CLOUD, kendi atomik modül transferleri tamamlanmadan MODULE_TRANSFER_WORKFLOW_REQUIRED ile reddedilir. 039 trigger değiştirilmedi; domain customer kimliği immutable kalır. Bu, Domain sahiplik transferinin uygulandığı anlamına gelmez. G-T01/G-R01/G-D01 OPEN.

Registry.attach artık Services/RegistrationEligibility üzerinden order→item→service→billing-state kilitleri altında canonical ödeme/köken/müşteri/product/snapshot/unit/refund/cancel ve QUEUED/AUTO koşullarını tekrar denetler. Salt eski materialization sonucuna güvenmez. Henüz register/renew dispatch yoktur; claim sırasında ödeme değişimi, renewal funding ve atomik modül transferi sonraki zorunlu işlerdir. Manual ürünler şimdilik attach'te fail-closed kalır; onay sonrası manual dispatch politikası henüz tamamlanmadı.

Yeni domains-boundaries suite gerçek MariaDB'de 3/3 geçti: ortak transfer application lock'ında Domain/Cloud retleri, unpaid/partial/refund/cancel kaynakları, pending approval ve değişmiş müşteri. Transfer testi Reflection ile start/accept'in ortak private lock'ını doğrudan çağırır; tam public start/accept ve HTTP concurrency testi olarak sunulmaz. Mevcut 251 test dosyaları ve 001–040 migration'ları değiştirilmedi. Yeni migration yoktur; rollback kod snapshot/forward-fix.

Bu kaynağın tam 254-test suite'i ve uzak PHP 8.3/8.4 CI henüz doğrulanmadı. Önceki 251 CI bu değişikliğe ait değildir. Domain lifecycle/failover, atomik sahiplik/contact-period modeli, kalıcı Cloud/task/cost, HTTP/paneller ve performans hâlâ eksiktir. F4 kabulü/DONE yok; F5/production/gerçek provider/mail/ödeme işlemi yoktur.
