# F6-D-R2 hardening — Docker Desktop doğrulama kaydı

## Kapsam ve plan

Kullanıcı, GitHub billing limitini gideremediğini bildirerek Docker Desktop üzerinden test yapılmasını istedi. Kapsam yalnız mevcut hardening adayının yerel doğrulanması ve kanıtların belgelenmesidir. Uygulama, mevcut test gövdeleri, CI workflow/registry ve migration değişmez. Yeni özellik, refactor, FULL ACCEPTANCE, Chromium, stability maratonu veya yeni GitHub koşusu yoktur.

Sabit aday SHA: `53162495902b3b168c2fb8554594e99c23a34d62`.
Tree: `e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47`.

Dosya planı: yalnız bu rapor, hardening raporuna bağlantı ve `docs/evidence/f6-d-r2-hardening/docker/` altındaki temizlenmiş kanıtlar. Kaynak ayrı Docker volume içine Git bundle üzerinden exact SHA ile alınır; host çalışma ağacındaki kullanıcı değişikliği veya gizli ortam dosyaları içeri alınmaz. PHP 8.3 ve 8.4 için ayrı owned MariaDB, internal network ve host portu yayımlanmayan geçici ortam kullanılır. Kilitli analiz bağımlılıkları hazırlanırken ağ erişimi vardır; uygulama testleri yalnız internal DB ağı üzerinden çalışır.

Test planı: FAST kalite adımları ve kayıtlı native broad runner. İlk başarısız sonuç korunur; yalnız başarısız paket ve ulaşılmayan paketler ayrıca çalıştırılır. Özet satırındaki nominal test sayısı, fail-fast nedeniyle çalışmayan testleri geçmiş saymak için kullanılmaz.

Migration: production 001–054 değişmez. Rollback: yalnız bu docs ekleri geri alınabilir; uygulama değişikliği yoktur. Temizlik yalnız bu çalışmanın etiketli Docker kaynaklarıdır; mevcut kullanıcı container/volume/network kaynaklarına müdahale edilmez.

## Kabul sınırı

Yerel Docker sonucu, GitHub Actions FAST PASS değildir. GitHub koşusu 40639410631 billing nedeniyle testler başlamadan başarısızdır; yeni GitHub retry/dispatch yapılmaz. R2 bağımsız kabul PENDING; native runtime recovery BLOCKED; F6-E yetkisiz; gerçek sistem GATE'leri OPEN ve production değişmez.
## Sonuçlar — 28 Ekim 2026

Docker Desktop 4.86.0 / Engine 29.7.2, Linux amd64 üzerinde PHP **8.3.36** ve **8.4.27**, MariaDB **10.11.18** kullanıldı. İmaj ID'leri sonuç JSON dosyalarında sabittir. Composer 2.10.3, Node 18.20.4 ve npm 9.2.0 yerel araçlardır; bu ortam GitHub runner eşdeğerliği iddiası taşımaz.

Her iki PHP sürümünde kalite adımlarının tamamı exit 0: kaynak doğrulama, CI scope, doküman kontrolü, 6 helper testi, locked Composer audit, PHPStan, lint, pattern audit, dört JS syntax kontrolü, üç JS state kontrolü ve locked npm audit. Uygulama SHA/tree başlangıç ve bitişte aynıdır. Adayın 134 eski test dosyası blob koruma kanıtı [önceki preservation kaydındadır](evidence/f6-d-r2-hardening/preservation.json); bu görev kaynak/test/registry değişikliği yapmadı.

| Çalıştırma (her PHP sürümünde) | Sonuç |
| --- | --- |
| İlk native broad | 50 tam paket / 398 test geçti; destek paketinde 59 PASS + 1 FAIL, 97 çalışmadı; exit 1 |
| Başarısız paket ve ulaşılmayan 9 paketin devamı | Destek: 60 PASS + 1 FAIL, 96 çalışmadı; diğer 9 paket / 227 test PASS; genel exit 1 |
| Geçen kayıtlı paketlerin toplamı | **59 paket / 625 test**, destek paketi hariç |
| Mevcut R2 | **24 PASS / 0 FAIL** |
| R1 | **25 PASS / 0 FAIL** |
| Yeni hardening | **2 PASS / 0 FAIL** |
| Kalan destek closure'larının ayrı tanı çalışması | **97 çalıştı / 31 PASS / 66 FAIL**; resmi paket başarısı değildir |

