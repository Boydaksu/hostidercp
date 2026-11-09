# ADR-022 — native UI, session drafts ve stale response sınırı

## Son bağımsız teknik kabul kaydı

**F5-E LIMITED MOCK TECHNICAL BASELINE — ACCEPTED.**

Proje sahibinin son bağımsız teknik kabulü, uygulama
[`e0d460d0aea05f496135d169ecbb64ce94659cc9`](https://github.com/Boydaksu/hostidercp/commit/e0d460d0aea05f496135d169ecbb64ce94659cc9)
için kaydedildi. [PHP 8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40367106726):
her sürüm **52 suite / 582 test / 0 hata**; önceki579 PHP testi eksiksiz korundu.
**Chromium11/11 ve state3/3 PASS.** Client author_id redaksiyonu P1 **CLOSED**;
yeni/legacy API projeksiyonları, can_attach sınırı ve native attachment authorization PASS.
277 korunan kaynak dosyasının hash eşliği ve001–054 migration koruması PASS.

F5-A/B/C/D önceki sınırlı kabulleri korunur; F5 test/mock alt fazları A–E sınırlı
teknik kapsamlarıyla kabul edilmiştir. Bu karar **FULL F5, V1 veya production-ready**
kabulü değildir. F5-C'nin dört canlı istisnası (mailbox routing/sender authentication;
kesin retention cutoff/scheduler; gerçek AV/IMAP/SMTP/genel MIME; mock HELD/ACCEPTED
sonucunun gerçek teslim olmaması), auto-close, merge/split, tarihsel SLA rapor
validasyonu, SUP-010/F6, DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED,
PROV-005 ve bütün canlı GATE'ler **OPEN** kalır.

PR #3 **OPEN/DRAFT** kalır. Merge, staging, production, canlı mail veya provider
işlemi yapılmaz. Yalnız bu kabul kaydı işlenir; çalışma durur ve sonraki aşama için
proje sahibinin ayrı talimatı beklenir. Aşağıdaki teslim, denetim bekleme ve tasarım
kayıtları tarihsel olarak korunur; güncel kabul durumu bu kayıttır.

---


F5-E test/mock teknik kararı; D4/SUP-001–009. F5_E_PLAN.md normatif matrisi verir.
Yeni auth/audit/queue yoktur; HTML ve JSON aynı native Identity/current ACL ile
korunur. Mevcut panel CSS/Response/CSP kullanılır. Yeni sayfa sadece mock veriyle
çalışan gerçek native UI'dır; görsel maket veya canlı kabul değildir.

Roster expiry/leaves native `Time` sözleşmesine göre UTC
`YYYY-MM-DD HH:MM:SS[.uuuuuu]` biçimindedir. Report range endpoint'inin ISO-Z
biçimiyle karıştırılmaz; browser yayını gerçek native prepare/execute ile sınanır.
Generation fence'in stopped kararı DOM adapter'ında da uygulanır: bütün ticket
panelleri, encrypted draft'ın client kopyası, pending payload ve kapalı dialog
içeriği temizlenir. Açık review promise iptal edilir; geç callback stopped durumu
sonrasında pending private payload'ı tekrar oluşturamaz.

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
deterministik pagination'dan önce uygulanır. Client projection private note/private audit actor/
mail/raw/root/roster içermez. Editor typed allowlist AST üretir; serbest SQL/PHP
ve arbitrary expression input'u yoktur. Policy/roster ticari değer seed edilmez.
F5-C dört istisna ve enterprise historical SLA/auto-close/merge/split/SUP-010/F6
gibi önceki açık maddeler bu UI ile kapanmaz. Export unsupported; canlı GATE OPEN.
