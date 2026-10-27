# F6-C — sentetik mutabakat ve güvenlik planı

27 Ekim 2026. F6-B kabul commit'i541cf9c; kabul edilen uygulama d4ead54,
kanıt dadef0b. F6-C yalnız SYNTHETIC DEVELOPMENT GO; bağımsız kabul bekler.
Gereksinimler: FR-OPS/R1-08/D6, SEC-004, FIN-003/006/007/008,
SUP-003/004/010, PROV-004 sentetik alt kapsamı. Native API kabulü genişlemez.

## Gereksinim sınıfları

| Sınıf | Kapsam |
|---|---|
| IMPLEMENTABLE_SYNTHETIC | F6-B facts/source-target count/hash/identity/dependency/owner, mapping/provenance/claims, snapshot/run/history/checkpoint, currency-separated totals, provider metadata, support metadata/privacy, bounded read-only DB, parallel deterministic reports |
| REVIEW_REQUIRED | UNKNOWN payment/FX/timezone/provider, refund reconciliation, partial/FAILED run, source/target differences, missing evidence, invalid metadata, stale checkpoint |
| EXTERNAL_GATE_BLOCKED | G-O03 gerçek schema/hash/extract, gerçek ledger/payment/FX/provider/resource, attachment/PDF byte/MIME/AV/retention/mailbox, production CRUD, F6-D/E |

## Modül ve izolasyon

Yeni src/Migration/Reconciliation Inspector, Finance summary ve immutable Report.
Kaynak yalnız F6-B Fixture özel constructor/byte hash/kod allowlist'inden gelir;
F6-A SourceEnvelope/SnapshotValidator, F6-B Plan/MappingEvidence yeniden kullanılır.
Keyfî source array/URL/path/DSN veya gerçek credential kabul eden extractor yoktur.
Native Identity/Finance/Services/Domains/Cloud/Support tablolarına adapter/yazı yoktur.

Kabul edilmiş Sandbox değiştirilmez: authority olarak assertReady/assertBound ve
sign kullanılır. Inspector ayrı f6c_<random> test hesabı, sabit127.0.0.1 ve authority'nin
exact schema/snapshot'ıyla açılır. Runtime yalnız literal schema SELECT + global
USAGE; DML/DDL/role/wildcard/grant option veya başka schema hakları reddedilir.
Geçici hesabı yalnız fixture setup oluşturur. Secret/hesap/grant ham loglanmaz.
Native Database transaction içinde READ ONLY consistent snapshot; aynı F6-B schema
mutex'i bounded acquire ile loader değişikliklerinden ayrılır. Başta ve sonda
authority readiness ve reader grant/identity/marker fence tekrar denetlenir.

## Karşılaştırma ve manifest

APPLIED bayrağı tek başına yeterli değildir. Her entity source-key/target-id/type,
content hash/payload, customer owner, parent/dependency; mapping önerisinin exact
evidence'i, provenance, source root ve fiziksel claim bağı bağımsız karşılaştırılır.
HMAC checkpoint ve bütün bounded history zinciri doğrulanır. Beklenmeyen target,
mapping/claim/provenance veya bozuk içerik review; otomatik repair/rebind yoktur.
Tamamlanmamış run INCONCLUSIVE, doğrulanmış fark REVIEW_REQUIRED, ortam/grant/lease
reddi BLOCKED, yalnız eksiksiz sentetik sıfır fark PASS. Bilinmeyen alan null veya
NOT_AVAILABLE'dır; gerçek WHMCS her durumda NOT_VERIFIED.

Manifest aggregate counts/reason codes ve opaque evidence kökleri taşır; message
body, author/customer IDs, e-posta, private path, mapping detail veya credential
döndürmez. Yeni HTTP/cron/client/export/search/notification endpoint yoktur.
Native session/revoke/department/impersonation authorization testi bu yeni yüzeyde
NOT APPLICABLE; report native client API yerine geçmez. F6-B scoped projection'ın
cross-owner reddi ve report redaksiyonu sentetik güvenlik testlerinde korunur.
Reviewer hash'i ve zaman ayrı attestation alanıdır; deterministik hash'e dahil değil.

## Finans / provider / support

Money/Currency ile her currency ayrı order/payment/confirmed/allocation/refund/
wallet/remaining/proforma/official toplamı. Allocation/payment/order limitleri ve
F6-A REFUND_RECONCILIATION_REQUIRED korunur. Ledger debit/credit NOT_AVAILABLE;
FX tahmini, yeni paid transition, numara, PDF veya finans hareketi yoktur.
Provider/account/service/external claim/customer metadata farkları review; CREATE,
RENEW/TERMINATE/TRANSFER/UPGRADE/DOWNGRADE yolu yoktur. Gerçek yönetim NOT_VERIFIED.
Support message sequence/time/timezone/kind/visibility/author ilişkileri accepted
validator ile doğrulanır. Attachment owner/ticket/message/path/MIME/size/checksum
yalnız metadata eşliğidir; byte/AV/MIME/retention kabulü değildir.

## Bound / dosya / migration / test / rollback

Source en çok256, her tablo512 satır, toplam2MiB ve satır64KiB sınırı. LIMIT513
probe ile taşma truncation yerine INCONCLUSIVE olur. Dokuz tablo bounded toplu
okunur; entity başına SQL yoktur, PK indeksleri kullanılır. Yeni production/test
DDL veya migration gerekmez;001–054 immutable c0729c92dd143c017fdec32421bf67fe6b51e05b.

Yeni tests/migration-reconciliation.php, ayrı reader fixture/child; suite hem
tools/ci/suites.json hem FULL listesine eklenir. Eski664 test gövdeleri değişmez.
Test matrisi talimattaki22 senaryoyu kapsar: full/repeat/reverse/partial, missing/
extra/integrity/checkpoint/owner/provider/finance/wallet/FX/docs/support/privacy,
DB readiness/permission/lease/schema, zero mutation, independent processes ve
real NOT_VERIFIED. Gerekli ek sınırlar boundedness ve immutable attestation'dır.
Yerel yalnız yeni hedefli suite/lint/PHPStan; son adayda tek geniş FAST8.3/8.4.
FULL/Chromium/10-30-20 dispatch yoktur. Kanıt ayrı docs-only kapanışta kaydedilir.
Rollback yalnız F6-C code revert ve owned fixture teardown. Forward-fix eski
evidence'ı overwrite etmez. Yeni055+ ihtiyacı olursa ayrı tasarım/onaya bırakılır.

## Açık kapılar

G-V01–04, G-P01–02, G-C01, G-R01, G-D01, G-X01–02, G-O01–04, G-T01;
gerçek SUP-010/PROV-004 NOT PASSED, PROV-005 OPEN, DOMAIN-002 PARTIAL/MANUAL_REVIEW,
production mapping/import CRUD DEFERRED; F5-C dört istisna, auto-close/merge/split/
tarihsel SLA, F6-D/E açık/yetkisiz. Main protected=false/rulesets=[] gözlendi;
ayar değişikliği yoktur. PR3 OPEN/DRAFT. Exact SHA/tree ve FAST ile teslim ve DUR.

## Kanıt bağlama düzeltmesi

Aggregate rapor özeti aynı olsa da farklı target içeriği farklı fingerprint üretmelidir.
Bu nedenle bounded, sıralanmış actual target tablolarının CanonicalJson kökü ayrıca
observed_target_evidence_sha256 alanına bağlanır; private içerik yalnız hash'e girer,
ham içerik rapora çıkmaz. Yanlış entity türü gerçek türünde extra olarak sayılır.
Ek tek regresyon hash bağını kanıtlar; yeni test toplamı35'tir.
