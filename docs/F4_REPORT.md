# F4 bütünleşik mock çıkış kabul paketi

25 Ekim 2026. Dal: `codex/f4-domain-cloud`. Bu paket mevcut uygulamayı denetime sunar; yeni faz başlatmaz.

**Karar: sınırları belirtilmiş mevcut mock uygulamanın bağımsız bütünleşik denetime sunulması için GO; koşulsuz bütün F4 mock çıkışı için NO-GO. F4 DONE veya production-ready değildir.** C/D/E/F modül teknik kabulleri, bütünleşik çıkış kabulü yerine geçmez. Mapping/import CRUD için yalnız erteleme onayı vardır; uygulama/kabul yoktur. F5/F6, merge, staging ve production için yetki verilmez.

## 1. Kaynak ve gerçek CI kimliği

Kabul edilen uygulama: [`6e25a8b98de6e0c9da5e84df6b78e46cd47854f2`](https://github.com/Boydaksu/hostidercp/commit/6e25a8b98de6e0c9da5e84df6b78e46cd47854f2).
Paket öncesi GitHub HEAD: [`8156208ad90afcb8b563864e3fe065d8bea05f35`](https://github.com/Boydaksu/hostidercp/commit/8156208ad90afcb8b563864e3fe065d8bea05f35).
Bu belgeyi içeren paket commit'inin SHA ve otomatik CI bağlantıları [taslak PR #2](https://github.com/Boydaksu/hostidercp/pull/2) açıklamasında ayrıca kaydedilir; kendi commit SHA'sını içermeyen belge uygulama SHA'sı gibi sunulmaz.

| HEAD 8156208 doğrulaması | PHP 8.3 | PHP 8.4 |
|---|---|---|
| PR CI 40177941530 | [job 121122590833](https://github.com/Boydaksu/hostidercp/actions/runs/40177941530/job/121122590833) | [job 121122590630](https://github.com/Boydaksu/hostidercp/actions/runs/40177941530/job/121122590630) |
| Branch CI 40177937532 | [job 121122578804](https://github.com/Boydaksu/hostidercp/actions/runs/40177937532/job/121122578804) | [job 121122579019](https://github.com/Boydaksu/hostidercp/actions/runs/40177937532/job/121122579019) |
| Tam sonuç | 49 suite / 394 test / 0 hata | 49 suite / 394 test / 0 hata |

PR işlerinin ham logları yeniden alındı: [8.3 API](https://api.github.com/repos/Boydaksu/hostidercp/actions/jobs/121122590833/logs), [8.4 API](https://api.github.com/repos/Boydaksu/hostidercp/actions/jobs/121122590630/logs). Erişim GitHub oturumu gerektirebilir. [CI özeti](evidence/f4-mock-exit/baseline-ci.json) her işin suite, PASS, runtime, kalite ve ölçüm kayıtlarını içerir.

[319 dosyalık koruma manifesti](evidence/f4-mock-exit/preservation.json), uygulama/test/contract/SDK referansı/workflow/analiz ve kilit dosyalarının kabul edilen uygulama, HEAD ve çalışma kopyasında aynı olduğunu doğrular. Bu paket runtime, test veya migration değiştirmez. [Plan](F4_MOCK_EXIT_PLAN.md) kapsam/dosya/migration/test/geri dönüş sınırlarını belirtir. Önceki raporun C–F uygulanmadı ifadeleri tarihsel A/B durumuydu; güncel kabul durumunu bu belge temsil eder.

## 2. Modül kabul matrisi

| Modül | Mevcut kanıt | Kabul ve sınır |
|---|---|---|
| F4-A Domain model | domains, domains-boundaries; canonical ad, account/service, immutable contact/config | Model mock doğrulandı; bütün ürün/live kabulü değildir |
| F4-B registrar sözleşmeleri | domains-provider, domain SDK contract testleri | Kalıcı mock, replay fence, bağımsız inspect; canlı registrar PASS değil |
| F4-C Domain operasyonları | [C denetimi](F4_C_POST_AUDIT_REVIEW.md); domains-operations/audit | Kullanıcı teknik mock kabulü; DOMAIN-002 PARTIAL/MANUAL_REVIEW |
| F4-D Cloud operasyonları | [D denetimi](F4_D_POST_AUDIT_REVIEW.md), [son finans düzeltmesi](F4_D_FINAL_AUDIT_FIX.md); cloud/audit/races/final-audit | Kullanıcı teknik mock kabulü; fiziksel kanıt ve güncel finans ayrılır |
| F4-E maliyet/finans | [E teslimi](F4_E_DELIVERY_REPORT.md); cloud-costs/cost-races | Kullanıcı teknik mock kabulü; gerçek saatlik Voyar maliyeti kanıtlanmadı |
| F4-F HTTP/kanıt | [F denetimi](F4_F_POST_AUDIT_REVIEW.md); provider-panel-http/evidence-cases | Kullanıcı teknik mock kabulü; mapping/import CRUD uygulanmadı/kabul edilmedi |

Gereksinim izleri: FR-DOMAIN/VOYAR, DOMAIN-001/002/003, PROV-001/002/004/005/006, FIN-010, SEC-001–004, OPS-001/005 ve F4_PLAN'daki ilgili R1/R5/R7 maddeleri. Tamamı PASS sayılmaz; aşağıdaki istisnalar ve canlı GATE'ler geçerlidir.

## 3. Modüller arası akış ve invariant kanıtı

[153 F4 testinin kaynak satırı ve iki PHP PASS eşlemesi](evidence/f4-mock-exit/test-index.json) denetim indeksidir. Bunlar mevcut 394 testin alt kümesidir; yeni 153 test eklenmedi. Kaynak yolları repository köküne göredir. Ayrı testler aynı canonical servis/finans/provider sözleşmelerini sınar; tek bir dev uçtan uca senaryonun bütün canlı koşulları kanıtladığı ileri sürülmez.

| Akış / invariant | Kaynak ve test kanıtı | Doğrulanan sonuç |
|---|---|---|
| Ödenmiş sipariş → Domain kayıt/yenileme → kanıt → hizmet dönemi | src/Domains; tests/domains-operations.php:302,333; tests/domains-audit.php:98,106,133 | Doğru canonical sipariş/kalem, immutable renewal bağı; timeout sonrası exact dönem yalnız bir kez artar; başka ödenmiş DOMAIN siparişi kullanılamaz |
| Domain UNKNOWN → bağımsız inspect → SUCCESS/FAILURE | tests/domains-operations.php:155; tests/provider-panel-evidence-cases.php | Kanıt operation/attempt/account/resource/generation ile bağlanır; forged/stale/outcome değişimi reddedilir; yalnız kaynak yokluğu finans no-effect değildir |
| Ücretli Cloud entitlement → tek CREATE → task/resource | src/Cloud/Engine.php; tests/cloud-audit.php:16,22,28,38,46; tests/cloud-races.php:13 | Katalog capability satın alma yetkisi değildir; canonical customer/order/item/unit/immutable price ve fixed spec doğrulanır; yüksek PID/custom kaynak veya başka ürün reddedilir |
| Bir hizmetten iki fiziksel kaynak oluşturamama | tests/domains-provider.php:38; tests/domains-audit.php; tests/cloud-races.php:13 | Kalıcı operation, lease/fencing ve provider replay bağı; 1/4/8 bağımsız PHP worker tek etki ve tek completion |
| Crash/timeout/expired lease ikinci POST üretmez | tests/domains-audit.php:42; tests/cloud-races.php; tests/cloud.php; Voyar SDK/fence testleri | UNKNOWN fiziksel belirsizliktir; kayıp response veya auth exception sonrası mutation kör tekrarlanmaz; imported VM yeniden CREATE edilemez |
| Task ACCEPTED / receipt SUCCEEDED fiziksel başarı değildir | tests/cloud.php; tests/cloud-audit.php; tests/cloud-final-audit.php:59,62,65 | Task/resource doğrulanmadan ACTIVE/TERMINATED yok; sağlıklı WAITING_TASK gereksiz UNKNOWN alarmı değildir; event teslimi operation completion değildir |
| Provider mutation HTTP transaction dışında | kalıcı Domain/Cloud dispatch ve provider-panel inspection queue; tests/provider-panel-http.php:44,51 | HTTP hazırlama mutation/ledger yaratmaz; inspect ayrı cron'da; registrar mock açık uygulama transaction'ında çağrıyı reddeder |
| Power state / service finance / provider maliyeti ayrımı | tests/cloud-audit.php; tests/cloud-final-audit.php:29,32,35,53; tests/cloud-costs.php:48 | STOPPED finans yetkisi veya maliyet sıfırı değildir; TERMINATED payable kapatmaz; refund/cancel sonrası tarihsel ACTIVE geri yüklenmez |
| Refund sonrası güvenli personel containment | tests/cloud-audit.php:50,53; tests/provider-panel-http.php:50 | Normal CREATE/UPGRADE/REINSTALL ret; fresh native session, scope, step-up, state-bound confirmation, waiting ve irreversible terminate kontrolü; finans geçmişine yazılmaz |
| Güncel finans / bağımsız suspension | tests/domains-operations.php:205; tests/cloud-final-audit.php | Reconciliation güncel canonical finansı değerlendirir; eski financial_context audit snapshot'ı yetkilendirme değildir; UNKNOWN kanıtsızken korunur, ikinci mutation yok |
| Usage → exact cost → tek Ledger accrual | src/Cloud maliyet akışı; tests/cloud-costs.php:9,17,94; tests/cloud-cost-races.php | Integer/exact decimal; duplicate/overlap/currency/coverage kontrolleri, atomic Ledger+outbox, rollback ve concurrent claim tek accrual |
| Invoice reconciliation | tests/cloud-costs.php:48,52,56,71; tests/provider-panel-http.php:74 | Tekil invoice/evidence binding; provisional final değildir; MATCHED ödeme değildir; eksik maliyet veya fark manuel inceleme |
| FIN-010 ayrı öneri/onay/publication | tests/cloud-costs.php:83; tests/cloud-cost-races.php:29; tests/provider-panel-http.php:61 | Maliyet artışı kendiliğinden müşteri fiyatı yapmaz; ayrı mevcut Commerce onayı ve state-bound publication; receipt tüketimi publication completion değildir |
| Native HTTP izolasyon ve çift confirmation | tests/provider-panel-http.php; tests/provider-panel-evidence-cases.php:52 | Gerçek cookie/session ve iki HTTP server; customer/service izolasyonu, services.manage + services.reconcile, MFA/fresh scope, CSRF, idempotency, stale/replay/tamper ret; paralel confirmation tek reconciliation |
| Güvenli projection | provider-panel HTTP/evidence tests; src/ProviderPanel | Allowlist kanıt özeti; raw provider/contact PII/EPP/VNC/root/credential/encryption context HTTP müşteri projection'ına açılmaz; yetkisiz staff/customer kanıt okuyamaz |

Maliyet tahakkuku mevcut Ledger'da provider_cost borç / provider_payable alacak olarak dengelenir; kasa hareketi, müşteri tahsilatı veya gerçek provider fatura ödemesi değildir. Finans ve hizmet/sağlayıcı state makineleri ayrıdır. Fiyat/maliyet doğrulanmamış ücretli plan/custom değişiklikleri kapalıdır. Domain kesin FAILURE için fiziksel ve finansal no-effect kanıtı alt katmanda yeniden doğrulanır; UI butonu tek başına yetki değildir.

## 4. Kalite, migration ve bütünleşik worker/HTTP kanıtı

Her iki PR işinde PHPStan hatasız, Composer audit 0 güvenlik bildirimi, 311 PHP lint dosyası 0 ihlal ve 323 dosyalık pattern scan 0 bulgu doğrulandı. Tam 394 PASS korunur; bu teslimde yeni test eklenmedi veya eski test gevşetilmedi. PHPUnit dışındaki proje test runner suite sayısı CI özetinde ayrı tutulur.

[Migration replay ham özeti ve 48 SHA256](evidence/f4-mock-exit/migration-replay.json), yalnız izole gerçek MariaDB 10.11.18 / PHP 8.4.24 üzerinde bu paket için yeniden üretildi; canlı provider IO yoktur.

| Yol | Sonuç |
|---|---|
| Boş şema 001–048 | 48 APPLIED; assertReady PASS |
| Aynı şemada replay | 0 yeni migration |
| 001–047 taban → güncel | Yalnız 048_provider_panel.php uygulandı |
| Upgrade sonrası replay | 0 yeni migration; assertReady PASS |
| Her iki şema checksum | 48/48 APPLIED SHA256 eşleşti; izole şemalar temizlendi |

001–048 değiştirilmedi; yeni migration yoktur. CI ayrıca Domain 042→güncel, Cloud 044→güncel ve maliyet 045→güncel upgrade kontrollerini çalıştırır. Bazı tarihsel stdout etiketleri ilk eklenen migration adını taşır; gerçek expected migration dizileri ve mevcut 048 kapsamı kaynak/manifest ile değerlendirilmelidir. Bu replay production restore veya production upgrade provası değildir.

Native HTTP/session stabilite döngüleri her PHP işinde Services HTTP 10, Commerce HTTP 30, session concurrency 20 kez 0 hata; üç JavaScript kontrolü başarılıdır. Provider panel suite gerçek cookie/session, farklı müşteriler/personel ve paralel HTTP confirmation kullanır; salt method unit testi değildir.

Domain/Cloud ve maliyet yarış suite'leri gerçek MariaDB ile bağımsız PHP process kullanır: 1/4/8 worker, duplicate claim/completion, expired lease, UNKNOWN, immutable billing ve tek etki. Cloud/cost cron testleri 101 bloklu kaydı aşarak sağlıklı işe ilerler; overlapping bounded cron'lar tek maliyet oluşturur. `cloud-final-audit.php:59,62,65` event receipt başarılı olduktan sonra Cloud WAITING_TASK, Cloud PENDING ve Domain PENDING işlerini bağımsız cron CLI üzerinden tamamlar. `cloud-cost-races.php:29` tüketilmiş cost receipt sonrası proposal işini bağımsız cron ile kurtarır. Receipt teslimi, operasyon ilerlemesi ve provider sonucu ayrı kavramlardır.

CI ölçümleri küçük mock test yükleridir; gerçek Domain lookup/panel production p50/p95, kapasite/SLO veya iki saatlik Voyar kullanım kanıtı değildir. Bu ölçümler doğrulanmadı; G-O01/PROV-005 OPEN kalır.

## 5. Açık istisnalar, ticari kararlar ve production engelleri

| Konu | Durum / kabul sınırı |
|---|---|
| DOMAIN-002 otomatik ikinci registrar/failover | **PARTIAL / MANUAL_REVIEW**; güncel fiyat/bakiye/ücret farkı onayı kanıtı yok; otomatik ikinci POST açılmaz |
| Mapping/import CRUD | **DEFERRED / NOT IMPLEMENTED / NOT ACCEPTED**; sahibinin açık erteleme onayı var. [Ayrı kapsam/yetki/test planı](F4_F_MAPPING_IMPORT_SCOPE.md); mock imported bind/CREATE guard testi CRUD kabulü değildir |
| PROV-005 gerçek iki saatlik Voyar maliyet/termination | **OPEN**; fixture/exact hesap gerçek tarife kanıtı değildir |
| Gerçek credential retrieval, canlı mapping, müşteri operasyonu | **NOT IMPLEMENTED / NOT VERIFIED**; secret/VNC/root teslimi kabulü yok |
| Domain transfer completion, DNS/EPP | **NOT IMPLEMENTED / NOT VERIFIED**; mock transfer initiation tamamlanmış transfer değildir |
| Ücretli custom/plan değişimi, gerçek provider EXTEND/renewal | **CLOSED / NOT VERIFIED**; SDK yöntemi tek başına entitlement/finans/operasyon kabulü değildir |
| Provider account ve gerçek response/provenance eşlemesi | Mock sözleşme kanıtı; canlı kabul yok |

[Canlı doğrulamalar](14_OPEN_VALIDATIONS.md) ve F4_PLAN esas alınır: **G-V01–04, G-R01, G-D01, G-C01, G-P01/02, G-X01/02, G-O01–04 ve G-T01 OPEN**. Bu rapor hiçbir GATE kapatmaz. Gerçek Voyar/registrar/PayTR/WHM/SMTP veya production çağrısı yapılmadı.

Voyar minimum quantum/yuvarlama, vergi/ek ücret, IP/lokasyon, STOP/TERMINATE fatura etkisi, FX ve ticari marj politikaları canlı kanıt ve ayrı ticari karar gerektirir. Mock fatura eşleşmesi provider borcunu ödemez; öneri onayı müşteri fiyatını kendiliğinden yayınlamaz. UNKNOWN, eksik maliyet/fatura, stale veya mismatch kanıt manuel inceleme gerektirir.

## 6. Başarısızlıklar, geri dönüş ve kabul kararı

Bu paket incelemesinde yeni runtime kusuru veya 394-test başarısızlığı görülmedi; kabul edilen modülleri yeniden yazmak gerekmemiştir. Önceki denetimlerde düzeltilen entitlement, finansal containment, eski ACTIVE snapshot ve kanıt/FAILURE kusurlarının kayıtları ilgili C/D/E/F raporlarında korunur. Bu belge eksik canlı kanıtı başarılı saymaz. Test/mock dışında güvenlik, ticari veya operasyonel bütünlük kabulü verilmez.

Paket yalnız belge/kanıt değiştirir; geri dönüş belge commit'inin geri alınmasıdır. Runtime geri dönüşü için tutarlı kod/DB/APP_KEY yedeği ve forward-fix/restore planı gerekir; immutable finans/evidence silinmez, migration downgrade veya UNKNOWN POST replay yapılmaz.

**GO:** Mevcut 394 testle sınırlı, istisnaları açık mock uygulamanın bağımsız bütünleşik F4 çıkış denetimine sunulması.

**NO-GO:** Bütün F4_PLAN kapsamının koşulsuz tamamlandığı iddiası; mapping/import CRUD kabulü; gerçek sağlayıcı/finans/operasyon kabulü; production-ready/F4 DONE; F5/F6, merge, staging veya production'a otomatik geçiş.

Sınırlandırılmış mock çıkışının açık istisnalarla kabul edilip edilmeyeceği bağımsız denetim ve proje sahibinin kararıdır. Bu paket bunu peşinen kabul etmez. Sonraki adım bağımsız bütünleşik F4 mock çıkış denetimidir; geliştirme burada durur.
