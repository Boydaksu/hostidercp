# F6-D-R1 — bağımsız cold verification hardening teslimi

## 28 Ekim 2026 — R2 read-only recovery teslimi / CI engeli

R1 **LIMITED SYNTHETIC COLD VERIFICATION ACCEPTED WITH EXPLICIT EXCEPTIONS**;
kabul docs `1bcad5f93f4d75b4af336180fe71b46d1a4c1374`.
R2 development **PARTIAL; INDEPENDENT ACCEPTANCE PENDING; FAST BLOCKED_EXTERNAL**.
Application `f0db545b5b5fd0e569b07e33efb85e245be16b40`, tree
`6482e0d546e9bcafbeeb7872052eda99a97d8d26`.
Yeni24 yerel R2 ve ilgili25 R1 test PASS; eski756 testin129 dosyası blob-equal.
Actual restored target, source kaybı/Vault expiry sonrası SELECT-only fresh process'te
doğrulandı. BLOCKED_IDENTITY_BOUND **PARTIAL yalnız read-only**; runtime verilmedi.
BLOCKED_ISSUER_RECOVERY PARTIAL, ORIGINAL_ARCHIVE_BYTES OPEN; executable-object
inventory NOT_VERIFIED_SELECT_ONLY, gerçek offsite/PITR/key custody NOT VERIFIED.
[FAST40637188575](https://github.com/Boydaksu/hostidercp/actions/runs/40637188575):
attempt1 ve açık kullanıcı izniyle aynı SHA attempt2 GitHub Billing & plans engeliyle
job başlamadan FAILURE. PHP8.3/8.4 **NOT RUN**;59 suite/780 yalnız beklenen registry
toplamıdır, başarılı CI sonucu değildir. Yeni tekrar başlatılmadı.
Migration001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmez.
Bütün gerçek GATE'ler OPEN; PR3 OPEN/DRAFT; main protected=false/rulesets=[] gözlendi,
ayarlar değiştirilmedi. FULL NOT DISPATCHED; F6-E NOT AUTHORIZED; production UNCHANGED.
[R2 planı](F6_D_R2_PLAN.md), [R2 teslimi](F6_D_R2_DELIVERY_REPORT.md), [CI kanıtı](evidence/f6-d-r2/ci.json).
Aşağıdaki eski bekleme/kabul kayıtları tarihçedir.

## 28 Ekim 2026 — F6-D-R1 bağımsız sınırlı kabulü

**F6-D-R1 LIMITED SYNTHETIC COLD VERIFICATION — ACCEPTED WITH EXPLICIT EXCEPTIONS.**
Application `3773351c01164dc3e58a7c4b869ef980a5b7cf79`, tree
`80beecd299c60ed669e4d0c52c76751ee020b692`; evidence docs
`2a3b4d1d8ffd9a06cbabe66d10de694645971198`.
[FAST40566808738](https://github.com/Boydaksu/hostidercp/actions/runs/40566808738):
PHP8.3/8.4 her sürüm58 suite/756 test/0 hata; eski731+25 ve57 suite korunmuş;
125 eski test dosyası blob-equal, PHPStan0, lint443/0, pattern465/0, locked audits temiz.
Kabul versioned Ed25519/pinned key/trusted operator policy, exact backup/source,
ciphertext SHA/XChaCha20-Poly1305/original issuer record digest, source schema loss
sonrasında bağımsız salt-okunur verification, expired Vault enrollment reddi ve
immutable evidence/negatif regresyon kapsamıdır.
F6-D genel PARTIAL. BLOCKED_IDENTITY_BOUND OPEN; BLOCKED_ISSUER_RECOVERY PARTIAL;
BLOCKED_ORIGINAL_ARCHIVE_BYTES OPEN. Native application restore BLOCKED;
production cold restore/offsite/key custody/RPO/RTO/PITR NOT VERIFIED.
Bu kabul yalnız altı belgeyi değiştirir; application/test/fixture/CI/migration aynı,
başarılı756 test tekrar çalıştırılmaz. Rollback yalnız kabul belge commit revert.
R2 yalnız izole sentetik read-only recovery authority geliştirme GO; runtime
activation/F6-E/FULL/staging/production/merge/deploy/cutover yetkisi yoktur.
PR3 OPEN/DRAFT; bütün gerçek GATE'ler korunur. Aşağıdaki eski statüler tarihsel kayıttır.

Durum: SYNTHETIC DEVELOPMENT PARTIAL; independent acceptance PENDING; final exact FAST PASS.

## Exact application ve son CI

Application SHA: `3773351c01164dc3e58a7c4b869ef980a5b7cf79`.
Application tree: `80beecd299c60ed669e4d0c52c76751ee020b692`.
[FAST40566808738](https://github.com/Boydaksu/hostidercp/actions/runs/40566808738),
push attempt1 **SUCCESS**; kaynak değişmeden ek geniş FAST yok.
[PHP8.3](https://github.com/Boydaksu/hostidercp/actions/runs/40566808738/job/122410289939)
ve [PHP8.4](https://github.com/Boydaksu/hostidercp/actions/runs/40566808738/job/122410289945):
her sürüm **58 suite /756 test /0 hata**.
Eski **731 PASS adı** multiset karşılaştırmasıyla eksiksiz; **57 eski suite sayımı**
aynı; **25 yeni R1** test PASS.125 eski test dosyasının Git blob eşliği korunmuştur.
PHPStan0; lint443/0; pattern465/0; Composer locked audit ve npm lock audit bulgusuz;
JS state3/0; ayrı scope/helper6/0 ve docs505 PASS (756 toplamına dahil değil).
Raw CI log'ları Git'e konulmadı; yalnız sanitized test/suite/quality kanıtı kaydedildi.
Runner her suite öncesi ve finalde59 kez sessiz exact SHA/tree/clean tracked checkout
kontrolü uygular. Tree log'da yazdırılmaz; GitHub Git commit API tree'si yerel tree
ile ayrıca eşleştirildi. FULL/Chromium/10/30/20 maratonları dispatch edilmedi.

[CI ve suite dökümü](evidence/f6-d-r1/ci.json),
[yeni25 ad/baseline referansı](evidence/f6-d-r1/test-index.json),
[125 blob korunması](evidence/f6-d-r1/preservation.json),
[source proof](evidence/f6-d-r1/source-proof.json),
[recovery matrisi](evidence/f6-d-r1/recovery-matrix.json),
[gerçek cold child örneği](evidence/f6-d-r1/example-cold-recovery-manifest.json),
[negatif önce FAIL](evidence/f6-d-r1/negative-before.txt).
Son yerel25/0 ve ilgili32/0; başarısız development23/1 sonucu kabul kanıtı sayılmadı.
Cached-issuer negatif testi final25 ve iki CI sürümünde PASS; eski F6D_LEASE
ret davranışı korunur, bağımsız authorization ile yalnız read-only verification eklenir.

Production migration001–054 subtree
`c0729c92dd143c017fdec32421bf67fe6b51e05b`.
Rollback application commit listesi yalnız
`3773351c01164dc3e58a7c4b869ef980a5b7cf79`; source/test/CI değişmeyen ayrı docs-only
kapanış hazırlanmıştır. Mevcut docs/F6_B_B01_REVIEW.md kullanıcı düzenlemesi dışarıda
korunur. Main `ada37dc52b324e34380ba836ae7794cebb174ec4`,
protected=false/rulesets=[] gözlemi güncel; ayarlar değişmedi, merge/release öncesi açık.
PR3 OPEN/DRAFT. Native recovery BLOCKED; issuer recovery PARTIAL; original bytes OPEN;
gerçek cold restore/offsite/key custody NOT VERIFIED. R1 independent acceptance PENDING.
FULL NOT DISPATCHED; F6-E NOT AUTHORIZED; production UNCHANGED. Teslim ve DUR.

## F6-D kabul kapanışı

Docs-only113334dd2c37556ed20a83b6e84eeeab3dcfc42b yalnız README, F6_PLAN,
F6_D_PLAN, F6_D_DELIVERY_REPORT değiştirir. Application71e916a6e8e6ceb1eef9f02b5e3c6ee379064eaa,
tree0c5f1729089dafb07dec2e374e1079efa75fcbba ve evidencef16c306 aynıdır.
F6-D LIMITED SYNTHETIC ENCRYPTED BACKUP & RECOVERY ESCROW — ACCEPTED WITH EXPLICIT
EXCEPTIONS. Genel F6-D PARTIAL; identity/issuer recovery/original bytes ve bütün gerçek
operasyon kapıları açık istisnadır. Kabul kaydı için eski731 test tekrar çalıştırılmadı.

## Uygulanan R1 sözleşmesi

[Plan](F6_D_R1_PLAN.md), [ADR-028](adr/ADR-028-offline-synthetic-recovery-attestation.md).
Yeni RecoverySigner, RecoveryAuthorization ve ColdRecovery; Vault'a verified enrollment
seam'i, AuthenticatedBackup'a authenticated ciphertext digest'i eklenmiştir.
Eski open/verify/restore validation koşulları kaldırılmamış veya gevşetilmemiştir.
Destination/Sandbox/Inspector/Loader/Crypto/Database/Migrator/Queue/Finance/Identity
değişmez. Yeni25-test suite, fixture/child/enrollment regression ve CLI demo vardır.
CI registry/FULL listesine yalnız yeni suite eklenmiştir; FULL çalıştırılmamıştır.

Enrolment orijinal verified AuthenticatedBackup, current Vault issuer HMAC/lease ve
ayrı Ed25519 signing key ister. Signer exact authenticated cipher hash'ini kontrol
eder, current issuer'ı imza öncesi ve fence'i imza sonrası doğrular. Byte'lar,
original issuer MAC/backup ID, history/checkpoint/mapping/provenance değişmez.
Eski artifact overwrite edilmez; sidecar capsule yalnız bellekte üretilir.

Versioned canonical capsule; fixed domain-separated Ed25519 context, ciphertext SHA,
original issuer record digest, backup/source roots/target snapshot, logical DB root,
encryption key ID ve zamanları bağlar. Native XChaCha20-Poly1305 kullanılır. Capsule'ın
kendi public key'i kabul edilmez; güvenilir operator config'inden max8 pinned key,
revocation ve expected exact identity gerekir. Fresh recovery authorization en fazla
900sn geçerlidir; source operational lease'inden bağımsızdır. Encryption key tek
başına issuer yetkisi değildir. Pinned public key tek başına decryption yapamaz.

ColdRecovery salt-okunur; DB/path/URL/credential input'u almaz, restore yetkisi veya
AuthenticatedBackup/Sandbox nesnesi üretmez. Çıktı yalnız sanitized aggregate/hash
raporudur. Report array'i Destination::restore'a verildiğinde TypeError olur.
Yeni child process'e kaynak DSN/account/signing secret/Vault token verilmez.
Gerçek source schema DROP edilmiş ve original lease expired iken cold verification
PASS. Source server'a bağlanmadan real sodium decrypt/signature doğrulanır.
Bu sentetik source loss testidir; production sunucu kapatma testi değildir.

## Açık recovery sınırları

**BLOCKED_IDENTITY_BOUND — OPEN.** Mevcut Sandbox exact f6b_ schema/account, marker,
secret ve tablo/grant sözleşmesi ister. Inspector private constructor Sandbox ve
original HMAC signing context'i ister. Yeni owned f6d_ restore eski marker'ı aynen
korur; native activation gerçekten reddedilir. Marker rewrite, history re-sign veya
Inspector bypass yoktur. Native recovery test BLOCKED; yalnız escrow restore PASS.
Signed attestation tek başına runtime authority değildir. Versioned read-only
recovery context + durable activation/replay/revocation sözleşmesi ayrı tasarım ister;
DDL gerekirse yalnız055+ PROPOSED/ayrı onay. Bu görevde migration veya DDL değişikliği yok.

**BLOCKED_ISSUER_RECOVERY — PARTIAL.** Önceden enrolled doğru sentetik artifact'ler
source/Vault lease/capability olmadan pinned verification key + encryption key ile
doğrulanır. Recovery signing key rotation eski doğru public key tutulduğunda old
capsule'ı korur; revoked key ret. Önceden enrol edilmemiş ve issuer'ı kayıp artifact
sonradan güvenilir ilan edilemez. Original issuer HMAC cold aşamada tekrar hesaplanmaz;
enrollment sırasında doğrulanmış original record ve ciphertext signature ile korunur.
Cold verification native restore değildir. Gerçek custody/offsite, durable anti-rollback
key policy/clock, recovery secret retention, encryption-key rotation/re-encryption,
anlık merkezi revocation ve production recovery NOT VERIFIED. Bellekteki policy'nin
revocation gecikmesi en fazla900sn'dir; operator trusted config/root güven sınırıdır,
operator ID yalnız hashed etikettir, native Identity/RBAC authentication değildir.

**BLOCKED_ORIGINAL_ARCHIVE_BYTES — OPEN.** Dört derived SYNTHETIC PDF sample özgün
WHMCS attachment/resmî belge değildir. Eski metadata/hash'leri değiştirilmez.
Gerçek arşiv, AV/MIME/retention/PDF/KVKK ve G-O03/04/SUP-010 kapıları açık kalır.

## Test ve failure kanıtı

Yeni25 R1 testi, ilgili32 F6-D testi hedefli yürütülmüştür. İlk23-test turunda tek
fixture hatası oldu: tüm history event_hash alanlarını aynı hash'e değiştirmek unique
indekse takıldı. Owned fixture mutation tek satırla sınırlandı; güvenlik assertion'ı
gevşetilmedi. Bu23/1 tur acceptance sonucu değildir; kayıt development-failed.txt.
Bir ön kontrol script'inde eksik namespace alias'ı düzeltildikten sonra eski kaynak
karşı-örneği gerçek F6D_LEASE nedeniyle FAIL olarak kaydedildi; bu eski fail-closed
kontrolün yanlışlığı değil, cold capability eksikliği kanıtıdır (baseline-before.txt).

Yeni R1 geliştirmesinde cached AuthenticatedBackup ile lease sonrası enrollment
karşı-örneği önce1 FAIL verdi (negative-before.txt). Current issuer/fence eklenince
aynı dar negatif test final suite'te PASS. HMAC/marker kontrolü kaldırılmadı.
Son test assertion'ları byte eşliğini boolean hash_equals ile kontrol eder; hata
çıktısına plaintext/ciphertext/key/nonce/signature/MAC dökülmez.

Crash before authority ve after verification gerçek child exit73;4 concurrent
verification DB/artifact state değiştirmez. Duplicate verification read-only ve
izinlidir; duplicate native activation BLOCKED'dır, one-time authorization iddiası yoktur.
R1 DB INSERT yapmadığından yeni cold restore INSERT/after-commit crash proof'u yoktur;
mevcut escrow crash/rollback/re-read garantileri değişmeyen32 F6-D testiyle korunur.
Old731 test adları/57 suite ve125 eski test dosyası Git blob eşliği korunmuştur;
son exact application FAST ayrı kanıtla kaydedilmiştir. Başarısız/cancelled koşu PASS sayılmaz.

## Migration / rollback / kapılar

Production001–054 subtreec0729c92dd143c017fdec32421bf67fe6b51e05b aynıdır. Yeni
production veya fixture DDL yoktur; existing owned fixture setup tekrar kullanılır.
Rollback yalnız R1 application commit revert + exact owned schema/user/directory
teardown; original artifact'ler ve immutable finance/mapping/provenance korunur.
Capsule forward-fix yeni contract version ister; gerçek cutover rollback ayrıca
delta/reconciliation ve owner kararı gerektirir.

G-O01/02/03/04 OPEN; production cold restore/PITR/offsite/key custody NOT VERIFIED.
Gerçek SUP-010/PROV-004 NOT PASSED; G-V01–04/G-P01–02/G-C01/G-R01/G-D01/G-X01–02,
G-T01 ve PROV-005 OPEN; DOMAIN-002 PARTIAL/MANUAL_REVIEW. Production mapping/import
CRUD DEFERRED. F5-C dört canlı istisna, support auto-close/merge/split/tarihsel SLA açık.
Main protected=false/rulesets=[] salt-okunur gözlendi; ayarlar değiştirilmedi.
PR3 OPEN/DRAFT. F6-E NOT AUTHORIZED; FULL NOT DISPATCHED; production UNCHANGED.
R1 bağımsız kabul PENDING; kanıt tesliminden sonra DUR.
