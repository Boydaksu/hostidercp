# F3 hizmet motoru — test/mock teslim raporu

Tarih: 23 Ekim 2026. Onaylı F2 tabanı `ada37dc52b324e34380ba836ae7794cebb174ec4`; çalışma dalı `codex/f3-service-engine`. Main'e birleştirme ve F4 başlangıcı yapılmadı. Bu rapor test/mock kabulü içindir; canlı sağlayıcı veya production kabulü değildir.

## Çalışan kapsam ve izlenebilirlik

| Faz / gereksinim | Gerçek çalışan davranış | Kod commit'i |
|---|---|---|
| A / FR-SERVICE | Ayrı hizmet/dönem/kaynak/konfigürasyon/fiyat/sahiplik modelleri, immutable snapshot, satır ve quantity başına ayrı hizmet | `920a634` |
| B / PROV-003/004, OPS-005 | Canonical paid order keşfi, item/unit tekilliği, kalıcı operation, outbox, lease/fence; queue kaybından yeniden keşif | `19a61ae` |
| C / FR-HOSTING, OPS-001 | Kalıcı mock cPanel dünyası; altı hosting işlemi; pool/plan/reseller/capacity/health/capability seçimi, şifreli credential | `d2926c8` |
| D / PROV-001/002 | UNKNOWN, bağımsız inspect, süreli ve aktöre bağlı delil, manuel found/not-found çözümü; ikinci create/failover yok | `b848fa6` |
| E / PROV-006, SVC-001/002/003 | Manuel onay; ayrı sabit yenileme faturası; cüzdan izni; doğrulanmış provider uzatması; grace/askı/iptal/sonlandırma; çift taraflı transfer; ücretli paket yükseltme | `80a1ff1`–`722a7f2` |
| F / SEC-004, OPS-001/005 | Identity kapsamlı HTTP ve müşteri/yönetici ekranları, salt hazırlık ve açık tek kullanımlık onay, replay yetki kontrolü, kalıcı inspect işi | `392d361` |
| Kabul / SVC-002, performans | Bağımsız renew/terminate süreçleri ve tekrar üretilebilir 1/4/8 süreç benchmark'ı | `01748e7` |

Parasal işlemler mevcut Finance/Orders/Billing uygulama servislerinden geçer; ledger'a Services modülünden doğrudan yazılmaz. Ödeme tek başına kaynak veya yeni hizmet dönemi doğrulaması sayılmaz. Provider çağrısı açık DB transaction içinde yapılmaz. UNKNOWN kapasiteyi serbest bırakmaz ve otomatik yeniden gönderilmez.

## Test kanıtı

Yerel PHP 8.4.24 / Windows / gerçek MariaDB 10.11: F3-F tam koşusunda **229/229**, önceki **180** test korunarak geçti. [Tam yerel çıktı](evidence/f3/local-f3-f-regressions.txt). Daha sonra eklenen bağımsız renew/terminate testiyle lifecycle paketi **12/12** geçti; toplam normal CI paketi **230** testtir. Benchmark isteğe bağlı ayrı deneydir; normal regresyon sayısına eklenmez.

