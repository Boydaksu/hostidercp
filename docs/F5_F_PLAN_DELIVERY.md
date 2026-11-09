# F5-F — opt-in departman auto-close planı ve teslim

30 Ekim 2026. DEVELOPMENT AUTHORIZED; independent code review pending.
SUP-003/004, R2-06 auto-close alt kapsamı. WHMCS MIGRATION DEFERRED; G-O03 OPEN.
Base eba5694b0c73ac126822e95baabd0ed94e591ae1; tree7b34de838219b7c19126eb968198b37558c3e178.

Kapsam: yeni Support/AutoClose policy/eligibility/engine/controller, Service'e dar system-close seam;
admin/support mevcut JS ekranı, bootstrap route, native console/Queue handler ve iki suite kaydı.
Yeni055 additive immutable policy/head/receipt;001–054 byte-equal kalır. Identity/Finance/Provider/Mail
güvenlik sözleşmeleri ve R2/P0/P1/P2/P3/G-O03 kaynak/kanıtları değişmez.

Policy default DISABLED ve wait_seconds=null. Açık aktivasyonda admin açık integer süre belirler;
1..31536000 teknik bound, ticari varsayılan değildir. Mevcut Identity/MFA/fresh step-up/CSRF/native
confirmation/current membership+wildcard customer manage scope gerekir. Policy revizyonları immutable.
Approved policy durable fixed SYSTEM_AUTO_CLOSE yetkisidir; staff impersonation değildir. Worker
issuer active/MFA/current native role/grant/department membership ve identity-version pin'i kontrol eder;
logout tek başına ayarı silmez, issuer capability değişikliği/revocation pause eder. Üretim kapısı kapalıdır.

Kapanış: mevcut Queue retry-safe max3, bounded sweep; department→policy→ticket kilitleri ve native
identity fence; gerçek son RESOLVED event, due UTC, current policy/version/ticket version, no reply/reopen,
same department ve terminal SLA yeniden okunur. Support.State RESOLVED→CLOSED, ticket version, immutable
Work event+auto-close receipt+Audit+HELD Outbox tek transaction. Staff-only support_events değiştirilmez.
Replay/duplicate event receipt unique(ticket,resolution_event) ile ret; eski queued revision stale skip.

Test: native MariaDB+HTTP+CLI ve gerçek paralel worker/reply;55 fresh/54→55 upgrade/replay/failure-forwardfix;
eski F5 suites, Identity/hardening/queue/UI state kontrolleri, PHP8.3/8.4 local Docker; PHPStan/lint/pattern/docs.
Kaynak manifest before/after, eski blob/migration/user dosyası korunumu. Başarısız kayıtlar saklanır.
FULL/Actions, gerçek cron/mail/provider/payment/production yoktur.
Rollback ayrı yetkiyle: önce policy disable/worker kapalı; geçmiş policy/audit/receipt silinmez; tutarlı
backup restore veya reviewed forward-fix. Destructive down/otomatik reset/revert yok.
Başarı sonrası tek [skip ci] commit ve exact-parent fast-forward, bağımsız inceleme için dur.


## Teslim — 30 Ekim 2026

F5-F DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING. Bu aday önceki F5-E kabulünü genişletmez. SUP-003/004 ve R2-06 auto-close alt kapsamı uygulanmıştır.

Yeni Policy, Eligibility, Engine ve Controller; mevcut Service/State, Access, Work, Audit, Identity, Queue ve CLI kullanır. `/admin/support` departman menüsünde opt-in toggle, açık integer saniye, politika versiyonu, son başarılı sonuç/recent güvenli özet ve yetkili önizleme vardır. Kaydetmeden önce istemci ve sunucu doğrulaması, native fresh MFA/session/CSRF ve300s payload/state-bound confirmation gerekir. Client route yetkisi yoktur. Async ekran sonuçları generation/isConnected ile sınırlandırılır.

