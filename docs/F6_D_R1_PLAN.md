# F6-D-R1 — recovery hardening planı

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

27 Ekim 2026. F6-D limited acceptance docs-only113334dd2c37556ed20a83b6e84eeeab3dcfc42b
tamamlandı. Baseline application71e916a6e8e6ceb1eef9f02b5e3c6ee379064eaa,
tree0c5f1729089dafb07dec2e374e1079efa75fcbba;57 suite/731 test korunur.
OPS-002/003/004, SEC-004, D6 yalnız sentetik test/CLI alt kapsamı.

## Kapsam ve tehdit modeli

Encryption key issuer yetkisi değildir. Yeni bağımsız Ed25519 recovery signing
context'i, native Crypto ile doğrulanmış eski paketin exact ciphertext digest'ini,
original issuer record digest'ini, backup ID/source roots/target snapshot/DB logical
kökünü ve encryption key version'ını versioned capsule'a bağlar. Orijinal paket,
ID, HMAC/history/mapping/provenance değiştirilmez. Canonical JSON ve bounded input
zorunlu. Capsule içinden public key veya yetki türetilmez: verifier ayrı güvenilir
operator config'inden pinned public keys/revocation ve exact expected backup/source
kimliklerini alır. Explicit recovery flag ve fresh kısa süreli operator authorization
gerekir; operational Vault lease'inin yenilenmesi gerekmez. Offline verification
read-only'dir, DB authority veya native restore yetkisi üretmez.

Enrolment yalnız orijinal Vault lease'i geçerliyken gerçek AuthenticatedBackup ve
ayrı signing key ile yapılır. Önceden enrol edilmemiş, issuer'ı kayıp eski artifact
sonradan güvenilir ilan edilemez. Mevcut backup verify/restore yolu korunur.
Public key rotation trusted allowlist'te eski doğru key'i tutarak modellenir;
revocation tüm ilgili capsule'ları ret eder. Gerçek custody/offsite/rollback-resistant
policy storage yoktur ve kabul edilmeyecektir. Clock ve policy güvenilir caller
girdisidir; compromised operator/root bu tehdit modelinin dışındadır.
Operator ID yalnız hashed rapor etiketidir; native kullanıcı authentication/RBAC
iddiası yoktur. Trusted policy caller/config ayrı güven sınırıdır. Bellekteki policy
en fazla900sn geçerlidir; merkezi anlık revocation veya policy rollback koruması yoktur.
Enrolment signer entrypoint'inde current Vault issuer yeniden doğrulanır; cached
AuthenticatedBackup tek başına lease sonrası yetki veremez.

## Native identity kararı

Sandbox::open yalnız f6b_ schema/account, exact environment ve secret-bound marker;
assertReady exact dokuz tablo ve loader grants; Inspector private constructor'ı
Sandbox ister, runValid orijinal secret ile HMAC doğrular. Yeni f6d_ target'a eski
marker kopyası runtime authority olamaz. Re-sign/marker rewrite yasaktır.
Signed recovery attestation tek başına mevcut Inspector'ın authority tipini ve
one-time/revocable activation'ın durable kaydını sağlamaz. Bu görevde generic ikinci
Identity/Audit sistemi veya eski Sandbox bypass'ı kurulmaz. BLOCKED_IDENTITY_BOUND
OPEN kalır; gerçek restored target üzerinde native ret yeniden kanıtlanır. Native
recovery PASS iddiası yoktur. İleride ayrı versioned read-only recovery authority ve
denetlenebilir durable attestation/replay sözleşmesi tasarlanmalıdır; yeni production
DDL gerekirse yalnız 055+ PROPOSED/ayrı onay, bu görevde migration uygulanmaz.

## Dosya değişiklikleri ve migration

Yeni Operations/RecoverySigner, RecoveryAuthorization, ColdRecovery; Vault'a yalnız
verified enrollment seam'i; AuthenticatedBackup'a exact authenticated ciphertext
digest'i eklenir. Destination/Sandbox/Inspector/Loader/Queue/Crypto/Finance/Identity
değiştirilmez. Yeni test suite/fixture/child ve CLI demo; suites.json/FULL listesine
yalnız yeni suite. Test body'leri ve production migrations001–054 immutable.

## Test / negatif kanıt

Önce eski Vault'ta lease sonrası doğrulamanın gerçek ret/capability sınırı kaydedilir;
bu yeni bir P1 kusuru veya eski güvenlik ret'inin yanlışlığı değildir. Sonra yeni
capsule ile kaynak schema silinmiş ve lease expired iken yeni PHP process'te cold
verification. Operator'a source DSN/secret/Vault token verilmez. Wrong/missing crypto,
missing/untrusted/revoked signer, yanlış key version/context/source/backup, forged
signature/issuer MAC, ciphertext/manifest/JSON tamper, expired authorization,
oversize ve old artifact replay ret. Rotation eski backup'ı uygun key ile doğrular.
Crash before/after verification gerçek child exit; verification DB write yapamaz.
Duplicate/concurrent verification salt-okunur ve deterministiktir, single-use runtime
activation değildir. Concurrent/duplicate native recovery BLOCKED; mevcut restore
transaction/crash/replay garantileri hedefli32 F6-D testiyle korunur.

Source signing secret missing iken capsule verification mümkündür; yeni hedefte
HMAC/native Inspector doğrulaması BLOCKED kalır. Wrong target/account/marker,
history/mapping/ownership farkları eski restore/native ret kontrollerinden geçemez.
BLOCKED_ORIGINAL_ARCHIVE_BYTES ve bütün gerçek GATE'ler OPEN.

## Kontrol, rollback ve kabul sınırı

Önce yeni hedefli testler ve dar32 F6-D regresyonu, PHP lint/PHPStan/pattern ve locked
audit. Son exact application SHA'da tek geniş FAST8.3/8.4; eski731 ad ve57 suite
sayımı korunur. Ayrı docs-only source/test/CI kanıt kapanışı. FULL/Chromium/10/30/20
yok. Rollback yalnız R1 application commit revert + exact owned fixture teardown;
eski immutable artifact'ler değiştirilmez. Capsule contract forward-fix version ister.
F6-D genel PARTIAL; R1 bağımsız kabul PENDING. Production cold restore/key custody/
offsite NOT VERIFIED, F6-E/staging/merge/deploy/cutover NOT AUTHORIZED.
