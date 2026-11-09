# F6-D-R2 INDEPENDENT ACCEPTANCE REVIEW PREPARATION

STATUS: BLOCKED / INSUFFICIENT EVIDENCE

2026-10-28, Europe/Istanbul. Prepared by Codex for a separate independent reviewer.
This is source/evidence audit preparation, not an independent acceptance decision.
Decision: B - PENDING / INSUFFICIENT EVIDENCE. Actual F6-D-R2 acceptance remains PENDING.

## 1. Executive Result

The exact immutable source identity, 40 manifest hashes and separate local Docker test groups are supported by the inspected Git objects and logs.
No critical PASS/root security violation was demonstrated in the inspected R2 path.
Approval is blocked by the unamended exact-source FAST criterion and incomplete corroboration of the environment's MariaDB patch version/capacity observations.
A local exception draft is reviewable but not effective. No test was run during this audit.

Scope/requirements: OPS-002/003/004, SEC-004, D6 limited synthetic reconciliation.
Files: only the two new acceptance-preparation Markdown files. Migration: none.
Verification: immutable objects, hashes, logs, assertion/source reading.
Rollback: only these new files may be removed after review; no user-file restore, reset, commit or push.
README and docs text inventory (576 text files, approximately 5.05 million characters) were read programmatically for inventory/policy search; this is not a claim of exhaustive semantic review of every historical document.
Detailed semantic review focused on the normative acceptance/decision documents, R2 plan/delivery/hardening/Docker reports, ADR-023/028/029 and linked R2/R1 evidence and sources listed below.

## 2. Git Identity Verification

| Object | Verified result |
| --- | --- |
| Application | 53162495902b3b168c2fb8554594e99c23a34d62 |
| Application tree | e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47 |
| Production migrations 001-054 | c0729c92dd143c017fdec32421bf67fe6b51e05b |
| Local and GitHub branch evidence HEAD | cc94f662454cb3f030addd2db0785a09900c0ed2 |
| Docker evidence subtree | 9110522a521348d9a1c44a0df3975c35c8cfac2a |
| Support-fix evidence subtree | 9ef15aae2798bd988678d861424e4f4db21a5247 |
| PR #3 | OPEN/DRAFT; exact evidence HEAD |

Repository: https://github.com/Boydaksu/hostidercp .
Read-only GitHub connector endpoints: git/ref/heads/codex/f5-support-center, git/commits/application-SHA, git/trees/evidence-SHA, recursive docs tree and pulls/3.
Seven specifically requested JSON/log files were also fetched at exact evidence SHA; their returned Git blob IDs match the immutable directory tree.
Local git ls-remote could not resolve github.com; the connected GitHub API supplied remote verification without shell network escalation.

git diff --name-status application-SHA evidence-SHA shows 56 added docs/evidence files only.
All non-docs tree entries, including src/tests/.github/migrations/tools, remain unchanged.
preservation.json was not trusted blindly: all 134 baseline/current test blob IDs were recomputed from Git at f0db545b5b5fd0e569b07e33efb85e245be16b40 and the application SHA; no mismatch.
The user's pre-existing docs/F6_B_B01_REVIEW.md change is excluded.
No main protection claim is refreshed by this review.

## 3. Evidence Verification Matrix

Paths below are relative to docs/evidence/f6-d-r2-hardening/ at evidence HEAD unless stated.
VERIFIED means recorded artifacts were corroborated, not that this audit reran their commands.

