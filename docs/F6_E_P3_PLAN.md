# F6-E-P3 — offline source inventory adapter planı

29 Ekim 2026. OPS-004/G-O03 hazırlığı; bağımsız teknik kabul bekler.
Base d8dd28ae7a6cd9b13b4dc7ff35742ba7c0faa955; tree9f967e2e7eaec0b418b6712bf77cf93b70f3b06c.
Yeni Discovery/InventoryAdapter ve sentetik fixture/suite, iki suite kaydı,
bu kısa plan, tek ADR ve teslim raporu. P2/P1/P0/R2 ve migration değişmez.

Çağıran önceden güvenli açılmış yerel regular handle, beklenen source/snapshot,
export byte SHA-256 ve32-byte özel operatör HMAC anahtarı sağlar.
Yol/URL/SQL/DB/SSH/vendor API yoktur. İzin/no-follow/anahtar custody caller sorumluluğudur.
Kapalı genel şema export sözleşmesi decoded duplicate-key kontrolü için mevcut StrictJson kullanır.
Ham isimler HMAC-SHA256 ile kind+parent+source+snapshot scope'unda opak kimliklere çevrilir;
mapping/anahtar saklanmaz, çıktıya çıkmaz. Metadata semantiğini yalnız mevcut P2 Validator doğrular.
Toplanmayan version/timezone/password/custom fields/files UNKNOWN; boş custom-field placeholder
P2'nin syntax gereğidir, gerçek sıfır alan iddiası değildir; P3 coverage çıktısı birlikte kullanılmalıdır.

Yeni suite önce; P2/F6-A/B/C ve hardening hedefli Docker PHP8.3/8.4, PHPStan/lint/pattern/docs.
Kaynak before/after SHA-256, mevcut blob/migration/user dosyası korunumu, sanitize kanıtlar.
Başarısız koşular saklanır. FULL/Actions/Chromium yok; dependency audit güncelliği iddia edilmez.
Rollback yalnız yeni P3 ekleri ve iki suite kaydı; migration yok.
Başarı sonrası exact remote HEAD yeniden kontrolü, tek [skip ci] commit, fast-forward.
G-O03 OPEN; source/operator UNVERIFIED, effects/cutover false, gerçek gate BLOCKED.
