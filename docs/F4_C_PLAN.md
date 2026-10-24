# F4-C Güvenli Domain Operasyon Motoru, Registrar Failover ve Reconciliation Planı

**Tarih:** 24 Ekim 2026  
**Dal:** `codex/f4-domain-cloud`  
**Referans Tabanı:** `7f875d678e4d580a0ce5ebd42875652b8873b322` (254 regresyon testi kabul edilmiş taban)  
**Kapsam:** Yalnız test/mock ortamı. Canlı sağlayıcı/finans/PayTR/WHM/Voyar ve main merge KAPSAM DIŞIDIR.

---

## 1. MİMARİ VE GEREKSİNİM ANALİZİ

### 1.1 Karşılanan Gereksinimler
- **DOMAIN-001 (Belirsiz Kayıt / UNKNOWN):** Birincil registrar timeout veya belirsiz sonuç verirse işlem `UNKNOWN` olur. Kör retry yapılmaz, otomatik alternatif registrar çağrılmaz, ikinci ödeme/domain oluşturulmaz. Inspect ile kanıt aranır, gerekirse manuel mutabakat (`MANUAL_REVIEW`) işletilir. Provider'da domain bulunamaması tek başına no-effect kanıtı sayılmaz.
- **DOMAIN-002 (Güvenli Alternatif Registrar Failover):** Alternatif registrar YALNIZCA ve YALNIZCA birincil denemenin kesin ve kanıtlı hiçbir domain/ücret etkisi oluşturmadan başarısız olduğu (`DEFINITIVE_FAILURE` + `no_effect=true`) durumda değerlendirilebilir. Alternatife geçmeden önce TLD desteği, contact policy, capability, fiyat/para birimi ve hesap durumu baştan revalidate edilir. Yeni attempt eski operation ile ilişkilendirilir, ayrı UUID ve ayrı fingerprint taşır.
- **DOMAIN-003 (Capability & Lifecycle):** DNS/EPP için veri modeli olmadığından `UNSUPPORTED` fail-closed korunur. Provider evidence (`domain_evidence`) olmadan domain asla `REGISTERED`, hizmet asla `ACTIVE` olamaz.
- **OPS-005 (Concurrency & Fencing):** 1/4/8 bağımsız PHP worker ile aynı domain için eşzamanlı register, duplicate renew, timeout-after-effect, crash-after-effect, stale lease ve fencing doğrulanır.
- **Finansal Bütünlük:** Canonical order, billing payment state (PAID, refund=NONE) ve unit claim korunur. Provider dispatch öncesi ve dispatch sonrasında kaynak denetlenir. Renewal asla ilk kayıt faturasını kullanamaz.

---

## 2. VERİTABANI DEĞİŞİKLİKLERİ (MIGRATION 041)

Eski migration'lar (001–040) değiştirilmez. Yeni `migrations/041_f4_domain_operations.php` eklenir:
1. `domain_operations`: Register ve Renew lifecycle operasyonları, generation, request fingerprint, lease token, lease_until, status (`PENDING`,`RUNNING`,`SUCCEEDED`,`FAILED`,`UNKNOWN`,`MANUAL_REVIEW`).
2. `domain_registrar_attempts`: Her attempt için tekil UUID, account_id, attempt_no (1 veya 2), status, AEAD encrypted request payload, request fingerprint, no_effect flag, safe_code.
3. `domain_attempt_events`: Immutable attempt yaşam döngüsü logları (`STARTED`,`FINISHED`,`HELD_FOR_REVIEW`,`RECONCILED`).
4. `domain_evidence`: Başarılı provider sonucunun AEAD şifreli ve hash'li kanıt kaydı.
5. `domain_inspections`: Unknown veya mutabakat durumunda harici registrar durumunun bağımsız inceleme kaydı.
6. `domain_resolutions`: Operatör mutabakat çözüm kaydı (`CONFIRMED_SUCCESS`, `CONFIRMED_FAILURE`).
7. `domain_renewals`: Domain yenileme dönemi, ayrı canonical sipariş ve periyot bağlantısı.

---

## 3. UYGULAMA KATMANLARI

- `src/Domains/DomainEngine.php`:
  - `enqueueRegister`: Canonical ödenmiş sipariş ve domain hizmetini doğrular, operation ve 1. attempt'i atomik oluşturur.
  - `enqueueRenewal`: Ayrı ödenmiş yenileme siparişi için renewal operation ve attempt oluşturur.
  - `claim`: Atomic transaction ile bir sonraki pending işi kilitler, kaynak finansal durumu (`RegistrationEligibility`) yeniden kilitler, lease tahsis eder ve provider çağrısı öncesi commit eder.
  - `finishAttempt`: Provider sonucunu (SUCCESS, DEFINITIVE_FAILURE, UNKNOWN) işler. Stale lease ve kaynak değişimlerini fence eder. DOMAIN-001 ve DOMAIN-002 kurallarını işletir.
  - `expire`: Süresi dolan running operasyonları `UNKNOWN`'a çeker (fencing).
- `src/Domains/DomainReconciliation.php`:
  - `inspect`: Provider üzerinde domain ve call durumunu tarafsız inceler.
  - `resolve`: Kanıtlı inceleme sonucuna göre UNKNOWN operasyonu sonuca bağlar.
- `src/Domains/DomainWorker.php`:
  - CLI ve queue runner tarafından çağrılabilen bounded batch worker.

---

## 4. TEST VE DOĞRULAMA PLANI

1. `tests/domains-operations.php`:
   - Canonical kaynak doğrulama (ödenmemiş, kısmi ödenmiş, iade edilmiş siparişler reddedilir).
   - Başarılı domain register yaşam döngüsü ve immutable provider evidence.
   - DOMAIN-001: Timeout-before-effect ve Timeout-after-effect senaryolarında UNKNOWN üretimi, kör retry yasağı, alternatif registrar yasağı.
   - DOMAIN-002: Kesin no-effect başarısızlık durumunda alternatif registrar re-validation ve güvenli failover dispatch. Alternatif registrar'ın TLD veya contact uyuşmazlığında fail-closed duruşu.
   - DOMAIN-003: DNS ve EPP capability retleri.
   - Concurrency: İki bağımsız PHP worker aynı domain register için yarıştığında tek create etkisi ve tek lease.
   - Concurrency: Expired lease ve stale worker fencing.
   - Concurrency: Claim anında ödeme iadesi veya iptali yarışında güvenli ret.
   - Concurrency: Dispatch sonrasında ödeme durumu değiştiğinde UNKNOWN'a çekilme.
   - Domain Renewal: Ayrı sipariş zorunluluğu, duplicate renew engeli, term aşım kontrolü.
   - Manuel mutabakat (inspect & resolve).
2. Mevcut 254 testin korunması ve sıfır hata ile geçmesi.
3. Lint, architecture check (`tools/check.php`) ve audit (`tools/audit.php`).