| Path | Claim and actual verification | Result |
| --- | --- | --- |
| docker/summary.json | Separate 59 packages/625 tests per PHP; parsed successful suite boundaries in first/continuation logs | VERIFIED |
| docker/manifest.json | 27 canonical Git blob SHA-256 values recalculated | VERIFIED |
| docker-support-fix/manifest.json | 13 canonical Git blob SHA-256 values recalculated | VERIFIED |
| docker/sanitized-83.txt; sanitized-84.txt | Exact PHP/source proofs; 50 successful suites/398 tests each; quality step records | VERIFIED |
| docker/continuation-sanitized-83.txt; continuation-sanitized-84.txt | Nine other successful suites/227 tests each; support still failed | VERIFIED |
| docker/result-83.json; result-84.json; continuation-result-83.json; continuation-result-84.json | Failed application exits are retained and consistent with support failures | VERIFIED |
| docker-support-fix/result.json | Segmented scope 60/782, quality_rerun=false, single_full_broad_pass=false | VERIFIED |
| docker-support-fix/after-4g-php83.txt; after-4g-php84.txt | 157 individual PASS lines, 157/0 summaries and source proofs at both ends | VERIFIED |
| docker-support-fix/after-4g-php83.json; after-4g-php84.json | Application exit 0, PHP image/source bindings; quality field separately excluded | VERIFIED |
| docker-support-fix/before-768m-php84.txt | 60 PASS + one failure, remaining 96 not executed; captured HY000/1005 | VERIFIED |
| docker-support-fix/capacity.json | Capacity figures are recorded but underlying historical DB-mount samples absent; explicit ENOSPC not captured | PARTIAL |
| docker-support-fix/runner-768m.ps1.txt; runner-4g.ps1.txt | One-line tmpfs change 768m to 4g; identical pinned images, internal network/no published host ports | VERIFIED |
| docker/application.sh; continuation.sh; support-fix/application.sh; prepare.sh | Exact-source preparation, registered broad/continuation and direct unchanged support entrypoints | VERIFIED |
| docker/quality.sh; docker/run-version.ps1 | Actual earlier quality steps and captured exits; distinct from last support placeholder | VERIFIED |
| docker/remaining-*.txt; focused-diagnostics.json and diagnostic PHP drivers | Partial closure diagnostic evidence; not official support-suite success | PARTIAL |
| local-targeted.txt; local-r2-24.txt; local-r1-25.txt; local-r1-failed.txt; negative-before.txt | Recorded 2/0, 24/0, final25/0; earlier25/1 and pre-fix1/1 remain failures | VERIFIED as historical logs; local precommit logs alone do not pin SHA |
| source-proof.json; preservation.json | Claimed identities checked against actual objects; 134 preserved blobs match | VERIFIED |
| docker/summary.json MariaDB 10.11.18 | Full version declared; image ID pinned, but no retained SELECT VERSION/image metadata corroboration | PARTIAL / NOT REVERIFIED |
| ci.json | Hosted run is reported billing-blocked; hosted success is not claimed | PARTIAL; live run/annotation not independently refetched in this review |

Manifest verification uses git cat-file blob, avoiding checkout line-ending conversion.
All 40 entries match; neither missing entries nor mismatched hashes were observed.
Matching hashes prove internal artifact consistency, not independent execution authenticity.
Logs are filtered; missing raw error bodies are deliberate, and absence of a filtered ERROR line is not standalone proof of absence of all runtime errors.

## 4. PHP 8.3 / PHP 8.4 Docker Results

| Runtime | First successful packages | Continuation successful packages | Later full support | Segmented union |
| --- | --- | --- | --- | --- |
| PHP 8.3.36 | 50 / 398 | 9 / 227 | 1 / 157 | 60 / 782 |
| PHP 8.4.27 | 50 / 398 | 9 / 227 | 1 / 157 | 60 / 782 |

The 59 successful non-support packages do not overlap; adding support exactly covers tools/ci/suites.json's 60 entries.
Each version has 782 individual successful names, all unique across the selected successful groups.
The six CI helper and three JS state checks are separate and are not added to the 782 PHP count.
No FAIL/ERROR/SKIP lines appear in the later full support logs, and all 157 PASS names are present.
The harness supports failures rather than a separate ERROR/SKIP taxonomy; there is no separate machine ERROR/SKIP counter. No success is inferred for unexecuted tests.

Historical first broad support: 59 PASS/1 FAIL/97 not executed.
Historical continuation support: 60 PASS/1 FAIL/96 not executed.
Diagnostic closure continuation: 31 PASS/66 FAIL out of 97, not an official successful package.
tests/harness.php executeTests() prints registered test count even on fail-fast; it must not be read as executed count.
Final support has 157 actual PASS lines, so that ambiguity does not affect final support success.
The original broad and continuation processes still exited 1; no uninterrupted full broad success occurred.

