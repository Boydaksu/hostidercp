# Destek paketi Docker hata düzeltme planı

Kapsam: SUP-005/006, SUP-001/004, SUP-008/009 test paketinde gözlenen hataların nedenini doğrulamak ve gereken en küçük düzeltmeyi yapmak. Önce eski 768 MiB geçici DB ortamında güvenli hata kodu ve kapasite ölçümü; sonra kanıtlanan nedene göre ortam veya kaynak düzeltmesi. Mevcut assertion ve güvenlik kontrolleri korunur. Dosyalar: bu rapor, yeni temizlenmiş kanıtlar; ancak kanıt gerektirirse hedefli kaynak/test değişikliği. Migration 001–054 değişmez. Test: başarısız support-http; düzeltmeden sonra PHP 8.3/8.4 aynı paket ve ilgili hedefli regresyonlar. Önceden geçen 59 paket gerekçesiz tekrarlanmaz. Rollback: yalnız yeni değişiklikler geri alınır, imzalı kanıt/kalıcı veriler değişmez. Gerçek sistemler, GitHub retry, FULL, Chromium, maraton, merge/deploy veya sonraki faz kapsam dışıdır. Kullanıcı düzenlemesi korunur.
## Tanı ve en küçük düzeltme

Düzeltme uygulama kodunda değil, bu görevin Docker test ortamındadır. Eski yerel runner MariaDB veri dizinini **768 MiB tmpfs** ile sınırlıyordu; GitHub FAST workflow'unda bu özel tmpfs sınırı yoktur. Paket aynı PHP süreci boyunca birçok ayrı schema tutar; fixture'ların DROP işlemleri shutdown sırasında gerçekleşir. Bu nedenle tekil testler geçerken uzun paket ilerledikçe birikimli DB alanı büyür.

Eski 768 MiB ortamı PHP 8.4'te yeniden üretildi: kullanım **703020 KiB / %90** seviyesine ulaştı; aynı migration yükseltme testi **SQLSTATE HY000 / driver 1005** ile durdu. Raw SQL/hata gövdesi veya müşteri verisi kaydedilmedi. İşletim sistemi ENOSPC kodu ayrıca yakalanmadı; tek başına 1005 kodu disk doluluğuna özgü değildir.

Yalnız MariaDB tmpfs sınırı **4 GiB** yapıldı. Aynı immutable PHP/MariaDB imajları, aynı source SHA/tree, aynı test gövdeleri ve assertion'lar kullanıldı. Yeni turda PHP 8.4 DB kullanımı **2287580 KiB**, PHP 8.3 **2174860 KiB** olarak gözlendi; her ikisi eski sert sınırın çok üzerindedir. Önceki hata sınırı ve sonrasında mail/attachment/automation testleri geçildi. Böylece değişmeyen paket için önceki runner'ın kapasitesinin yetersiz olduğu doğrulandı; kaynakta veya testlerde değişiklik gerekmiyor.

Bu görevdeki `quality_exit: 0` orchestrator metadata alanı kalite adımlarının yeniden çalıştırıldığı anlamına gelmez: bu adım kasıtlı olarak atlanmıştır ve alan placeholder'dır. Önceki exact-SHA kalite sonuçları korunur. Aynı SHA'nın geçen 59 paketi tekrar edilmedi. Çalıştırılan hedef yalnız `php tests/support-http.php` tam, değiştirilmemiş girişidir; önceki tanı sürücüsünün closure seçimi yeni PASS kanıtında kullanılmaz.

## Son test sonucu ve koruma

| Sürüm | Tam `tests/support-http.php` | Exit |
| --- | --- | --- |
| PHP 8.3.36 | **157 test / 157 PASS / 0 FAIL** | 0 |
| PHP 8.4.27 | **157 test / 157 PASS / 0 FAIL** | 0 |

Her iki turda source-proof başlangıç ve bitişte uygulama `53162495902b3b168c2fb8554594e99c23a34d62`, tree `e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47` doğruladı. Source, mevcut testler, CI registry/workflow veya migration değişmedi. Yeni uygulama SHA'sı üretilmedi; düzeltme yalnız yerel runner kapasitesidir. Eski 134 test dosyasının blob-equal koruma kaydı aynen geçerlidir. Production migration subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` korunur.

Aynı SHA'daki [önceki 59 paket / 625 test](evidence/f6-d-r2-hardening/docker/summary.json) ve bu tam 157 test birlikte her PHP sürümünde **60 paket / 782 test yerel, ayrı çalıştırmalar üzerinden PASS kapsamı** sağlar. Bu tek bir full broad koşusu değildir; önceki başarısız turlar korunur ve tarihçeden silinmez. Önceki 97 closure tanısının 66 hatası, bu yeni tam paket başarısından sonra adayda açık kaynak regresyonu olarak değerlendirilmez. Yeni 2 hardening, mevcut 24 R2 ve 25 R1 sonucu önceki aynı-SHA başarılı paketlerdedir; gereksiz tekrar edilmedi.

[Makine özeti](evidence/f6-d-r2-hardening/docker-support-fix/result.json), [kapasite kanıtı](evidence/f6-d-r2-hardening/docker-support-fix/capacity.json), [dar ortam FAIL](evidence/f6-d-r2-hardening/docker-support-fix/before-768m-php84.txt), [PHP 8.3 tam paket PASS](evidence/f6-d-r2-hardening/docker-support-fix/after-4g-php83.txt), [PHP 8.4 tam paket PASS](evidence/f6-d-r2-hardening/docker-support-fix/after-4g-php84.txt). Filtrelenmiş logların tekrarları ve runner kayıtları aynı klasördedir; raw credential/payload tutulmaz. Yeni runner da geçici internal ağ, ayrı schema'lar ve host portu yayımlanmaması sınırlarını korur.

GitHub FAST hâlâ **NOT_RUN_EXTERNAL_BILLING**; yerel sonuç platform CI PASS olarak adlandırılmaz. Bağımsız F6-D-R2 kabul **PENDING**. Native runtime recovery BLOCKED; gerçek GATE'ler OPEN; F6-E/FULL/staging/merge/deployment/production kapsam dışıdır. PR/main ayarlarına yazılmadı ve yeni platform durumu iddiası oluşturulmadı. Kullanıcı düzenlemesi commit dışında korunur. Bu görevde kalan yerel destek paketi test engeli çözülmüştür; bağımsız inceleme sınırında durulur.
