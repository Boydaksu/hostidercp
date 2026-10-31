# F6-F — Offline encrypted native backup / restore rehearsal

31 October 2026. DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING.
Requirements: OPS-002 (backup/offsite/restore rehearsal), OPS-003 (pre-upgrade recovery), OPS-001 (test environment effects boundary), SEC-004 (existing customer-scope security regression), AGENTS red line12 (no secrets in logs), finance and support immutable-history preservation. This bounded native synthetic implementation does not close real operational gates.

## Package and scope

New independent `Hostider\OfflineBackup` namespace (8 classes), `bin/offline-backup.php`, native fixture/child-process harness and 36 assertion-bearing cases. Only existing files changed: suite registry and one standalone FULL workflow test registration; no workflow execution. All prior application/bootstrap/public/Migration/R2/P0/P1/P2/P3/Support/Core/Finance runtime and old tests are unchanged. No shared boot/HTTP path is modified, so 23 relevant suites (listed in summary.json) were selected rather than claiming a new full 71-suite acceptance. The old 70/1054 result remains historical. Candidate registry 71/1090 is expected inventory, not full PASS. No new production migration; 001–057 remain byte-identical.

The initial plan is implemented: add an independent bounded CLI, use native migration/schema/finance/managed storage APIs, test positive and hostile/crash paths, preserve existing blobs and publish one exact-parent commit only after validation. No design decision overrides docs/01_DECISIONS.md; no ADR or additional report chain needed.

## Operation and trust boundary

The CLI requires 64-bit PHP8.3+, pdo_mysql, sodium, POSIX UID support, fsync for files/directories, non-root UID, APP_ENV=test and F6F_SYNTHETIC_ALLOWED=1. Missing capabilities fail closed. Commands: `keygen`, `init`, `seal`, `backup`, `restore`, `verify`, `status`, `cleanup`. This is not a production backup frontend. No caller-supplied host/DSN/source schema/archive path/restore path. All operations use loopback on a caller-selected isolated test port, an already-provisioned `hostider_f0_test.f6f_test_instance` token hash with a maximum one-hour expiry, newly random f6f_src/dst/ref namespaces and ownership control rows. The component never provisions the instance pin. APP_ENV/flags alone cannot bypass it. The trusted operator must independently establish that this entire server is disposable and synthetic; a malicious administrator with root DB/OS access is outside this boundary.

Private configuration is exactly classification, port, account, password, app_key and instance_token. Pass an owner-only regular 0600 file as config FD3 and a separate 32-byte key FD4; never put secrets in argv. Example shape: `php bin/offline-backup.php backup --config-fd=3 --key-fd=4 --operation-id=<24hex>`. The caller opens those descriptors, securely provisions/retains the original application key and backs up the encryption key separately. `keygen --key-out-fd=3` writes 256 random bits only into an already-open empty private regular file; nonempty output is rejected without overwriting. Source fixture population uses existing native application services after init and before explicit seal. No arbitrary source attachment/root may be supplied. `status` is local journal state, explicitly not archive attestation; `verify` reauthenticates the archive and rechecks a completed target without repairing data.

All real backup/key/config/source/restore contents exist only in ignored private storage inside disposable test containers. None are evidence artifacts or uploaded to GitHub. Only sanitized logs, hashes, source diff and manifests are published. Passwords, configuration and keys are not exported into the backup; native application table data is included as required for a full native data rehearsal, protected by authenticated encryption. This does not include MySQL system schemas, server users or deployment configuration.

## Consistency and durability

The source is newly created and synthetic. Explicit seal drains native application writers through full table READ locks; validates native schema, all rows, foreign keys/check constraints, migration checksums and file catalog; then sets private managed file directories0500/files0400. Application writers run as non-root and cannot write sealed paths. Every backup holds the table READ locks throughout DB/file capture, checks frozen file hashes both before/after and immediately before publish, authenticates a readback of the completed ciphertext, fsyncs file and parent directory, then publishes and journals COMPLETE. The competing SQL writer test observes a real lock timeout; noncooperating file change is detected and produces no complete backup. Owner-only flock prevents concurrent operations on the same rehearsal.

This is a bounded quiescent synthetic writer boundary, not an atomic storage snapshot across independent live DB/filesystems. The same OS owner can deliberately chmod files and root can defeat locks; no resistance to a malicious owner/root or production quiescence claim is made. No automatic production maintenance mode, cron or offsite connection exists. Exact row/file checks prove this captured checkpoint only; PITR, RPO/RTO and offsite durability are NOT VERIFIED.

Records have per-row hashes, full native table definitions, generated columns, AUTO_INCREMENT counters, migration checksums, final native triggers and authenticated file catalog. Actual managed encrypted support attachment bytes and official PDF bytes are captured from native stores, with logical and physical checksums and DB-reference hashes. Orphan, missing, partially uploaded, link, traversal and invalid-permission inputs are blocked. The native zero-byte attachment lock file is explicitly recognized as a non-data auxiliary file, frozen but not exported.