PHP and DB image IDs are pinned in runner/result metadata.
MariaDB claimed version 10.11.18, Docker Desktop 4.86.0, Engine29.7.2, Composer2.10.3, Node18.20.4/npm9.2.0 are recorded environment assertions; this audit does not independently attest all installed versions.
The DB image is sha256:de61fed4a40d3842f3ee09944ba52792156cfd9adf489b2cc670fc6ded28df8d.
The runner diff proves the intended capacity change is test-only.
Failure1005 is not uniquely disk exhaustion; missing OS ENOSPC and missing retained DB df captures limit the causal proof. The successful unchanged package is independently visible in the recorded logs.

## 5. Quality Verification

docker/sanitized-83.txt and sanitized-84.txt:1-69, quality.sh and run-version.ps1 corroborate earlier exact-SHA quality execution:
source before/after, scope, documentation525/0, helper6/0, Composer locked audit no advisories,
PHPStan no errors, lint453/0, patterns475/0, four JS syntax steps, JS state3/0 and npm audit zero vulnerabilities, all step exits0.
These are VERIFIED RECORDED RESULTS; not newly executed, not hosted CI, not a comprehensive secret/security audit.
Support-fix runner: quality_exit=0 is assigned without executing quality. capacity.json explicitly identifies the placeholder and result.json says quality_rerun=false.
Quality on the final support execution: NOT RUN / NOT REVERIFIED. Current vulnerability databases and FULL/Chromium/load/session marathon checks: NOT REVERIFIED.
Earlier same-source quality evidence may be considered only as that earlier evidence.

## 6. Security Invariant Audit

All source line references below refer to application SHA, whose source trees match evidence HEAD.

- RecoveryContext::open, src/Migration/Operations/RecoveryContext.php:12-18 calls ColdRecovery::verify and pins private reference data.
  ColdRecovery::verify, ColdRecovery.php:14-31 checks canonical capsule, trusted authority/key, Ed25519 signature, ciphertext digest/decryption, backup envelope and signed logical tables/source roots.
- RecoveryContext::compare, RecoveryContext.php:24-45 compares row multisets/counts, finance, ownership and privacy.
  Line38 computes Contract::hash($tables) and appends EVIDENCE_ROOT_MISMATCH on inequality with verified logical_sha256 before status selection at39.
  Only empty reasons and no ownership/privacy mismatch yield MATCHED_REFERENCE; differing ordered roots yield REFERENCE_DIFFERENCES, or BLOCKED on privacy/ownership.
  The different-root result is REVIEW_REQUIRED in the actual inspector, not a literal FAIL status; it cannot PASS.
- RecoveryInspector::inspect, RecoveryInspector.php:36-49 is the inspected R2 actual DB PASS path.
  It reads fixed projections/PK ordering under bounded READ ONLY REPEATABLE READ, repeats fences, calls compare, and maps only MATCHED_REFERENCE to PASS at46.
  At47 observed_target_evidence_sha256 is assigned the exact candidate hash from compare, not an unrelated summary.
  immutable_evidence at RecoveryContext.php:44 is MATCHED iff that root equals verified logical_sha256.
  Thus PASS implies immutable_evidence MATCHED and observed SHA equals verified logical SHA.
- Contract::hash, src/Migration/Contract.php: hash method, uses Core/CanonicalJson.php.
  CanonicalJson::normalize sorts object keys but retains list order. Equal row multisets do not imply equal ordered root.
- tests/migration-recovery-integrity.php:7-13 reverses multirow f6b_history, asserts all counts/multiset matches and finance equality, explicitly asserts unequal root, DIFFERENCES, REFERENCE_DIFFERENCES and EVIDENCE_ROOT_MISMATCH.
  This is a real rejection assertion, not a name-only check. It is pure comparison and does not claim an actual DB ordered-permutation attack.
- The second integrity test:14-22 first asserts actual DB PASS/MATCHED/exact root, changes payload_hash without changing row counts, then requires REVIEW_REQUIRED/DIFFERENCES/root inequality/fingerprint change and no effects.
  Existing target tests at33-63 cover provenance/history/finance/ownership/privacy/schema/migration/missing rows and DB privilege denials.
  Recorded Docker continuation logs include integrity2/0, R2 target24/0 and R1 recovery25/0 on both PHP versions.
