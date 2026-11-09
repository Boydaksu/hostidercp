# F6-D-R2 LOCAL DOCKER CI EXCEPTION PROPOSAL

STATUS: PROPOSED / NOT APPROVED

Prepared 2026-10-28 (Europe/Istanbul) by Codex. PROPOSAL ONLY; NOT APPROVED.
This document is not an independent acceptance decision and does not amend policy.
F6-D-R2 independent acceptance remains PENDING.

## Scope, files, migration, verification, rollback

Requirements: OPS-002/003/004, SEC-004 and D6, limited to synthetic restored-target reconciliation.
Only this document and F6_D_R2_INDEPENDENT_ACCEPTANCE_REVIEW.md are created in a clean detached worktree.
No application, test, workflow, dependency or migration change; no new feature.
Verification is read-only Git object, manifest SHA-256, log/assertion and source inspection.
No regression suite, restore or migration is executed. Rollback means removing only these two newly created documents after review; no automatic removal, reset, commit or push.

## Reason and authority

GitHub FAST is recorded NOT_RUN_EXTERNAL_BILLING; the owner will not pay for hosted CI.
No retry, dispatch, paid service, push or PR state change is proposed.
Local Docker evidence must retain LOCAL_DOCKER_SYNTHETIC_SEGMENTED_VALIDATION classification.
It must never be relabeled GitHub Actions PASS, uninterrupted broad PASS, FULL or production acceptance.

F6_D_R2_PLAN.md:71-75 requests exact-source broad FAST on both PHP versions.
F6_D_R2_DELIVERY_REPORT.md:29 and 149 leaves exact-source CI evidence pending.
ADR-023 and CI_OPTIMIZATION_REPORT.md preserve distinct FAST/FULL scopes; the latter:106 says the final FULL requirement must not be removed.
No preapproved local substitution was found. The owner authorized preparation of a proposal, not its enactment.
Therefore this proposal cannot presently discharge the FAST requirement. It cannot waive FULL or any higher security/release condition.
Any adopted change needs the owner's explicit recorded policy decision and independent reviewer assessment under docs/01_DECISIONS.md and the ADR process. No ADR is enacted here because no policy decision is authorized and only two new files are allowed.

## Exact immutable envelope

- Application commit: 53162495902b3b168c2fb8554594e99c23a34d62
- Application tree: e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47
- Production migrations 001-054 tree: c0729c92dd143c017fdec32421bf67fe6b51e05b
- Evidence commit: cc94f662454cb3f030addd2db0785a09900c0ed2
- Docker evidence tree: 9110522a521348d9a1c44a0df3975c35c8cfac2a
- Support-fix evidence tree: 9ef15aae2798bd988678d861424e4f4db21a5247
- Repository Boydaksu/hostidercp; branch codex/f5-support-center; PR #3 OPEN/DRAFT at read time.

GitHub connector GETs verified branch ref, application commit/tree, evidence tree and PR.
The two remote evidence subtrees equal the local Git objects. Forty manifest entries were hashed directly from immutable Git blob bytes; 40/40 match.
Windows checkout CRLF bytes are not substituted for canonical blob bytes.
The application-to-evidence diff contains only 56 added documentation/evidence files; source, tests, workflows and migrations are unchanged.

## Conditions to be presented to the owner and independent reviewer

