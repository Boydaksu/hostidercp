# F2 teslim sonrası teknik denetim ve risk kapanışı

23 Ekim 2026. Denetlenen başlangıç `da38e40a11b0214ca340462f77ec2e1fedaadbdc`; son F2 uygulaması `f7b1df77c4f6fa000a1e3e71bbfb1d9f9a0afe13`. Yerel çalışma, gerçek MariaDB 10.11.18 ve sentetik veriler kullanır. Production, gerçek tahsilat veya sağlayıcı işlemi yapılmadı; F3 başlatılmadı.

Kaynaklar: README, AGENTS, `docs/01_DECISIONS.md`, gereksinimler/finans/workflow/API/operasyon/roadmap/kabul/GATE belgeleri, birleşik Master Specification, F0–F2 raporları ve ADR'ler. Repository'de ayrı Account Handoff adlı dosya bulunmadı; konuşmadaki devir talimatı ve mevcut teslim kanıtları kullanıldı. Plan: [F2_POST_DELIVERY_PLAN](F2_POST_DELIVERY_PLAN.md); teknik karar: [ADR-007](adr/ADR-007-f2-review-risk-closure.md).

## Bulgular, neden, tekrar ve kapanış

| ID / önem | Başlangıç dosya/satır ve teknik neden | Tekrarlanabilir senaryo | Düzeltme / kanıt / kalan risk |
|---|---|---|---|
| R01 / yüksek | `FinanceService.php:29`, `Controller.php:60`: reconciliation yalnız immutable etiket; açık/çözüldü ve kanıtlı finans sonuçlandırma yok | Geç tahsilat unallocated kalır; liste bir kaydı gösterir ama operatör denetlenebilir biçimde kapatamaz | `Finance/Reconciliation.php:12,20,38`, migration 020: scoped staff + fresh step-up + saf onay, unique immutable resolution, şifreli kanıt/hash/actor/trace/effect; wallet credit veya original refund rezervi aynı transaction'da. OPEN/RESOLVED liste ve yetkili kanıt görüntüleme. post-delivery ve HTTP regresyonları. FX/policy belirsizliği açık kalır; dış iade gönderilmez. |
| R02 / yüksek | `Commerce/State.php:13`: her işlem bütün katalog/kur/kasa satırlarını exclusive kilitler; müşteri bağımsızlığı kaybolur | 8 farklı müşteri State transaction'ı; mevcut algoritma p95 kilit aşaması 460,01 ms, DB toplam kilit beklemesi 25.552 ms | `State.php:10`, `Core/Database/VersionFence.php:9`; migration 022: catalog/FX shared reader ve exclusive writer version fence, customer financial state ayrı. 020 customer indeksleri. Yeni State aynı yükte p95 7,66 ms, DB bekleme 0. Aynı müşteri hâlâ serialize olur. Çok büyük tek müşteri geçmişi ve üretim donanımı ayrıca ölçülmeli. |
| R03 / orta | `EventSink.php:25`, `Orders/OrderService.php:27`: queue başarıyla tüketilir ama F3 discovery/kalıcı ortam kökeni sözleşmesi yok | Eski demo READY siparişi veya tüketilmiş queue üzerinden gelecekte otomatik replay tasarlamak, ortam karışması/unutulmuş hizmet riski taşır; mevcut F2 gerçek dispatch yapmaz | Migration 021 immutable origin; eski tüm satırlar LEGACY_UNKNOWN, yeni Commerce Config ortamını taşır. `HandoffPolicy.php:7` salt eligibility testi ve [devir sözleşmesi](F2_F3_HANDOFF_CONTRACT.md). Queue kaybı canonical order/bill/items'i silmez. Gerçek claim/operation/service F3 işidir; uygulanmış gibi sunulmaz. |
| R04 / yüksek | `Core/Idempotency.php:29` + eski `Controller.php:91`: cached response doğrudan döner, callback içindeki fresh step-up ve authorization atlanır | Quote execute başarılı olduktan sonra session verified_at değerini 301 saniye eskilt, aynı Idempotency-Key/payload'ı gönder: beklenen 403 yerine 200 | İsteğe bağlı validateReplay callback ve `Controller.php:98` aynı transaction'da Identity fence ile güncel yetki/scope/step-up doğrular. [Önce başarısız](evidence/f2/review-replay-before.txt), [sonra başarılı](evidence/f2/review-replay-after.txt). Çift mali hareket oluştuğu iddia edilmez; açığın etkisi eski işlem yanıtına erişimdir. |
| R05 / yüksek, açık operasyon riski | GitHub `main`: protected=false, applicable branch rules=[] | Salt okunur branches/main ve rules/branches/main API yanıtı | [GitHub kanıtı](evidence/f2/review-branch-rules.json). Ayar değiştirilmedi. Zorunlu PR, inceleme ve CI önerileri aşağıda. |

