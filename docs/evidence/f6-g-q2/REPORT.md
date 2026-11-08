# F6-G-Q2 Deterministik Identity throttle regresyonu

Q1 tabanı `681bbee480d15846e0e32e772bdf0d5ce6f42348` ve tree `419b8e8431c6dc06050fbbdf176a70644a38c41f`. Q1 sınırlı bağımsız incelemesi proje sahibi tarafından bildirildi.

Eski F1-B testi aynı hesap için 10 başarısız girişten sonra 429 bekliyordu. Servis login hesabı 300 saniyelik sabit pencere kullanıyor. İstekler sınırı geçerse yeni pencere boş sayaçla başlıyor. Q1 kontrollü karşı örneği bu durumda beklenen exception'ın gelmediğini gösterdi. Tarihsel F1-B logunda assertion ayrıntısı ayıklanmış olduğu için bu koşunun kesin nedeni `ROOT_CAUSE_UNVERIFIED`. Önceki başarısız sonuç ve karşı örnekler Q1 kanıtlarında korundu.

Yalnız `tests/identity.php` değişti. Mevcut bootstrap, 10 normal kimlik doğrulama reddi ve 11. istekte HTTP 429 assertion'ları korundu. Kontrol başlangıç, orta ve son saniyede, ayrı sentetik pencerelerde çalışıyor. Yeni test önce pencerenin son saniyesinde 429 ve RATE_LIMITED doğruluyor. Sonraki pencerenin ilk saniyesindeki isteğin normal kimlik doğrulama reddine ulaştığını doğruluyor. Test helper'ı mevcut integrationDb test ortamı kontrolünden geçen bağlantıda `SET SESSION timestamp` kullanıyor. Zaman ayarı `finally` içinde DEFAULT değerine dönüyor. Diğer bağlantılar ve PHP duvar saati bu ayarın dışında.

Her PHP sürümünde üç Identity tekrarı 17/0, Q1 yedi test 7/0. Identity Security, Identity HTTP, Session, Session Concurrency, Integration ve Hardening Unit dahil toplam **113 PASS / 0 FAIL**. İki sürümde PHPStan level 5, lint, pattern ve belge kontrolleri başarılı. Yerel Docker iç ağında, geçici MariaDB ve non-root PHP ile çalıştırıldı. Testler için gerçek saat sınırı beklenmedi. Sonuç JSON'larında sabit imaj kimlikleri, suite ayrıntıları ve kaynak eşliği kayıtlı.

İlk Q2 adayında parametreli zaman ayarıyla iki sürümde de yeni zaman kontrollü testler başarısız oldu. Ayrı sentetik prob aynı SQL biçimi için iki sürümde de `42000:1232` verdi. Q1 ile aynı tamsayı SQL biçimi kullanıldı ve tüm assertion’lar korundu. İlk koşular aşağıda FAILED olarak duruyor. Bu bulgu tarihsel F1-B hatasından ayrıdır.

| PHP | Koşu | PASS | FAIL | Exit |
| --- | --- | ---: | ---: | ---: |
| 8.3.36 | hostider-q2-83-7f62b380 | 113 | 0 | 0 |
| 8.3.36 | hostider-q2-83-8ed71ddd | 107 | 6 | 1 |
| 8.4.27 | hostider-q2-84-83cb946b | 107 | 6 | 1 |
| 8.4.27 | hostider-q2-84-f5bee035 | 113 | 0 | 0 |

[549 dosyalık kaynak manifesti](source-manifest.json) SHA-256 `dab9c38a41f46c14a2f0b8a1514f93ee063a954354a37c94429cc9ff73fa1bd6`. Üretim RateLimiter ve Identity davranışı, migration 001–057 subtree `01b51a655655cb4c7483d6b5937555504264ca3f`, önceki kaynak ve kanıtlar ile yerel kullanıcı düzenlemesi korundu. Şema değişikliği yok. Geri dönüş yalnız bu test ve yeni kanıt dosyalarının ayrı yetkiyle geri alınmasıdır.

Yayın tek fast-forward commit üzerinden yapılır. Commit SHA ve Git tree teslim mesajında yer alır. [GitHub değişiklik karşılaştırması](https://github.com/Boydaksu/hostidercp/compare/681bbee480d15846e0e32e772bdf0d5ce6f42348...codex/f5-support-center)

```text
F6-G-Q2: DEVELOPMENT VALIDATED / INDEPENDENT REVIEW PENDING
F6-G-Q1: LIMITED INDEPENDENT CODE REVIEW PASSED
HISTORICAL F1-B ROOT CAUSE: UNVERIFIED
REAL HOSTING COMPATIBILITY: NOT VERIFIED
PRODUCTION: BLOCKED
```
