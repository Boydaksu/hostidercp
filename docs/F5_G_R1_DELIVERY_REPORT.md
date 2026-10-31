# F5-G-R1 — merge SOURCE read-only integrity

31 Ekim 2026. DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING.
Base7da6ad75f43013b8b2e6e51765adc784d463d767; tree66ea38cc927df0d222a480be6c0d59da46257c97; migration001–056 subtree67280d29411bb2b570dbc83e1b8fa0c8d3a0e468.
FR-SUPPORT; SUP-003/004 privacy/ACL, SUP-007 conflict, SUP-008 queue/idempotency.

Plan: first reproduce source CSAT CREATE through native HTTP on unchanged app and retain FAIL. Add existing Projection.writable after native CSAT ACL and before replay/mutation. Audit all Support view-default entries; narrowly guard incident-link service and queued outbound binding where demonstrated. Preserve history reads/download/retention. No new architecture/permission/migration.
Files: necessary runtime guards, new regression suite/fixture/process helper, suite registry, this single report and evidence/f5-g-r1. Previous F5-G report and evidence untouched.
Tests: source CREATE/UPDATE/replay denied with snapshot equality, normal/TARGET CSAT, history, IDOR/stale/session/CSRF/disclosure, actual CSAT/merge process race and other source mutations; PHP8.3/8.4 local Docker +G36/F31/D/E/Identity/HTTP/hardening, PHPStan5/lint/pattern/docs. FULL/Chromium/Actions excluded. Canonical manifest and old blob/migration/user file protection.
Rollback separately authorized reviewed forward-fix/consistent restore; no automatic revert/reset/clean. After successful checks one exact-parent [skip ci] commit, force=false fast-forward; PR3 OPEN/DRAFT. WHMCS DEFERRED; G-O03 OPEN; LIVE MERGE/AUTO-CLOSE/STAGING/PRODUCTION NOT AUTHORIZED.


## Sonuç ve kök neden

CSAT mutation varsayılan view yetkili ticket yüklemesini yazma yetkisi sayıyordu; mevcut RESOLVED/window/version şartları merged SOURCE'u dışlamıyordu. Writable guard replay'den önce eklendi; CREATE/UPDATE ve önce tamamlanmış-key replay Source'ta409 alır, Target'a yönlendirilmez. Normal ve TARGET native CSAT policy/status/version/replay davranışı aynı; eski Source rating'i ACL ile okunabilir.

Denetimde incident-link'in u/s yetkili iç servis yolu ve önceden kuyruğa alınmış outbound işleri de bulundu. Incidents.linkable mevcut manage/incident yetkisinden sonra writable ister. Mail.Outbound.bound hem ready hem dispatch binding'inde SOURCE'u reddeder; receipt transaction içindeki kontrol current locking read'dir. Handler FAILED döner; domain row/event/audit/outbox yazılmaz, native queue retry/bookkeeping yeni ticket yetkisi oluşturmaz.

Ayrıca normal repeatable-read snapshot'ı merge'den önce açılmışsa eski Projection.writable yeni SOURCE bağını göremiyordu. Zorlanmış sıra ile ayrı süreçte merge commit edildikten sonra stale snapshot'ın gerçekten açık kaldığı kanıtlandı. Writable artık edge ve alias scope doğrulamasını FOR UPDATE current read ile yapar; alias açıklanmadan her iki güncel ticket/ACL kontrol edilir. View metodlarının varsayılan okuma davranışı değişmedi. Service target-department pin çağrısı da current read kullanır. Yeni permission/mimari/migration yoktur.

[Mutation denetim matrisi](evidence/f5-g-r1/mutation-audit.json): Service, CSAT, Incident, Work, Automation, Mail Attachments/Inbound/Outbound/Retention, Merge ve AutoClose incelendi. Reply/note/state/move/priority/assign/draft/upload ve inbound canonical seam mevcut/current guard'larla SOURCE'u reddeder. Historical/list/message/CSAT view ve native attachment download korunur. Retention/expiry/hold, unbound inbound REVIEW intake, session/rate/presence metadata ve idempotent receipts kendi güvenlik sözleşmelerine göre çalışır; bunlar Source üzerinde yeni business yazma yetkisi değildir.

## Gerçek test kanıtı