Başlangıç satırları denetlenen commit içindir. Yeni dosya/satırlar kapanış uygulamasını gösterir; kaynak dosyaları rapor commit'inde görülebilir.

## Mutabakat sözleşmesi

Geç/fazla doğrulanmış tahsilat aynı payment'a bağlı açık case üretir; bakiye unallocated kalır. Sahte/imzasız veya tutar/para birimi uyuşmayan test fixture reddedilir, para yaratmaz; reddedilmiş bildirimi para kabul edilmiş gibi sonuçlandıran komut yoktur. Gerçek PayTR bildirimi G-P01/P02 nedeniyle hiçbir ortamda kabul edilmez.

Operatör, global finance.view listesinde OPEN/RESOLVED durumunu görür. Çözüm için hedef müşteriye `finance.approve` grant, staff/MFA ve fresh step-up gerekir. Plan case/payment/customer/currency/available/evidence etkisini gösterir; payload/state/plan HMAC ve açık onay korunur. Çözüm kalan dağıtılmamış bakiyenin tamamını `CREDIT_WALLET` veya `RESERVE_ORIGINAL_REFUND` ile mevcut servise taşır. `REVIEW_NO_FUNDS` yalnız kalan tutar sıfırken kanıtlı kapanış yapar; ledger yazmaz. Keyfî bakiye veya tahsilat değiştirme komutu yoktur.

Case/payment/wallet kilitleri, unique case resolution ve tek transaction sayesinde iki ayrı PHP çözüm sürecinden biri 200, diğeri 409 alır; tek kredi/karar oluşur. Kesinti testinde resolution, kredi/refund ve audit birlikte rollback olur. Eski case/tahsilat/fişler değiştirilmez. Kanıt metni AEAD şifreli, hash ve effect/actor/trace saklıdır. Yetkili scoped finance.view + fresh step-up ile kanıt görüntülenebilir; log/cache plaintext kanıt içermez. Kanıt insan tarafından doğrulanan belge referansı ve gerekçedir; otomatik banka ekstresi doğrulaması değildir. FX/orijinal yöntem politikası belirsiz case'ler GATE açıkken kapatılamaz.

## Performans ve concurrency

Tek Windows geliştirme makinesi, PHP 8.4.24 / MariaDB 10.11.18 / loopback. İlk 150, sonra 1000 yeni sentetik katalog aggregate'i eklendi. Son eşlenmiş State ölçümünde 1411 ürün, 1504 fiyat, 213 wallet entry, 132 sipariş, 12 kasa entry vardı. Kasa/müşteri geçmişi bu ölçümde küçüktür; milyon satırlı muhasebe yükü veya production hosting kapasitesi iddiası yoktur. Eski State, başlangıç commit'inden alınan aynı algoritmayla yeni ve eski karşılaştırmada **aynı şema ve veri hacminde** çalıştırıldı.

