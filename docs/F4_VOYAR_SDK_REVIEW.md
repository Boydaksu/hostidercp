# F4 Voyar özgün SDK incelemesi — 24 Ekim 2026

Kaynak: `references/voyar-api-client-user-provided.zip`, SHA-256 `6f76355c1fb32b9c86889e74c68f5caafec03c3670e140e61b53543432c3eb7c`. Arşiv değiştirilmedi, SDK çalıştırılmadı veya gerçek endpoint çağrılmadı. Bu rapor yerel kaynak incelemesidir; güncel canlı davranış doğrulaması değildir.

| Akış | Arşivde kanıt | Hostider sınırı |
|---|---|---|
| Katalog / custom quote | `docs/resources.md`, `Api/Catalog/CustomPackage.php`, `Model/CustomPackageSpec.php` | Group custom-builder flag; CPU min/max/step, RAM/core ve toplam, disk/RAM ve toplam, IPv4 sınırları server options ile doğrulanmalı. Spec constructor yalnız pozitif sayıları kontrol eder; tek başına sağlayıcı uygunluk kanıtı değildir. |
| Create | `docs/services.md`, `Api/Services.php` | `pid` veya `custom_package`; `prepaid`/`ondemand` ayrımı SDK'da vardır. Create yanıtının idempotency ve task/resource bağlama semantiği doğrulanmadı: G-V03 OPEN. |
| Inspect / task | `Service::get`, `Services::tasks`, `Service::unseenTasks`, `Vm::status` | Task sorgusunda service public ID gerekir. Task başarı string'i için varsayılmış DTO kullanılmaz; gerçek response mapping henüz yok. G-V03 OPEN. |
| Start / stop / restart | `docs/vm.md`, `Api/Service/Vm.php` | Güç durumu ile faturalama sonu eşitlenmez. |
| Reinstall / extend / terminate | `Api/Service.php` | Metotlar bulunuyor; request alanları ve nihai sonuç/termination ücret etkisi hesapta doğrulanmadı. |
| Upgrade | `Service::upgrade` yorum satırı ve README | Custom-package servis başka catalog ürününe bu yöntemle geçirilemez. Genel downgrade yöntemi varsayılmaz. G-V04 OPEN. |
| VNC / password | `Vm::createVncSession`, `Vm::password`, `docs/vm.md` | VNC sessionToken/password hassastır. Browser/log/idempotency response/history'ye genel DTO olarak taşınamaz; ayrı kısa ömürlü güvenli teslim tamamlanmadan kapalı. |
| Auth | `docs/auth.md`, README, `Http/HttpClient.php` | 6 saat/IP-bound token SDK belgesi iddiasıdır; headless 2FA/güvenilir cihaz sürekliliği gerçek hesapta doğrulanmadı. G-V02 OPEN. |
| Retry | `HttpClient::request` | Transport/5xx retry yalnız GET içindir; **AuthenticationException sonrası credentials varsa aynı request bir defa tekrar edilir ve method filtresi yoktur**. Bu nedenle mali etkili POST için SDK'nın varsayılan withCredentials yolu Hostider güvenli tekrar politikası sayılmaz. Canlı mutation transport'u tasarlanıp G-V02/V03 kapatılmadan bağlanmaz. |

G-V01: 30 günlük müşteri peşin satışı, sağlayıcıda 30 gün prepaid satın alma zorunluluğu anlamına gelmez. SDK'nın `hourly_price`/`daily_price` alanları gerçek muhasebeleştirilmiş maliyet değildir. Minimum ücret, stop/suspend/terminate etkisi ve addon maliyeti hesap kanıtı ister. Gerçek iki saat ondemand billing ve termination görülmeden PROV-005 canlı PASS değildir.

Endpoint/auth/request bilgileri SDK'da görüldü diye uygulamaya hardcode edilmedi. ResellerClub/DomainNameAPI gerçek hesap sözleşmesi incelemesi G-R01/G-D01 olarak OPEN kalır. Genel `src/Providers/Mock/Voyar.php` önceki F0 mock'udur; F4 kalıcı Cloud dünyası veya tamamlanmış entegrasyon sayılmaz.
