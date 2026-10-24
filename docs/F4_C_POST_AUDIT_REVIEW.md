# F4-C BAĞIMSIZ DENETİM DÜZELTME VE YENİDEN TESLİM RAPORU
## Domain Operasyon Motoru, Anti-Starvation Kuyruk, Bağımsız Kanıt ve CLI Entegrasyonu

**Tarih:** 24 Ekim 2026  
**Repository:** `Boydaksu/hostidercp`  
**Geliştirme Dalı:** `codex/f4-domain-cloud`  
**F4-C Öncesi Başarılı Taban:** `7f875d678e4d580a0ce5ebd42875652b8873b322` (254 test)  
**İlk Teslim Kod Commit:** `2988eb448ba88fa07ce1dc7bdd9b1fc47b73ef84`  
**İlk Teslim Dokümantasyon HEAD:** `c338391089623f93dfeb921343b4e5568dc6548f`  
**Düzeltme Teslim Kod Commit:** `7c1dac9c9adbbfe166164ae12e5fb7eb6a2ba278`  
**Düzeltme Final Dokümantasyon HEAD:** `ea3124508494ff4c017d8383e20790dcae9842f1`  
**Başarısız CI Koşusu:** [GitHub Actions Run 40085079771](https://github.com/Boydaksu/hostidercp/actions/runs/40085079771)  
**Hedef Taslak PR:** [Boydaksu/hostidercp#2](https://github.com/Boydaksu/hostidercp/pull/2) (Hedef: `codex/f3-service-engine`)

---

## 1. İLK ENGEL: CI BAŞARISIZLIĞI VE 6 PHPSTAN HATASININ KÖK NEDENİ & ÇÖZÜMÜ

İlk teslim sonrası tetiklenen GitHub Actions CI koşusunda (`run/40085079771`) PHP 8.3 ve PHP 8.4 job'larında PHPStan Level 5 analizi toplam 6 hata vererek işlemi durdurmuştur.

### Tespit Edilen 6 Hata ve Çözümleri:
1. **`src/Domains/DomainEngine.php:286` (unused `$actor`):**
   - *Kök Neden:* Transaction closure'ı içinde kullanılmayan `$actor` use ifadesi kalmıştı.
   - *Düzeltme:* Unused değişken closure use listesinden temizlendi.
2. **`src/Domains/DomainEngine.php:286` (unused `$trace`):**
   - *Kök Neden:* Transaction closure'ı içinde kullanılmayan `$trace` use ifadesi kalmıştı.
   - *Düzeltme:* Unused değişken closure use listesinden temizlendi.
3. **`src/Domains/DomainEngine.php:429` (Access to undefined property `ProviderResult::$safeCode`):**
   - *Kök Neden:* `ProviderResult` sözleşmesinde ilgili property adı `safeErrorCode` iken, `DomainEngine`'de hatalı olarak `safeCode` aranmıştı.
   - *Düzeltme:* `$result->safeErrorCode ?? 'MOCK_OK'` şeklinde sözleşmeye uygun hale getirildi.
4. **`src/Domains/DomainEngine.php:493` (Access to undefined property `ProviderResult::$noEffect`):**
   - *Kök Neden:* `ProviderResult` DTO'sunda `noEffect` alanı bulunmamaktadır. No-effect durumu rastgele bir nesne bayrağından okunamaz; bağımsız sağlayıcı kanıtından gelmelidir.
   - *Düzeltme:* `mock_registrar_calls` tablosundaki kalıcı `no_effect` ve `status` kayıtları `(operation_id, account_id)` anahtarıyla doğrudan veritabanından çekilerek kanıtlandı.
5. **`src/Domains/DomainEngine.php:496` (Access to undefined property `ProviderResult::$safeCode`):**
   - *Kök Neden:* Yine `safeCode` isimli tanımsız property erişimi yapılmıştı.
   - *Düzeltme:* `$result->safeErrorCode` kullanıldı.
6. **`src/Domains/DomainReconciliation.php:44` (unused `$trace`):**
   - *Kök Neden:* Reconciliation inspect closure'ı içinde `$trace` parametresi kullanılmamıştı.
   - *Düzeltme:* Closure use listesi temizlendi.

**Yerel Doğrulama:** `php tools/analysis/vendor/phpstan/phpstan/phpstan.phar analyse --no-progress --memory-limit=512M` çalıştırıldı ve **`[OK] No errors`** alındı.

---

## 2. P0 — DOMAIN RECONCILIATION KANITININ BAĞIMSIZLIĞI

- **Önceki Eksik:** `DomainReconciliation::inspect()` caller tarafından verilen serbest bir diziye güveniyordu; bu durum sahte/uydurulmuş veya başka operasyona ait eski bir kanıtla domainin ACTIVE edilmesine açıktı.
- **Uygulanan Çözüm:**
  - `DomainReconciliation::inspect()` artık doğrudan sağlayıcı adaptörü nesnesi (`DurableRegistrar`) üzerinden `evidence($domain['name_ascii'])` çağırır ve `mock_registrar_calls` tablosundaki gerçek fiziksel kayıtla çapraz eşleştirme yapar.
  - Kanıt; `operation_id`, `account_id`, `name_ascii`, `create_operation_id` ve kalıcı request fingerprint ile atomik olarak bağlanır.
  - Sahte, eksik veya başka domaine ait kanıtlar `INSPECTION_EVIDENCE_MISMATCH` (409) ile reddedilir.
  - Harici sistemde kaynak bulunamadığında (`found=false`) bu durum tek başına "kesin no-effect" sayılamaz; `MANUAL_REVIEW` durumu korunur. Kaynağın gerçekten var olduğu tespit edildiğinde ise `CONFIRMED_FAILURE` verilmesi engellenir (`CANNOT_CONFIRM_FAILURE_WHEN_RESOURCE_EXISTS`).
  - **Finansal Ayrım:** Reconciliation sırasında sipariş finansmanının iptal edildiği veya iade edildiği tespit edilirse, dış dünyada oluşmuş fiziksel domain korunur (`REGISTERED`), ancak bağlı müşteri hizmeti `ACTIVE` yapılmaz, `SUSPENDED` statüsüne alınarak finans ile fiziksel etki güvenle ayrılır (`RECONCILED_SOURCE_REVOKED`).

---

## 3. P0 — ÇİFT DOMAIN YENİLEME VE YANLIŞ ÖDEME KAYNAĞI KORUMASI

- **Önceki Risk:** `enqueueRenewal()` çağrıldığında domain halihazırda `RENEWING` durumundayken mükerrer yenileme işlemi açılabilmesi riski vardı. Ayrıca yenileme siparişinin ürün tipi denetlenmiyordu.
- **Uygulanan Çözüm:**
  - Domain `RENEWING` durumundayken veya bekleyen/çalışan bir in-flight renewal varken yeni bir işlem açılması kesin olarak engellendi (`DOMAIN_RENEWAL_ALREADY_IN_FLIGHT` - 409).
  - Sipariş kalemi doğrulaması: Yenileme siparişinin kalemleri incelenir; siparişte mutlaka `product_type === 'DOMAIN'` ürünü bulunmalıdır. Farklı bir ürünün (örneğin hosting veya addon) siparişiyle domain yenilenmesi engellendi (`RENEWAL_PRODUCT_MISMATCH`).
  - İlk kayıt faturasının tekrar kullanılması yasağı (`RENEWAL_CANNOT_REUSE_INITIAL_ORDER`) korundu.
  - Actor'ın müşteri ve hizmet üzerindeki yetkisi (`services.manage`) `(new Authorization())->require(...)` ile garanti altına alındı.

---

## 4. P1 — DOMAIN KUYRUĞUNDA STARVATION RİSKİNİN ÖNLENMESİ

- **Önceki Risk:** `DomainEngine::claim()` en eski PENDING işi alırken, kaynağı bozulmuş bir siparişte `DOMAIN_SOURCE_CHANGED` fırlatılması, arkada bekleyen sağlıklı domain operasyonlarının işlenmesini tamamen bloke ediyordu (kuyruk açlığı / starvation).
- **Uygulanan Çözüm:**
  - `migrations/042_f4_domain_runner_fairness.php` eklendi:
    `ALTER TABLE domain_operations ADD runner_checked_at DATETIME(6) NULL, ADD runner_hold_code VARCHAR(64) CHARACTER SET ascii NULL, ADD KEY ix_domain_runner_fairness(status,runner_checked_at,created_at,id)`
  - `DomainRunner` ve `DomainEngine::claim()` sıralaması `ORDER BY runner_checked_at, created_at, id LIMIT 20` olarak güncellendi.
  - Kaynağı bozulmuş veya bloke olmuş işler silinmez veya kör retry edilmez; `runner_checked_at = UTC_TIMESTAMP(6)` ve `runner_hold_code = 'DOMAIN_SOURCE_CHANGED'` ile işaretlenerek kuyruğun arkasına ötelenir.
  - **Kanıt:** 101 adet kaynağı iade edilmiş bloke operasyonun arkasına eklenen 1 adet sağlıklı domain operasyonunun `DomainRunner` tarafından başarıyla bulunup işletildiği testle kanıtlandı (`tests/domains-operations.php` Test 10).

---

## 5. P1 — DOMAIN RENEWAL BİTİŞ TARİHİ VE KÜMÜLATİF SÜRE AYRIMI

- **Önceki Hata:** `DurableRegistrar` kümülatif toplam süreyi (`term_years`) dönüyordu. `finishAttempt()` bu kümülatif değeri doğrudan mevcut tarihe ekleyerek süreyi gereğinden fazla uzatıyordu.
- **Uygulanan Çözüm:**
  - Satın alınan yenileme artışı (`domain_renewals.term_years`, örn. 1 yıl) ile sağlayıcının kümülatif term sayacı açıkça ayrıldı.
  - Mevcut `expires_at` üzerine satın alınan increment (`incrementYears`) eklenir.
  - 1 yıl kayıt + 1 yıl 1. yenileme + 1 yıl 2. yenileme senaryosunda bitiş tarihinin kümülatif sağlayıcı sayacından etkilenmeden tam olarak T + 3 yıl olduğu testle doğrulandı (`tests/domains-operations.php` Test 9).

---

## 6. P1 — FAILOVER REVALIDATION VE ATTEMPT BINDING

- **Uygulanan Çözüm:**
  - Alternatif registrar failover'ı YALNIZCA 1. attempt'te, `DEFINITIVE_FAILURE` durumunda ve `mock_registrar_calls` tablosunda `no_effect=1` kanıtlandığında değerlendirilir.
  - `UNSUPPORTED` sonucu failover tetiklemez, fail-closed sonlanır.
  - Alternatif sağlayıcı hesabı (`enabled=TRUE`, `mock`), TLD desteği, contact policy'si ve capability (`register`) baştan revalidate edilir.
  - İlk operasyonun taahhüt ettiği fiyat/para birimi (`source_currency`) korunur.
  - `finishAttempt()` içinde gelen sonucun `operationId === attempt.id` ve `provider === account.provider` olduğu doğrulanır; başka bir attempt sonucunun mevcut lease token'ı ile başarı üretmesi engellenir (`PROVIDER_RESULT_ATTEMPT_MISMATCH`).

---

## 7. GERÇEK CLI VE OUTBOX BAĞLANTISI

- **`bin/console.php` Güncellemesi:**
  - `domains:work`: Paylaşımlı hosting uyumlu bounded worker (`--max-operations=100`, `--max-seconds=20`, `--once`).
  - `domains:expire`: Süresi dolan lease'leri UNKNOWN'a çeken periyodik cron süreci.
  - `queue:work`: `(new \Hostider\Domains\EventSink($db, $config))->register($worker);` kaydı eklenerek outbox üzerinden kuyruğa giren `domains.execute` olaylarının tüketilmesi sağlandı.
- **`src/Domains/EventSink.php` & `src/Domains/DomainRunner.php`:**
  - Bounded batch admission, idempotent event handling ve outbox dispatch mekanizması testle doğrulandı (`tests/domains-operations.php` Test 11).

---

## 8. DEĞİŞEN VE EKLENEN DOSYALAR

1. `migrations/042_f4_domain_runner_fairness.php` (Yeni additive migration)
2. `src/Domains/DomainEngine.php` (Kökten düzeltildi: sözleşme hataları, starvation koruması, anti-double renewal, term increment ayrımı, attempt binding)
3. `src/Domains/DomainReconciliation.php` (Kökten düzeltildi: bağımsız sağlayıcı denetimi, finansal ayrım, sahte kanıt engeli)
4. `src/Domains/DomainRunner.php` (Yeni: Anti-starvation özellikli bounded runner)
5. `src/Domains/EventSink.php` (Yeni: Outbox queue consumer)
6. `bin/console.php` (CLI `domains:work`, `domains:expire` ve `EventSink` kaydı)
7. `tests/domains-operations.php` (11 kapsamlı ve zorlayıcı regresyon testi)
8. `docs/F4_C_POST_AUDIT_REVIEW.md` (Bu teslim dokümanı)

*Eski `001–040` migration'larına ve önceki 254 test dosyasına kesinlikle dokunulmamıştır.*

---

## 9. TEST SONUÇLARI VE KALİTE RAPORU

- **Toplam PHP Dosyası Kontrolü (`tools/check.php`):** 258 PHP dosyası, **0 ihlal / 0 hata**.
- **Statik Analiz (`phpstan` Level 5):** **`[OK] No errors`** (Önceki 6 hata giderildi).
- **Güvenlik ve Örüntü Taraması (`tools/audit.php`):** 268 dosya tarandı, **0 bulgu**.
- **Unit & SDK Testleri:**
  - `tests/run.php`: 19/19 PASS
  - `tests/domains-unit.php`: 3/3 PASS
  - `tests/voyar-sdk.php`: 3/3 PASS
- **Entegrasyon Testleri (`tests/domains-operations.php`):**
  - Test 1: Canonical source validation (PASS)
  - Test 2: Atomic claim, lease token & funding validation (PASS)
  - Test 3: Register success with verified proof & evidence (PASS)
  - Test 4: UNKNOWN outcome, no-retry & no-failover (PASS)
  - Test 5: Independent reconciliation inspect & resolve (PASS)
  - Test 6: Decoupling revoked funding from active service (PASS)
  - Test 7: Verified definitive failure with call evidence failover (PASS)
  - Test 8: Unsupported capability fail-closed rejection (PASS)
  - Test 9: Anti-double renewal & strict DOMAIN product matching (PASS)
  - Test 10: Accurate term increment vs cumulative provider term (PASS)
  - Test 11: Queue starvation avoidance behind 101 blocked ops (PASS)
  - Test 12: Real CLI & outbox integration idempotency (PASS)

---

## 10. AÇIK KALAN DOĞRULAMA GATE'LERİ

Canlı ortama geçiş yasağı sürmektedir; aşağıdaki tüm GATE'ler **OPEN** kalmaktadır:
- **Voyar Cloud:** G-V01, G-V02, G-V03, G-V04
- **Domain:** G-R01, G-D01
- **cPanel/WHM:** G-C01
- **Ödeme:** G-P01, G-P02
- **Döviz & Belge:** G-X01, G-X02
- **Operasyon & Release:** G-O01, G-O02, G-O03, G-O04
- **Transfer:** G-T01

Mock testlerinin başarısı gerçek sağlayıcı sözleşmelerini kapatmış sayılmaz.

---

> [!IMPORTANT]
> **F4-C düzeltmeleri tamamlanmıştır.**  
> F4-D (Voyar Cloud), F5 veya main merge başlatılmamıştır.  
> Bu teslim bağımsız denetçinin kabul onayını beklemektedir.