İlk uygulama baytları exact7da6ad75 parent iken yeni16 suite: **10 PASS /6 FAIL**. Source CSAT CREATE/UPDATE/replay, post-merge mutation/race, incident service link, queued mail ve inconsistent target-scope ret testleri açığı üretti. İlk üç dar guard sonrası iki runtime **397/0 +quality PASS** verdi; bu koşular kendi3161314b… manifestleriyle ara adaydır, final kabul değildir. Ardından current-read iki ek test aynı snapshot'ta ayrı merge süreciyle **16 PASS /2 FAIL** verdi; diğer regresyon ve kalite bu başarısız koşuda NOT_RUN. Bu kayıtlar değiştirilmedi. Final üç ek snapshot/pin testi dahil19 yeni assertion içerir; ilk16 assertion aynıdır.

| Suite | PHP8.3.36 PASS/FAIL | PHP8.4.27 PASS/FAIL |
|---|---:|---:|
| support-merge-readonly |19/0|19/0|
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
| **Toplam** |**400/0**|**400/0**|

Yeni native MariaDB/HTTP/process testleri: Source CSAT POST reddi ve tüm Support tabloları+rating/revisions/event/audit/outbox/inbox/jobs/idempotency SHA-256 snapshot eşliği; normal/Target CREATE+UPDATE+replay, Source geçmişi, IDOR/disclosure, stale versions, revoked session, CSRF, gerçek CSAT–merge yarışı, Service/Work/UI/incident/upload/queued mail Source guards, attachment original-byte download, Target policy/window/status, zorlanmış eski snapshot'ta current writable ve inbound ret, Target department pin. Snapshot business kapsamı identity last_seen/rate counters gibi bağımsız telemetry'yi içermez; audit ve outbox dahil bütün ilgili business kayıtlarını içerir.

Her final runtime:489 PHP lint /0 violation;512 pattern /0 finding; PHPStan level5 /0 error; snapshot docs PASS. Ayrı pinned PHP/MariaDB, internal networks/tmpfs, published host port yok. Host/container önce/sonra512 source SHA-256 eşliği PASS. source_commit=null: testler publish commit'inden önce yapıldı.68 suite/1003 PHP test yalnız beklenen registry envanteri, FULL PASS değildir. FULL/Chromium/GitHub Actions/current Composer/npm audit çalıştırılmadı. Önceki R2 istisnası/CI kabulü bu yeni adaya aktarılmaz. [Summary ve ayrı run/log/source kayıtları](evidence/f5-g-r1/summary.json).

## Bütünlük, yayın ve sınırlar

Canonical512-source SHA-256: d0f508c1acc6253563289a4038391c13f2bd74d2a0d6bb2ff9748cbce30216f4; exact UTF-8 json.dumps(sort_keys=True), default separators/no newline [manifest](evidence/f5-g-r1/candidate-source-manifest.json). [Source diff](evidence/f5-g-r1/source.diff), [korunum](evidence/f5-g-r1/preservation.json), [dosya listesi](evidence/f5-g-r1/changed-files.json), [kanıt manifesti](evidence/f5-g-r1/manifest.json).1295 eski dosyadan7 izinli değişiklik dışında1288 blob korunur. Eski150 test dosyası ve605 evidence birebir; önceki F5-G teslim raporu/sonuçları yeniden yazılmadı.001–056 migration subtree **67280d29411bb2b570dbc83e1b8fa0c8d3a0e468** aynı; yeni migration yok. User review SHA-256 fe09203507b7ad8d97200aa4641b52ad103072804ad66ad41f804d47209a8aa7 korundu. Eski worktree'lere reset/clean/silme uygulanmadı.

Tek commit mesajı fix(F5-G-R1): enforce merged source read-only integrity [skip ci]; exact parent7da6ad75f43013b8b2e6e51765adc784d463d767. Yayın öncesi remote HEAD tekrar kontrol edilir; force=false fast-forward ve gönderim sonrası commit/tree/remote-source/PR/check doğrulaması final mesajında verilir. Bu belge kendi commit hash'ini içermez; dairesel hash/ikinci commit yoktur. [skip ci] CI PASS değildir. PR3 OPEN/DRAFT. Rollback ancak ayrı yetkiyle reviewed forward-fix/consistent restore; otomatik revert yoktur. Gerçek WHMCS/customer/provider/payment/mail/merge/staging/production işlemi yapılmadı.

F5-G-R1: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING

F5-G: FINAL LIMITED REVIEW PENDING R1

WHMCS MIGRATION: DEFERRED

G-O03: OPEN

LIVE MERGE / AUTO-CLOSE / STAGING / PRODUCTION: NOT AUTHORIZED
