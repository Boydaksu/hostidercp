# F4 ilerleme raporu — tamamlanmamış test/mock kapsamı

24 Ekim 2026. Dal `codex/f4-domain-cloud`, kabul edilen taban `c9eb0fcc87038ea3b3c58d9a2b5415af8be5d731`. **F4 mock teslimi tamamlanmadı; teknik kabul veya DONE önerilmez.** F5 başlamadı. Main push/merge, production veya gerçek finans/sağlayıcı işlemi yapılmadı.

## Uygulanan kapsam

İlk tasarım/izlenebilirlik teslimi [F4_PLAN](F4_PLAN.md), özgün SDK incelemesi [F4_VOYAR_SDK_REVIEW](F4_VOYAR_SDK_REVIEW.md) içindedir. Ayrı Account Handoff/Developer Package dosyaları bu adlarla depoda bulunamadı; içerikleri doğrulanmış varsayılmadı.

F4-A veri modeli temeli: `Domains/DomainName`, `TldPolicy`, `Registry`; explicit TLD/provider mapping, müşteri ve service bağlantısı, context-bound AEAD/HMAC contact snapshot, canonical IDNA unique name, nameserver snapshot ve immutable history. Registry yalnız test ortamında trusted in-process servistir; public endpoint değildir. Service materyalizasyonu mevcut Services repository üzerinden yapılır; finans/Identity tablolarına doğrudan yazılmaz. Aynı transaction'da history, kontrollü audit ve outbox vardır. Replay sırasında farklı contact/nameserver/name/rule reddedilir. Domain status PENDING kalır; attach başarıyla kayıt yapıldığı anlamına gelmez.

F4-B kalıcı mock temeli: `DomainManagement` optional contract ve `DurableRegistrar`, hem ResellerClub hem DomainNameAPI yapay account kimliğiyle çalışır. Availability/register/renew/transfer-initiation/status/nameserver/lock/privacy yöntemleri vardır; eylem ayrıca TLD policy'den geçer. DNS ve EPP güvenli teslim/model tamamlanmadığından UNSUPPORTED döner ve global mock capability listesinde bulunmaz. Transfer-initiation yalnız TRANSFER_PENDING yaratır; transfer completion veya gerçek EPP süreci tamamlanmış değildir. Yıl sayısı mock sentetik term sayacıdır; gerçek registrar expiration/grace hesaplama kanıtı değildir.

Mock dış dünya kendi commit edilmiş transaction'ında domain ve call saklar; açık uygulama transaction'ında çağrı reddedilir. Operation UUID+account+action+resource+configuration keyed fingerprint ile bağlanır. Contact plaintext veya raw response call tablosuna yazılmaz. Aynı register/renew UUID'si dört bağımsız PHP worker'da bir fiziksel etki oluşturur. Availability rezervasyon değildir. TIMEOUT_AFTER_EFFECT dış domaini saklar ama UNKNOWN sonucunda resource ID döndürmez; ayrı inspect mevcut kaynağı bulur. TIMEOUT_BEFORE_EFFECT sonucu no-effect kanıtı sayılmaz. DEFINITIVE_FAILURE no-effect yalnız sentetik fixture kanıtıdır.

## Gereksinim ve kabul ayrımı

| Kimlik | Bu değişiklikte doğrulanan | Henüz tamamlanmayan |
|---|---|---|
| FR-DOMAIN / R1-05 | IDNA, TLD/contact capability, encrypted model, mock register/renew/status/transfer-initiation | Üretim contact sözleşmeleri, tam domain operation/renewal/transfer/grace-redemption, DNS/EPP ve paneller |
| DOMAIN-003 | In-process policy ve provider mock unsupported retleri | Gerçek API/UI capability davranışı; tam kabul PASS değil |
| DOMAIN-001 / DOMAIN-002 / R7-07 | Mock belirsiz/no-effect fixture'ları hazır | Uygulama operation/lease/fence, alternatif registrar revalidation ve manuel reconciliation henüz yok; PASS değil |
| SEC-004 | Domain/contact başka müşteri retleri, scope/freshness ve client projection | Domain HTTP session commit fence, confirmation/replay/revocation ve browser testi henüz yok |
| OPS-005 | Mock UUID replay için dört bağımsız PHP process; tek create ve tek renew etkisi | Domain uygulama job claim/fencing ve 1/4/8 worker performans/cron testi henüz yok |
| OPS-001 | Model ve durable mock test-only sınırı; eski live effect policy regresyonları korunur | F4 public dispatch henüz eklenmedi; canlı transport yok |
| PROV-001/002/006 | Önceki F3 hosting regresyonları geçiyor | Cloud VM/task/UNKNOWN/extend henüz uygulanmadı; hosting kanıtı Cloud kabulü değildir |
| FIN-010 | Önceki finans testleri korunur, yeni provider mock para hareketi yazmaz | Cloud cost ledger dedupe ve fiyat önerisi/onay pipeline henüz yok |
| PROV-005 | SDK kaynak incelemesi | Kalıcı Cloud mock, iki saat mock cost ve gerçek ondemand/termination kanıtı yok; canlı PASS değil |

## Cloud ve canlı kanıt sınırı

