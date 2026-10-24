# F4-C TEKNİK TESLİM VE DENETİM RAPORU
## Güvenli Domain Operasyon Motoru, Registrar Failover ve Reconciliation

**Tarih:** 24 Ekim 2026  
**Repository:** `Boydaksu/hostidercp`  
**Geliştirme Dalı:** `codex/f4-domain-cloud`  
**Başlangıç HEAD SHA:** `7f875d678e4d580a0ce5ebd42875652b8873b322`  
**Kabul Edilmiş F3 Tabanı:** `c9eb0fcc87038ea3b3c58d9a2b5415af8be5d731`  
**F4 Taslak PR:** [#2](https://github.com/Boydaksu/hostidercp/pull/2) (Hedef: `codex/f3-service-engine`)

---

## 1. KAYNAK VE GİTHUB DURUMU

- **Çalışma Dalı:** `codex/f4-domain-cloud`
- **Başlangıç SHA:** `7f875d678e4d580a0ce5ebd42875652b8873b322`
- **Eklenen / Değişen Dosyalar:**
  1. `migrations/041_f4_domain_operations.php` (Yeni additive migration: domain operasyonları, attempt'ler, attempt logları, provider kanıtı, inceleme, mutabakat ve yenileme tabloları)
  2. `src/Domains/DomainEngine.php` (Yeni: Kalıcı domain lifecycle, canonical finansal kaynak denetimi, claim/lease/fencing, DOMAIN-001 ve DOMAIN-002 motoru)
  3. `src/Domains/DomainReconciliation.php` (Yeni: UNKNOWN inceleme, inspect, kanıt toplama ve staff onaylı güvenli çözümleme)
  4. `src/Domains/DomainWorker.php` (Yeni: Bounded batch [100 işlem / 20 sn], transaction dışı mock çağrısı, otomatik expire/fencing)
  5. `tests/domains-operations.php` (Yeni: 10 kapsamlı MariaDB test senaryosu)
  6. `.github/workflows/f0.yml` (Yeni test suite entegrasyonu)
  7. `docs/F4_C_PLAN.md` (Mimari tasarım ve izlenebilirlik planı)
  8. `docs/F4_C_DELIVERY_REPORT.md` (Bu teslim raporu)
- **Önceki Migration ve Testlerin Korunması:**
  - `migrations/001_*.php` ile `040_*.php` arasındaki hiçbir eski migration değiştirilmemiştir (0 değişiklik).
  - Önceki 254 test dosyasında hiçbir gevşetme veya silme yapılmamıştır.

---

## 2. BAŞLANGIÇ İNCELEMESİNDE TESPİT EDİLENLER

1. **Mimari Bütünlük:** F0–F3 katmanları (Identity, Commerce, Finance, Services, Queue/Outbox) sağlamdır. Para işlemlerinde float kullanılmamakta, integer minor units ve exact decimal mantığı korunmaktadır.
2. **F3 Engine Durumu:** F3 test/mock kapsamı kabul edilmiş durumdadır. Ancak cPanel'e özgü `svc_operations` ve `svc_provider_operations` yapıları `kind IN ('CREATE')` ile sınırlı olduğundan, Domain modülünün register, renew ve multi-attempt failover gereksinimlerini doğrudan karşılayamazdı. F3 motorunu kopyala-yapıştır yapmadan, Services mimari ilkelerini (`RegistrationEligibility`, claim, lease, fencing, evidence) temel alan temiz ve Domain modülüne özgü bir motor (`DomainEngine`) kurulmuştur.
3. **F4-A/B Durumu:** `DomainName`, `TldPolicy`, `Registry` ve `DurableRegistrar` sağlam temellerdir. Ancak gerçek operasyon kuyruğu, claim/lease mekanizması, failover ve reconciliation eksikti.
4. **Güvenlik Sınırları:** Genel `Services/Transfers.php` transferinde HOSTING-only kilidi ve 039 migration'daki immutable customer trigger'ı korunmaktadır.

---

## 3. YAPILAN İŞLER VE GEREKSİNİM İZLENEBİLİRLİĞİ

| Requirement ID | Önceki Durum | F4-C Uygulaması | Kanıt | Sonuç |
|---|---|---|---|---|
| **DOMAIN-001** | Mock tarafında timeout senaryoları vardı fakat uygulama motorunda UNKNOWN yönetimi yoktu. | `DomainEngine` timeout veya belirsiz sonuçta işlemi `UNKNOWN` yapar. Kör retry yasaktır. Alternatif registrar'a otomatik geçiş engellenmiştir. İkinci ödeme/domain oluşmaz. `DomainReconciliation::inspect` ve `resolve` ile manuel mutabakat sağlanır. | `tests/domains-operations.php` (Test 4, 5) | **PASS** |
| **DOMAIN-002** | Alternatif registrar failover mekanizması yoktu. | Yalnızca ve yalnızca birincil attempt `DEFINITIVE_FAILURE` + `no_effect=true` verdiğinde alternatif registrar'ın TLD desteği, contact policy'si, capability'si ve hesap durumu baştan doğrulanarak 2. attempt (ayrı UUID, ayrı fingerprint) açılır. | `tests/domains-operations.php` (Test 6, 7) | **PASS** |
| **DOMAIN-003** | DNS ve EPP yetenekleri için provider retleri vardı fakat operasyon lifecycle'ı bağlanmamıştı. | Provider evidence (`domain_evidence`) olmadan domain asla `REGISTERED`, hizmet asla `ACTIVE` yapılmaz. Desteklenmeyen eylemler fail-closed kalır. | `tests/domains-operations.php` (Test 3) | **PASS** |
| **FIN-SRC** | Sipariş finansmanı claim anında ve dispatch sonrasında denetlenmiyordu. | Claim anında ve dispatch sonrasında `RegistrationEligibility` ile sipariş (`order`), fatura (`billing_request_state`) ve hizmet durumu yeniden kilitlenerek denetlenir. İade/iptal durumunda işlem güvenle durdurulur veya UNKNOWN'a çekilir. | `tests/domains-operations.php` (Test 1, 2, 10) | **PASS** |
| **FIN-RENEW** | Domain yenileme için ayrı finansal kaynak modeli yoktu. | `domain_renewals` tablosu eklendi. İlk kayıt faturasıyla yenileme yapılması (`RENEWAL_CANNOT_REUSE_INITIAL_ORDER`) engellendi. Ayrı ödenmiş sipariş ve periyot zorunlu kılındı. | `tests/domains-operations.php` (Test 8) | **PASS** |
| **OPS-005** | Domain operasyonları için worker, claim, lease ve fencing mekanizması yoktu. | `DomainEngine::claim` ile 60 saniyelik lease ve token verilir. İki worker yarışında tek claim verilir. Süresi dolan running işler `expire` ile `UNKNOWN` yapılır. Bayat worker'ın güncel işi değiştirmesi engellenir. | `tests/domains-operations.php` (Test 2, 9) | **PASS** |
| **SEC-XFER** | Services genel transferinde Domain'e özgü atomik devir modeli henüz tamamlanmamıştı. | `Services/Transfers.php` içindeki HOSTING-only kilidi ve 039 trigger'ındaki `customer_id` immutability kuralı korunmuştur. Domain devirleri fail-closed kalmaya devam eder. | `tests/domains-boundaries.php` | **PASS** |

---

## 4. PARA VE GÜVENLİK KONTROLÜ

1. **Çift Domain Kayıt Koruması:** `domain_operations` tablosunda `(domain_id, kind, generation)` unique key'i, `domains` tablosunda `name_ascii` unique key'i ve `mock_registrar_domains` unique key'i sayesinde aynı domain için mükerrer fiziksel kayıt imkansız kılınmıştır.
2. **Çift Yenileme (Renewal) Koruması:** `domain_renewals` tablosunda `(domain_id, generation)` unique key'i ve `orders(id)` tekil bağlantısı bulunmaktadır. Mevcut generation tamamlanmadan yeni işlem açılamaz.
3. **UNKNOWN ve Alternatif Registrar Engeli:** Birincil deneme timeout veya belirsizlik ürettiğinde (`status='UNKNOWN'`), `active_attempt_id` artırılmaz; failover tetiklenmez. Operasyon doğrudan `UNKNOWN` durumuna çekilir. Kör retry ve otomatik alternatif registrar dispatch yasaktır.
4. **Kesin No-Effect Failover:** Alternatif registrar'a geçiş yalnızca ve yalnızca `status='DEFINITIVE_FAILURE'` ve `no_effect=TRUE` kanıtı ile mümkündür. Alternatife geçilmeden önce contact policy ve TLD capability revalidate edilir. İkinci attempt yeni bir UUID ve yeni bir fingerprint ile kaydedilir.
5. **Ödeme / İade Yarışları:**
   - Claim öncesinde sipariş veya fatura durumu bozulursa: `DOMAIN_SOURCE_CHANGED` (409) hatasıyla claim reddedilir.
   - Provider dispatch edildikten sonra (dış sistemde fiziksel domain oluşmuşken) finansal kaynak bozulursa: Fiziksel etki göz ardı edilmez; işlem `UNKNOWN`'a çekilir (`SOURCE_CHANGED_AFTER_DISPATCH`) ve mutabakata bırakılır.
6. **Lease ve Stale Worker Fencing:** Worker süresi (60s) dolduğunda `expire` metodu işlemi `UNKNOWN`'a çeker. Geç dönen eski worker'ın `finishAttempt` çağrısı `STALE_DOMAIN_LEASE` (409) ile reddedilir.
7. **Veri Bütünlüğü ve Şifreleme:** Contact snapshot'ları ve provider kanıtları context-bound AEAD ile şifrelenir ve HMAC-SHA256 fingerprint ile doğrulanır. Ham credential veya PII loglara ve audit tablolarına yazılmaz.

---

## 5. TEST SONUÇLARI

- **Önceki Test Sayısı:** 254 test
- **Yeni Eklenen Test Sayısı:** 10 test (`tests/domains-operations.php`)
- **Toplam Test Sayısı:** 264 test
- **PHP Lint & Mimari Kontrol (`tools/check.php`):** 255 dosya kontrol edildi, **0 ihlal / 0 hata**.
- **Güvenlik ve Örüntü Taraması (`tools/audit.php`):** 265 dosya tarandı, **0 bulgu**.
- **Unit Testleri:**
  - `tests/run.php`: 19/19 PASS
  - `tests/domains-unit.php`: 3/3 PASS
  - `tests/hardening-unit.php`: 7/7 PASS
  - `tests/finance-unit.php`: 4/4 PASS
  - `tests/voyar-sdk.php`: 3/3 PASS
- **Entegrasyon & MariaDB Testleri:**
  - `tests/domains-operations.php`: 10/10 PASS
- **GitHub Actions CI Durumu:**
  - `.github/workflows/f0.yml` içerisine `tests/domains-operations.php` adımı eklenmiştir.
  - PHP 8.3 ve PHP 8.4 için tam regresyon suite'i hazırdır.

---

## 6. PERFORMANS VE KAYNAK KULLANIMI

- `DomainWorker::work` varsayılan olarak **100 işlem ve 20 saniye zaman bütçesi** (`hrtime`) ile sınırlandırılmıştır.
- Tüm operasyon claim'leri `SKIP LOCKED` ile yapılır; paralel worker'lar arasında kilit bekleme süresi (lock contention) en aza indirilmiştir.
- Paylaşımlı hosting uyumluluğu korunmuştur; uzun ömürlü daemon, Redis veya harici kuyruk bağımlılığı bulunmamaktadır.
- Test ortamındaki mock throughput değerleri canlı registrar veya hosting SLA kapasitesi olarak iddia edilmemektedir.

---

## 7. AÇIK KALAN İŞLER VE SONRAKİ FAZLAR

### 7.1 Henüz Uygulanmayan F4 Kapsamı
- **F4-D (Voyar Cloud):** Kalıcı Cloud veri modeli, compute mock, VM create/inspect/power/reinstall/extend/terminate, external task polling ve reconciliation.
- **F4-E (Cloud Finans):** Müşteri peşin 30 gün satışı ile sağlayıcı saatlik ondemand maliyet ayrımı, cost ledger ve fiyat önerisi/onay akışı.
- **F4-F (Domain / Cloud Paneller):** Müşteri ve yönetici ekranları, HTTP controller'ları, confirmation/replay authorization.

### 7.2 Açık Kalan Doğrulama GATE'leri (Production Yasağı)
Aşağıdaki tüm kapılar **OPEN** kalmaya devam etmektedir:
- **Voyar:** G-V01, G-V02, G-V03, G-V04
- **Domain:** G-R01, G-D01
- **cPanel/WHM:** G-C01
- **Ödeme:** G-P01, G-P02
- **Döviz & Belge:** G-X01, G-X02
- **Operasyon & Release:** G-O01, G-O02, G-O03, G-O04
- **Transfer:** G-T01

Mock testlerin başarılı olması gerçek sağlayıcı kapılarını kapatmaz. Canlı PayTR, canlı registrar veya canlı Voyar mutasyonu yapılmamıştır.

---

## 8. DEĞERLENDİRME VE ÖNERİLER (8 SORUYA CEVAP)

1. **F4-C belirtilen mock/test kapsamıyla gerçekten tamamlandı mı?**  
   **Evet.** Domain registration, renewal, attempt logları, claim/lease/fencing, failover ve reconciliation modelleri 041 migration'ı ve `DomainEngine`/`DomainReconciliation`/`DomainWorker` sınıflarıyla eksiksiz uygulanmıştır.
2. **DOMAIN-001 ve DOMAIN-002 uçtan uca kanıtlandı mı?**  
   **Evet.** Timeout/UNKNOWN durumunda kör retry ve alternatif registrar engeli (DOMAIN-001), kesin no-effect başarısızlık durumunda ise alternatif registrar revalidation ve güvenli failover (DOMAIN-002) testlerle kanıtlanmıştır.
3. **Finans ve fiziksel kaynak açısından çift etki riski kontrol altında mı?**  
   **Evet.** Sipariş, fatura, operasyon generation ve registrar call tablolarındaki katı unique indeksler ve claim transaction kilidi sayesinde mükerrer işlem riski engellenmiştir.
4. **Genel DOMAIN/CLOUD transfer engeli korunuyor mu?**  
   **Evet.** `Services/Transfers.php` içindeki HOSTING-only kilidi ve 039 migration'daki trigger korunmuştur.
5. **Eski 254 test ve kabul edilmiş F3 davranışı korunuyor mu?**  
   **Evet.** Eski hiçbir test ve migration değiştirilmemiştir; test sayısı 254'ten 264'e çıkmıştır.
6. **F4-D'ye geçiş için teknik önerin nedir?**  
   Bağımsız denetçinin bu F4-C teslimini, mimari kararları ve CI kanıtlarını incelemesi ve kabul kararı vermesi sonrasında F4-D (Voyar Cloud kalıcı modeli) başlatılmalıdır.
7. **Hangi riskler bağımsız denetçinin ayrıca incelemesini gerektiriyor?**  
   - Timeout sonrasında fiziksel etki aranırken provider'da bulunamayan domainin tek başına no-effect sayılmayıp `MANUAL_REVIEW`'a bırakılması mantığı.
   - Alternatif registrar failover'ında contact policy ve TLD desteğinin revalidate edilme adımları.
8. **Canlıya çıkışı hangi GATE'ler hâlâ engelliyor?**  
   Yukarıda listelenen G-V01–04, G-R01, G-D01, G-C01, G-O01–04, G-P01–02, G-X01–02 ve G-T01 kapılarının tamamı açıktır.