İlk native broad hatası: `SUP-006 native HTTP permission revoke session version and stale step-up cut access`. Aynı değişmeyen test PHP 8.4'te tek başına ve her iki sürümde paket tekrarında geçti. Paket tekrarı bu kez `F5-B additive 049 to 050 upgrade replay and old checksums are preserved` testinde durdu. Bu test de PHP 8.4'te tek başına geçti. Sonraki 97 closure, mevcut b/b-post-audit/c/d test kayıtlarından başarısız upgrade sınırından itibaren dış tanı sürücüsüyle seçildi; assertion ve test gövdeleri değiştirilmedi. Bu çalışmanın 66 hatası, ilk fail-fast nedeniyle daha önce çalışmayan mail/advanced destek testlerindedir. Örnek ilk mail testi (`SUP-001 malformed MIME is encrypted bounded review and oversized raw writes nothing`) PHP 8.4'te tek başına geçti.

Bu veriler paket sırası/ortak durum veya ortam etkisinin araştırılmasını gerektirir; kök neden kesinleştirilmedi. 66 tanı hatası yeni hardening koduna mal edilmez; aynı şekilde tekil PASS sonuçları paket hatalarını geçersiz kılmaz. Mevcut testler gevşetilmedi, kaynakta düzeltme yapılmadı ve geniş paket tekrar döngüsüne girilmedi. **60 paket / 782 test toplu PASS kanıtı yoktur.** Harness'in nominal `157 tests` satırı, fail-fast sonrası çalışmayan testleri geçti olarak saymaz.

[Özet ve araç sürümleri](evidence/f6-d-r2-hardening/docker/summary.json), [PHP 8.3 ilk tur](evidence/f6-d-r2-hardening/docker/sanitized-83.txt), [PHP 8.4 ilk tur](evidence/f6-d-r2-hardening/docker/sanitized-84.txt), [8.3 devam](evidence/f6-d-r2-hardening/docker/continuation-sanitized-83.txt), [8.4 devam](evidence/f6-d-r2-hardening/docker/continuation-sanitized-84.txt), [8.3 kalan tanı](evidence/f6-d-r2-hardening/docker/remaining-sanitized-83.txt), [8.4 kalan tanı](evidence/f6-d-r2-hardening/docker/remaining-sanitized-84.txt), [tekil tanılar](evidence/f6-d-r2-hardening/docker/focused-diagnostics.json). Loglar güvenli test isimleri, sayımlar ve SHA kanıtlarıyla filtrelendi; raw hata gövdeleri/HTTP yanıtları dışarı aktarılmadı. İlk hazırlık denemesinde eksik APPLICATION_SHA nedeniyle test başlamadan duran devam komutu düzeltildi; sonuç dosyaları yalnız uygulama testlerine ulaşan devam turuna aittir. Windows PowerShell execution-policy nedeniyle başlamayan iki komut da test sayılmaz; mevcut izinli PowerShell üzerinden çalıştırıldı.

## Kalan iş ve durma sınırı

R2 hardening hedefli testleri iki PHP sürümünde doğrulandı. Genel Docker doğrulaması **FAILED_SUPPORT_PACKAGE** durumundadır. Kalan teknik engel, eski destek paketindeki sıra/ortam kaynaklı olabilecek hataların ayrı, hedefli olarak açıklanması ve paketin değişmeyen assertion'larla tamamlanmasıdır. Bu görevde kabul edilmiş eski destek/Identity/migration davranışlarına ek düzeltme yapılmadı. Yeni faz veya refactor açılmadı.

GitHub FAST **NOT_RUN_EXTERNAL_BILLING** kalır. Docker sonucu Actions sonucu olarak sunulmaz ve CI engelini kaldırmış sayılmaz. FULL veya yeni GitHub koşusu başlatılmadı. Bağımsız kabul **PENDING**; F6-E/FULL/staging/merge/deployment/production kapsam dışında. Production migration subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` ve tüm gerçek GATE'ler aynıdır. PR/main ayarlarına bu yerel görevde yazılmadı; önceki salt okunur durum kaydı yeni platform gözlemi gibi sunulmaz.

Kanıtlar yalnız yerel docs-only kapanış içindir. GitHub billing engeli sürerken yeni push/otomatik workflow döngüsü oluşturulmaz. Kullanıcının `docs/F6_B_B01_REVIEW.md` düzenlemesi korunur ve commit dışında bırakılır. Geçici Docker verileri yalnız bu görev etiketli kaynaklardan temizlenir; cleanup kanıtı ayrıca kaydedilir.

## Güncel kapanış — kapasite düzeltmesi

Yukarıdaki başarısız turlar tarihçedir. Kullanıcı düzeltme talimatıyla yerel MariaDB alan sınırı 768 MiB'den 4 GiB'ye çıkarıldı; kaynak ve testler değiştirilmeden PHP 8.3/8.4 tam destek paketi157/0 geçti. Önceki59 paket/625 ile aynı SHA için ayrı çalıştırmalarda60 paket/782 test yerel PASS kapsamı tamamlandı; tek broad veya GitHub CI PASS iddiası yoktur. [Tanı, düzeltme ve yeni kanıt](F6_D_R2_SUPPORT_DOCKER_FIX.md).
