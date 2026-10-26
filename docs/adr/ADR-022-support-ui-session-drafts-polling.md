# ADR-022 — native UI, session drafts ve stale response sınırı

F5-E test/mock teknik kararı; D4/SUP-001–009. F5_E_PLAN.md normatif matrisi verir.
Yeni auth/audit/queue yoktur; HTML ve JSON aynı native Identity/current ACL ile
korunur. Mevcut panel CSS/Response/CSP kullanılır. Yeni sayfa sadece mock veriyle
çalışan gerçek native UI'dır; görsel maket veya canlı kabul değildir.

054 additive server encrypted drafts: actor+session+customer+ticket+kind key, bounded
TTL/version/base ticket version. LocalStorage/IndexedDB/cookie içinde hassas gövde
yoktur. Send outer native Idempotency transaction'ı canonical Support compose seam
ile message/event/SLA/outbox ve exact draft cleanup'ı atomik tutar. Replay güncel
yetki ister; yeni taslak revision'ını silemez. Note ayrı key/permission'dır.

Polling15s min/tek in-flight ve generation/sequence fence; version geriye gitmez.
Hidden/revoked session durur; scope fingerprint değişimi private DOM/cache temizler.
409 otomatik retry/rebase değildir. Unknown network outcome yalnız kullanıcı
tarafından aynı immutable payload/key ile doğrulanır. Presence120s approximate count,
staff-only/current ACL; gerçek online veya note typing bilgisi değildir.

Encrypted subject q unsupported kalır. Filtreler tüm yetkili pool'da SQL tarafında,
deterministik pagination'dan önce uygulanır. Client projection private note/actor/
mail/raw/root/roster içermez. Editor typed allowlist AST üretir; serbest SQL/PHP
ve arbitrary expression input'u yoktur. Policy/roster ticari değer seed edilmez.
F5-C dört istisna ve enterprise historical SLA/auto-close/merge/split/SUP-010/F6
gibi önceki açık maddeler bu UI ile kapanmaz. Export unsupported; canlı GATE OPEN.
