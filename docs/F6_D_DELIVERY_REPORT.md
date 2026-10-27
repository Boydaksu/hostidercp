# F6-D — sentetik operasyon ve encrypted recovery escrow teslimi

## 27 Ekim 2026 — F6-D-R1 sentetik recovery hardening teslimi

F6-D **LIMITED SYNTHETIC ENCRYPTED BACKUP & RECOVERY ESCROW — ACCEPTED WITH EXPLICIT
EXCEPTIONS**, kabul docs `113334dd2c37556ed20a83b6e84eeeab3dcfc42b`.
F6-D genel **PARTIAL**. R1 geliştirme **PARTIAL; INDEPENDENT ACCEPTANCE PENDING**.
Application `3773351c01164dc3e58a7c4b869ef980a5b7cf79`, tree
`80beecd299c60ed669e4d0c52c76751ee020b692`.
[FAST40566808738](https://github.com/Boydaksu/hostidercp/actions/runs/40566808738): PHP8.3/8.4 her sürüm58 suite/756 test/0 hata; eski731 ad ve57 suite sayımı korunmuş, yeni25 PASS.
Yeni25 hedefli R1 ve32 ilgili F6-D test PASS; eski731 testin125 dosyası blob-equal.
Bağımsız pinned Ed25519 attestation, exact cipher/issuer/source binding, fresh operator
policy ve gerçek source-schema-loss/expired-Vault/fresh-process cold verification
uygulandı. Orijinal artifacts/MAC/history/provenance/backup ID değişmez.
**BLOCKED_ISSUER_RECOVERY PARTIAL**: yalnız önceden enrolled paketin read-only
verification'ı; gerçek custody/offsite/key-policy operasyonu NOT VERIFIED.
**BLOCKED_IDENTITY_BOUND OPEN; native recovery BLOCKED**; Sandbox/Inspector/marker
koşulları gevşetilmedi. **BLOCKED_ORIGINAL_ARCHIVE_BYTES OPEN**.
Migration001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmez.
G-O01–04 ve bütün gerçek vendor/finance/support/cutover kapıları açık;
PR3 OPEN/DRAFT, main protected=false/rulesets=[] gözlendi ve değiştirilmedi.
FULL NOT DISPATCHED; F6-E NOT AUTHORIZED; production UNCHANGED.
[R1 planı](F6_D_R1_PLAN.md), [R1 teslimi](F6_D_R1_DELIVERY_REPORT.md), [recovery matrix](evidence/f6-d-r1/recovery-matrix.json).
Aşağıdaki önceki teslim/bekleme ifadeleri tarihsel kayıttır.

## 27 Ekim 2026 — F6-D sınırlı bağımsız kabulü

**F6-D LIMITED SYNTHETIC ENCRYPTED BACKUP & RECOVERY ESCROW — ACCEPTED WITH EXPLICIT EXCEPTIONS.**
Kabul edilen application `71e916a6e8e6ceb1eef9f02b5e3c6ee379064eaa`, tree
`0c5f1729089dafb07dec2e374e1079efa75fcbba`; evidence docs
`f16c306d7a6670ad512fbb224a824af1e844eac6`.
[FAST40555003177](https://github.com/Boydaksu/hostidercp/actions/runs/40555003177):
PHP8.3/8.4 her sürüm57 suite/731 test/0 hata, eski699+32 korunmuş;
PHPStan0, lint435/0, pattern457/0, migrations001–054 değişmez.
Kabul sentetik read-only/bounded DB snapshot, private file inventory, gerçek
XChaCha20-Poly1305, deterministic identity, atomic immutable publication/replay,
authenticated owned escrow restore/INSERT-only writer/metadata witness,
checksum/provenance/finance eşliği, crash/rollback/retry,1/4/8 process,
retention dry-run ve native Queue/Worker test/mock güvenliği içindir.
Genel F6-D **PARTIAL**. Açık istisnalar: **BLOCKED_IDENTITY_BOUND,
BLOCKED_ISSUER_RECOVERY, BLOCKED_ORIGINAL_ARCHIVE_BYTES**, gerçek offsite/PITR/binlog,
RPO/RTO/hosting kapasitesi, WHMCS/support/PDF restore ve key custody/rotation.
Bu işlem yalnız dört kabul belgesini değiştirir; source/test/fixture/CI/migration
değişmez, başarılı731 test tekrar çalıştırılmaz. Rollback yalnız docs commit revert.
F6-D-R1 recovery hardening yalnız sentetik GO. F6-E/FULL/production restore/staging/
merge/deployment/cutover yetkisi yoktur. Main protected=false/rulesets=[] gözlemi,
PR3 OPEN/DRAFT ve bütün gerçek GATE'ler korunur. Aşağıdaki bekleme kayıtları tarihseldir.

Durum: SYNTHETIC DEVELOPMENT PARTIAL; bağımsız kabul PENDING; son exact aday FAST PASS.
F6-C LIMITED SYNTHETIC ACCEPTED: docs-only90aaaaa8a8c8c743119b2c8f08e71cc58445a534.
Yalnız README/F6_PLAN/F6_C_PLAN/F6_C_DELIVERY_REPORT değişti; application c5ee926,
tree6418118929a977729f6fe12383c689c91f3edad5 ve evidence2e57619 ayrıdır.
Eski699 test/kod korunur; kabul için eski FAST tekrar çalıştırılmadı.

## Exact source ve son FAST kanıtı

Application SHA: `71e916a6e8e6ceb1eef9f02b5e3c6ee379064eaa`.
Application tree: `0c5f1729089dafb07dec2e374e1079efa75fcbba`.
[FAST40555003177](https://github.com/Boydaksu/hostidercp/actions/runs/40555003177): PHP8.3/8.4 her sürüm57 suite/731 test/0 hata; eski699 ve56 suite sayıları korunmuş, yeni32 PASS.
[PHP8.3 job](https://github.com/Boydaksu/hostidercp/actions/runs/40555003177/job/122368732702);
[PHP8.4 job](https://github.com/Boydaksu/hostidercp/actions/runs/40555003177/job/122368732856).
PHPStan0, lint435/0, pattern457/0, Composer/npm audit0, JS state3/0.
Scope helper6/0 ve docs487 PASS ayrıca; 731 toplamına eklenmez.
İki job'ın raw log'larından PASS adlarının multiset karşılaştırması yapıldı;
eski699 ad eksiksiz,56 eski suite sayısı aynı,32 yeni ad eşittir.
[32 yeni test adı ve korunmuş adlar](evidence/f6-d/test-index.json),
[CI/suite/quality](evidence/f6-d/ci.json),
[119 test dosyasının Git blob eşliği](evidence/f6-d/preservation.json),
[source/commit proof](evidence/f6-d/source-proof.json),
[failure matrix](evidence/f6-d/failure-matrix.json).
ciSourceProof her suite öncesi ve finalde58 kez exact SHA/tree/clean tracked source
kontrolünü sessiz uygular; tree log'da yazdırılmaz. GitHub Git commit API tree'si
yerel tree ile ayrıca eşleştirildi. Son kaynak için tek push FAST attempt1 success;
başarısız/cancelled koşu sonuca dahil edilmedi. FULL/Chromium/10/30/20 maraton yok.
Linux symlink case iki job'da gerçekten yürüdü; yerel Windows blok kaydı korunur.

Rollback application commit listesi: yalnız
`71e916a6e8e6ceb1eef9f02b5e3c6ee379064eaa`.
Migration001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b`.
Kanıt kapanışı ayrı docs-only commit'tir; kaynak/test/CI değişmez.
Mevcut kullanıcı düzenlemesi docs/F6_B_B01_REVIEW.md commit dışı korunur.
Main `ada37dc52b324e34380ba836ae7794cebb174ec4`: protected=false/rulesets=[];
PR3 OPEN/DRAFT, ayarlar değişmedi. Bu açık branch koruması merge/release öncesi
sahip tarafından ayrı kapatılmalıdır. F6-D PARTIAL, bağımsız kabul PENDING;
FULL NOT DISPATCHED, production backup/restore NOT VERIFIED, F6-E NOT AUTHORIZED.
Gerçek WHMCS/provider/payment/mail/file transfer/staging/merge/deploy yok. DUR.

## Gerçek geliştirilen bileşenler

[Plan](F6_D_PLAN.md), [ADR-027](adr/ADR-027-synthetic-operational-recovery.md).
Yeni Snapshot, Vault, AuthenticatedBackup, Destination, Retention ve test/CLI demo.
Core Database/Migrator/CanonicalJson/Crypto, accepted F6-B Sandbox/SourceIdentity,
F6-C Inspector ve native Queue/Worker yeniden kullanılır. Yeni scheduler/queue/
Identity/Finance/Audit motoru veya production dispatcher/HTTP endpoint yoktur.

Snapshot SELECT-only bağlantı ve bounded READ ONLY transaction kullanır; source
mutex ve F6-C pre/post reconciliation ile dokuz tablonun actual evidence kökü aynı
olmalıdır. Her tablo512 satır/source256/raw2MiB, satır64KiB civarı sınır; PK sırası
belirlidir. Schema CREATE metadata fingerprint'i, migration/checkpoint/history ve
financial/support facts aynen tutulur. Source DB connection kill gerçekten test edildi.

Private storage/ kökü0700, dosyalar0600 (POSIX); path input'u yoktur, root random
capability + lease ile bound'dur. Source sample dosyaları private FS'de gerçektir;
capture sırasında missing/size/SHA/snapshot/symlink kontrolü yapılır. İçerik açıkça
SYNTHETIC F6-D SAMPLE ONLY'dir. F6-B eski SHA/size metadata'sı byte kaynağı olmadığı
için bu yeni dosyalar DERIVED samples olarak ayrı contract taşır; eski facts değişmez.

Native Crypto XChaCha20-Poly1305 authenticated encryption/CSPRNG nonce ve key ID
context'i kullanılır. Düz metin paket disk'e yazılmaz. Private temp encrypted part,
fflush/fsync, decrypt/verify ve lease kontrolü sonrası atomic rename yapılır.
Immutable artifact ID actual DB/schema/file/report data hash'idir; operasyon zamanı,
encryption nonce ve attestation bu logical ID'yi değiştirmez. Aynı snapshot replay
mevcut paketi tekrar authenticate eder, ikinci final artifact üretmez. 1/4/8 gerçek
PHP process aynı artifact'e ulaşır. flock bounded10s, private storage16MiB/128 entry;
gerçek disk quota veya hosting kapasitesi kabulü değildir.

Durumlar kod akışında PLANNED/CAPTURING, encrypted partial SEALED, tam auth+publish
VERIFIED; exception FAILED, process partial INCONCLUSIVE'dur. Genel production
state engine veya audit ledger eklenmedi. Status yalnız sealed candidate/partial
dosya sayar; key verification olmadan success söylemez. Failed-before-write, kalıcı
last verification/restore receipt ve native queue oldest/DLQ global dashboard bağları
NOT_AVAILABLE'dır. Native Queue testlerinde gerçek UNKNOWN, stale fence ve DLQ;
Worker testinde yalnız açıkça registered synthetic handler çalışır, ikinci once boş.

## Restore ve açık teknik sınır

Destination yalnız yeni owned f6d_ schema, exact marker/account/secret/lease ve
ayrı private target Vault kabul eder. Data writer sadece SELECT+INSERT9 tablo;
UPDATE/DELETE/DDL/native başka schema erişimi yoktur. Ayrı setup/DDL witness yalnız
metadata okur; doğru target schema'da TRIGGER-visible yetkiyle unapproved definer
trigger yokluğunu doğrular. Witness yoksa restore BLOCKED; data writer'a TRIGGER
veya DDL hakkı eklenmez. Schema compatibility data okunmadan karşılaştırılır;
existing target okumaları da bounded READ ONLY transaction'dadır.

Tam authenticate/manifest doğrulamasından sonra yalnız boş target'a transaction
INSERT yapılır. Existing nonempty target yalnız exact dataset eşitse replay;
başka kayıtlar overwrite edilmez. Post-read logical row digest source ile aynıdır,
finance metadata ve original mapping/provenance/history/HMAC bytes korunur. Private
sample dosyaları size/SHA/SourceIdentity ownership ile doğrulanır. Restore crash
commit öncesi DB rollback; files private kalabilir ama success verilmez. Commit
sonrası gerçek crash retry'ı durable rows/files'i yeniden karşılaştırır; kör tekrar
INSERT veya provider CREATE yoktur. Provider operations üretecek adapter yoktur.

**BLOCKED_IDENTITY_BOUND:** restored f6b_environment source schema/account/snapshot
ve imza bağlarını korur; yeni target için rewrite/re-sign yapılmaz. Native Sandbox
activation gerçekten reddedilir, F6-C'yi yanlış target authority ile çalıştırıp PASS
üretilmez. Escrow byte/logical equality VERIFIED, native application restore BLOCKED.
Cold restore için original signing context/key strategy ayrıca gereklidir.

**BLOCKED_ISSUER_RECOVERY:** doğrulanmış capture'ın logical ID'si private issuer
capability HMAC'iyle encrypted envelope içinde bound'dur. Yalnız encryption key
sahibi başka bir source/report üretemez. Issuer capability/lease de restore için
gerekir; mevcut test lease30dk'dır. Kalıcı/dış key storage, issuer recovery ve key
rotation/re-encryption production kabulü yoktur. Capability/key/nonce/MAC rapora
ve Git kanıtlarına yazılmaz. Üretimde bağımsız disaster recovery iddiası kurulmaz.

**BLOCKED_ORIGINAL_ARCHIVE_BYTES:** dört sample dosya gerçek private byte örneğidir,
özgün F6-B attachment/PDF byte'ı değildir. Orijinal metadata SHA'sının preimage'i
uydurulmaz. Gerçek arşiv/MIME/AV/retention ve legal PDF kabulü NOT_VERIFIED'dır.

## Negatif testler ve gelişim hataları

32 yeni F6-D testi son yerel kaynakta32/32 PASS. Eski699 yerelde tekrar edilmedi.
Önce22,27,28,30,31-test geliştirme turları geçti; bunlar final acceptance sayılmaz.
Bounded restore okuma düzenlemesinde32-test turu2 FAIL verdi: canonical JSON alfabetik
kolon sırası PK sırası sanılmıştı; güvenli digest ret oluştu. PK'ler açık seçilerek
düzeltildi; assertion/gate gevşetilmedi, son32/32 PASS.

Üç dar negatif bulgu önce1 FAIL, minimum düzeltmeden sonra final suite'te PASS:
1. Duplicate JSON keys: authenticated payload canonical eşlik kontrolüyle reddedilir.
2. Encryption-key-only source forgery: trusted capture issuer capability MAC'i
   olmadan forged ownership/report/manifest kabul edilmez.
3. Definer trigger: sınırlı INSERT rolü trigger metadata'sını göremeyebilir;
   privileged metadata-only witness ile data insert öncesinde ret edilir.

Gerçek child process exit73: before/capture DB/files/manifest/encryption/publish,
restore insert/files/after commit. Bunlar exception mock'u diye sunulmaz.
DISK_FULL fault kontrollü kısa write'tır; fiziksel disk doldurulmadı. EXPIRE fault
commit öncesi stale-op kontrollü reddidir; ayrıca gerçek lease zamanı geçmiş marker
testleri vardır. Windows symlink fixture alt senaryosu BLOCKED_PLATFORM; Linux
FAST'te gerçek symlink case yürüdü. Full power-loss/fsync-directory dayanıklılığı
ve Windows ACL/shared-host filesystem kabulü NOT_VERIFIED'dır.

## Retention / monitoring / rollback

Retention yalnız dry-run14 günlük/8 haftalık/12 aylık union-reference planıdır.
Son bilinen recoverable VERIFIED kopya korunur; failed/incomplete/key-unavailable
adaylar silinmez. key_available tüm gerekli key+issuer capability erişimi için
sentetik metadata'dır, gerçek doğrulama yerine geçmez. Future clock/quota review;
duplicate IDs ret. Format full-snapshot-only'dir; incremental dependency chain
metadata'sı desteklenmez ve BLOCKED olur. Hiçbir deletion execution API'si yoktur.
Ayrı fixed-source aylık sınır probe 12 monthly referans ve daily/monthly union eşliğini doğruladı; nonempty incremental chain reddedildi (32 suite testine eklenmez).
Gerçek clock/retention/KVKK G-O04 açıktır. Telemetry no mail/SMS/webhook; secret,
body, source path veya credential döndürmez. Prod capacity/RPO/RTO NOT_VERIFIED.

Production migration001–054 ve subtreec0729c92dd143c017fdec32421bf67fe6b51e05b aynıdır.
Restore marker ve native Queue test DDL yalnız owned ephemeral schema'dadır;
055+ yoktur. Rollback F6-D application commit revert + exact owned schema/directory
teardown; production DOWN/DROP/restore yoktur. Forward-fix immutable source records
overwrite etmez. Gerçek cutover rollback'i ayrıca delta/reconciliation/owner kararı ister.

## Operasyon kapıları

| ID | Sentetik kanıt | Gerçek GATE durumu | Eksik kanıt | Risk | Sonraki yetkili faz |
|---|---|---|---|---|---|
| OPS-002 | Auth encrypted escrow/DB+derived files | NOT VERIFIED | Offsite copy, cold native restore, signing/issuer key recovery | Bağımsız recovery garantisi yok | Ayrı key/restore tasarımı ve yetki |
| OPS-003 | Readiness/schema/index/trigger/checksum ret | Gerçek operasyon OPEN | Gerçek update/forward-fix/restore provası | Release engeli | Ayrı operasyon doğrulama |
| OPS-004 | Source/escrow digest equality | Real cutover NOT VERIFIED | Delta, WHMCS cron, single actor | Cutover yasak | F6-E ayrıca yetkili olmalı |
| OPS-005 | 1/4/8 processes + native Queue/Worker fence | Hosting cron OPEN | Gerçek bir-dakika overlap/CPU/timeout | Kapasite/RTO garantisi yok | G-O01 |
| G-O01/02 | Local ephemeral DB/FS proof | OPEN | Hosting/PITR/binlog/quota/ACL/power-loss | RPO/RTO beyanı yok | Ayrı gerçek operasyon |
| G-O03/04 | Synthetic source/files/retention model | OPEN | WHMCS/version/hash/realbytes/KVKK | Gerçek arşiv ve recovery kabulü yok | Ayrı keşif/hukuki kabul |
| G-V01–04/G-C01/G-R01/G-D01 | No provider dispatch | OPEN | Vendor/account/API/OT&E | Canlı provider kabulü yok | Ayrı vendor GATE |
| G-P01–02/G-X01–02 | Preserved currency facts | OPEN | PayTR/FX/e-belge gerçek kanıtı | Gerçek finans kabulü yok | Ayrı finans GATE |
| G-T01/SUP-010/PROV-004 | Owned metadata korunur | Real NOT PASSED | Transfer/archive/resource gerçek kanıtı | Native service/archive kabulü yok | Ayrı doğrulama |
| PROV-005/DOMAIN-002 | No external effects | OPEN/PARTIAL-MANUAL_REVIEW | Hourly/definitive-failure proof | Otomatik canlı kabul yok | Vendor doğrulama |
| F5-C1–4 | Private sample/file metadata | OPEN | Routing/auth, cutoff, AV/MIME/transport, gerçek teslim | Canlı mail/file kabulü yok | Ayrı GATE |
| Production CRUD / ileri Support | No API/automation | DEFERRED/OPEN | Native authorization/confirm, auto-close/merge/split/tarihsel SLA | Kapsam açılmadı | Ayrı görev |
| SEC-MAIN | Read-only observation | protected=false/rulesets=[] | Aktif branch koruması | Merge/release öncesi açık gereksinim | Proje sahibi ayrı işlem |
| F6-E/FULL | Not dispatched | NOT AUTHORIZED | Ayrı açık talimat | Canlı ilerleme yok | Owner kararı |

F6-A/B/C ACCEPTED LIMITED SYNTHETIC; F6-D PARTIAL, INDEPENDENT ACCEPTANCE PENDING.
PR3 OPEN/DRAFT; production UNCHANGED. Son exact SHA/tree FAST kanıtıyla teslim ve DUR.
