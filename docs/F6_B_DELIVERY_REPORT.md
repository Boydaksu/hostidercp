# F6-B — sentetik izole import / dry-run teknik teslimi

## 27 Ekim 2026 — proje sahibi F6-B kabul kararı

**F6-B LIMITED SYNTHETIC ISOLATED IMPORT & DRY-RUN TECHNICAL BASELINE — ACCEPTED.**
**B-01 CLOSED.** Kabul edilen uygulama `d4ead54db3aad6208e1a40e70822da7ad1346e33`,
tree `0057f233dbbeebfae1b3c094ec8eeafdb0fad7a4`; ayrı kanıt belge commit'i
`dadef0b3c2d36e44dcbf2236227d160071945566`.
[FAST40514399931](https://github.com/Boydaksu/hostidercp/actions/runs/40514399931):
PHP8.3/8.4 her sürüm55 suite/664 test/0 hata; eski621+42 ve yeni1 B-01 PASS.
001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` korunur.

Kabul yalnız sentetik source validation, deterministic dry-run, isolated load,
mapping/provenance, idempotency/replay, transaction/checkpoint/recovery, sandbox/grant
izolasyonu, provider CREATE-zero, sentetik finance/support privacy ve commit öncesi
migration readiness içindir. Gerçek WHMCS, payment/ledger mutabakatı, provider resource,
support dosyaları, mailbox/AV/retention, FULL ve production-ready kabulü değildir.
F6-C SYNTHETIC RECONCILIATION & SECURITY DEVELOPMENT — GO; gerçek bağlantı,
production mapping CRUD, F6-D/E, merge/staging/deploy/cutover yetkisi yoktur.

Bu kabul commit'i yalnız dört belgeyi değiştirir; uygulama/test/fixture/CI/migration
aynıdır. Önceki CI tekrar çalıştırılmadı. Rollback yalnız belge commit revert'üdür.
PR3 OPEN/DRAFT. Son kontrolde main protected=false ve rulesets=[] görüldü;
ayarlar değiştirilmedi. Aşağıdaki teslim/bekleme kayıtları tarihçe olarak korunur.

## 27 Ekim 2026 — B-01 bağımsız denetim düzeltmesi

Commit öncesi tam readiness kontrolü eklendi; mevcut assertBound korundu.
[B-01 karşı-örnek, hedefli test ve kanıt kaydı](F6_B_B01_REVIEW.md).
Yeni uygulama / FULL aday SHA `d4ead54db3aad6208e1a40e70822da7ad1346e33`,
tree `0057f233dbbeebfae1b3c094ec8eeafdb0fad7a4`. Tek yeni negatif test eski
kaynakta FAIL, düzeltilmiş kaynakta PASS; eski42 test değişmedi.
[FAST40514399931](https://github.com/Boydaksu/hostidercp/actions/runs/40514399931):
PHP8.3/8.4 her sürüm55 suite/664 test/0 hata; eski621+42 adları eksiksiz, yeni1 PASS.
Bağımsız teknik kabul bekler; FULL/F6-C yetkisi yoktur.
Aşağıdaki663-test/eb3c2f6 kaydı ilk F6-B tesliminin tarihsel kanıtıdır.

Durum: sentetik geliştirme ve gerçek PHP8.3/8.4 FAST tamamlandı; bağımsız teknik kabul bekler.
F6-B ACCEPTED, FULL DONE veya PRODUCTION READY değildir.
Yetki/kapsam: [F6_B_PLAN](F6_B_PLAN.md), [ADR-025](adr/ADR-025-synthetic-isolated-import.md).
F6-A sınırlı kabulü ayrı c70ecf84215d65a5d582f5251cfb6a7de7a76087 belge commit'indedir.

## Çalışan kapsam ve kaynak sınırı

`src/Migration/Synthetic/` yalnız controlled Fixture→F6-A validation→Plan→Sandbox→
Loader akışıdır. Native Database/transaction/Migrator ve F6-A Money/Currency,
SourceIdentity/MappingEvidence tekrar kullanılır. DRY_RUN salt öneridir, imported_records0;
SYNTHETIC_LOAD yalnız ephemeral teknik facts yazar. Başarılı load'da imported_records,
committed cursor'dur; replay=true yeni26 kayıt oluşturduğu anlamına gelmez.
FAILED manifestindeki0, mevcut toplamın yeniden okunup sıfırlandığı iddiası değildir;
kalıcı ilerleme yalnız doğrulanmış DB checkpoint'i yeniden okunarak belirlenir.
Yeni Identity/Queue/Outbox/Audit/Finance engine veya native API/cron handler yoktur.

Input kabul edilmiş JSON'un sabit byte SHA-256'sı ve kod allowlist'i ile sınırlıdır.
Gerçek WHMCS/dump/URL/path/DB/credential input'u yoktur. APP_ENV=test ve explicit
SYNTHETIC_IMPORT_ALLOWED=1, loopback-only bağlantı, actual DATABASE/CURRENT_USER,
role/grant allowlist, süreli secret marker, exact schema/table/migration readiness
birlikte gerekir. Runtime sadece SELECT/INSERT ve runs UPDATE hakkına sahiptir.
Schema SELECT grant'indeki underscore literal escape edilir; benzer isimli kardeş
DB'ye SELECT de reddedilir. PUBLIC/role/başka schema/global haklar fail-closed'dur.

## Mapping / idempotency matrisi

| Durum | Gerçek davranış |
|---|---|
| Aynı fixture dry-run / reversed sıra | Aynı plan hash/count/reasons; hiç DB yazısı yok |
| İlk synthetic load |26 typed entity +26 mapping +26 provenance, ayrı source/target keys|
| Aynı snapshot tekrar / worker retry / queue replay simülasyonu |APPLIED no-op; checkpoint/history/facts değişmez|
| Aynı source ID, farklı içerik/snapshot |SOURCE_CONTENT_CHANGED / SOURCE_CONTEXT_CHANGED review; overwrite yok|
| Aynı snapshot ID, farklı tam kök; değişen satır henüz yüklenmemiş |SOURCE_SNAPSHOT_CONFLICT; committed prefix ve orijinal resume korunur|
| Başka source'un aynı target'ı sahiplenmesi | preflight conflict ve fiziksel UNIQUE(target_id) ret |
| Aynı resource/payment/email başka source | unique external claim conflict; sessiz merge yok |
| Ayrı instance/account/ref | ayrı52 fact/mapping; source identity birleştirilmez |

## Transaction / checkpoint / recovery matrisi

| Senaryo | Kanıtlanan sınır |
|---|---|
| PLANNED→VALIDATED→APPLYING→APPLIED | sadece F6-A eligible source; invalid source VALIDATED olmadan REVIEW_REQUIRED |
| Batch write | native transaction'da entity+claim+mapping+provenance+history+checkpoint |
| Entity sonrası / checkpoint sonrası exception | failed batch rollback; önceki cursor korunur; ayrı durable FAILED history |
| Committed prefix |3'er kayıtlık batch'lerden aynı manifest ile26'ya resume; dependency gap yok|
| Stale/wrong target checkpoint | hash/target snapshot/run/version/cursor uyuşmazlığı ret, target yazısı yok |
| Process exit73 | gerçek child kapanışı DB rollback; yeni process aynı source'u bir kez tamamlar |
| Own SQL connection kill | gerçek bağlantı kaybı; safe FAILED manifest, reconnect durable checkpoint'ten devam |
| Failed additive test DDL |APPLYING history kalır; readiness ve blind replay reddedilir|
|1/4/8 PHP process | sandbox-local DB mutex + unique constraints,26 mapping/provenance ve tek run |

FAILED manifest her zaman yeni checkpoint commit edildiği anlamına gelmez. Process crash
ve bağlantı kaybında en son durable checkpoint korunur; bilinmeyen outcome kör replay
yerine tekrar okunur. Her batch güncel fence ve mapping'i tekrar doğrular. Advisory
mutex yeni genel queue/worker engine değildir; bounded test import batch'ini seri yapar.

## Finansal kaynak–hedef matrisi

| Kaynak | Sentetik hedef ve sınır |
|---|---|
| Order/payment/allocation | ayrı facts; kaynak PARTIAL ve4000 TRY allocation değişmez; proof olmadan PAID yok |
| Wallet liability | tek source owner claim,2000 TRY immutable evidence; tekrar yüklemede ikinci hareket yok |
| Refund | ayrı entity sözleşmesi ve original payment limit kontrolü; F6-A REFUND_RECONCILIATION_REQUIRED nedeniyle review-only, otomatik load yok |
| Historical FX / başka currency | tahmin veya dönüşüm yok; explicit review/ret |
| Official PDF / proforma | ayrı tür ve özgün numara/tutar; yeni yasal belge veya PDF dosyası üretilmez |

Native production ledger'a tek debit/credit, cash/payment/billing operation yazılmaz.
F6-C mutabakat veya refund policy kabulü bu teknik modelle kapanmaz.

## Provider CREATE-zero ve destek gizliliği

CPANEL/VOYAR/RESELLERCLUB/DOMAINNAMEAPI için ilk load, ikinci load, retry/queue replay
simülasyonu ve1/4/8 child sonuçlarının CREATE sayacı0'dır. Diğer payment/billing/
mail/notification sayaçları da0. Effect boundary explicit talepleri reddeder;
runtime native adapter/transport bağımlılığı yoktur ve native tablo hakları yoktur.
Bu gerçek provider resource doğrulaması veya gerçek queue consumer teslimi değildir.

| Sentetik provider | İlk load | İkinci load / retry / queue replay |1/4/8 process|
|---|---|---|---|
| cPanel |CREATE0|CREATE0|CREATE0|
| Voyar |CREATE0|CREATE0|CREATE0|
| ResellerClub |CREATE0|CREATE0|CREATE0|
| DomainNameAPI |CREATE0|CREATE0|CREATE0|

| Support alanı | Korunan davranış |
|---|---|
| Ticket/customer/requester | immutable source bağları; test-only public projection cross-owner ret |
| Mesaj sırası/zamanı | source ID, sequence, original timestamp değiştirilmeden saklanır |
| PUBLIC_REPLY | yalnız KNOWN PUBLIC; F6-A P1 değişmez |
| INTERNAL_NOTE | doğrulanmış source STAFF + KNOWN PRIVATE; public mesaj listesine alınmaz |
| Public attachment view | yalnız aynı owner/ticket ve PUBLIC_REPLY+PUBLIC mesajın metadata'sı |
| Private attachment / author/path | native public handler'a bağlanmaz; projection author_id/author/path veya private body çıkarmaz |
| Missing/corrupt manifest | review; tamamlanmış import veya gerçek checksum/MIME/AV doğrulaması ilan edilmez |

Native F5-E client projection/can_attach, Identity/MFA/session/revoke/Finance değişmez.
Yeni HTTP endpoint yok: yeni CSRF/native-session-revoke/step-up endpoint testleri
NOT APPLICABLE; yeni auth yüzeyi açılmadı. Eski native621 regresyon aynen korunur.

## Hedefli test ve başarısız denemeler

[Yerel42 test](evidence/f6-b/local-targeted.txt): PHP8.4.24 + gerçek MariaDB10.11,
42/42 PASS. F6-A31+8 ve ayrı6 CI helper de PASS. PHPStan0 hata; lint414/0,
pattern436/0. Gerçek FAST sonuçları exact kaynak kimliğiyle aşağıda kayıtlıdır.
[Gerçek demo manifest'i](evidence/f6-b/example-manifest.json) DRY_RUN, ilk load ve
replay'i ayrı gösterir; gerçek dosya/native entity yazısı yoktur. Tekrar demo:
Manifest mapping_plan alanı F6-A MappingEvidence hedef/kanıt/dependency önerilerinin
sıralanmış digest'ini, source_snapshot_roots tam kaynak bağını verir. Ek42. test
bu digest'i gerçek DB mapping kayıtlarından yeniden hesaplar; eşitlik ve köklerin
UPDATE/DELETE reddi PASS. Son hedefli test toplamı42/42'dir.
yalnız explicit test env ve izole admin fixture harness ile `php tools/migration-import-demo.php`.
Root/setup hesabı yalnız test schema/account kurup kaldırır; loader en az yetkili ayrı hesap kullanır.

İlk37-test geliştirme turu33 setup FAIL verdi: `cursor` reserved keyword DDL hatası.
DDL düzeltmesinden sonraki37/33 FAIL, yeni yerel MariaDB'nin varsayılan PUBLIC test
haklarının gate tarafından reddiydi. Yalnız görev için oluşturulan/datadir kimliği
doğrulanan yerel test sunucusunda PUBLIC test hakları kaldırıldı; runtime gate
gevşetilmedi. Sonraki37/37 PASS. Ek kardeş-schema regresyonu39-test turunda1 FAIL
ile underscore wildcard riskini yakaladı; literal privilege escape sonrası39/39,
son40/40 PASS. Başarısız turlar başarı veya kabul yerine sunulmaz; yeni testler dışında
eski test/assertion değiştirilmedi. Gerçek dosya/MIME/AV/retention henüz doğrulanmadı.

Ek negatif test, eski422bf81 kaynağında ilk1 kayıt sonrası aynı snapshot'ın henüz
aktarılmamış customer-a içeriğinin değişmesini yakaladı:41 test/1 FAIL,
beklenen REVIEW_REQUIRED yerine APPLIED. [Önceki çıktı](evidence/f6-b/snapshot-before.txt).
Tam kaynak snapshot'ı ilk batch öncesinde immutable envelope köküne bağlandı;
SOURCE_SNAPSHOT_CONFLICT artık prefix'i değiştirmez, orijinal checkpoint devam eder.
Snapshot düzeltmesi sonrası41/41 PASS. Kaynak kökleri runtime UPDATE/DELETE dışındadır.

İlk gerçek FAST [40501763210](https://github.com/Boydaksu/hostidercp/actions/runs/40501763210)
iki PHP sürümünde eski621 testi0 hata ile geçti; yeni40 test35 setup hatası verdi
(F6B_TEST_SETUP_FAILED_1045). Docker köprüsünde server-observed client host'u
127.0.0.1 olmadığından yalnız test hesaplarının literal host bağlamı uyarlandı;
wildcard host/hak eklenmedi. [Başarısız CI kaydı](evidence/f6-b/ci-first-failure.json)
kabul kanıtı değildir; son adayda iki gerçek FAST sonucu ayrıca gereklidir.

Ara aday5bdda55 için [40504982899](https://github.com/Boydaksu/hostidercp/actions/runs/40504982899)
yeni eb3c2f6 push'u nedeniyle concurrency tarafından CANCELLED oldu; tamamlanmış
uygulama testi kanıtı olarak sayılmaz. Son aday tüm55 suite'i yeniden çalıştırır.

## Migration / rollback / F6-C bağımlılığı

001–054 production migration byte/hash korunur. Yeni production migration yoktur.
Yalnız `tests/fixtures/f6b-schema/001_synthetic_import.php` ayrı schema'ya native
Migrator ile uygulanır; runtime DDL yetkisi yok. Fixture teardown exact rastgele
schema ve iki geçici hesabı kaldırır; native DB restore/down yapılmaz. Rollback
F6-B uygulama commit revert ve owned test fixture teardown; forward-fix immutable
facts/provenance'i overwrite etmez. Gerekli sözleşme değişikliği versioned olmalıdır.
Kod geri dönüşü gerekirse son uygulama değişiklikleri ters sırayla ele alınır:
eb3c2f6 →5bdda55 →422bf81. Ayrı F6-A kabul kaydı c70ecf8 bu geri dönüşün parçası
değildir. Bu rapor bir rollback icrası değil, geri dönüş planıdır.

F6-C: gerçek schema discovery/adapter, native application seam uygunluğu, para ve
ownership/file mutabakatı, gerçek resource account eşliği ve current ACL tekrar
kontrolü ayrıca gerekir. G-O03, gerçek SUP-010/PROV-004, DOMAIN-002 PARTIAL,
production mapping/import CRUD DEFERRED, PROV-005, F5-C dört canlı istisna,
auto-close/merge/split/tarihsel SLA ve tüm vendor/operasyon/live GATE'ler açık kalır.
F6-D/F6-E, staging/merge/deploy/cutover yetkisi yoktur. PR3 OPEN/DRAFT;
main protection değişmez. FULL bu adayda henüz çalıştırılmadı; dispatch yapılmayacak.
Son SHA/tree ve gerçek FAST kanıtı aşağıda kaydedilmiştir; F6-C'ye geçilmeden DURULUR.

## Açık GATE envanteri

[Asıl teknik kapı kaydı](14_OPEN_VALIDATIONS.md) ve [kabul testleri](13_ACCEPTANCE_TESTS.md)
değişmez; bu faz aşağıdakileri kapatmaz:

| Kapı | Açık doğrulama |
|---|---|
| G-V01–04 | Voyar ücretli saat, account auth, unknown create ve custom upgrade |
| G-P01–02 | PayTR merchant currency ve gerçek callback/refund mutabakatı |
| G-C01 / G-R01 / G-D01 | WHM ACL/versiyon, ResellerClub hesap/API, DomainNameAPI OT&E |
| G-X01–02 | Ticari FX kaynağı ve resmî e-belge/mali müşavir kabulü |
| G-O01–04 | Hosting runtime, PITR/restore, gerçek WHMCS schema/hash, retention/PDF erişimi |
| G-T01 | Gerçek hizmet devri edge kabulü |
| SUP-010 / PROV-004 | Gerçek destek arşivi ve imported provider resource kabulü |
| DOMAIN-002 / mapping-import CRUD / PROV-005 | PARTIAL-MANUAL_REVIEW / DEFERRED / OPEN |
| F5-C dört istisna | Gerçek mailbox routing/sender auth; retention cutoff/scheduler; AV/IMAP/SMTP/genel MIME; mock transport kabulü gerçek müşteri teslimi sayılmaz |
| Support ileri kapsam | Auto-close, merge/split ve kurum geneli tarihsel SLA doğrulaması |
| F6-C / F6-D / F6-E | Gerçek mutabakat-güvenlik / operasyon hazırlığı / canlı cutover |

F5-C istisnalarının bağlayıcı kaydı [F5_C_DELIVERY_REPORT](F5_C_DELIVERY_REPORT.md)
başındadır. Bütün vendor/operasyon/production kapıları ayrıca açık kalır.

## Son kaynak kimliği ve CI kapanışı

| Kayıt | Exact değer |
|---|---|
| Ayrı F6-A kabul belge commit'i |`c70ecf84215d65a5d582f5251cfb6a7de7a76087`|
| F6-B son uygulama SHA |`eb3c2f64f8515dcf3ceff6ca7f41c3b9c702e576`|
| Uygulama tree |`7c69d2420591777ec09aee56d57d79cc46783fa1`|
| FULL ACCEPTANCE aday SHA |`eb3c2f64f8515dcf3ceff6ca7f41c3b9c702e576`|
| Son FAST run |[40505208818](https://github.com/Boydaksu/hostidercp/actions/runs/40505208818), SUCCESS|
| PHP8.3 job |[122193388678](https://github.com/Boydaksu/hostidercp/actions/runs/40505208818/job/122193388678),55 suite/663 test/0 hata|
| PHP8.4 job |[122193388489](https://github.com/Boydaksu/hostidercp/actions/runs/40505208818/job/122193388489),55 suite/663 test/0 hata|

[Makinece okunabilir CI kanıtı](evidence/f6-b/ci.json), kabul edilmiş FULL40475881946
job122093868403'teki621 PASS adını her yeni job'un uygulama test adlarıyla multiset
olarak karşılaştırır: her sürüm621/621 korunmuş, missing0; yeni42/42 PASS. Eski54
suite'in test sayıları da aynıdır; yeni suite55.'dir. F6-A31+8 bu621'e dahildir.
Scope job122193280924'te ayrı6 CI helper PASS. Her PHP job'unda PHPStan0,
lint414/0, pattern436/0, Composer locked audit temiz, dört native JS syntax kontrolü,
state3/3 ve browser lock audit temizdir. Bu yeni Chromium veya10/30/20 koşusu değildir.
Stable FAST result job122200474221 SUCCESS; FULL ACCEPTANCE ayrı ve çalıştırılmamıştır.

[Koruma manifesti](evidence/f6-b/preservation.json) eski404 kaynak/test dosyası için
Git blob eşliği,54 migration SHA-256 ve migration subtree kimliğini içerir.
[Yeni test indeksi](evidence/f6-b/test-index.json)42 F6-B testini aday kaynak
satırına ve talimattaki28 asgari senaryoya bağlar. Test/CI kaynakları bu uygulama
SHA'sında dondurulmuştur; bu kapanış ayrı docs-only commit olarak kaydedilir.
[Kaynak kanıtı](evidence/f6-b/source-proof.json) GitHub commit/tree eşliğini ve
ayrı altı-dosyalık F6-A kabul kaydını gösterir. Yeni FULL dispatch yoktur.
