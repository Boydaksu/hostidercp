# F6-D — sentetik operasyon ve encrypted recovery escrow teslimi

Durum: SYNTHETIC DEVELOPMENT PARTIAL; bağımsız kabul PENDING; son aday FAST bekler.
F6-C LIMITED SYNTHETIC ACCEPTED: docs-only90aaaaa8a8c8c743119b2c8f08e71cc58445a534.
Yalnız README/F6_PLAN/F6_C_PLAN/F6_C_DELIVERY_REPORT değişti; application c5ee926,
tree6418118929a977729f6fe12383c689c91f3edad5 ve evidence2e57619 ayrıdır.
Eski699 test/kod korunur; kabul için eski FAST tekrar çalıştırılmadı.

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
FAST'te gerçek symlink case yürür. Full power-loss/fsync-directory dayanıklılığı
ve Windows ACL/shared-host filesystem kabulü NOT_VERIFIED'dır.

## Retention / monitoring / rollback

Retention yalnız dry-run14 günlük/8 haftalık/12 aylık union-reference planıdır.
Son bilinen recoverable VERIFIED kopya korunur; failed/incomplete/key-unavailable
adaylar silinmez. key_available tüm gerekli key+issuer capability erişimi için
sentetik metadata'dır, gerçek doğrulama yerine geçmez. Future clock/quota review;
duplicate IDs ret. Format full-snapshot-only'dir; incremental dependency chain
metadata'sı desteklenmez ve BLOCKED olur. Hiçbir deletion execution API'si yoktur.
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
PR3 OPEN/DRAFT; production UNCHANGED. Son exact SHA/tree FAST kanıtı sonrası DUR.