## Encryption, limits and restore

PHP sodium XChaCha20-Poly1305 secretstream; fresh random header, authenticated sequence context, bounded canonical JSON frames, mandatory authenticated FINAL manifest, no compression and no archive-supplied SQL execution. Wrong key, changed/reordered/truncated/trailing bytes and semantic/manifest mismatch reject. Pre-decrypt archive limit96MiB/frame256KiB; plaintext total64MiB, raw DB16MiB,20,000 rows/100,000 cells,512 files/32MiB total, PDF10MiB, native attachment16KiB logical, table512/columns128. Source SQL has two-second statement/lock limits; no global wall-clock SLA is claimed. App and DB filesystems require128MiB measured free headroom.

DB headroom uses the actual MariaDB datadir mount's DISKS metadata, rather than assuming the client filesystem has DB space. This requires the Linux DISKS plugin and its metadata permission. The local Docker harness loads it on the owned synthetic server; test fixtures may install it only after strict loopback test DSN validation. The production CLI never installs it. Generic cPanel availability/permissions are not claimed: without this capability, CREATE/DROP isolated DB, trigger/lock and native fsync support, operations fail closed. See [MariaDB DISKS documentation](https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-disks-table) and [plugin requirements](https://mariadb.com/docs/server/reference/plugins/other-plugins/disks-plugin).

Restore authenticates the whole archive and validates source binding, exact DB version, local migration/schema model, every row/file record and ledger before allocating a target. It creates only a new owned random schema using trusted local57 migrations; target-only native trigger removal/reinstatement and parameterized import cannot mutate the source. A second authenticated read of the same archive descriptor must match the first full ciphertext hash. File staging descriptors are unlinked before any bytes are written: a killed process leaves no named plaintext staging files. Only after complete authentication and DB validation are actual intended files materialized into private target storage. Target READ locks cover row/schema/ledger verification, file materialization/checks and completion marking. All rows (including support tickets/messages, audit/event histories), native migrations/triggers/counters/FK/CHECKs and file logical/physical checksums must agree before COMPLETE. No partial success is returned.

On ordinary failure, only newly created owned targets and nonce-bound quarantine files are cleaned. On SIGKILL, WRITING/RESTORING state remains blocked; explicit cleanup revalidates operation/database/path ownership, removes only incomplete owned resources and preserves the source/completed archive. A crash during final file materialization may leave intended private quarantine data until explicit owned cleanup; it is not a completed restore. A crash before creation of a DB/path ownership marker can require operator review; the component refuses to delete unverified resources. Completed targets cannot be overwritten or deleted by replay/cleanup. These boundaries are deliberately not a cross-resource atomic activation or guaranteed power-loss recovery claim.

**Audit boundary:** native001–057 has no audit_logs DELETE trigger. The tool preserves exact audit rows and READ-locks them during restore verification; native support message/event immutable triggers are reinstated and tested. Independent `verify` detects a subsequent privileged audit deletion against the authenticated backup and does not repair data or return success. This does not invent DB-root-resistant audit immutability or alter an accepted schema.

## Validation and preserved failures

PHP8.3.36:23 targeted suites / 565 PASS /0 FAIL. PHP8.4.27:23 targeted suites / 565 PASS /0 FAIL. Each includes the new36/0 suite; counts are actual test executions and may include existing suite fixture overlap, not a claim of unique full coverage. Both final runs use exactly the same536-file manifest; non-root UID10001, separate internal Docker networks, no published host ports, pinned PHP and MariaDB10.11 images, ephemeral tmpfs. PHPStan level5, PHP lint, pattern and documentation checks all passed in both runs. Raw quality details and suite names are in the runtime logs/JSON.

The 36 cases include full native roundtrip/independent reopen, actual attachment/PDF reads, wrong key, modified/truncated/malformed authenticated data, missing file/traversal/link, real chmod denial and filesystem space pressure, blocked concurrent DB writer, file mutation, partial export/import, replay and foreign path protection, process contention/SIGKILL/owned cleanup, secret leakage, production inputs, schema/migration substitution, ledger and immutable event checks, expired pin, private keygen/nonempty FD protection, and post-checkpoint privileged audit drift detection.

All earlier test/quality failures remain in the evidence directory; none are relabeled PASS. Initial fixture policy, auxiliary lock permission, trusted final trigger folding, observer closure and audit-trigger assumptions were corrected; a killed-backup cleanup transition WRITING→FAILED→NONE was also corrected. Dependent cases not executed after an early fail are explicitly BLOCKED in their logs, not counted PASS. The successful two-case smoke and36-case intermediate run are not final manifest evidence. A local preparation assertion about locating the workflow registration failed before the expanded final runs; it neither modified old tests nor constituted a successful test run.