F4-D/E/F Cloud veri modeli, kalıcı Voyar mock, VM action/task polling, exact hourly usage/cost, FX/cost ledger, fiyat önerisi onayı ve müşteri/yönetici panelleri uygulanmadı. F0 `Mock/Voyar` yeni F4 entegrasyonu sayılmaz. SDK'da görülen yöntemlerin ayrıntılı kanıtı ayrı rapordadır. Özellikle credentials ile AuthenticationException sonrası POST tekrarının method filtresi olmadığı görüldü; canlı mutation için bu davranış güvenli kabul edilmedi.

G-V01–04, G-R01, G-D01, G-C01, G-O01–04, G-P01–02, G-X01–02, G-T01 **OPEN**. G-V01 kapanmadan Cloud otomatik satışı, G-V02/V03 kapanmadan canlı dispatch, G-R01/G-D01 kapanmadan registrar mutation açılmaz. Gerçek PROV-005 maliyet/termination kanıtı yoktur. G-C01/G-O01 dahil önceki fazların gerçek WHM/hosting cron/runtime kabulü kapanmaz. Main koruma ayarları bu değişiklikte değiştirilmedi; gerekli kurallar plan ve kullanıcıya verilen bildirimde belirtildi.

## Migration, test ve kanıt

039: domain account/TLD/rule/domain/contact/nameserver/history + immutable trigger'lar. 040: ayrı mock registrar domain/call dünyası + immutable calls. Migration 001–038 ve eski test dosyaları için 97 dosyalık Git blob karşılaştırmasında sıfır değişiklik: [preservation.json](evidence/f4/preservation.json).

Yerel PHP **8.4.24**, gerçek MariaDB **10.11.18**: önceki **238** + yeni **10**, toplam **248/248**, sıfır hata: [tam çıktı](evidence/f4/local-final-tests.txt). Yeni testler: 3 IDNA/policy unit, 3 model/izolasyon/rollback, 4 durable contract/timeout/no-effect/bağımsız-process replay. 039/040 boş şemada uygulanır ve suite'ler boyunca migrate/replay edilir. Yeni domain DB testleri ayrı `hostider_f0_test_domain_*` geçici şema oluşturup shutdown'da kaldırır.

İlk model koşusunda canonical JSON anahtar sırası assertion'ı ve provider fixture config değişkeni düzeltildi. İlk tam koşuda önceki domain fixture verisinin ortak test şemasında kalması Identity cleanup FK hatasına yol açtı: [ilk çıktı](evidence/f4/local-tests.txt). Test izolasyonu düzeltildi, sonraki koşu temiz ayrı şemada 248/248 geçti. DB constraint kaldırılarak test gevşetilmedi; önceki testler değiştirilmedi.

[Statik kontrol](evidence/f4/checks.txt): 246 PHP dosyası/0 ihlal, PHPStan level 5/0 hata, 256 source/config/test dosyası güvenlik örüntü taraması/0 bulgu. Bu örüntü taraması tam güvenlik denetimi değildir. Workflow'a intl ve yeni üç suite eklendi. **PHP 8.3/8.4 uzak CI henüz çalıştırılmadı, CI bağlantısı yoktur.** Eski F3 CI bu yeni kaynağın kanıtı olarak kullanılmaz. Yeni Composer bağımlılığı yoktur; özgün SDK runtime'a yüklenmez.

Demo komutları: `php tests/domains-unit.php`, `php tests/domains-model.php`, `php tests/domains-provider.php`. DB testleri yalnız `APP_ENV=test` ve `TEST_DB_DSN` içinde `hostider_f0_test*` izole şema kabul eder. CLI process çıktıları synthetic status/resource ile sınırlıdır. Kullanıcıya açık yeni ekran henüz yoktur.

F4 1/4/8 worker benchmark, domain lookup/panel p50/p95, backlog, lock-wait/deadlock ve operation throughput ölçümleri yapılmadı. Tam suite'in F3 benchmark satırı yalnız eski hosting mock ölçümüdür; Domain/Cloud performansı veya production kapasite iddiasına çevrilmez.

Geri dönüş: additive DDL için otomatik destructive down yoktur. Worker durdurma ve tutarlı kod+DB+APP_KEY+korumalı dosya backup; doğrulanmış snapshot restore veya forward-fix gerekir. Domain history/call/contact kayıtları uygulamadan silinmez. Production backup/restore provası bu testlerde yapılmış sayılmaz.

## Sonraki bağımlılıklar

F4-A/B hâlâ kısmi; domain lifecycle state binding, provider response evidence, operation/lease/fencing, finans hizmetleriyle domain order/renewal bağlantısı ve DOMAIN-001/002 failover ilk sıradadır. Mevcut service ownership transferine domain owner/contact bağlamı henüz eklenmedi; bu nedenle Domain ürünlerine müşteri transferi veya lifecycle HTTP erişimi açılmaz. Sonra Cloud D/E ve ortak HTTP/panel F tamamlanmalı, tüm concurrency/performance/CI/demolar doğrulanmalıdır. **Tam F4 mock teslimi ve gerçek roadmap çıkış şartları karşılanmadı; kabul bekleyen bitmiş F4 teslimi olarak sunulmaz.**
