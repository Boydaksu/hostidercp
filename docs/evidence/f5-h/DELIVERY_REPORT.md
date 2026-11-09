# F5-H — scoped public-message ticket split

31 Ekim 2026. DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING.
Base a6cba83791002e1be19094f79c48817635cc7663; tree315b429dc2f029a299530c854905337427d0cfec; migration001–056 subtree67280d29411bb2b570dbc83e1b8fa0c8d3a0e468.
FR-SUPPORT; SUP-003/004 privacy/ACL, SUP-005 SLA, SUP-007 concurrency/idempotency, SUP-008 automation.

Plan:057 additive permission, immutable scoped edge/member/message tables and relational customer/department/message FKs. No old message row rewrite. Native staff split permission+view/manage/current membership/MFA/fresh confirmation, current locking state and sorted selected public IDs (1..25), at least one remaining public source message; any existing merge/split endpoint blocked. Native requester active/verified/customer membership revalidated. Source user hint is immutable, used only to acquire native identity fence before department/ticket locks; all authority reread under current locks.

Native Service split-creation seam creates independent NEW child/UUID/number/encrypted subject and SPLIT_CREATED native event at DB current time; Work treats this as fresh start through existing Clocks/calendar contracts, no old message-time clock or fake reply. Source Work/SLA/assignment untouched. Outer transaction contains edge/messages/Work/audit/HELD outbox/confirmation/idempotency. Parent/child moves and merge combinations blocked.

Projection: selected public messages only in child active timeline, source active excludes them and explicit history/provenance links retained. Both current ACLs required; attachment reads original storage/ticket with both endpoint ACLs, selected historical uploads blocked. Normal replies on each ticket independent. Existing merge R1 guards retained. UI message selection/subject/preview/native confirmation/result; client links/public provenance.

Files: new Split classes/controller,057,new native suites/JS state, minimal Service/Work/Merge/view/mail/UI/registry hooks and migration inventory expectations/exact historical fixture paths.001–056 immutable. Tests PHP8.3/8.4 local Docker: new native MariaDB/HTTP/process concurrency/fault/migration cases and selected400 existing F5/R1/Identity/security, PHPStan5/lint/pattern/docs/JS state. Failures preserved; FULL/Actions/Chromium not claimed. Canonical SHA256 before/after and publication blob equivalence; user review protected.

Rollback separately authorized: reviewed forward-fix/consistent restore, immutable history preserved; no reset/clean/rebase/revert/delete. After all checks exact-parent one [skip ci] force=false fast-forward; PR3 OPEN/DRAFT. WHMCS DEFERRED; G-O03 OPEN; LIVE SPLIT/MERGE/AUTO-CLOSE/STAGING/PRODUCTION NOT AUTHORIZED.

## Validated delivery and limits

Native PHP Split Commands/Controller/Projection, additive057, native Service/Work creation seam and existing admin/client UI are implemented. Both endpoint current ACLs govern selected-message projection and original attachment download. Client output omits author identity/private notes; escaped textContent is retained. Selection is bounded1–25 public messages and leaves public source content. Source physical messages/files, source Work/SLA/history/assignment and all old migration bytes remain unchanged. Child starts NEW with a fresh native current-time SLA cycle; no fake reply or real mail.

Preparation binds payload, current scope/version/requester/authorization epoch. Execute rechecks locks, MFA/fresh session/current grants/membership, uses native confirmation/idempotency, and atomically writes child/immutable membership/audit/events/HELD outbox. Replays reauthorize without creating another child. Real process tests cover split/split, split/reply, split/merge and stale repeatable-read membership; invalid combinations return409. Forced membership insertion failure demonstrates complete rollback including SLA, audit and confirmation. Relational FKs and immutable triggers cover actual customer/department/message ownership. Department changes and any split/merge chain remain blocked.

Final PHP8.3.36 and8.4.27 each:432 PASS /0 FAIL (new32 + selected existing400 test executions; native combined support-http also includes cases that are run individually). PHPStan level5, PHP lint, pattern and documentation checks pass in both runs. Node native F5-E3, merge3, split4 state assertions pass (10 total); both changed JS syntax checks pass. The split intent helper blocks stale selection/scope/stopped preview and execute; subject max191.

Historical first8.3 run:30 PASS /1 FAIL, case31 fixture incorrectly used non-native SUSPENDED; corrected to existing DISABLED. Case32 and regressions/quality were not run in that failed execution. Its source snapshot/log/status remain retained. Intermediate successful runs precede the final narrow UI guard and are identified by their own source manifests. An initial Node invocation used a nonexistent filename and executed no tests; corrected native suite passed3/0, retained in js-state.json. No failed result is presented as PASS.

69 PHP suites/1035 tests is expected registry inventory only. FULL, Chromium, GitHub Actions and current Composer/npm vulnerability audits were NOT RUN. This is synthetic development validation, no live split, merge, auto-close, mail, WHMCS or deployment authorization. Common environment/mail safety gates remain enforced. Attachment antivirus contract retains existing synthetic fixture-only limits; no new storage/provider mechanism. Selection UI is limited to currently displayed public messages (backend max25); immutable source history is explicit. Both endpoint ACL is intentionally fail-closed if either is revoked. Native SLA configuration may remain UNCONFIGURED/UNAVAILABLE by existing policy; no fabricated guarantee.

Source integrity: candidate-source-manifest.json is the exact sorted canonical runner input, checked inside container before/after and on host. Its SHA256 is a9d0c5efc53145928c979330c5d144c9f015c1062608ce8bc7516d375884489e. Final two runs share identical source bytes. source.diff covers exactly25 source/config/test files;17 authorized existing hooks/inventory files plus8 new files. Previous test names/assertions retained; old056 migration tests use exact001–056 fixture directory. All636 old evidence files and56 old migrations are byte-identical; preservation.json records old/new migration tree and all exceptions. Main Windows user review file hash remains fe09203507b7ad8d97200aa4641b52ad103072804ad66ad41f804d47209a8aa7.

Publication: one exact-parent commit, feat(F5-H): add scoped support ticket split [skip ci], expected parent a6cba83791002e1be19094f79c48817635cc7663. Immutable commit/tree/diff and post-publication workflow/PR observations are delivered in the final response; this precommit evidence cannot include its own future commit hash. Published source blob equivalence is checked against the tested canonical manifest. [skip ci] is not CI PASS. PR3 remains OPEN/DRAFT.

Rollback is not automatic or destructive: separately authorized reviewed forward-fix or consistent database/application restore only; preserve immutable split history and actual source/files. Partial057 readiness stays blocked; isolated test quarantines the synthetic collision and explicitly completes remaining DDL before marking APPLIED. This is test evidence, not an authorized production repair procedure. No reset/clean/rebase/revert/force push. No next phase until independent code review.

F5-H: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING

F5-G/R1: LIMITED SYNTHETIC CODE REVIEW PASSED

WHMCS MIGRATION: DEFERRED

G-O03: OPEN

LIVE SPLIT / MERGE / AUTO-CLOSE / STAGING / PRODUCTION: NOT AUTHORIZED

Review artifacts: [summary](summary.json), [canonical source manifest](candidate-source-manifest.json), [source diff](source.diff), [evidence hashes](manifest.json), [preservation](preservation.json). Component: [Commands](../../../src/Support/Split/Commands.php), [Projection](../../../src/Support/Split/Projection.php), [native tests](../../../tests/support-split.php).
