# F2 CI kararsızlığı — kök neden ve kapanış kanıtı

23 Ekim 2026. Başlangıç HEAD `d78e708de69f0fd0c1cfdf020cf1d0d026d06439`; önceki başarılı uygulama `d4fc4b705768d1eab1a5eda20c584f7ad91c2b65`. F3, production ve gerçek finans/sağlayıcı işlemi başlatılmadı. Ledger, müşteri izolasyonu, iş modülleri ve 001–022 migration geçmişi değiştirilmedi.

## 1. İlk hata ve kanıtın sınırı

[Başarısız run 39982782126](https://github.com/Boydaksu/hostidercp/actions/runs/39982782126) tam logları indirildi. PHP 8.3.36 job'ında ilk HTTP senaryosu 15:59:09 UTC'de geçti. İkinci quote/order senaryosu 15:59:10 UTC'de ilk `200 != 0` hatasını verdi. Sonraki bağlantılar da 0 döndü; `$httpCommerceOrder` ve `$reviewReplay` eksiklikleri birincil hata değildi. PHP 8.4.27 HTTP paketi 10/10 geçti, job matrix fail-fast nedeniyle sonradan cancelled oldu. İptal edilmiş job başarılı sayılmadı.

Eski harness curl errno/error, server exit/signal veya stderr artifact'i kaydetmiyordu. Dolayısıyla eski koşunun doğrudan core dump'ı yoktur. Kaynakta eski `curl_exec(false)` sonrasında curl hata kontrolü yapılmadan status 0 assertion'a aktarılıyordu. İlk readiness döngüsü de 40 başarısız denemeden sonra hatasız devam edebiliyordu. Sabit port, sessiz başlangıç ve kaynak sonlanması olasılıkları önce hipotez olarak tutuldu; kanıt olmadan kök neden sayılmadı.

Seçilmiş orijinal loglar: [PHP 8.3](evidence/f2-ci-stability/original-php83-http.txt), [PHP 8.4](evidence/f2-ci-stability/original-php84-http.txt).

## 2. Yeniden üretim: native SIGSEGV ve JIT bağımlılığı

`3a706ed128a881a6109b8461419ec1040f0064d1` yalnız tanı altyapısını ekledi; native motor ayarlarını miras almaya devam etti. [Run 39985089198](https://github.com/Boydaksu/hostidercp/actions/runs/39985089198) PHP 8.3'te 30 HTTP suite tekrarının **5'inde** aynı quote `prepare` isteğinde çöktü. İstek yolunun SHA-256 değeri `7a012c70fe2f1e77f6c39759eba9cc5206bf8cb15ec3f6cf191e6b8588d48ca2`, `/api/v1/client/commerce/prepare` ile eşleşir. Curl errno **52 / empty reply**; sonraki process snapshot **signaled=true, termsig=11 (SIGSEGV)**. Stderr fatal/OOM/listen marker'ı yoktu. Bazı ilk snapshot'larda running=true görülmesi süreç kapanmasıyla yarışan gözlemdir; stop snapshot gerçek sinyal 11'i kaydetti. Bu nedenle hata normal uygulama 4xx/5xx yanıtı veya test assertion'ı değildir.

Dinamik port ve instance/PID doğrulaması altında aynı arıza tekrarlandığından sabit port çakışması yeterli açıklama değildir. Yeni port/readiness düzeltilmesi tek başına sorunu kapatmadı. Kontrollü GDB deneyi 10/10 geçti; o deneyde SIGSEGV yakalanmadığından native C stack veya belirli upstream PHP bug/patch kimliği iddia edilmiyor. GDB kurulumu uzun sürünce iptal istendi; koşunun gerçek sonucu ve başarısız normal tekrarlar korunur. Sonraki tanı adımları süre sınırına alındı.

### Kontrollü A/B

`6000236d3ec4d1d422f500cef0ebca9fcdf32c77` aynı HTTP test kaynağını iki server modunda çalıştırır: `inherit` mevcut PHP ini'sini kullanır; `interpreter` yalnız child built-in server'a `-d opcache.jit=disable -d opcache.jit_buffer_size=0` ekler. PHP sürümü, uzantılar, uygulama ve assertion'lar değişmez. Readiness yanıtından child'ın gerçek `opcache_get_status(false)` JIT durumu kaydedilir; parent `opcache.enable_cli=0` bilgisinin cli-server için JIT kapalı demek olduğu varsayılmaz.

[A/B run 39986996418](https://github.com/Boydaksu/hostidercp/actions/runs/39986996418):

| PHP / child motoru | Bağımsız suite koşusu | Başarısız | Sonuç |
|---|---:|---:|---|
| 8.3.36 / inherit JIT | 50 | 7 | failure, maskelenmedi |
| 8.3.36 / interpreter | 50 | 0 | success |
| 8.4.27 / inherit JIT | 50 | 0 | success |
| 8.4.27 / interpreter | 50 | 0 | success |

Bu negatif kontrol workflow'sunun kırmızı olması beklenen deney kanıtıdır; F0 kabul workflow'sunun başarısı olarak raporlanmaz. `continue-on-error` ile yeşile çevrilmedi. Aynı runner/CPU/PHP/DB üzerinde ardışık ek kontrol `tools/compare-http-engines.php` ile yapıldı: [run 39987367336](https://github.com/Boydaksu/hostidercp/actions/runs/39987367336), PHP 8.3 inherit **5/30 başarısız**, hemen ardından interpreter **0/30 başarısız**; PHP 8.4 iki modda da **0/30 başarısız**. Job 8.3 failure, 8.4 success; negatif kontrolün toplam sonucu failure olarak korunur. Child runtime kanıtında inherit `enabled=true,on=true,kind=3,opt_level=5,buffer_size=268435440`; interpreter `enabled=false,on=false,buffer_size=0`. Uygulama OPcache'i açık kalır; yalnız JIT kapatılır.

**Kök neden sınıflaması:** PHP 8.3.36 built-in server'ın JIT etkin yürütme yolunda native SIGSEGV. JIT'i child server'da devreden çıkarmak, aynı işlemin interpreter ile doğrulanmasını sağlar. Bu, upstream PHP motorunu düzelttiğimiz iddiası değildir; test harness'ın gereksiz/kararsız JIT bağımlılığını kaldıran sınırlı düzeltmedir. Tarihsel koşunun doğrudan sinyali saklanmadığından orijinal olayla bağlantı eşdeğer yeniden üretim ve A/B kanıtına dayanır; bu ayrım gizlenmez. Production PHP JIT ayarı değiştirilmedi veya güvenli ilan edilmedi; JIT açık production dağıtımı ayrıca doğrulanmadan yayın GO yoktur.

## 3. Harness düzeltmeleri ve korunmuş testler

- `tests/support/HttpTestServer.php`: kernel'in atadığı port (`127.0.0.1:0`), benzersiz instance, PID ve readiness doğrulaması; 10 saniye başlangıç sınırı; uygulama health yanıtının ayrıca 200 olması; stdout/stderr dosyaları instance başına ayrılır.
- Curl false/status 0 artık assertion'a çevrilmez. İlk transport hatası errno, sabit curl hata açıklaması, route hash, süreç running/exit/signal ve sınırlı JIT runtime bilgisiyle `HttpTestTransportFailure` üretir. Sinyal kapanmasını görmek için sadece hata tanısında en fazla 250 ms gözlenir; HTTP isteği tekrar gönderilmez.
- Tanılar body, cookie, header, token, APP_KEY/DB şifresi veya ham stderr içermez. Stderr byte sayısı/hash ve allowlist marker'ları korunur. Ham loglar cleanup'ta silinir; CI yalnız JSON tanıları yükler. Artifact ilk hata sonrasında ve job sonunda `always()` ile alınır.
- Stop idempotenttir, yalnız başlatılan process resource'una uygulanır; sınırlı terminate/kill ve proc_close kullanılır. Porttaki başka sunucu kapatılmaz. Çakışan explicit port/yanlış readiness, owner sunucusuna zarar vermeden test edilir.
- Commerce paketi sıralı bir integration senaryosudur: login→quote/order→payment→PDF ve replay. On mevcut testin assertion'ı korunur; ilk hata sonrası bağımlı testler **BLOCKED**, passed sayılmaz. İlk gerçek hatanın arkasından eksik global değişken assertion seli üretilmez. Başarılı koşuda on senaryonun tamamı çalışır.
- Matrix `fail-fast:false`: PHP 8.3 hatası PHP 8.4 kanıtını iptal etmez. Her job 15 dakika, native tanı adımı 3 dakika ve kendi komutu 90 saniye ile sınırlıdır.

`tests/http-harness.php` beş yeni regression içerir: bağımsız ephemeral listener/readiness; durmuş server'da maskelenmiş ilk hata; explicit port çakışmasında owner korunması; fixture cleanup path sınırı; Windows delete-pending dosya yaşam döngüsü ve cleanup. Önceki **175 senaryo korundu**, toplam **180 test**. Finansal komut retry, assertion gevşetme veya migration yoktur.

## 4. Windows session-concurrency temizliği

Eski suite kaynak değişmeden Windows PHP 8.4.24 / OneDrive workspace'te 30 kez çalıştırıldı: **1/30 başarısız**. Hata stale replacement senaryosu teardown'unda `rmdir: Directory not empty`; child stderr boştu. Böylece önceki hata tekrar üretildi; kayboldu diye kapatılmadı.

Kontrollü deney: dosya başka süreçte açıkken `unlink` true, `file_exists` false; Windows `rmdir` yine nonempty hatası verir. Handle kapandığında dizin kaldırılır. Linux aynı deneyde açık fakat unlink edilmiş dosya, dizinin kaldırılmasını engellemez. Bu Windows delete-pending davranışını doğrudan gösterir. Orijinal anlık handle'ın hangi harici sürece ait olduğu kanıtlanmadı; OneDrive veya antivirüs kesin neden diye sunulmaz.

`FixtureDirectory::remove()` yalnız gerçek storage altındaki `session-test-<random>` / `harness-test-<random>` dizinlerini temizler. Symlink/üst dizin reddedilir, recursive silme yoktur. Teardown en fazla 2 saniye bekler; ilk görünür dosya sayısı, deneme/uyarı sayısı ve süre JSON'a yazılır. Süre dolarsa test gerçek failure olur. Session assertion'ı veya child çalışması tekrar edilmez. Gerçek yerel cleanup tanısında görünür dosya 0 iken ikinci denemede 29,58 ms'de başarı görüldü; kontrollü tutulan handle deneyi de çoklu denemeyi doğrular.

Düzeltme sonrası Windows **30/30 session suite** geçti; 210 mevcut session senaryosu. Yerel HTTP **20/20 suite** geçti. Linux kabul CI'ında PHP 8.3/8.4 başına **20/20 session suite** ve aynı delete-pending regression'un Linux dalı geçti. Ham tekrar özetleri `evidence/f2-ci-stability/windows-*.json` ve CI artifact'lerindedir. Yeni bir cleanup timeout gizlenmez.

Bir yerel tam paket HTTP tekrar deneyiyle yanlışlıkla aynı DB'de çakıştırıldı ve iki audit count assertion'ı etkilendi. [Başarısız çıktı](evidence/f2-ci-stability/local-db-overlap-failure.txt) korunur; bu koşu başarılı sayılmaz. Sonraki tam paket ve DB'li deneyler seri çalıştırıldı; [yerel çıktı](evidence/f2-ci-stability/local-full-tests.txt) 180/180 başarılıdır. CI job'ları ayrı MariaDB service'leri kullanır.

## 5. 173 / 171 dosya farkı

Başlangıç commit'inde kontrolün kapsadığı **171 tracked PHP dosyası** vardır. Yerel `tools/check.php` ayrıca Git'in dışladığı iki scratch dosyasını saymıştı: `tools/local/f2-preview.php`, `tools/local/f2-preview-migrate.php`. 171+2=173. CI checkout'unda bu dosyalar yoktu. Eksik kaynak veya atlanan test değildir; önceki raporda yerel 173 sayısı CI sayısı gibi okunmamalıdır.

[Dosya manifesti ve açıklama](evidence/f2-ci-stability/php-count-manifest.json). Checker artık audit aracıyla tutarlı biçimde `tools/local/` dizinini kapsam dışı bırakır. Güncel tracked sayı **177**: 171 eski + altı yeni harness/araç PHP dosyası. Yerel ve CI aynı kapsamı sayar. Eski kanıt dosyaları değiştirilmez; tarihsel raporların sayısı bu açıklamayla düzeltilir.

## 6. Main koruması ve uygulanacak kurallar

[Salt okunur güncel API kanıtı](evidence/f2-ci-stability/branch-rules.json): main protected=false, applicable rules boş. Repository yönetim ayarı değiştirilmedi.

Repository sahibi GitHub Settings → Rules → Rulesets üzerinden main hedefli aktif branch ruleset oluşturmalı: PR zorunlu, en az bir bağımsız inceleme, yeni push sonrası eski onayları düşürme ve konuşma çözümü; force-push/delete engeli; bypass'ı sınırlama. Required status checks olarak **F0 foundation** workflow'sunun gerçek `verify (8.3)` ve `verify (8.4)` sonuçları seçilmeli, güncel base zorunlu olmalı. Bilerek hata üreten **F2 HTTP engine investigation** negatif kontrolü release check olarak seçilmemeli. Ruleset oluşturulduktan sonra yetkili kişiyle deneme PR'ında direkt push/eksik review/kırmızı CI merge engeli doğrulanmalı. Bu ayarların uygulandığını veya test edildiğini iddia etmiyoruz.

Uygulama adımları ve seçenekler [GitHub'ın ruleset oluşturma belgesi](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/creating-rulesets-for-a-repository) ve [kural seçenekleri](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/available-rules-for-rulesets) ile kontrol edildi. Plan/repository uygunluğu ve bypass listesi repository sahibi tarafından değerlendirilmelidir.

## 7. Test/CI teslim kaydı ve karar

`3a706ed`: hata yakalama deneyi. `6000236`: test server JIT izolasyonu, bounded cleanup ve beş yeni regression. `132937a`: aynı runner üzerinde sıralı A/B aracı; iş kodu değişmez. Kaynak kontrol diff'i ve son koşu makine kanıtları `evidence/f2-ci-stability/` altındadır.

[Kabul CI 39986996036](https://github.com/Boydaksu/hostidercp/actions/runs/39986996036), kaynak `6000236`: PHP 8.3.36 ve 8.4.27, her birinde **180/180**, ayrıca **30/30 HTTP suite + 20/20 session suite**, PHPStan, Composer audit, lint/security scan ve JS syntax success. Aynı summary'nin iki artifact'te yüklenmesi iki ayrı deney sayılmaz; tekrar sayıları dosya adı/içerik hash'iyle tekilleştirilir.

İkinci bağımsız kabul koşusu [39987366562](https://github.com/Boydaksu/hostidercp/actions/runs/39987366562), kaynak `132937a98a8f94a63ff3f0d2374e7af7ac6d5960`: her iki PHP sürümü **180/180**, ilave **30/30 HTTP + 20/20 session suite** ve zorunlu statik/güvenlik kontrolleri success. Bu iki kabul koşusunda PHP sürümü başına toplam **60 ek HTTP suite / 600 HTTP senaryosu** ve **40 session suite / 280 session senaryosu** hatasızdır; A/B interpreter 80 koşusu ve precursor 30 koşusu bu sayıya ayrıca eklenip iki kez sayılmaz.

[Son yerel lint/PHPStan/audit/Composer kanıtı](evidence/f2-ci-stability/final-checks.txt). Bütün başlangıç 175 test + 5 yeni test başarılı; 177 PHP dosyası lint/mimari, güvenlik pattern scan, PHPStan level 5 ve Composer audit temizdir. Pattern scan tam penetrasyon testi değildir. Kapanış commit'i yayınlandıktan sonra kendi HEAD CI sonucu ayrıca doğrulanır; önceki başarılı uygulama SHA'sından yeni HEAD'e otomatik başarı atfedilmez.

**Karar:** ölçülen PHP 8.3/8.4 interpreter test sunucusu ortamında CI kararsızlığı giderildi; JIT açık PHP 8.3 negatif kontrolü hâlâ gerçek başarısızlıktır. Upstream JIT motorunun iç native hatası düzeltilmiş değildir, test harness bu gereksiz bağımlılıktan ayrılmıştır. Kullanıcı kabulü sonrası **F3 mock/sözleşme geliştirmesi teknik olarak uygundur**; F3 bu görevde başlatılmaz. Production veya JIT açık dağıtıma GO verilmez. Main koruması, operasyon kabulü ve önceki tüm GATE'ler açık kalır. Nihai HEAD kabul CI'sı başarısız olursa bu karar askıdadır; kullanıcıya başarılı denmez.