- RecoveryInspector::fence:20-29 rejects incorrect account/schema/role/grants, requires exact schema SELECT and global USAGE, denies grant option/extra privileges, validates InnoDB base tables before marker reads, and checks schema/lease/migration readiness.
  RecoveryAuthorization::flags and RecoveryTargetPolicy::allow/check require test/synthetic flags, backup/purpose/epoch/expiry and loopback target constraints.
  Database.php disables multi-statements. Migrator::assertReady only reads; migrate() is not called by this path.
- Database-level evidence is more than source inference: target.php:56-64 asserts UPDATE/DELETE/CREATE/cross-schema/CALL denial, rejection of UPDATE/EXECUTE/TRIGGER grants, and view rejection.
  target-definer.php:7-13 checks the marker-view attack is blocked before the definer's GET_LOCK side effect; IS_USED_LOCK remains null.
  Separate fixture setup has elevated rights to build/mutate synthetic targets; those are not reader rights.
- Exceptions in inspect map to BLOCKED at49; compare finance errors add FINANCE_UNVERIFIABLE and privacy parse errors block. Unknown/missing data does not gain PASS.
  Other Reconciliation/Report instances can carry arbitrary content; a Report fingerprint is not a cryptographic authority certificate and is not accepted as runtime authority.
  Caller-supplied compare arrays remain REFERENCE_COMPARISON_ONLY_NO_DB_AUTHORITY (target-boundary test).
- SELECT-only refers to persistent target access. Session settings, transactions and advisory locks are reachable intentional effects; no target DML/DDL/CALL/provider/payment/mail path was found in the inspected reader.
  Source review and retained synthetic tests do not prove live deployment grants, root-compromise resistance, complete trigger/routine inventory, durable config anti-rollback or production safety.
  executable_object_inventory remains NOT_VERIFIED_SELECT_ONLY. Trusted setup/server administrative authority and concurrent hostile privileged DDL are outside this limited threat model.

Conclusion: the inspected source and existing negative assertions support the scoped invariant.
This audit did not rerun tests or prove all system security properties; no source fix is made.

## 7. Local Docker CI Exception Assessment

See the accompanying proposal's 15-criterion table.
F6_D_R2_PLAN.md:71-75 and DELIVERY_REPORT.md:29/149 retain exact-source FAST.
No existing approved Docker substitution is demonstrated. The current request permits drafting only.
The proposal may go to the owner/independent reviewer; it cannot silently supersede those conditions.
ADR-023/CI_OPTIMIZATION_REPORT.md's separate FULL requirements remain in force; no FULL exception is sought.
No new ADR or accepted decision is created. A subsequent policy decision needs its proper authorized recorded process.

## 8. Remaining Blockers

1. Owner's explicit decision and separate independent technical approval of the narrowly scoped verification-method substitution are absent.
2. Exact historical MariaDB patch-version/image binding and DB-mount capacity capture are incompletely corroborated. Read-only existing image inspection was unavailable; no test/container started.
3. This is first-party local evidence, with filtered logs and run_id=local in source proofs; runner/result context supplies correlation, not an independent execution signature.
4. GitHub application tests NOT RUN due recorded billing failure; FULL/Chromium/marathons not supplied. No retry or paid option is proposed.
5. Native runtime recovery BLOCKED; real production recovery unauthorized; original archive bytes OPEN; issuer recovery limited/partial.
6. Offsite/key custody, PITR, RPO/RTO and physical executable-object inventory remain unverified.
7. All real provider/payment/mail/import/cutover gates remain open. F6-A/B/C and F6-D/R1 prior limited statuses are unchanged. F6-E remains unauthorized.

## 9. Files Changed

