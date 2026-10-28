# F6-D — sentetik operasyon / backup / restore planı

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
[R1 planı](F6_D_R1_PLAN.md), [R1 teslimi](F6_D_R1_DELIVERY_REPORT.md).
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

27 Ekim2026. F6-C kabul90aaaaa; application c5ee926, evidence2e57619.
Yalnız sentetik geliştirme GO. OPS-002/003/004/005 teknik alt kapsamı;
gerçek operasyon/F6-E/FULL/staging/merge/deploy yetkisi yoktur.

1. **Kapsam:** private CLI library, Core Crypto/Database/Migrator/CanonicalJson,
   F6-B Sandbox ve F6-C Inspector tekrar kullanılır. Yeni scheduler/queue/audit yoktur.
2. **Veriler:** dokuz accepted synthetic tablo, migration/checkpoint/history,
   finance/support facts ve ayrı F6-D SYNTHETIC dosya örnekleri. F6-B fixture SHA/size
   alanları byte doğrulaması değildir; bu eski metadata değiştirilmez.
3. **Snapshot:** SELECT-only source, accepted mutex, bounded consistent READ ONLY
   transaction. Export kökü F6-C observed evidence köküyle önce/sonra eşleşmelidir.
   Dosya inventory source fingerprint'e bağlanır; farklı snapshot karıştırılmaz.
4. **Manifest:** v1/classification/id/logical/schema/migration/count/finance/
   evidence/file digest/bytes; content hash deterministik, created/reviewer ayrı.
5. **Crypto:** native XChaCha20-Poly1305 Crypto, CSPRNG nonce ve context-bound key ID.
   Anahtar/ciphertext/nonce kanıta yazılmaz. Eksik/yanlış/revoked key fail-closed.
6. **Restore:** yeni owned f6d_ schema ve private dizinde transaction insert;
   bütün paket önceden doğrulanır; counts/digest/HMAC ve dosya hash eşliği kontrolü.
   Accepted F6-B environment/account/schema binding yeni hedefte değiştirilmez.
   Bu yüzden native Sandbox/F6-C activation BLOCKED_IDENTITY_BOUND; escrow mantıksal
   restore proof ayrı sunulur. Gerçek attachment/PDF byte eşliği de BLOCKED_EXTERNAL.
7. **Migration:** current readiness zorunlu, checksum/APPLYING/extra columns/index
   bozulması source Inspector tarafından ret. Native DDL transaction rollback
   varsayılmaz; failed DDL incelenmiş forward-fix veya owned restore gerektirir.
8. **Overlap/fence:** accepted DB mutex + private operation flock, bounded lease
   ve idempotent immutable artifact adı;1/4/8 PHP process ve crash sonrası partial.
9. **Retry/DLQ/UNKNOWN:** native Queue contract incelenir; production dispatcher
   bağlanmaz. Unsafe UNKNOWN blind retry yoktur. Gerçek cron/dispatcher entegrasyonu
   ayrıca BLOCKED_EXTERNAL; sentetik concurrency modeli genel queue motoru değildir.
10. **Monitoring:** salt-okunur sanitized status/age/failed-partial/quota/fence/
    encryption/restore/queue availability; mail/SMS/webhook yoktur.
11. **Test:** izin/lease/path/symlink/duplicate/case/unicode/size/checksum/type,
    key/tamper/truncation/crash/disk budget/connection loss/stale/replay/restore
    atomicity/currency/provenance/migration/retention/zero effects/privacy.
12. **Bound:** source256, table512, logical2MiB; files8x64KiB; no compression
    (decompression bomb format denied). Private root storage/ outside public,
    Linux0700/0600; Windows ACL real hosting proof NOT_VERIFIED.
13. **Migration:** production001–054 unchanged c0729c92dd143c017fdec32421bf67fe6b51e05b.
    Restore marker/test DDL yalnız owned test schema, yeni055+ yoktur.
14. **Rollback:** yalnız F6-D code revert + exact owned fixture teardown.
    Immutable facts overwrite ve production DOWN/DROP/restore yoktur; forward-fix
    contract version ister. Gerçek cutover sonrası delta/reconciliation ayrıca şarttır.
15. **Dış kapılar:** G-O01/02/03/04, tüm vendor/finance/domain/support GATE'leri,
    productionCRUD, F5-C dört istisna, auto-close/merge/split/SLA açık; main protection
    pasif/ruleset boş gözlemi raporlanır, ayar değiştirilmez. PR3 OPEN/DRAFT.
16. **Kabul:** yeni hedefli testler, eski699 body/blob eşliği, tek son-source FAST83/84,
    ayrı docs-only kanıt. F6-D INDEPENDENT ACCEPTANCE PENDING, gerçek backup/PITR/RTO
    NOT_VERIFIED. Uygulanamayan activation açık BLOCKED; diğer sentetik işleri tamamla.

Retention ilk aşama dry-run14gün/8hafta/12ay union-reference; son doğrulanmış,
anahtarı mevcut kopya korunur. Eksik chain/clock/quota review, silme yoktur.
Paketler full-snapshot-only'dir; nonempty incremental requires zinciri reddedilir.
Issuer capability ve lease encryption key'den ayrı gereklidir; kalıcı cold recovery
BLOCKED_ISSUER_RECOVERY. Destination metadata-only TRIGGER-visible witness olmadan
restore yapmaz. Bu iki sınır gerçek hosting/key storage kabulü değildir.