State deneyinde 1/4/8 bağımsız PHP süreci × 10 transaction, her transaction'da sabit 20 ms sentetik iş beklemesi; toplam eski 260 ve yeni 260 işlem. `lock_ms`, State::lock toplam süresidir; salt DB bekleme süresi değildir. Ayrı `Innodb_row_lock_time` global sayaç farkı gerçek DB kilit beklemesini toplu verir; eşzamanlı başka yük olmadan ölçüldü. Zaman ölçümünde float kullanımı yalnız performans telemetrisidir, para hesabı değildir.

| Süreç / müşteri | Eski State p50/p95 ms | Yeni State p50/p95 ms | Eski/yeni transaction p95 ms | Eski/yeni toplam DB kilit bekleme ms |
|---|---|---|---|---|
| 1 farklı | 20,26 / 21,92 | 5,20 / 5,72 | 45,85 / 30,55 | 0 / 0 |
| 4 farklı | 155,99 / 171,09 | 5,31 / 8,70 | 195,32 / 30,43 | 5139 / 0 |
| 8 farklı | 337,27 / 460,01 | 6,15 / 7,66 | 482,42 / 32,74 | 25552 / 0 |
| 4 aynı | 156,25 / 171,37 | 94,33 / 96,12 | 195,47 / 120,55 | 5163 / 3350 |
| 8 aynı | 350,20 / 476,83 | 215,67 / 218,05 | 510,68 / 242,84 | 26474 / 15687 |

Her iki algoritmanın ölçülen 260 işleminde deadlock/timeout hatası 0. Bu, deadlock ihtimalinin sıfır olduğunu kanıtlamaz. Yeni regression shared catalog ve FX fence tutulurken ikinci writer'ı 1 saniye timeout'a zorlar; 1205 sonrası rollback ve sonraki başarılı writer doğrulanır. Deadlock/timeout otomatik finansal tekrar üretmez; transaction rollback olur ve kullanıcı yeniden incelemelidir.

Kaynak ve ham sonuç: [benchmark aracı](../tools/f2-lock-benchmark.php), [eski büyük hacim](evidence/f2/review-lock-baseline-large.json), [yeni büyük hacim](evidence/f2/review-lock-after-large.json). Eski algoritma dosyasını üretmek için başlangıç commit'indeki State.php `storage/review-baseline-state.php` altına alınır ve sınıf adı BaselineState yapılır; araç `baseline-large` / `after-large` etiketleriyle çalışır. `seed 1000` yalnız izole test DB'sinde yeni sentetik kayıt ekler. Eski finans kayıtları silinmez.

### EXPLAIN ve gerçek ANALYZE

JSON kanıtlarında EXPLAIN ve `ANALYZE FORMAT=JSON` birlikte vardır. Eski catalog_prices tam PRIMARY index taraması yapar; son kayıttaki gerçek `r_rows` bütün fiyat satırlarıdır. Yeni catalog fence PRIMARY const lookup, tahmini 1 satırdır; MariaDB const optimizasyonunda r_rows=null döndürür, bu değer gerçek 1 satır ölçümü diye sunulmaz. finance_allocations `ix_allocation_customer` ref kullanır; bilinen müşteri örneğinde gerçek r_rows=1. Wallet customer unique const; payment_attempts ix_attempt_customer ref ve filesort, örnekte gerçek r_rows=1. Tek müşteri geçmişi büyürse customer state hâlâ büyür; sonraki ölçüm aggregate sınırına göre gereklidir. Geçmiş indeks dosyaları değişmedi; yeni customer indeksleri 020'dedir.

### Gerçek HTTP API gecikmesi

Sekiz ayrı PHP HTTP sunucusu, gerçek login/CSRF/step-up, 1/4/8 eşzamanlı istek, her yükte 5 round. Kimlik/anahtarlar rapora yazılmadı. Tek yerleşik PHP sunucusunun seri çalışmasını concurrency kanıtı saymadık. Toplam 260 HTTP prepare/execute isteği.