`392d361` [CI çalışması](https://github.com/Boydaksu/hostidercp/actions/runs/40006054747): **PHP 8.3 ve 8.4 başarılı**. Her sürümde 229 normal test, ardından 10 hizmet HTTP, 30 Commerce HTTP ve 20 session-concurrency tekrarı; **başarısız tekrar yok**. PHPStan, Composer audit, mimari/güvenlik taramaları ve JavaScript sözdizimi kontrolleri başarılı. [Doğrudan job loglarından çıkarılan kanıt](evidence/f3/ci-392d361.json). Son test commit'i `01748e7` için [CI 40006892239](https://github.com/Boydaksu/hostidercp/actions/runs/40006892239) de iki sürümde **230/230** ve aynı 10/30/20 kararlılık tekrarlarıyla başarılıdır. [Son uygulama/test commit kanıtı](evidence/f3/ci-01748e7.json). İki ardışık commit üzerindeki yeşil sonuçlar ayrı kaydedildi.

Windows PHP 8.4 üzerinde ayrıca **10/10 hizmet HTTP** ve **20/20 session-concurrency** tekrarının tamamı geçti: [HTTP kanıtı](evidence/f3/windows-services-http-stability.json), [session/cleanup kanıtı](evidence/f3/windows-session-stability.json). Başarısız deneme başarılı bir tekrar ile gizlenmedi.

| P0 | Test / doğrulama |
|---|---|
| PROV-001 | `services-hosting`, `services-reconciliation`: timeout-before/after mutation, kalıcı UNKNOWN, tek fiziksel mock kaynak, otomatik failover yok |
| PROV-002 | `services-reconciliation`: found attach, not-found manual review, stale/actor-bound evidence, refund ve audit rollback |
| PROV-003 | `services-engine`, `services-lifecycle`, `services-http`: paid manual approval öncesi provider çağrısı yok; scoped staff approval yalnız kuyruklar |
| PROV-004 | `services-model`: imported/origin guard; imported kaynak tekrar create edilemez. Gerçek WHMCS içe alma ve imported finans/lifecycle eşleme kabulü F6 kapsamında açık |
| PROV-006 | `services-lifecycle`: ödeme dönemi ilerletmez; provider renewal+inspect gerekir; timeout sonucu ayrı delille çözülür |
| SVC-001 | `services-transfer`: gerçek iki Identity owner, süreli iki onay, borç kontrolü, eski finans geçmişi eski malikte; revoked sender/expired token ret |
| SVC-002 | `services-lifecycle`: operation çatışması ve iki bağımsız renew/terminate süreci; yalnız bir terminate operasyonu, yeni renewal yok |
| SVC-003 | `services-package`: exact integer proration, sabit ayrı bill, full-paid+capability+aynı kaynak inspect; downgrade kredi varsayımı yapılmaz |
| SEC-004 | `services-http`: farklı müşteri read/mutation/override/CSRF ret; client allowlist; tek kullanımlı state-bound onay; cached replay mevcut permission ve step-up ister |
| OPS-001 | `services-hosting` ve mevcut effect-policy testleri: gerçek WHM yolu flags ile de açılamaz, diğer canlı etkiler kapalı |
| OPS-005 | `services-engine`, `services-hosting`, `services-lifecycle`: bağımsız discovery/claim/worker süreçleri, tek fence/resource, expired lease/stale worker ret, ayrı cron overlap ret |

Çoklu unit, finansal ledger değişmezliği, beklenmedik provider sonucu, payment/source değişimi, transaction rollback ve primitive state geçişleri ilgili DB paketlerinde sınanır. Eski 001–022 migration için taban karşılaştırmasında fark yoktur.

## Ölçülen performans

[Tekrar üretilebilir ham sonuç](evidence/f3/performance.json), araç: `php tools/f3-benchmark.php`; yalnız açık test DB ortam değişkenleriyle çalışır. 1000 hizmetlik keşif verisi, her mod/eşzamanlılık için 32 işlem; ayrı kimliği doğrulanmış müşteride 60 hizmet ve görünüm başına 30 gerçek HTTP isteği. Gerçek WHM ağı yoktur. Bu hacim ve örnek sayıları production kapasite testi değildir.

| Süreç | Claim + sağlıklı pool seçimi/rezervasyon p50 / p95 ms | Tam mock worker p50 / p95 ms | Worker işlem/sn | Toplam InnoDB lock wait ms / deadlock |
|---|---|---|---|---|
| 1 | 8,03 / 21,81 | 19,45 / 46,15 | 31,28 | 0 / 0 |
| 4 | 23,44 / 28,52 | 39,59 / 58,13 | 43,42 | 510 / 0 |
| 8 | 40,84 / 57,94 | 78,44 / 104,82 | 36,68 | 1804 / 0 |

Pool seçimi claim ölçümünün içindedir; bağımsız adapter ağ gecikmesi sayılmaz. Kuyruk beklemesi operation oluşturma → STARTED: min **4,02 s**, ortalama **5,76 s**, max **7,52 s**; deney önce tüm operasyonları ürettiği için kasıtlı batch beklemesini içerir. Tamamlanma yaşı ayrıca JSON'dadır. Müşteri listesi p95 **271,79 ms**, yönetici operasyon detayı p95 **77,16 ms**. Cron overlap ayrı süreç testinde mevcut named lock nedeniyle hemen reddedilir.

Aynı sipariş ve sunucu mutex'inde eşzamanlılık arttıkça lock wait artar. Yerel deneyde 4 worker, 8'den daha yüksek throughput verdi. Başlangıçta düşük eşzamanlılıkla ölçüm önerilir; kullanıcı adına sayısal SLA kararı verilmedi. Gerçek hosting CPU/DB limitleri, büyüyen geçmiş ve gerçek WHM rate limitleri ayrıca ölçülmelidir.

## Bulunan sorunlar ve düzeltmeler

- Tekrarlı yerel veri birikiminde cron ilk 100 bloke kaydı yeniden seçerek sonraki hizmeti aç bırakıyordu. Gerçek grace testi başarısız oldu. Migration 037 son kontrol zamanını ekledi; bounded batch'ler artık adil döner. İlk başarısızlık başarılı olarak sayılmadı; düzeltme sonrası test tekrar geçti.
- Unsuspend kuyruğa alındıktan sonra ödeme/refund değişirse eski kontrol yeterli değildi. Worker dispatch ve sonuç yazımında güncel verified/paid/refund-free dönemi tekrar kontrol eder. Provider etkisi sonrası tutarsızlık UNKNOWN/manual review olur.
- HTTP fixtures gerçek Identity switch alanını ve customer step-up sözleşmesini kullanacak şekilde düzeltildi. İlk kurulum hataları nihai başarılı koşuya dahil edilmedi.

## Arayüz demosu

Gerçek sentetik kullanıcıyla tarayıcıda `/client` → hesap seçimi → `/client/services` → aktif hizmet ayrıntısı → kimlik yenileme → iptal etki özeti kontrol edildi. İşaretlenmemiş açık onay kutusunda uygulama düğmesi devre dışıdır; demo iptal edilerek kapatıldı. [Ekran kanıtı](evidence/f3/browser-confirmation.png). HTTP testleri yönetici onayı, ayrı worker aktivasyonu, UNKNOWN inspect ve delille çözümü ayrıca çalıştırır. Demo kimlik bilgileri Git'e alınmadı; geçici sunucu durduruldu.

Test ortamı operatörü: `php bin/console.php migrate`, `services:discover`, `services:work`, `services:expire`, `services:schedule`. Birlikte çalışan cron/worker aynı APP_KEY/DB ve test-only effect policy kullanmalıdır. Keşif bir transaction'da en çok 100 yeni unit/5 saniye, batch/scheduler 100 kayıt/20 saniye hedef sınırında çalışır; DB lock ve hosting zaman aşımı gerçek ortamda ayrıca doğrulanmalıdır.

## Migration ve geri dönüş

023–037 additive şemadır; yeni Services/hosting/mock tabloları, price/config/history/delil bağları, Identity resource API ve yeni service permission'ları eklenir. 033 permission dağıtımı Identity sürümü/oturumlarını geçersizleştirir; tekrar giriş gerekir. 001–022 değişmedi. İlgili immutable trigger'lar uygulama bypass'ını da reddeder.

Destructive down sunulmaz. Dağıtım öncesi kod+DB+APP_KEY+korumalı dosyalar aynı tutarlı backup setine alınır. Geri dönüş doğrulanmış set restore veya forward-fix'tir; finansal hareket varsa ayrıca mutabakat gerekir. Burada production backup/restore provası yapıldığı iddia edilmez. Mock tablo ve sentetik test verileri production'a taşınmaz.

## Açık GATE ve kabul sınırları

- **G-C01 açık:** Gerçek endpoint/alan/ACL/credential sağlanmadı. Canlı WHM transport kapalı. HTTPS/TLS hostname/sertifika, en dar ACL, timeout/rate limit, WHM sürümü ve gerçek create/inspect/renew/suspend/package/terminate davranışı ayrı izinli ortamda kanıtlanmalı. Mock sonuçları bu kanıtın yerine geçmez.
- **G-O01 açık:** Gerçek paylaşımlı hosting cron/lock/DB limitleri, deployment/restore ve PHP 8.3 JIT SIGSEGV yolu doğrulanmalı. Interpreter CI, production JIT güvenliği değildir.
- **G-T01 açık:** Canlı transfer borç kapsamı ve ticari onaylar tamamlanmadı; testte SERVICE/CUSTOMER açık politikası gerekir.
- HELD notice gerçek SMTP teslimi değildir. Zorunlu fiyat bildirimi teslim kanıtı yoksa yeni yenileme fail-closed kalır. ONCE bileşenli protected recurring fiyat, downgrade kredi ve imported ticari eşleme belirsizliği manuel inceleme ister; başarılı otomasyon gibi gösterilmez.
- Gerçek PayTR/FX/registrar/Voyar/WHM ve production açılmadı. F4/F5/F6 tamamlanmış sayılmaz. Hiçbir açık GATE kapatılmadı.

## Main koruması ve sonraki adım

[Salt okunur GitHub kanıtı](evidence/f3/main-protection.json): main hâlâ onaylı F2 SHA'sında; `protected=false`, etkili branch rule sayısı **0**. Ayarlar değiştirilmedi. İlk merge öncesi repository sahibi main için PR zorunluluğu, en az bir onay, son değişiklikte onayı yenileme, stale review iptali, force-push/silme yasağı ve zorunlu **verify (8.3)** / **verify (8.4)** kontrollerini açmalıdır. Yeni commit'lerin kontrolü başarılı olmadan merge edilmemeli; uygun plan destekliyorsa yönetici bypass'ı da sınırlandırılmalıdır.

F3 mock/sözleşme kodu kullanıcı incelemesine sunulur. **F4'e otomatik geçiş önerisi veya production GO yoktur.** Önce bu test/mock teslimin kullanıcı kabulü ve ilk merge için branch koruması gerekir. Canlı kabul için yukarıdaki bağımsız kanıtlar ayrıca zorunludur.
