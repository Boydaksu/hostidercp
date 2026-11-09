# F6-D-R2 — izole read-only recovery authority

28 Ekim2026. R1 kabul docs1bcad5f93f4d75b4af336180fe71b46d1a4c1374.
OPS-002/003/004, SEC-004 ve D6 yalnız sentetik verification kapsamı.

## Kapsam / authority / tehdit modeli

1. Original Sandbox schema/account/marker/secret ve loader grants ile bound'dur;
   yeni f6d_ target'ta geçersiz olması doğrudur. Sandbox/Loader/Inspector değiştirilmez.
2. Orijinal checkpoint/history HMAC, kaynak/mapping/provenance/ownership/facts ve
   ciphertext aynen korunur. Cold aşamada original HMAC yeniden hesaplanmaz.
3. R1 ColdRecovery/RecoveryAuthorization gerçek Ed25519/ciphertext/source doğrulamasını
   yapar. Yeni RecoveryContext private reference dataset tutar; yalnız read-only
   comparison/report sağlar, AuthenticatedBackup veya Sandbox üretmez.
4. Ayrı trusted versioned target policy, exact loopback port/f6d_ schema/r2_ reader,
   f6d_restore_marker row ve physical-definition fingerprint, backup ID, purpose, current policy epoch ve kısa
   expiry ile target'ı bağlar. Marker pin'i untrusted capsule'dan değil owned target
   setup/operator config'inden gelir. Writer credential/secret verifier'a verilmez.
5. RecoveryInspector strict SELECT-only reader ile actual base tables'ı READ ONLY
   REPEATABLE READ transaction altında okur; max512 rows/table,64KiB/row,2MiB total.
   SHOW CREATE, fixed columns/PK/unique, InnoDB/base-table ve Migrator readiness
   kontrol edilir. Accepted Inspector kopyalanıp bypass edilmez; ayrı dar row-equality
   verifier ve mevcut Report/Finance kullanılır. Aynı özetli farklı satırlar actual
   evidence root/fingerprint'i değiştirir; incomplete/limit asla PASS değildir.
6. R1 key trust/revocation/expiry ve target policy başlangıçta/rapor öncesinde fence.
   Salt-okunur tekrar/concurrency izinli; one-time activation yok. Config snapshot
   max300sn, R1 authority max900sn; durable anti-rollback/instant revocation iddiası
   yoktur. Eski epoch current trusted config'e karşı reddedilir; trusted config'in
   kendisinin rollback'i ayrı GATE'tir.
7. Original signing key yokken trusted enrollment kanıtı ve exact target byte/logical
   equality doğrulanır. Original HMAC yeniden hesaplandı denmez; R1 verification ile
   actual target reconciliation ayrı raporlanır.
8. Yalnız owned ephemeral test target/source; kaynak kaybı yalnız exact fixture DROP.
9. Production migration veya existing fixture DDL değişmez. Runtime activation/durable
   replay için gelecekte contract/055+ gerekirse PROPOSED OWNER APPROVAL REQUIRED.
10. Read-only PASS runtime activation değildir. Native Identity/Finance/Queue/Audit
    ikinci motor yoktur; HTTP/provider/payment/mail/files endpoint yoktur.

## Metadata/definer sınırı

Reader TRIGGER/EXECUTE/DDL yetkisi almaz. Views/non-InnoDB ve değişen base-table
tanımları reddedilir; query projections yalnız sabit kolon ve native SQL primitives.
INSERT/UPDATE/DELETE/CALL yoktur: triggers ve routines çalıştırılamaz. SELECT-only
hesabın göremeyebileceği trigger/routine inventory'si doğrulandı denmez;
NOT_VERIFIED_SELECT_ONLY olarak raporlanır. R2 PASS yalnız actual base-table data
eşliğidir; tüm schema executable objects veya runtime safety kabulü değildir.
Gerçek metadata visibility/runtime activation ayrıca GATE'tir. Testlerde view ret,
TRIGGER/EXECUTE grant ret, trigger/procedure mevcutken DML/CALL denial ve sıfır
verifier write gösterilir. Bu kapsam sınırı açıkça teslim edilir.
Marker dahil tüm base-table/schema tanımları herhangi bir marker/migration/data
SELECT'inden önce metadata üzerinden denetlenir. Source-host/setup privileged DDL
yetkisi güvenilir bakım sınırıdır; server root compromise/runtime activation kabulü yoktur.
Context'in saf array comparison sonucu DB authority raporu değildir; yalnız gerçek
Inspector sorgu/fence yolu ACTUAL_RESTORED_DB_READ_ONLY raporu üretebilir.