| Eşzamanlı / müşteri | Prepare p50/p95 ms | Execute p50/p95 ms | Sonuç |
|---|---|---|---|
| 1 farklı | 71,52 / 71,79 | 70,44 / 74,44 | 5 prepare ve 5 execute 200 |
| 4 farklı | 48,37 / 68,95 | 60,67 / 66,43 | 20 prepare ve 20 execute 200 |
| 8 farklı | 68,48 / 77,25 | 62,54 / 78,37 | 40 prepare ve 40 execute 200 |
| 4 aynı | 63,60 / 101,94 | 68,17 / 109,59 | Her round tek execute 200; 15 stale 409 |
| 8 aynı | 130,10 / 212,74 | 124,60 / 200,13 | Her round tek execute 200; 35 stale 409 |

Farklı müşterilerde stale 0; aynı müşteri eşzamanlı aynı state'e hazırlanmış onaylarda beklenen 50 stale ret, yanlış kabul 0 gözlendi. 5 round istatistikleri sınırlı örneklemdir. [Ham HTTP sonuçları](evidence/f2/review-http-load-large.json), [tekrar aracı](../tools/f2-http-benchmark.php). Auth hazırlığı ve kayıt fixture maliyeti tabloda yoktur; API HTTP roundtrip süreleri vardır.

**Onaya sunulan hedef önerisi, karar değildir:** gerçek staging hosting üzerinde 8 bağımsız müşteri, temsilî geçmiş hacmi ve en az 1000 round için prepare/execute p95 ≤250 ms; ilgisiz müşteri kaynaklı stale/lock timeout 0, kabul edilen çift mali hareket 0. Gerçek hosting kapasitesi görülmeden SLA verilmez; bu hedef için kodda eşik eklenmedi.

## Güvenlik/operasyon matrisi

| Alan | İncelenen sınır ve kanıt | Kalan risk |
|---|---|---|
| Isolation/IDOR | customer override ret; order/bill/PDF owner; reconciliation scope ve 404/403 testleri | Production veri/rol matrisi ayrıca kabul edilmeli |
| RBAC/impersonation | Identity yetkisi ve açık customer grant; Commerce finans impersonation ret | Global finance.view kurumsal ledger yetkisidir; yalnız finans personeline verilmeli |
| MFA/onay/replay | Güncel step-up, actor/session/version/input/state/plan HMAC, TTL, tek kullanım; yeni cache-replay regresyonu | UI'daki checkbox tek başına güvenlik sınırı değildir; server kontrolleri esastır |
| CSRF/idempotency/transaction | HTTP testleri, duplicate/refund/spend süreç yarışları, rollback ve outbox testleri | Ağ çağrısı transaction içinde yok; deadlock olasılığı tümüyle sıfırlanmadı |
| Decimal/overflow | Money string BIGINT sınırı, Decimal kesin string, JS BigInt; eski finance-unit testleri | DB/global SUM iç raporu büyük hacim için ayrıca kapasite izlemeli |
| Ledger/trigger yetkisi | Immutable trigger UPDATE/DELETE red testleri; unique finans referansları | Test DB izole admin/skip-grant-tables ile çalışır. Production runtime DDL/TRIGGER/DROP/ALTER/GRANT yetkisinin kaldırıldığı **kanıtlanmadı**; F6 release blocker. Migration rolü runtime rolünden ayrılmalı |
| PDF | Owner/path/MIME/hash/EOF/aktif token ret, attachment sandbox; şifreli metadata; POSIX özel izin kontrolü | Tam PDF parser/AV yok; obfuscation taraması yeterlilik iddiası yok. Dosya/DB ortak commit değildir; yarım upload yeniden başlatılır. Windows ACL ve retention/AV politikası deployment kabulüne bağlı |
| Secret/audit/log | SafeLogger exception sınıfı/trace dışında payload yazmaz; kontrollü AuditEvent; cache AEAD; kaynak taraması | Pattern scan tam penetrasyon testi değildir; DB/PDF/APP_KEY yedek ve rotasyon denetimi F6 |
| Ortam | Production Commerce mutation fail-closed; gerçek PayTR her ortam kapalı; sentetik FX yalnız test; immutable order origin | Konfigürasyonun güvenilir dağıtımı, DB import/restore kökeni operasyon sorumluluğu |
| Branch koruması | GitHub API protected=false, rules=[] | Yetkili repository sahibi kuralları etkinleştirmeden main doğrudan değişebilir |

