# F6-B — sentetik izole import / dry-run teknik teslimi

Durum: hedefli geliştirme tamamlandı; gerçek FAST ve bağımsız teknik inceleme bekler.
F6-B ACCEPTED, FULL DONE veya PRODUCTION READY değildir.
Yetki/kapsam: [F6_B_PLAN](F6_B_PLAN.md), [ADR-025](adr/ADR-025-synthetic-isolated-import.md).
F6-A sınırlı kabulü ayrı c70ecf84215d65a5d582f5251cfb6a7de7a76087 belge commit'indedir.

## Çalışan kapsam ve kaynak sınırı

`src/Migration/Synthetic/` yalnız controlled Fixture→F6-A validation→Plan→Sandbox→
Loader akışıdır. Native Database/transaction/Migrator ve F6-A Money/Currency,
SourceIdentity/MappingEvidence tekrar kullanılır. DRY_RUN salt öneridir, imported_records0;
SYNTHETIC_LOAD yalnız ephemeral teknik facts yazar. Cumulative imported_records,
committed cursor'dur; replay=true yeni26 kayıt oluşturduğu anlamına gelmez.
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

[Yerel40 test](evidence/f6-b/local-targeted.txt): PHP8.4.24 + gerçek MariaDB10.11,
40/40 PASS. F6-A31+8 ve ayrı6 CI helper de PASS. PHPStan0 hata; son lint/pattern
ve gerçek FAST sonuçları kaynak kimliğiyle kapanış kaydına eklenecek.
[Gerçek demo manifest'i](evidence/f6-b/example-manifest.json) DRY_RUN, ilk load ve
replay'i ayrı gösterir; gerçek dosya/native entity yazısı yoktur. Tekrar demo:
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

## Migration / rollback / F6-C bağımlılığı

001–054 production migration byte/hash korunur. Yeni production migration yoktur.
Yalnız `tests/fixtures/f6b-schema/001_synthetic_import.php` ayrı schema'ya native
Migrator ile uygulanır; runtime DDL yetkisi yok. Fixture teardown exact rastgele
schema ve iki geçici hesabı kaldırır; native DB restore/down yapılmaz. Rollback
F6-B uygulama commit revert ve owned test fixture teardown; forward-fix immutable
facts/provenance'i overwrite etmez. Gerekli sözleşme değişikliği versioned olmalıdır.

F6-C: gerçek schema discovery/adapter, native application seam uygunluğu, para ve
ownership/file mutabakatı, gerçek resource account eşliği ve current ACL tekrar
kontrolü ayrıca gerekir. G-O03, gerçek SUP-010/PROV-004, DOMAIN-002 PARTIAL,
production mapping/import CRUD DEFERRED, PROV-005, F5-C dört canlı istisna,
auto-close/merge/split/tarihsel SLA ve tüm vendor/operasyon/live GATE'ler açık kalır.
F6-D/F6-E, staging/merge/deploy/cutover yetkisi yoktur. PR3 OPEN/DRAFT;
main protection değişmez. FULL bu adayda henüz çalıştırılmadı; dispatch yapılmayacak.
Son SHA/tree ve gerçek FAST kanıtı tamamlandıktan sonra DURULUR.