Default DISABLED / wait_seconds=null;1..31536000 saniye yalnız teknik bound. Disable yeni immutable revision ile null süre kaydeder. Issuer active/MFA/identity-version/current role+grant+membership tekrar kontrol edilir. Logout politika ayarını silmez; yetki veya identity-version iptali worker'ı durdurur. Policy yetkisi sabit RESOLVED→CLOSED eylemiyle sınırlıdır; SYSTEM_AUTO_CLOSE aktörü altında kalır. Staff hesabı/session veya sınırsız admin üretilmez.

`support:auto-close --once [--dry-run] [--max-tickets=25] [--max-seconds=20]` yalnız bounded native Queue seed eder. Asıl iş mevcut `queue:work --once` ile tüketilir; bunlar production kurulum talimatı değildir. Deterministik resolution time/ticket id sırası, tam candidate dedupe ve native retry_safe/max_attempts3/backoff/dead-letter vardır. Worker lock wait2s; batch25/20s. Exact seed edilmiş failed job tekrar kendiliğinden üretilmez; tekrar için operator policy review/republish gerekir. Hiçbir cron/scheduler kurulmadı. Dry-run/preview business state/job/audit değiştirmez; interaktif native auth last_seen güncelleyebilir.

Gerçek son RESOLVED event, same-department anchor, UTC due, current ticket/policy version ve current capability kilit altında doğrulanır. Reply/reopen/stale policy/active SLA clock/receipt koşulları fail-closed. Ticket state+version, immutable Work event, yeni receipt, audit ve HELD outbox tek transaction'da yazılır. Normal staff-only support_events actor FK'leri korunur. AUTO_CLOSED yeni Automation trigger'ı üretmez; gerçek mail/effect yoktur. RESOLVED'ta bitmiş SLA cycle/history yeniden hesaplanmaz. Native allow_reopen ve reply/state kuralları korunur; yarışta409 alan istemci güncel versiyonla native retry yapar, kabul edilmiş mesaj kaybolmaz.

### Hedefli sonuçlar

| Suite | PHP8.3.36 | PHP8.4.27 |
|---|---:|---:|
| support-auto-close |31/0|31/0|
| support-migrations |4/0|4/0|
| support-http |157/0|157/0|
| support-b |28/0|28/0|
| support-c |42/0|42/0|
| support-d |41/0|41/0|
| support-e |27/0|27/0|
| identity-security |8/0|8/0|
| hardening-unit |7/0|7/0|
| **Toplam PASS/FAIL** |**345/0**|**345/0**|

31 yeni test; deadline/diğer-state, customer reply/reopen, stale ticket/policy/department, iki worker, reply race, duplicate/replay, current capability/MFA/session revoke, CSRF, IDOR/private note/attachment, immutable audit/event/SLA, transaction fault rollback, retry exhaustion/dead-letter, gerçek CLI/HTTP, migration fresh/upgrade/replay/failure-forwardfix ve2s lock contention kapsar. Her sürüm ayrı internal-network/tmpfs MariaDB, published host port olmadan pinned Docker image kullanır. Kaynaklar container öncesi/sonrası ve host öncesi/sonrası aynı502 SHA-256 ile doğrulanmıştır. Kaynak commit alanı null: testler yayın commit'inden önce çalıştı.

Her iki sürümde480 PHP lint /0 violation,502 pattern /0 finding, PHPStan level5 /0 error ve snapshot belge kontrolü PASS. Native JS syntax ve mevcut support-e state suite3/0 ayrıca kontrol edildi; yeni ekran için Chromium/visual browser kabulü iddia edilmez. Registry66 suite/948 test yalnız beklenen envanterdir, FULL PASS değildir. FULL, GitHub Actions, güncel Composer/npm audit ve gerçek entegrasyon çalıştırılmadı. R2 yöntem istisnası bu yeni adaya aktarılmaz.