Repository için öneri: main'e yalnız PR; en az 1 bağımsız onay, son push'tan sonra eski onayı düşürme, açık konuşmaları çözme; zorunlu `verify (8.3)` ve `verify (8.4)` check'leri ve güncel base; force-push/delete engeli; yönetici bypass'ını mümkün olduğunca kapatma; güvenlik/migration/finans için CODEOWNERS incelemesi. Bu ayarlar ve yeni CODEOWNERS ataması kullanıcı adına uygulanmadı. Koruma önerisi GitHub ürün arayüzünde test edilmiş bir konfigürasyon teslimi değildir.

## F2 kapsamının yeniden ayrımı ve karar

| Alan | Karar |
|---|---|
| Çalışan F2 çekirdeği | Katalog, fiyat/FX/quote, order/payment request, wallet/kısmi/karma ödeme, vergi/promosyon, test tahsilatı/havale onayı, iade rezervi/kredi, proforma/korumalı PDF/kasa ve saf onay çalışır. Eksik mutabakat kapanışı bu denetimde tamamlandı. |
| F2'ye ait, GATE nedeniyle henüz canlı tamamlanmamış | Gerçek PayTR create/hash/callback/refund ve para birimi kabiliyeti G-P01/P02; gerçek FX kaynağı/tazelik/fallback G-X01; vergi/e-belge/iade iş akışının resmî uygunluğu G-X02. Bunlar F3 veya F6'ya aitmiş gibi kapsamdan çıkarılamaz. FX/orijinal yöntem politikası belirsiz mutabakatlar açık kalır. |
| Gerçek F3 işi | Service/operation claim, manual activation, renewal/suspend scheduler, cPanel handler ve provider UNKNOWN inspect/reconciliation. Salt hesaplama RenewalPricePolicy F2'de var; çalışan service lifecycle F2 teslimi değildir. |
| F4/F5 | Domain/Cloud adaptörleri ve gelişmiş destek; F2'de yapılmış gibi gösterilmez. |
| F6/production blocker | Gerçek hosting cron/runtime ve least-privilege DB, backup/restore/PITR provası, mail teslimi, retention/PDF güvenlik kabulü, WHMCS geçişi ve cutover. Branch koruması yayın öncesi kapatılması gereken yönetişim riskidir; kullanıcı ayarı gerekir. |

**F2 bütün gereksinimleriyle tamamlandı mı?** Test edilebilir çekirdek ve kullanıcıca izin verilen mock/GATE sınırındaki F2 geliştirme kapsamı düzeltmelerle tamamlandı. Bütün V1 canlı finans şartları tamamlandı denemez; yukarıdaki F2 GATE bağımlılıkları açık ve gerçek entegrasyon kanıtı yoktur. Önceki raporun sınırsız tamamlanma yorumu doğru değildir.

**Gerçek eksik V1 özelliği var mı?** Denetim öncesi kanıtlı reconciliation resolution eksikti; giderildi. PayTR/gerçek kur ve resmî süreç doğrulamaları hâlâ eksik canlı kabiliyetlerdir, kapsamdan düşürülmedi. Provider operation/hizmet yönetimi ise gerçekten sonraki fazdır.

**F3 geliştirilmeye başlanabilir mi?** Kullanıcı bu denetimi kabul ettikten sonra mock ve sözleşme odaklı F3 geliştirmesi başlayabilir. Canlı provisioning için izin/kanıt yoktur. Bu görev F3'ü başlatmaz. F3 atomik unique claim/lease/UNKNOWN testleri ayrıca zorunludur; yalnız HandoffPolicy uygunluğu gerçek exactly-once create garantisi değildir.

