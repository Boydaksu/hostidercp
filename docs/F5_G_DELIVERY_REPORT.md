# F5-G — scoped support ticket merge

30 Ekim 2026. DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING.
Base c7f51e3de3479cc35b64e05d13462889f1cfba23; tree a7eae1c82b2776b2189d3275b9459a458d432fd5.
FR-SUPPORT merge alt kapsamı; SUP-003/004 privacy+ACL, SUP-005/006 SLA+assignment, SUP-007 conflict ve SUP-008 automation korunumu; SPLIT/automatic merge/live operations excluded.

Plan: additive056 immutable edge + unique endpoint claims; existing Identity permission code support.ticket.merge (explicit grant required), native fresh step-up/CSRF/300s confirmation/idempotency, sorted departments/ticket locks. Existing customer/department/view/manage/merge grants rechecked from DB. No message movement/deletion or ID rewrite. Disjoint one-to-one merge only; any participating endpoint cannot enter another merge, so triples/chains/cycles BLOCKED. Source becomes read-only alias without rewriting status/SLA history; target remains native reply target. Both ticket versions bump, no fake reply event, native Work event/audit/HELD outbox.

Projection: legacy and UI views retain original message IDs/authors/time/privacy and origin-ticket metadata, bounded combined timeline with per-origin current ACL; customer author redaction and native attachment access remain. Source write rejected with explicit merged-target reference only after both ACL checks. Source excluded from auto-close and SLA scheduling; target version invalidates old auto-close candidate. Target department moves blocked while alias exists to preserve scope.

Active/uncertain work: native migration readiness required; presence of F6 sandbox schema blocks merge (WHMCS DEFERRED). RUNNING jobs or attempted PENDING/retryable mail for either ticket block operation. No live worker/provider/mail effects. Merge and old jobs re-read pins/current ACL. No queue engine/authentication rewrite.

Files: new Support/Merge commands/projection/controller,056,new real MariaDB/native HTTP/concurrency suite; narrow native Access/Service/UI/Work/AutoClose/bootstrap/UI hooks, registry additions, migration expectation append-only in old tests where056 requires it. Old001–055 and evidence byte-equal; existing test assertions retained, migration inventory expectations advanced only.

Validation: PHP8.3/8.4 isolated local Docker new suite + selected F5-A–F/Identity regressions, PHPStan5/lint/pattern/docs, JS syntax/state (no unrun Chromium PASS). SHA-256 manifests before/after; failures preserved. Source diff and final results added here after validation.

Rollback requires separate authorization: disable merge use/stop affected workers, preserve immutable aliases/audit and messages; reviewed forward-fix or consistent backup restore. No destructive down/reset/clean/revert. Single authorized commit after exact remote recheck, [skip ci], fast-forward only. PR3 OPEN/DRAFT. WHMCS DEFERRED; G-O03 OPEN. LIVE MERGE/AUTO-CLOSE/STAGING/PRODUCTION NOT AUTHORIZED.


## Teslim ve sonuçlar

Native Merge.Commands/Controller/Projection; açık staff confirmation ve Idempotency-Key ile çalışır. Current native view/manage/merge grant, membership, enabled MFA factor, fresh session, identity-version, yön, source/target version, DB customer/department ve policy/department snapshot yeniden doğrulanır. Source ve target sürümü ilerler; raw status tarihsel durumunu korur, immutable SOURCE üyeliği görüşmeyi salt okunur alias yapar. İki Work event/audit/HELD outbox, edge ve unique endpoint claim tek transaction'dır. Mesaj taşıma/silme, ID/author/time/visibility/attachment ownership değişikliği, sahte reply veya SLA history hesaplaması yoktur. Her endpoint yalnız bir merge'e girebilir; zincir/üçlü/cycle BLOCKED.

Mevcut admin ekranında hedef ticket kimliğiyle yetkili detay önizleme, açık yön/sürüm onayı ve sonuç/hedef seçimi vardır. Alias/message origin numaraları textContent ile gösterilir. Preview aynı selection generation/scope epoch gerektirir; belirsiz execute mevcut native aynı-key retry yolunu kullanır. Client source görünümünde hedef açıkça gösterilir; source reply409 authorized target UUID içerir, mesaj sessiz eklenmez. Client author_id/system sponsor/private note görmez. Native attachment listing/token/download origin ticket üzerinden çalışır; gerçek original-byte download testi vardır. Legacy detail ve UI birleşik ACL projection kullanır; UI50 mesaj/20 event ile bounded, global time/id sıralı, provenance korunur. Source polling target version'ı da snapshot'a bağlar. Target department move pinned; native target reply/reopen/allow_reopen kuralları aynı kalır.

Source SLA cycles/clocks/history ve assignment snapshot'ı merge anında byte-equal kalır; source scheduling durur ve archived assignment aktif least-load'a sayılmaz. Eski source/target auto-close ve automation jobs version pin'leriyle skip olur; replay ek etki üretmez. Yeni target auto-close adayı F5-F kontrollerinden geçebilir. RUNNING job, attempted PENDING/retryable mail, incomplete schema migration veya F6 sandbox schema varlığı BLOCKED. Son koşul konservatiftir; gerçek WHMCS keşfi veya migration izni değildir. Native HELD receipts dışında gerçek mail/provider/finans etkisi yoktur.

