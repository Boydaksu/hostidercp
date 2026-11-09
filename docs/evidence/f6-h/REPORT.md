# F6-H Shared hosting release paketi ve kurulum provası

F6-G-Q2 sınırlı bağımsız incelemesi proje sahibi tarafından bildirildi. Runtime paketi kaynak commit'i `8ce41bf547bd866a43a4b437689af9ac11bcfea3`, tree `b248ff2e9a62d025f9346bfa3ade21328471317e`. Release araçları ve paket şablonları F6-H adayında geliştirilip aynı kaynak manifestine bağlandı. Runtime kaynakları ve migration'lar korunuyor.

## Paket ve doğrulama

Yerel geliştirme kopyasında Python 3.9+ ve Git ile `python tools/release/release.py build 8ce41bf547bd866a43a4b437689af9ac11bcfea3 HOSTIDER.zip` çalıştırılır. ZIP dosyası hedef hosting üzerinde bir Python, Git, Composer, Node veya Docker bağımlılığı getirmez. Aynı commit ve şablonlarla iki üretim bayt düzeyinde eşleşti. Builder çalışma ağacındaki uygulama dosyaları yerine seçilmiş Git blob'larını okur. Kurulum kılavuzu ve boş yapılandırma örneği release aracı girdileridir. Bu ayrım dosya envanterinde `origin` ile görünür.

Bağımsız doğrulama `python tools/release/release.py verify HOSTIDER.zip --manifest-sha256 d6969d4a0d773d213400b7c9a100379c6f408109d6c77dbcda16a0970d2eabe7`. Bu işlem Git gerektirmez. `extract` doğrulamanın ardından yalnız yeni hedefe yazar. Arşiv 354 yük dosyası ve manifest içeriyor. [Envanter](release-manifest.json) her dosyanın boyutunu, SHA-256 değerini, formatı, kaynak SHA'yı ve hedef tabanı kaydediyor. ZIP SHA-256 `56251df83e059e58d321efdbadef3289de3c2ef87c243ec054e1446ac3504587`. Manifest SHA-256 `d6969d4a0d773d213400b7c9a100379c6f408109d6c77dbcda16a0970d2eabe7`. İmzasız manifest yalnız envanter ve hash bütünlüğünü gösterir. Güvenilir kanaldan beklenen manifest hash'i alınmalıdır.

İzinli runtime yolları PHP kaynakları, bootstrap, public CSS/JS, migration 001–057 ve console/Identity/hosting-preflight CLI'larıdır. Demo, WHMCS discovery ve offline backup CLI'ları operatör paketinin dışında bırakıldı. Git metadata, geliştirme bağımlılıkları, test fixture dosyaları, eski evidence, WHMCS arşivleri, yerel storage, gerçek env ve kullanıcı dosyası hariç tutuldu. 15 arşiv kontrolü traversal, absolute ve Windows yolları, duplicate/case çakışması, link, bozuk veya eksik içerik, beklenmeyen PHP, duplicate JSON ve güvenli fresh extraction kapsamını geçti.

## İzole kurulum

İki çalışma paket ZIP'inden fresh extraction ile başladı. Uygulama container'ına yalnız çıkarılan paket ve ayrı smoke harness'i bağlandı. Native autoload, tam envanter ve dosya hash'leri uygulamadan önce doğrulandı. Root olmayan PHP ve ayrı iç Docker ağı kullanıldı. MariaDB portu hosta açılmadı. Runtime hesabı sentetik şemada DML ile sınırlıydı, bu hesapla health geçti ve migration DDL reddedildi. Şema sahibi fixture hesabı migration kurulumunu yürüttü. Üretim asgari tablo yetkileri ayrıca doğrulanmalı.

Paketin ilk kurulumunda sentetik tablo çakışması ilk DDL'yi kalıcı bıraktı. APPLYING kaydı hem readiness hem tekrar migration'ı engelledi. Bilinen boş kısmi nesnelerin ve sentetik APPLYING kaydının kontrollü temizliğinden sonra native runner 57 migration uyguladı. Tekrar uygulama boş sonuç verdi. Bu işlem gerçek bir şemada güvenli otomatik rollback kanıtı değildir. Gerçek geri dönüş kod, DB, storage ve anahtar eşliği ile ayrı restore kanıtı gerektirir.

