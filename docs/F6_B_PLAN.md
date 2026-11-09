# F6-B — sentetik izole import / dry-run planı

27 Ekim 2026. F6-A kabul kaydı c70ecf8; kabul edilen uygulama063e846,
başlangıçf45e85e. Yetki yalnız F6-B SYNTHETIC ISOLATED IMPORT & DRY-RUN GO.
FR-OPS/R1-08/D6, SEC-004/OPS-001/005, FIN-003/006/007/008,
SUP-003/004/010 ve PROV-004 sentetik alt kapsamı izlenir; gerçek kabul değildir.

## Modül ve dosya kapsamı

Yeni `src/Migration/Synthetic/` saf plan, kontrollü fixture kataloğu, bağlantı
izolasyonu ve bounded loader'ı içerir. F6-A39 test ve kaynakları değişmez.
Core Database/transaction/Migrator/CanonicalJson ve Finance Money/Currency
F6-A validator üzerinden tekrar kullanılır. İkinci Identity/Finance/Queue/Outbox/
Audit motoru ve native tablo adapter'ı yoktur. Yeni HTTP/cron/native queue handler yoktur.
Testler `tests/migration-import.php`, child/harness ve ayrı test DDL dizinindedir;
suite envanteri ile FULL listesi birlikte genişler. ADR-025 ve teslim/kanıt raporu eklenir.

## İki mod ve kontrollü input

DRY_RUN aynı F6-A SnapshotValidator ile tam snapshot'ı doğrular, deterministik
bağımlılık sırası ve MappingEvidence önerisi üretir. Sandbox verilirse mevcut
mapping/target/external claim'ler salt okunur incelenir; run/checkpoint/target dahil
hiçbir tablo yazılmaz. Plan veya review, gerçek import sayılmaz.

SYNTHETIC_LOAD yalnız geçici sandbox'ta aynı fixture'ı tekrar doğrular. Immutable
plan fingerprint server tarafından türetilir; caller idempotency key veya target ID
almaz. Her batch güncel mapping/target/external claim ve checkpoint'i yeniden denetler.
Plan PASS, load garantisi değildir. Belirsiz/invalid fixture REVIEW_REQUIRED olur;
İlk batch öncesinde instance+snapshot kimliği, bütün üyelerin sıralı envelope
fingerprint köküne immutable olarak bağlanır. Henüz aktarılmamış bir satır değişse
bile aynı snapshot kimliği farklı kökle devam edemez (SOURCE_SNAPSHOT_CONFLICT).
UNKNOWN/FX/refund için F6-A'nın review kararları gevşetilmez. Refund contract/limit
senaryoları review-only kalır; native ledger veya yeni finans kararı oluşturulmaz.

Input yalnız kabul edilmiş sabit fixture JSON'unun byte SHA-256 doğrulaması ve
kod içindeki allowlisted sentetik varyantlardan gelir. Caller array/dump/URL/path/
source DB/credential kabul eden extractor yoktur. SYNTHETIC etiketi tek başına güven değildir.

## İzole DB ve yetki sınırı

APP_ENV=test VE SYNTHETIC_IMPORT_ALLOWED=1 zorunlu. Bağlantı API'si serbest DSN/host
almaz; yalnız127.0.0.1, açık test portu ve rastgele ephemeral schema/user/secret
biçimi kabul eder. Gerçek DATABASE()/CURRENT_USER(), aktif role olmaması ve SHOW
GRANTS denetlenir: global yalnız USAGE; SELECT yalnız exact schema; INSERT yalnız
allowlisted teknik tablolar; UPDATE yalnız run checkpoint tablosu. DDL/DELETE,
diğer schema/table/global hakları ve grant option ret edilir. Grant çıktısı loglanmaz.

Fixture harness ayrı schema ve rastgele hesap/secret üretir. Süreli marker secret
hash'i schema/account/target snapshot'a bağlar; grant ve marker bağımsız kontrollerdir.
Native tablolar bu schema'da bulunamaz; sadece beklenen teknik tablo seti kabul edilir.
Her batch öncesi/commit öncesi fence ve native migration readiness doğrulanır.
Runtime kullanıcı DDL veya history/provenance/target UPDATE/DELETE yapamaz.

## Transaction, mapping ve recovery

