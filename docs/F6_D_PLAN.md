# F6-D — sentetik operasyon / backup / restore planı

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