## Dosyalar / migration / test / rollback

Yeni RecoveryContext, RecoveryTargetPolicy, RecoveryInspector; yeni test fixture,
child, suite ve CLI demo. Mevcut accepted source dosyaları değişmez; registry/FULL
suite listesine yalnız yeni suite. Production001–054 ve tüm eski756 test body/blob
korunur. Plan/ADR-029 source öncesi yazılır.

Actual source→backup→enrollment→restore→source DROP/Vault expiry→fresh child SELECT
target zinciri. Negative matrix: schema/account/source/backup/key/signature/purpose/
expiry/epoch/marker, row/mapping/provenance/HMAC/checkpoint/ownership/finance/currency,
index/migration/extra/missing/partial/bound, privileges/cross-schema/view, privacy,
same-summary fingerprint,1/4/8 concurrent child, real crash, native type rejection.
Yeni capability eski kaynakta yoksa bu eksiklik açık baseline BLOCKED olarak kaydedilir;
gerçek güvenlik karşı-örneği bulunursa önce FAIL/sonra PASS yapılır, uydurulmaz.

Önce yeni hedefli suite ve ilgili25 R1 regresyonu, PHPStan/lint/pattern/locked audit;
son exact application'da tek geniş FAST8.3/8.4. FULL/Chromium/10/30/20 yok.

### 28 Ekim 2026 — exact-SHA owner yöntem istisnası

Yukarıdaki exact-source FAST gerekliliği tarihsel ve genel kural olarak korunur.
Proje sahibinin açık onayıyla [ADR-030](adr/ADR-030-f6-d-r2-local-docker-ci-method-exception.md)
yalnız application `53162495902b3b168c2fb8554594e99c23a34d62`,
tree `e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47` ve immutable evidence
`cc94f662454cb3f030addd2db0785a09900c0ed2` için parçalı LOCAL DOCKER CI
kanıtlarının exact-source FAST yerine sınırlı bağımsız değerlendirmede kullanımını kaydeder.
Her PHP 8.3.36 / 8.4.27 için 50/398 + 9/227 + ayrı support 157 = 60 paket/782 test;
tek uninterrupted broad PASS yoktur. FAST NOT_RUN_EXTERNAL_BILLING kalır.
Önceki exact-SHA kalite kayıtları kullanılır; son support quality_exit:0 placeholder'dır.
MariaDB metadata/runtime ve tarihsel ENOSPC/kapasite sınırlamaları ADR-030'da açık kalır.
Genel FAST/FULL kuralları, diğer fazlar ve yeni application commit'leri için muafiyet yoktur.
LOCAL DOCKER CI METHOD EXCEPTION: OWNER APPROVED — EXACT SHA LIMITED.
F6-D-R2 INDEPENDENT ACCEPTANCE: PENDING; native runtime recovery BLOCKED; genel F6-D PARTIAL.
FULL / F6-E / MERGE / STAGING / PRODUCTION: NOT AUTHORIZED.

Rollback yalnız R2 app commit revert + exact owned fixture teardown. Original artifact
ve R1 certificate değişmez; forward-fix yeni contract version ister. Genel F6-D PARTIAL;
R2 bağımsız kabul PENDING, native production restore NOT VERIFIED, gerçek GATE'ler OPEN.

## 28 Ekim 2026 — sonraki sınırlı teknik kapanış ve P0 geliştirme yetkisi

[R2 bağımsız kabul kaydı](F6_D_R2_ACCEPTANCE_RECORD.md) proje sahibinin ilettiği ayrı
incelemeci kararını exact application53162495902b3b168c2fb8554594e99c23a34d62 / evidence
cc94f662454cb3f030addd2db0785a09900c0ed2 için ACCEPTED WITH EXPLICIT EXCEPTIONS kaydeder.
Önceki PENDING/FAST kayıtları tarihçedir; FAST hâlâ NOT_RUN_EXTERNAL_BILLING.
Native runtime recovery BLOCKED; genel F6-D PARTIAL; FULL/canlı cutover/merge/staging/production yetkisiz.
Yeni [F6-E-P0](F6_E_P0_PLAN.md) yalnız offline/sentetik kod ve test geliştirmesidir;
R2 yöntem istisnası/kabulü yeni adayın doğrulaması yerine kullanılamaz.