Only docs/F6_D_R2_LOCAL_DOCKER_CI_EXCEPTION_PROPOSAL.md and this document are newly created in the isolated detached worktree.
Original workspace retains only its pre-existing modified docs/F6_B_B01_REVIEW.md.
User-file SHA-256 preservation reference: fe09203507b7ad8d97200aa4641b52ad103072804ad66ad41f804d47209a8aa7.
Final Git status/preservation checks are reported at delivery.
No application, test, migration, workflow or existing document is edited; no commit/push is created.
No migration/DB rollback is needed. Keep the worktree while these uncommitted deliverables are needed; do not automatically archive it.

## 10. Final Recommendation

B - PENDING / INSUFFICIENT EVIDENCE.
A limited LOCAL DOCKER CI method can be drafted for review, but is not currently approved or an effective replacement for FAST.
F6-D-R2 is suitable for independent examination with explicit open items; it is not ready for an unconditional limited-acceptance recommendation under unchanged criteria.
The recommendation can change after the reviewer resolves missing environment corroboration and the owner explicitly records a policy-compatible exact-SHA exception, without reducing security or production/FULL gates.
The smallest next step is human review of these two drafts and retrieval of existing version/capacity evidence. No broad rerun is authorized or needed by default.
Codex must stop here pending the owner's response; no later phase is started.

## Reproduction of hash audit

Read each manifest from git cat-file blob EVIDENCE_SHA:path/manifest.json, and for every files entry compute SHA-256 of git cat-file blob EVIDENCE_SHA:path/file.
Compare to the listed sha256; do not hash PowerShell text-pipeline conversions.
The following appendix records the recalculated canonical blob hashes.