**Açık GATE'ler:** G-V01/V02/V03/V04, G-P01/P02, G-C01, G-R01, G-D01, G-X01/X02, G-O01/O02/O03/O04, G-T01. Hiçbiri bu denetimle kapatılmadı. Kaynak: [14_OPEN_VALIDATIONS](14_OPEN_VALIDATIONS.md).

## Migration, test ve teslim kanıtları

020 immutable reconciliation resolutions ve müşteri indeksleri; 021 immutable sipariş ortam kökeni; 022 catalog/fx/cash sürüm fence'leri. Uygulanmış 001–019 değişmedi. Destructive down yok. Snapshot restore kod+DB+APP_KEY+PDF birlikte yapılmalı; yeni finans hareketi varsa önce mutabakat/forward-fix. Bu görev restore provası yapmadı.

Yerel tam test çıktısı [review-all-tests](evidence/f2/review-all-tests.txt); statik/Composer güvenlik kanıtı [review-checks](evidence/f2/review-checks.txt). Başarısız ilk replay testi özellikle kanıt olarak korunur; başarılı gibi sayılmaz. Performans ölçümleri ayrı deneydir, test sayısına eklenmez.

Nihai yerel paket: **175 test, 0 hata** = önceki 164 + 9 yeni post-delivery + 2 yeni HTTP regresyonu. Eski 164 senaryo kaldırılmadı veya gevşetilmedi. Post-delivery suite: şifreli kanıt/tek onay, scope/IDOR/impersonation/stale MFA/kanıt/GATE retleri, rollback ve refund reserve, no-funds kararının mali yazı üretmemesi, paralel tek karar/kredi, ilgisiz katalog değişimi, handoff eligibility, immutable origin ve shared version fence timeout/rollback. HTTP ekleri: gerçek reconciliation list/resolve/detail ve expired step-up cache replay reddi. Kaynak kontrolleri 173 PHP dosyası / 0 violation, 179 taranan dosya / 0 bulgu; PHPStan level 5 ve Composer locked audit başarılı. Runtime üçüncü taraf PHP bağımlılığı yoktur.

Bir yerel tam paket tekrarında eski session-concurrency testinin rotation senaryosu sonrasında `rmdir: Directory not empty` temizlik hatası görüldü; [başarısız koşu](evidence/f2/review-session-cleanup-failure.txt) korunur. Aynı suite kaynak değişmeden 7/7 geçti; başarısızlık anından sonra dizinde artık dosya görülmedi. Geçici Windows/OneDrive dosya temizliği yarışı olasılığı vardır, kök nedeni kesin kanıtlanmadı. Finans veya session güvenliğinde yanlış kabul saptandığı anlamına gelmez; başarısız koşu gizlenmedi. Linux CI ve nihai yerel tam paket ayrıca doğrulanır.

Kapanış uygulama commit'i: **d4fc4b705768d1eab1a5eda20c584f7ad91c2b65**. [GitHub Actions 39982455223](https://github.com/Boydaksu/hostidercp/actions/runs/39982455223): `verify (8.3)` ve `verify (8.4)` completed/success; MariaDB 10.11, her iki job'da 175 test, PHPStan, lint/architecture, security scan, Composer audit ve JS syntax başarılı. [CI makine kanıtı](evidence/f2/review-ci.json). Sonradan yapılan kapanış raporu commit'i kaynak kodunu değiştirmez; test edilen uygulama SHA'sı budur. [Koruma ve migration/test muhafaza kanıtı](evidence/f2/review-preservation.json): 001–019 değişiklik sayısı 0, toplam 175 ve hata 0.

Kalan riskler saklanarak teslim edilir: branch koruması kullanıcı/repository sahibi tarafından etkinleştirilmeli; production DB yetkileri ve restore provası kanıtlanmalı; PDF/retention ve gerçek hosting yük kabulü tamamlanmalı; PayTR/FX/e-belge GATE'leri kapanmalı. Bunları bu raporla kapatmış saymıyoruz. Kullanıcı kabulü beklenir; F3 veya canlı işlem başlatılmaz.