Teknik tablolar: environment marker, runs/checkpoints, ayrı typed entity facts,
source↔target mappings, unique external claims, immutable provenance ve run history.
Customer/user/membership/order/payment/service/resource/wallet/document/ticket/message/
attachment ayrı entity türleridir; hedef source referansları ve F6-A evidence korunur.
Source identity PK ve target identity UNIQUE ayrı kısıtlardır. External resource,
payment reference, email ve wallet-owner claims sessiz birleşmeyi engeller.

Schema-local bounded DB advisory mutex batch'leri seri yapar; genel worker engine
değildir. Native Database::transaction içinde claim+entity+mapping+provenance+
checkpoint/history atomiktir. Bağımlılıklar stable topological sıra ile yüklenir.
Run state: PLANNED→VALIDATED→APPLYING→APPLIED; review/failed açık sonuçlardır.
Checkpoint run/plan/target snapshot/cursor/version ve secret HMAC'a bağlıdır.
İlerleme sadece commit ile görünür; crash/connection loss sonrası durable cursor
tekrar okunur. Stale veya başka sandbox checkpoint'i ret; aynı input replay no-op.
Committed prefix yeniden yazılmaz; rollback yalnız başarısız batch'i geri alır.

## Ayrımlar ve dış etki yasağı

F6-A Money/Currency kuralları korunur; original payment/allocation/refund/wallet
ve proforma/official document ayrı facts'tir. Yeni invoice no, FX veya paid transition
üretilmez. Dört sağlayıcı için imported CREATE her replay/worker/queue simülasyonunda0.
Kapalı effect boundary provider/payment/billing/mail/notification taleplerini reddeder;
loader bu native motorlara bağlanamaz. Private source payload yalnız sentetik arşivdir;
internal note ve private attachment public read model'e çıkmaz; author/path redakte edilir.
Gerçek attachment okuma/kopyalama, MIME/AV/retention doğrulaması yoktur.

HTTP yok: yeni CSRF/session revoke/native MFA/IDOR endpoint senaryosu NOT APPLICABLE.
Bu bir auth bypass değildir; native API yüzeyi açılmaz ve mevcut621 regresyon korunur.
Test-only customer-scoped public arşiv projection ayrıca cross-owner ret ile doğrulanır.

## Migration / test / geri dönüş

001–054 byte/hash korunur; production migration eklenmez. Ayrı
`tests/fixtures/f6b-schema/001_synthetic_import.php` native Migrator'la yalnız
ephemeral schema'ya additive uygulanır. Runtime hesap DML ile sınırlıdır;
setup/migration hesabı ayrıdır. Failed DDL APPLYING/readiness ret ve replay test edilir.
Test bitince yalnız exact rastgele schema ve hesaplar fixture harness tarafından kaldırılır.

Gerçek MariaDB'de deterministik dry-run/zero-write, ilk load/replay,8 provider-zero
yolları, conflict/ownership/credentials/private note, finans limitleri, yarım batch,
stale/wrong snapshot/checkpoint, exception/rollback, process exit ve own-connection
loss;1/4/8 bağımsız PHP worker yarışı sınanır. Yerel hedefli suite/lint/PHPStan/audit,
son adayda gerçek FAST PHP8.3/8.4 eski621+yeni test ad eşliği. Chromium ve10/30/20
tekrarları yalnız ayrıca yetkilendirilecek FULL'e bırakılır. FULL dispatch yoktur.

Rollback F6-B code revert ve exact test schema/account teardown; production DB down
yoktur. İleri düzeltme immutable evidence'ı overwrite etmez; gerekirse açık sürüm artırır.

## F6-C ve açık kapılar

F6-C gerçek mutabakat/güvenlik, G-O03 real schema/hash, SUP-010 gerçek arşiv,
PROV-004 gerçek resource, DOMAIN-002 PARTIAL/MANUAL_REVIEW, production mapping/import
CRUD DEFERRED, PROV-005, F5-C dört istisna, auto-close/merge/split/tarihsel SLA ve
tüm vendor/operasyon/live GATE'ler açıktır. F6-D operasyon ve F6-E cutover yoktur.
PR3 OPEN/DRAFT, main protection değişmez. F6-B ACCEPTED/FULL DONE ilan edilmeden,
exact SHA/tree ve gerçek FAST raporu teslim edilir ve DURULUR.