Native Identity bootstrap, HTTP health, public CSS, özel session dizini ve güvenli cookie başlangıcı geçti. Kaynak/config/storage ve diğer kardeş yollar loopback HTTP üzerinden erişilemedi. Smoke harness'i HTTPS göstergesini yalnız yerel HTTP sunucusunda simüle etti. Apache rewrite, gerçek TLS, vhost ve panel davranışı doğrulanmadı. Root olmayan süreçte 0500 session dizini yazılabilir görülmedi. PHP 8.2.35 gerçek eski PHP reddi ve iki hedefte no-ini eksik extension reddi geçti.

Cron değerlendirmesi 1 dakikalık plan için komutların statik incelenmesi ve ayrı sentetik native Outbox/Queue smoke çalıştırmasından oluştu. Yerel core.probe dış etki oluşturmaz. Outbox bir kaydı aktardı, sonraki çağrı boş döndü. Worker --once bir işi tamamladı, sonraki çağrı boş döndü ve --once olmadan çağrı reddedildi. Cron kurulmadı. Sağlayıcının aralığı, çalışma süresi ve overlap politikası G-O01 kapsamında açık. Ana domain document root cPanel kullanıcı arayüzünde değiştirilemez. Diğer domain yerleşimleri sağlayıcıya bağlıdır. Paketin public alt dizinine güvenli eşleme yapılamıyorsa model BLOCKED. [Kurulum ve rollback kılavuzu](../../../tools/release/INSTALL.md) · [cPanel domain belgeleri](https://docs.cpanel.net/cpanel/domains/domains/manage-the-domain/) · [cPanel cron belgeleri](https://docs.cpanel.net/cpanel/advanced/cron-jobs/)

## Sonuçlar ve koruma

PHP 8.3.36 ve 8.4.27 üzerinde ayrı ayrı **21/0 paket kurulum kontrolü** ve **125/0 kaynak regresyonu**. F6-G preflight, Q1 yedi test, Q2 Identity ve ilgili Identity HTTP/Security, Session, Integration ve Hardening suite'leri dahil. İki sürümde PHPStan level 5, lint, pattern, belge ve kaynak manifest kontrolleri geçti. FULL, Chromium ve GitHub Actions NOT_RUN. İlk başarılı adayın paketleme kontrolü Windows satır sonunu işaretledi. LF düzeltmesi ve cron etki bayrağı assertion'ı son kaynakta yeniden doğrulandı. İlk koşular tabloda kendi manifestleriyle duruyor.

| PHP | Aşama | Koşu | PASS | Exit |
| --- | --- | --- | ---: | ---: |
| 8.3.36 | install | hostider-f6h-83-68e737c8 | 21 | 0 |
| 8.3.36 | regression | hostider-f6h-83-d9c6581a | 125 | 0 |
| 8.3.36 | install | hostider-f6h-83-f19dc9f5 | 21 | 0 |
| 8.3.36 | regression | hostider-f6h-83-fb54ffb6 | 125 | 0 |
| 8.4.27 | regression | hostider-f6h-84-53060a71 | 125 | 0 |
| 8.4.27 | regression | hostider-f6h-84-869fe3d4 | 125 | 0 |
| 8.4.27 | install | hostider-f6h-84-95ffc4b2 | 21 | 0 |
| 8.4.27 | install | hostider-f6h-84-d7215e1f | 21 | 0 |

[555 dosyalık kaynak manifesti](source-manifest.json) SHA-256 `28f1622142a94532bbdea59671e148b62ee8bc14ad3e68ea25367c8e970be65f`. Önceki 549 kaynak dosyası, tüm eski kanıtlar, migration 001–057 tree `01b51a655655cb4c7483d6b5937555504264ca3f` ve kullanıcının yerel inceleme dosyası korundu. Repo değişikliği release araçları, test ve yeni kanıtlarla sınırlı. Paket ZIP'i yerel teslim artefaktıdır. Commit ve yeni tree teslim mesajında verilir. Geri dönüş bu araç ve kanıt commit'inin ayrı yetkiyle geri alınmasıdır.

Gerçek cPanel kurulumu NOT VERIFIED. G-O01, G-O02, G-O03, OPS-002 ve OPS-003 OPEN. WHMCS migration DEFERRED. Staging, production ve canlı kurulum yetkisi yok.