| Criterion | Assessment |
| --- | --- |
| 1. Billing reason | Recorded failure; existing run only, no new invocation |
| 2. Scope | Only F6-D-R2 limited synthetic SELECT-only reconciliation |
| 3. Exact application SHA/tree | VERIFIED locally and via GitHub |
| 4. Migration subtree | VERIFIED; 54 files; no migration executed |
| 5. Separate PHP versions | VERIFIED logs: 8.3.36 and 8.4.27 |
| 6. Segmented coverage | VERIFIED each version: 50/398 + 9/227 + 1/157 = 60/782; 782 unique PASS names |
| 7. Hash/source binding | VERIFIED 40 hashes, source-proof before/after and matching GitHub trees; local logs are not independent execution attestations |
| 8. Environment/capacity | PARTIAL: pinned image IDs and runner-only 768m to 4g diff verified; exact MariaDB 10.11.18/version mapping and capacity observations lack independently corroborating capture |
| 9. Hosted/local separation | Mandatory; GitHub FAST remains NOT_RUN_EXTERNAL_BILLING |
| 10. Quality | Earlier exact-SHA Docker quality logs verified; last support quality_exit=0 is a placeholder, quality_rerun=false |
| 11. Integrity negatives | Source/assertions and recorded 2/0 integrity, 24/0 R2 and 25/0 R1 logs verified; not rerun |
| 12. Unchanged application | VERIFIED app-to-evidence diff and 134 preserved baseline test blobs |
| 13. Exclusions | Native recovery, live systems, release gates and FULL remain excluded/open |
| 14. Invalidation | Any application/security change, tree drift, evidence substitution/hash mismatch or materially different environment requires fresh review |
| 15. Human approval | Missing; independent technical review and explicit owner decision required before effect |

## Confirmed evidence and remaining gaps

For each PHP version the first broad run failed in support after 50 successful packages/398 tests.
Continuation also failed support but completed nine additional packages/227 tests.
The later unchanged full support-http.php produced 157 PASS, zero failures and exit 0.
The groups do not overlap at package level; their union exactly matches the 60-entry registry.
Prior support failures, 66 diagnostic closure failures and earlier local R1 setup failure remain historical evidence; they are not counted as successes.

The fixed runner differs from the 768m runner in exactly one line: MariaDB /var/lib/mysql tmpfs size.
No production capacity change occurred. HY000/1005 is captured, but is not an ENOSPC-specific proof.
capacity.json records 703020 KiB before failure and later 2174860/2287580 KiB usage, but the sanitized logs do not preserve underlying df samples. The fixed application script runs df on /workspace, not directly on the MariaDB data mount, and its output is filtered.
The DB image ID is pinned in all result metadata; the exact MariaDB patch version is stated in summary/report, not independently captured in the preserved execution output.
A read-only attempt to inspect the existing image was unavailable. No image was pulled or container started.

Smallest evidence supplement: existing immutable DB image/version metadata, or retained sanitized SELECT VERSION() tied to that image/run; retained DB-mount capacity observations tied to the historical runs if available.
Current measurements alone cannot retroactively prove historical usage. If unavailable, the reviewer must explicitly accept this limitation or authorize a narrowly scoped diagnostic separately; no broad rerun is implied.

## Exclusions and invalidation

F6-A/B/C limited synthetic acceptance and F6-D limited escrow/general PARTIAL remain unchanged.
R1 limited cold verification remains unchanged. Native runtime recovery BLOCKED; real production recovery unauthorized.
Original archive bytes OPEN; issuer recovery PARTIAL/open beyond pre-enrolled read-only verification.
Offsite/key custody, PITR and RPO/RTO are not verified. Executable-object inventory remains NOT_VERIFIED_SELECT_ONLY.
No real customer data, providers, payments, mail, WHMCS cutover, F6-E, merge, staging, deployment or production operation is authorized.
FULL ACCEPTANCE remains unauthorized and is not supplied by these tests.

This proposal is restricted to the exact envelope above; it has no continuing/general waiver effect.
Changes to application commit/tree, security fix, migrations, evidence bytes, source/runner relationship, trust assumptions or material environment require reevaluation.
A confirmed security invariant failure blocks acceptance and requires a separately authorized task; this review must not patch code.

## Recommendation

B - PENDING / INSUFFICIENT EVIDENCE.
A narrowly scoped local method can be submitted as a draft, but cannot currently replace the binding FAST criterion.
First resolve the policy decision and the identified environment evidence gaps through the owner and independent reviewer.
Codex authorship and creation of this document confer no approval.
