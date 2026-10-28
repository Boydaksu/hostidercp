# F6-D-R2 — actual restored-target read-only recovery teslimi

28 Ekim2026. Development PARTIAL; independent acceptance PENDING; FAST BILLING engelinde; PHP8.3/8.4 NOT RUN.

## Exact source ve CI'nin açık sınırı

Application SHA: `f0db545b5b5fd0e569b07e33efb85e245be16b40`.
Application tree: `6482e0d546e9bcafbeeb7872052eda99a97d8d26`.
GitHub Git commit API tree'si yerel tree ile eşleştirildi. Kaynak dondurulduktan sonra
değişmedi. Old129 test dosyasının blob eşliği ve registry'de eski58 suite korunur.
Bu source koruma kanıtıdır; current SHA'da756 eski testin CI PASS kanıtı değildir.

[FAST40637188575](https://github.com/Boydaksu/hostidercp/actions/runs/40637188575),
[attempt1](https://github.com/Boydaksu/hostidercp/actions/runs/40637188575/attempts/1)
ve [attempt2](https://github.com/Boydaksu/hostidercp/actions/runs/40637188575/attempts/2):
ikisi de **FAILURE — JOB NOT STARTED, BILLING/SPENDING LIMIT**.
GitHub annotation: recent account payments have failed or spending limit needs increasing.
İlk scope job122646752817, ikinci scope job122647590476; PHP matrix her ikisinde
SKIPPED, ayrı8.3/8.4 job/test sonucu yoktur. Log blob'u job başlamadığından yoktur.
Kullanıcı billing engelini giderdiğini bildirip aynı SHA için bir tekrar açıkça
yetkilendirdi; o tek tekrar da aynı platform mesajıyla durdu. Üçüncü retry yapılmadı.
Başarısız attempt'ler hiçbir PASS toplamına dahil edilmedi.

**PHP8.3 NOT RUN; PHP8.4 NOT RUN.** Beklenen registry59 suite/780 test;
bu sayı runtime sonucu değildir. Current CI source guard/helper/PHPStan/lint/audit
job'ları da başlamadı. Yerel PHP8.4.24: yeni24/0, ilgili25 R1/0; PHPStan0,
lint452/0, pattern474/0, helper6/0, Composer locked audit advisory yok.
Npm audit/current Linux runtime CI doğrulaması yapılmış sayılmaz.
FULL/Chromium/10/30/20 yok. Bağımsız teknik kabul ve exact-source FAST kanıtı bekler.

[CI girişimleri](evidence/f6-d-r2/ci.json),
[ilk başarısız attempt](evidence/f6-d-r2/failed-first-attempt.json),
[source proof](evidence/f6-d-r2/source-proof.json),
[preservation](evidence/f6-d-r2/preservation.json),
[test adları](evidence/f6-d-r2/test-index.json),
[recovery authority matrix](evidence/f6-d-r2/recovery-authority-matrix.json),
[gerçek target report](evidence/f6-d-r2/example-readonly-recovery-report.json).
Rollback application commit yalnız `f0db545b5b5fd0e569b07e33efb85e245be16b40`.
Ayrı docs-only kapanış source/test/CI değiştirmez. Kullanıcının mevcut
docs/F6_B_B01_REVIEW.md düzenlemesi commit dışında korunur.
Main protected=false/rulesets=[] değişmedi; PR3 OPEN/DRAFT; bütün gerçek GATE'ler OPEN.
R2 PARTIAL/CI BLOCKED; independent acceptance PENDING; native runtime BLOCKED. DUR.

## R1 kabul kapanışı

Docs-only1bcad5f93f4d75b4af336180fe71b46d1a4c1374 yalnız README/F6_PLAN/F6_D_PLAN/
F6_D_DELIVERY_REPORT/F6_D_R1_PLAN/F6_D_R1_DELIVERY_REPORT değiştirir. Accepted app
3773351c01164dc3e58a7c4b869ef980a5b7cf79, tree80beecd299c60ed669e4d0c52c76751ee020b692,
evidence2a3b4d1d8ffd9a06cbabe66d10de694645971198 korunur. Kabul LIMITED SYNTHETIC COLD
VERIFICATION ACCEPTED WITH EXPLICIT EXCEPTIONS; genel F6-D PARTIAL, native runtime
BLOCKED. Kabul için eski756 test tekrar çalıştırılmadı; source/test/CI/migration aynı.

## Authority ve actual target doğrulaması

[Plan](F6_D_R2_PLAN.md), [ADR-029](adr/ADR-029-readonly-restored-target-authority.md).
Yeni RecoveryContext, RecoveryTargetPolicy ve RecoveryInspector; accepted source
dosyaları değiştirilmedi. Original Sandbox/Loader/Inspector ile Core/Identity/Finance/
Audit/Queue tekrar yazılmadı veya gevşetilmedi. Registry/FULL listesine yalnız yeni suite.

R1 ColdRecovery ve RecoveryAuthorization signature/pinned-key/cipher/source/issuer
doğrulamasını yapar. Private RecoveryContext immutable reference tutar; kaynak DB,
Vault capability veya original signing key istemez. Salt array comparison yalnız
REFERENCE_COMPARISON_ONLY_NO_DB_AUTHORITY sonucu üretir, DB verification raporu değildir.

Ayrı trusted target binding contract hostider.recovery-target.v1: loopback port,
owned f6d_ schema, random r2_ reader, original backup ID, marker row+physical DDL pin,
policy epoch/purpose/expiry. Pin untrusted capsule'dan alınmaz; owned setup/operator
config'idir. Current epoch mismatch, revoked policy veya runtime purpose ret. Policy
en fazla300sn; R1 yetkisi en fazla900sn. Snapshot config kalıcı anti-rollback/anlık
revocation sistemi değildir. Operator root/config ayrı güven sınırıdır.

Reader yalnız exact schema SELECT+global USAGE; role/grant option/extra schema,
TRIGGER/EXECUTE/DML/DDL hakları ret. Writer/admin credential ve source signing secret
fresh child'a verilmez. Metadata table types/InnoDB, tüm dokuz base-table definition
ve fiziksel PK/unique index eşliği, marker definition/row/lease ve native Migrator
readiness veri SELECT'lerinden önce denetlenir. Views reddedilir. Sabit kolon/SQL
primitives; READ ONLY REPEATABLE READ snapshot,512 rows/table/64KiB row/2MiB total.
Known restore advisory lock kullanılır; pre/post authorization/identity/schema fence.

Actual DB satırları canonical logical root üzerinden trusted backup reference ile
karşılaştırılır. Mapping/provenance/checkpoint/history digest, row counts/matched/
unmatched/extra, customer/attachment owner ve privacy farkları raporlanır. Finance
mevcut Money/Finance totals/differences ile aynı currency içinde hesaplanır. Rapor
actual evidence root'u kapsar: aynı aggregate counts ve farklı bozuk içerik farklı
fingerprint üretir. Customer IDs/body/author/private path/MAC/key/ciphertext döndürmez.
Report reviewer/time attestation mevcut Report sözleşmesiyle content hash'inden ayrıdır.

## Gerçek akış ve kabul sınırı

Source→F6-B/F6-C PASS→backup→R1 enrollment→F6-D escrow restore→source owned schema DROP
ve Vault expiry→fresh PHP process→SELECT-only target queries→PASS actual row equality
gerçekte yürütüldü. Original encrypted artifact, MAC, source roots, HMAC/history,
mapping/provenance/ownership/facts aynen korunur. HMAC cold aşamada tekrar hesaplanmaz;
trusted enrollment evidence ile immutable byte/logical equality ayrı raporlanır.

BLOCKED_IDENTITY_BOUND PARTIAL: yalnız read-only recovery verification alt kapsamı
karşılandı. Original Sandbox native restore target'ı hâlâ reddeder; runtime activation,
native write/dispatch ve production restore BLOCKED/NOT VERIFIED. Report native restore
API'ye verildiğinde type rejection. Recovery verification tekrar edilebilir;1/4/8 gerçek
PHP process aynı deterministik fingerprint üretir, native write/effect yoktur.

SELECT-only reader trigger/routine inventory'sini bütünüyle göremez. Bu yetenek
NOT_VERIFIED_SELECT_ONLY olarak açık kalır; DML/CALL yok, EXECUTE/TRIGGER privilege yok,
views ve farklı base definitions ret. Routine/trigger bulunduğunda bile verifier
bunları çağırmaz. PASS yalnız base-table data reconciliation'dır; tüm executable-object
audit veya runtime safety değildir. Privileged host/setup DDL güvenilir bakım
sınırıdır; server-root compromise/cold production activation kabulü yoktur.

BLOCKED_ISSUER_RECOVERY PARTIAL (R1 pre-enrolled doğrulama); ORIGINAL_ARCHIVE_BYTES OPEN.
Real offsite/key custody/PITR/RPO/RTO NOT VERIFIED. Durable one-time activation veya
kalıcı revocation ayrı contract/owner approval ister. Bu görev production migration
gerektirmedi; gelecekte gerekiyorsa055+ PROPOSED OWNER APPROVAL REQUIRED, uygulanmadı.

## Negatif test ve gelişim kanıtı

Yeni24 R2 ve ilgili25 R1 hedefli test PASS. İlk22/1 development turu FK'ye bağlı parent
entity deletion fixture hatası verdi; missing-row case leaf history üzerinden düzeltildi,
DB constraints/assertion gevşetilmedi. Sanitized development-failed.txt kabul kanıtı
değildir. Sonraki23/0 intermediate ve final24/0 birbirine eklenerek sayılmaz.

İki gerçek karşı-örnek önce1 FAIL sonra final suite'te PASS:
1. Saf caller array karşılaştırması DB/schema/authorization PASS raporu üretebiliyordu;
   Context yalnız scope-labelled reference array verir, actual DB claim sadece Inspector'da.
2. Marker VIEW replacement ret edilmeden önce SELECT definer GET_LOCK yan etkisi
   çalışabiliyordu. Zararsız owned advisory-lock testi bunu gösterdi; table-type ve
   physical schema pin doğrulaması data okumalarından önceye alındı. Sonuç BLOCKED
   olur ve probe lock hiç alınmaz. Original accepted modüller etkilenmedi.

Matrix: wrong target/account/source/backup/key/signer/purpose/expiry/epoch, stale marker,
row/mapping/provenance/HMAC/checkpoint/owner/privacy/finance/currency/schema/index/
migration/extra/missing/partial/bound, cross-schema and DML/DDL/CALL denial, rotated-key
R1 regressions,1/4/8 concurrency, real exit73 before/capture/report, duplicate read-only,
privacy/type/native rejection. Exception mock gerçek process crash diye sunulmaz.
Only JSON reference hash actual target proof diye sunulmaz.

## Migration / rollback / gerçek kapılar

Production001–054 subtreec0729c92dd143c017fdec32421bf67fe6b51e05b değişmez; accepted
fixture DDL değişmez. Yalnız existing owned setup yeni SELECT hesabı oluşturur.
Rollback yalnız R2 app commit revert ve exact owned fixture cleanup; original artifact
ve R1 certificate korunur. Production restore/DROP/DELETE yok. Forward-fix yeni target
contract version ister, gerçek cutover delta/reconciliation ayrı owner kararı gerektirir.

G-O01/02/03/04, vendor G-V01–04/G-P01–02/G-C01/G-R01/G-D01/G-X01–02 ve G-T01 OPEN;
real SUP-010/PROV-004 NOT PASSED, PROV-005 OPEN, DOMAIN-002 PARTIAL/MANUAL_REVIEW,
production mapping/import CRUD DEFERRED, F5-C dört canlı istisna ve ileri support/
tarihsel SLA OPEN. Main protected=false/rulesets=[] gözlemi raporlanır, değiştirilmez.
PR3 OPEN/DRAFT; FULL NOT DISPATCHED; F6-E NOT AUTHORIZED; production UNCHANGED.
R2 bağımsız kabul PENDING. Exact kaynak/CI kanıt kapanışı sonrası DUR.