| Evidence file | Recalculated SHA-256 | Match |
| --- | --- | --- |
| docker/application.sh | f2b2556a142528c6b78d0091bf2b9759706da53e517a27ca3dcf83201afa3a70 | VERIFIED |
| docker/cleanup.json | cdb9d1772ff0efb657aaaa0833759368ca9eae4bd64a8b591f2fab7b1c91f487 | VERIFIED |
| docker/continuation-result-83.json | 316cb9a8e86bff89cb592f39e587cc797b6acc721878dcbd4667fe360f1d66c0 | VERIFIED |
| docker/continuation-result-84.json | 3dd2d9f6b19aca68171c60ee8ab6efd0394166fcdd4e6b182dcec410b971be4c | VERIFIED |
| docker/continuation-sanitized-83.txt | 4b474a16bbe41ef7e1cc18172bbfa78f7ccb78e61409cb29bb61b31c4ddecf43 | VERIFIED |
| docker/continuation-sanitized-84.txt | a45ed2674a16bdb057abb1d09e9b2ec33ba5110076efd1cd142ee68a91aac479 | VERIFIED |
| docker/continuation.sh | 70ee43104d0fc919fb1b1689a75fcfed47ec946fc8de83751a08971561be3fb7 | VERIFIED |
| docker/continue-version.ps1 | 5b28be404d574caddafd14b7dda992fcc45d1f628ad6a83a88d9adb78e9c6bfc | VERIFIED |
| docker/diagnose-mail.php | db18ace3652649a73155924d02c6789a8eb1d7c191c1e6d5577477c251cad1e2 | VERIFIED |
| docker/diagnose-support.php | a9f0f3c49b43b96e2911bdc5a27f3694852dbf5f9da01ba312ec9ad53b3fb807 | VERIFIED |
| docker/diagnose-upgrade.php | 10d1bf49378b980ec7cbf740e3d17239a3dfc8a99afe4ba71c3f40c5f9de8a19 | VERIFIED |
| docker/focused-diagnostics.json | eaf60facdb35baaf3631ef4bdb714b9df839820c0d8a4631f23aba18c2cffff3 | VERIFIED |
| docker/prepare.sh | c91aa5a22190c0f00b9fb0c2fee25db31c7a23bd98b08dea8aa1d4d002cd5e84 | VERIFIED |
| docker/quality.sh | abd5a9e17adfd032d129ff889458af7cb2cacef4312a9151829a35c0dff44a9b | VERIFIED |
| docker/remaining-result-83.json | 99b16ac529f93745755c574af4d1dae191f915e7f95f18c4516daa65db9ae3bd | VERIFIED |
| docker/remaining-result-84.json | 702e0d47e44dbb98567881a66de6725ea0cd0bb063fb51a2f1ae25e3d16a2e8e | VERIFIED |
| docker/remaining-sanitized-83.txt | b2a96aa755a574ef11851448df30fe2f2fce49ba1999f48a2552764ce980734b | VERIFIED |
| docker/remaining-sanitized-84.txt | cbbba002fbdc4d8550e511ac9493c9c86e68bd645077d7bae9be3e9855fe0a31 | VERIFIED |
| docker/remaining-support.php | 58583ccff0a40da55cdb51fbf1bd77162f8b8f79a56789551476397edd4cbb55 | VERIFIED |
| docker/remaining-version.ps1 | 42c87d770a9824d6c36d98f46c56ad8a95398ecdbe53955c1a6dcac7ee5e0212 | VERIFIED |
| docker/remaining.sh | b2005fb85b408b7973070a5e185d81c3c484eca7ed2b9f9d1829c8e14e0da7dd | VERIFIED |
| docker/result-83.json | a66b52efe95451d2040ff6cb072075e65e50d322409a80be2651acf12a2b9f05 | VERIFIED |
| docker/result-84.json | 885e56afd5aa1e77bb065aa410fa1b67b4f0e80f2b0f6efbec975a34876da8fd | VERIFIED |
| docker/run-version.ps1 | b8e75f4946171f5d38ce80e183406076f8f04bce6528fa68a5b20bb7d85f230f | VERIFIED |
| docker/sanitized-83.txt | a213288d14d54a27c2bdc89a96519395ad440331fb8e1b0bc62c10d292b19aed | VERIFIED |
| docker/sanitized-84.txt | bddcdad12230185aa8f431cff0c77c025aaff39905a0e88981594df7852923f6 | VERIFIED |
| docker/summary.json | 0dc469cda1fca1c4537e69fc732ba1985c3b5591fd30eeb4013c20d7725d8266 | VERIFIED |
| docker-support-fix/after-4g-php83.json | 1ae6087548f0e201ea8413256665e50474b927056f6b58b5046dad3ca3e49d2d | VERIFIED |
| docker-support-fix/after-4g-php83.txt | fd3b1fc9d1182081b997f5b1ff88ce26307380a400bd1a9c7d6e2c3704291639 | VERIFIED |
| docker-support-fix/after-4g-php84.json | 0e138838659cb731ab72c1e669019b2cb239450aec036258ca68b81ab57a2c86 | VERIFIED |
| docker-support-fix/after-4g-php84.txt | ad16279800b31fe8f387e9e53d73d5e11b4025fa80dc21fed99fb4951765d7c2 | VERIFIED |
| docker-support-fix/application.sh | db33939a4793c8f8e999321e1bbce7fd91ef6b15a546e7d41c928e74663ffc03 | VERIFIED |
| docker-support-fix/before-768m-php84.json | ef9d2dcd07478deea6ab467b56754639d00885c4d3de043e0664830269880257 | VERIFIED |
| docker-support-fix/before-768m-php84.txt | 50ccb805c54f1f80338039780eaa288104a1df73d5b099ed4e242c45cb1769bc | VERIFIED |
| docker-support-fix/capacity.json | 42e0d0ecab22aad9b2de2032f69972e42a33a8460caee6e55d424ffd34ea8245 | VERIFIED |
| docker-support-fix/cleanup.json | 00312cbcc7ce844cb7f7a9914a5873d28bfff343f54c80971db9e9dc0ea413ec | VERIFIED |
| docker-support-fix/prepare.sh | c91aa5a22190c0f00b9fb0c2fee25db31c7a23bd98b08dea8aa1d4d002cd5e84 | VERIFIED |
| docker-support-fix/result.json | 4fa006ffb11bfe1acaaaa25d97af66f2f0ea6f804b43502e9e715e9d640d7e99 | VERIFIED |
| docker-support-fix/runner-4g.ps1.txt | bfeeeea3b03c100968ff5758b58399c2551e18e3a7776b79f1e52f6a3180bf15 | VERIFIED |
| docker-support-fix/runner-768m.ps1.txt | 6157cdec658e0aeea14f1f44731e602f2edde549dfd3b0e77e4fa2b85d47a01e | VERIFIED |