| Suite | PHP8.3.36 PASS/FAIL | PHP8.4.27 PASS/FAIL |
|---|---:|---:|
| support-merge |36/0|36/0|
| support-auto-close |31/0|31/0|
| support-migrations |4/0|4/0|
| support-http |157/0|157/0|
| support-b |28/0|28/0|
| support-c |42/0|42/0|
| support-d |41/0|41/0|
| support-e |27/0|27/0|
| identity-security |8/0|8/0|
| hardening-unit |7/0|7/0|
| **Toplam** |**381/0**|**381/0**|

36 yeni gerçek MariaDB/native HTTP/process assertion; cross-scope/IDOR/unscoped staff, session/MFA/grant/member revoke, stale versions, CSRF/direction/confirmation replay, inverse process race/triples/cycle, duplicate execute, source reply/alias ACL, private metadata/original attachment download, SLA hash, audit/provenance, auto-close process race, transaction fault rollback, pending automation replay, uncertain jobs/mail, migration, pagination/polling ve least-load kapsar. Her runtime ayrı internal-network/tmpfs MariaDB ve pinned PHP image, host port olmadan çalışır.

Her iki final koşu:487 PHP lint0 violation,510 pattern0 finding, PHPStan level5 /0 error ve snapshot docs PASS. JS syntax ve existing state3/0 +merge state3/0 PASS. Chromium/visual browser NOT_RUN. Registry67 suite/984 test yalnız beklenen PHP envanteri; FULL PASS değildir. FULL, GitHub Actions, güncel Composer/npm audit çalıştırılmadı. Önceki R2 yöntemi/CI kabulü bu yeni adaya aktarılmaz.

Başarısız kayıtlar korunur: shell CRLF exit2/PHP başlamadı;6 PASS/1 FAIL current-factor MFA eksikliği (23 yeni test ve tüm regressions/quality NOT_RUN);16 PASS/1 FAIL fixture Attachments tür hatası (16 yeni test ve regressions/quality NOT_RUN). Native enabled-factor guard eklendi; fixture mevcut factory nesnesini kullanır, assertion kaldırılmadı. Ara379/0+quality PASS yalnız kendi eski manifestine aittir. Final381/0 iki koşu aynı510-file manifestine bağlıdır; host/container önce/sonra source eşliği PASS. source_commit=null, testlerin publish commit'inden önce yapıldığını belirtir. [Summary ve ayrı run kayıtları](evidence/f5-g/summary.json).

056 yalnız native merge permission/role bucket ve iki yeni immutable edge/member tablosu+trigger ekler; explicit customer/department grant olmadan staff yetkili olmaz.001–055 blob'ları korunur: prefix202a5803956639f4b282d990fcfe4a5b353cb083; yeni001–056 subtree67280d29411bb2b570dbc83e1b8fa0c8d3a0e468. Fresh56,055→056 upgrade, replay, partial APPLYING/readiness/blind-retry block ve explicit isolated forward completion PASS. Collision quarantine sonrası kalan exact DDL yalnız testte tamamlandı; production repair/down eklenmedi. F5-F055 assertion'ları exact001–055 izole path ile aynen tutuldu; diğer eski testlere yalnız056 inventory expectation eklendi. Eski test adları korundu.

510 source canonical SHA-256: c08dd224b714936e02a8c398ff82677941ab181cb88ba840d988ae4777cda30e. Exact UTF-8 json.dumps(sort_keys=True), default separators/no newline [kaynak manifesti](evidence/f5-g/candidate-source-manifest.json). [Diff](evidence/f5-g/source.diff), [korunum](evidence/f5-g/preservation.json), [dosya listesi](evidence/f5-g/changed-files.json), [kanıt manifesti](evidence/f5-g/manifest.json).1253 eski dosyanın20 izinli değişiklik dışındaki1233 blob'u,572 eski evidence ve55 migration aynıdır. Identity/Finance/Provider/Mail ve R2/P0/P1/P2/P3/G-O03 source/evidence korunur. User review SHA-256 fe09203507b7ad8d97200aa4641b52ad103072804ad66ad41f804d47209a8aa7 değişmedi. Eski worktree'ler reset/clean/silinmedi.

Tek commit: feat(F5-G): add scoped support ticket merge [skip ci]; exact parent c7f51e3de3479cc35b64e05d13462889f1cfba23; force=false fast-forward. Remote HEAD tekrar kontrol edilir; gönderim sonrası SHA/tree/files/source/PR/workflow sonucu final mesajında verilir. Belge kendi commit SHA'sını içermez. [skip ci] CI PASS değildir. PR3 OPEN/DRAFT kalır. Rollback ayrı yetkiyle reviewed forward-fix/consistent restore gerektirir; immutable alias/audit/provenance silinmez. Native test-only/mail-disabled gate korunur; gerçek live merge/auto-close/cron, WHMCS/provider/payment/mail, merge-PR/staging/production etkinleştirilmedi.

F5-G: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING

F5-F: LIMITED SYNTHETIC CODE REVIEW PASSED

WHMCS MIGRATION: DEFERRED

G-O03: OPEN

LIVE MERGE / AUTO-CLOSE / STAGING / PRODUCTION: NOT AUTHORIZED