| Run | PHP | PASS | FAIL | Status |
| --- | --- | ---: | ---: | --- |
| hostider-f6f-712cc131edf1-83 | 8.3.36 | 0 | 1 | FAILED |
| hostider-f6f-85f27691bfb1-83 | 8.3.36 | 0 | 1 | FAILED |
| hostider-f6f-a11be1293b60-83 | 8.3.36 | 1 | 1 | FAILED |
| hostider-f6f-523f0879c9b4-83 | 8.3.36 | 2 | 0 | PASS |
| hostider-f6f-16ff028386db-83 | 8.3.36 | 14 | 1 | FAILED |
| hostider-f6f-b8981a02e582-83 | 8.3.36 | 23 | 1 | FAILED |
| hostider-f6f-8e59823d4b5f-83 | 8.3.36 | 24 | 1 | FAILED |
| hostider-f6f-341034c96a54-83 | 8.3.36 | 36 | 0 | PASS |
| hostider-f6f-ec28602128d8-83 | 8.3.36 | 565 | 0 | PASS |
| hostider-f6f-9829a5346ad0-84 | 8.4.27 | 565 | 0 | PASS |

A pre-existing FULL workflow block lists the Support auto-close/merge/read-only/split/integration commands as a continued plain run scalar rather than separate steps; it was retained byte-for-byte outside the new F6-F registration. The local runner invokes every selected PHP suite separately. This phase does not validate that old workflow block or claim future FULL CI coverage. An optional host YAML-parser probe could not run because that module is absent; no dependency was installed.

GitHub Actions, FULL acceptance, browser/Chromium, current Composer/npm advisory audits and live operations were not run. `tools/audit.php` is the source pattern scan, not a dependency-advisory audit. Registration for a future authorized CI run is not CI acceptance; `[skip ci]` is not PASS. R2's approved method exceptions are not transferred to F6-F.

## Integrity, publication and rollback

Base HEAD1aa120c3be06ed71b7536b20e70ef276cc576780; base treef2797eeb81102a3a1d4e70fbf4add5a9f65d8853. Canonical536-source SHA256: `c29eb1e09fb45c20998a48080a96baa74390c617b8c98a0e04550674731d0b4b` (`candidate-source-manifest.json`, exact UTF-8 sorted-key JSON bytes without final newline). `source.diff` covers exactly14 source/registry/workflow paths. `new-source-manifest.json`, `preservation.json`, runtime source manifests, runner proof and evidence `manifest.json` permit independent reproduction. Both final runs check the source manifest before and after execution; published Git blobs must match it.

All1418 prior blobs were checked locally;1416 unchanged, only two CI registrations modified. All713 prior evidence blobs and57 migrations preserved. Migration subtree remains01b51a655655cb4c7483d6b5937555504264ca3f; no058. Main Windows review edit SHA256 `fe09203507b7ad8d97200aa4641b52ad103072804ad66ad41f804d47209a8aa7` remains untouched. No old worktree reset/clean/deletion.

Publication requires rechecking the exact expected branch parent, one commit `feat(F6-F): add offline encrypted backup restore rehearsal [skip ci]`, and force=false fast-forward only. [GitHub change comparison](https://github.com/Boydaksu/hostidercp/compare/1aa120c3be06ed71b7536b20e70ef276cc576780...codex/f5-support-center), [source diff](source.diff), [source manifest](candidate-source-manifest.json), [summary](summary.json). The final immutable commit/tree and API readback verification are delivered in the task completion message, avoiding a self-referential commit/tree hash inside its own report. PR3 must remain OPEN/DRAFT. No automatic revert. A separately authorized reviewed removal/revert of this independent new CLI/namespace/test and registrations can roll back code; no production schema rollback is needed. Cleanup is limited to incomplete owned synthetic resources; it never removes source or completed targets.

Real OPS-002/003 acceptance still needs operator-authorized actual environment inventory, independently verified source freeze/quiescence and DB/files consistency, least-privilege hosting capabilities and DB disk measurements, secured separate key custody/rotation/recovery, encrypted offsite copy and retention policies, actual restore rehearsal with measured RPO/RTO, backup monitoring and independent operational approval. No offsite, cron, live upgrade recovery or WHMCS operation was authorized here.

F6-F: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING

F5-I: LIMITED INDEPENDENT CODE REVIEW PASSED

OPS-002 REAL BACKUP/RESTORE: NOT VERIFIED

OPS-003 LIVE MIGRATION RECOVERY: NOT VERIFIED

WHMCS MIGRATION: DEFERRED

G-O03: OPEN

LIVE BACKUP / RESTORE / MERGE / STAGING / PRODUCTION: NOT AUTHORIZED
