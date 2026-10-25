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

## Ara denetim: sentetik transport ile yeniden üretim

Özgün `src/Voyar/Client/Http/HttpClient.php`: satır 193 network/5xx retry'ı GET ile sınırlar; satır 223–227 AuthenticationException catch'i method kontrolü yapmadan `retriedAuth=true`, token clear ve `continue` uygular. Böylece POST da auth yenilemesi ardından yeniden gönderilir. Satır numaraları yukarıdaki hash'i sabit ZIP içindir; güncel vendor sürümü iddiası değildir.

`tests/voyar-sdk.php` özgün ZIP'in SHA-256'sını doğrular ve `zip://` ile yalnız test sürecinde kaynak sınıflarını okur. ZIP çıkarılmaz/değiştirilmez; CurlTransport yaratılmaz ve gerçek ağ kullanılmaz. Sabit synthetic token ile ilk POST 401/logout döndürülür, auth/login başarılı olur ve özgün SDK'nın aynı mali etkili POST'u ikinci kez gönderdiği çağrı sayımıyla kanıtlanır. Create, extend/renew, terminate, reinstall, upgrade, start/stop/restart, password ve VNC için mutation sayısı **2**'dir. `max_retries=0` bunu engellemez: bu ayar auth replay'i kapsamıyor.

`Providers/VoyarMutationFence` tek dispatch için gerçek mutation transport gönderimini **önce** tüketir. Sentetik transport her mutation send'ini fence üzerinden geçirince SDK auth yenilemesini tamamlasa bile ikinci mutation 409 `VOYAR_MUTATION_REPLAY_BLOCKED` ile gönderilmeden durur; fiziksel mutation sayısı **1** kalır. G-V02/G-V03 canlı dispatch bütün test dışı ortamlarda kapalıdır. Bu sınıf kalıcı operation/lease/idempotency kaydının yerine geçmez ve henüz canlı SDK adaptörü veya Cloud worker'a bağlanmış kabul edilmez.

Bağlantı timeout'u ile 200 yanıtının body kaybı ayrı sentetik transport senaryolarıdır; ikisi de SDK TransportException üretir, auth/login çalışmaz, mutation sayısı 1 kalır ve fence tekrar açılmaz. Auth yenilemesinin başarılı olması ilk mutation'ın etkisizliğini kanıtlamaz. Provider task belirsizliği bu testlerin kapsamı değildir; Cloud task/resource inspect ve manual reconciliation tamamlanmalıdır. Bu sonuçlar G-V02/G-V03'ü kapatmaz.

## F4-D kalıcı mock eşlemesi

Kaynak ZIP/hash aynıdır; özgün kaynak değiştirilmedi. `tests/cloud-sdk.php` SDK’yı salt okunur
zip:// autoload ile ve yalnız enjekte sentetik transport ile çalıştırır. `Engine.fence()` callback’i
Cloud operation/lease/fingerprint kilidi altında `cloud_dispatches` satırını I/O öncesinde commit eder.
Auth/login sonrasında SDK ikinci POST’u denese bile hem süreç içi fence hem yeni süreçlerde kalıcı
admission ikinci gönderimi engeller. SDK create/stop/restart/reinstall/upgrade/terminate yöntem ve
URL sonları gerçek arşiv kaynaklarıyla test edilir; önceki testler start dahil on yöntemi korur.

| Cloud eylemi | SDK kaynağı / gerçek yöntem | Mock sonucu / canlı sınır |
|---|---|---|
| CREATE | Api/Services.php::create | ACCEPTED task kabulüdür; VM başarısı değildir |
| POLL / INSPECT | Services::tasks(serviceid), Service::get/unseenTasks, Vm::status | Sentetik normalize task/resource gözlemi. Gerçek array/result şeması mapper’ı YOK; G-V03 OPEN |
| START / STOP / RESTART | Api/Service/Vm.php::start/stop/restart | Güç durumu ayrı; stop maliyet kesimi kanıtı değildir |
| REINSTALL | Api/Service.php::reinstall(osid) | Fresh scoped staff + payload/version bağlı onay, task/resource kontrolü |
| UPGRADE | Api/Service.php::upgrade(pid) | Yalnız explicit sentetik ücretsiz hedef; custom upgrade, genel downgrade ve ek ücretli değişim kapalı |
| TERMINATE | Api/Service.php::terminate | Explicit onay + policy bekleme süresi + doğrulanmış terminated kaynak/task |
| EXTEND | Api/Service.php::extend(days) | Yöntem mevcut; yeni Cloud renewal finans kaynağı tamamlanmadığından bu fazda dispatch kapalı |
| Custom config | CustomPackageSpec::toArray; CustomPackage::options/quote | Exact spec alanları eşlenir; Hostider mock min/max/step/ratio policy sentetiktir, gerçek response alanı değildir |
| Password / VNC | Vm::password/createVncSession | Runtime Cloud public method/DTO yok; normal log/history/projection’a taşınmaz |

İç UUID ile sağlayıcının opak service/task referansı ayrıdır. cloud_effects tekil operation/task,
account, provider resource/task ref, resource version ve MOCK provenance saklar. Bu F4-E için
başlangıç/bitiş durum kaynağıdır; kullanım ölçümü, saatlik tick, maliyet, bakiye veya gerçek ücret
kanıtı DEĞİLDİR. F4-E/F başlatılmadı. Canlı SDK transport ve response normalization hâlâ kapalıdır.