İlk PHP8.3 koşusu15 PASS/1 FAIL: fixture native Identity::invalidate işlemini transaction dışında çağırıyordu; kalan14 yeni test ve tüm ilgili regression/quality çalışmadı. Kayıt FAILED olarak korunur. Fixture transaction sınırı düzeltildi; native CSRF sözleşmesine göre403 assertion kullanıldı. Ara344/0 koşu kendi eski manifestine bağlıdır. Son kaynak lock bound/UI stale-result guards/test31 içerir; iki final koşu345/0 aynı manifestle geçmiştir. Hiçbir eski test adı/assertion sırf PASS için silinmedi; altı eski F5 test dosyasında yalnız055 migration list/count beklentileri ilerletildi. Ayrıntılar [summary.json](evidence/f5-f/summary.json), ayrı run JSON/log/source manifestleri ve [source.diff](evidence/f5-f/source.diff) içindedir.

### Migration ve bütünlük

055 additive: immutable policy revisions, mutable current head ve immutable unique auto-close receipt olmak üzere3 yeni tablo/trigger; yeni native module veya security engine yoktur.001–054 dosya blob'ları aynıdır; prefix tree `c0729c92dd143c017fdec32421bf67fe6b51e05b`.001–055 yeni subtree `202a5803956639f4b282d990fcfe4a5b353cb083`; yeni migration nedeniyle değişmesi beklenir. Fresh55,54→55 upgrade, checksum/replay ve partial APPLYING/runtime block testleri geçti. Forward-fix yalnız izole fixture'da çakışmayı quarantine edip kalan exact055 DDL'yi tamamlayarak kontrollü APPLIED/readiness doğrulamasıdır; production otomatik repair/down mekanizması eklenmedi.

502 kaynak dosyasının canonical manifest SHA-256: `926df6d77291ad3c398464d0d0a69fa2cd1c184281206bc2aff7ac9b7662dce7`. [Kaynak manifesti](evidence/f5-f/candidate-source-manifest.json), [yeni kaynaklar](evidence/f5-f/new-source-manifest.json), [korunum kanıtı](evidence/f5-f/preservation.json), [dosya listesi](evidence/f5-f/changed-files.json), [kanıt manifesti](evidence/f5-f/manifest.json). Mevcut1218 dosyanın14 izinli değişiklik dışındaki1204 blob'u ve547 eski evidence dosyası korunur;394 eski src/tests dosyasından yalnız Service seam ve altı migration beklenti dosyası değişir. R2/P0/P1/P2/P3/G-O03 kaynak ve kanıtları aynıdır. Ana Windows user review SHA-256 `fe09203507b7ad8d97200aa4641b52ad103072804ad66ad41f804d47209a8aa7` korunur. Diğer worktree'ler temizlenmedi/reset edilmedi.

### Yayın, geri dönüş ve kabul sınırı

Tek commit mesajı `feat(F5-F): add opt-in support ticket auto-close [skip ci]`; parent tam olarak `eba5694b0c73ac126822e95baabd0ed94e591ae1` olmalıdır. Exact HEAD tekrar kontrol edilerek force=false fast-forward yapılır; commit/tree/skip/PR ve uzaktaki dosyalar gönderim sonrası doğrulanıp final teslim mesajında bildirilir. Bu dosya kendi commit SHA'sını içermez; dairesel hash yoktur. `[skip ci]` CI PASS değildir. PR3 OPEN/DRAFT kalır.

Rollback için ayrı yetki gerekir: policy disable/ilgili worker durdurma, immutable audit/receipt/policy geçmişini koruma, tutarlı backup restore veya bağımsız reviewed forward-fix. Destructive down, otomatik revert/reset/clean yapılmaz. Gerçek ticket'larda auto-close, cron, mail/provider/payment, merge, staging ve production etkinleştirilmedi. Mevcut test-only gate (env=test, mail disabled) korunur; canlı dağıtım kapısı ayrıca bağımsız inceleme/operasyon yetkisi gerektirir.

F5-F: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING

WHMCS MIGRATION: DEFERRED

G-O03: OPEN

LIVE AUTO-CLOSE / MERGE / PRODUCTION: NOT AUTHORIZED
