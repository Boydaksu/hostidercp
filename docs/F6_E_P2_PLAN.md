# F6-E-P2 — offline WHMCS metadata validator planı

29 Ekim 2026. DEVELOPMENT AUTHORIZED; bağımsız inceleme bekler.
Base 06de312796f19485aabb830f6e2cf531238f9975; tree 50a27f2ca35d6ac97976c44b2a72c0fc0adae87d.
OPS-004 / G-O03 hazırlığı; F6-A/B/C yalnız metadata sözleşmesi uyumluluk sınırı.

Kapsam: yeni Migration/Discovery StrictJson ve Validator; sentetik fixture ve suite;
yalnız iki mevcut registry/FULL liste kaydı. Mevcut güvenlik/P0/P1/R2 sınıfları değişmez.
Contract v1 sabit alanlar, opak source/snapshot ve schema/table/column/file kimlikleri,
sınırlı enum/fact metadata'sı; isim, satır, parola/hash, müşteri verisi ve yol yoktur.
Her kategori aynı source/snapshot'a bağlı; schema ve tam içerik canonical digest ile bağlanır.
Native JSON grammar/Unicode/depth + ayrı decoded-key duplicate kontrolü; byte/string/node/list limitleri.
Byte girişi veya güvenilir çağıranın açtığı bounded regular file handle; pathname/URL kabul edilmez.
Hash eşliği kaynak doğruluğu, yetkili operatör veya attestation değildir.

Test: yeni pozitif/negatif suite önce, sonra F6-A contract/visibility, F6-B import,
F6-C reconciliation ve hardening; pinned ücretsiz Docker PHP8.3/8.4, ephemeral DB/internal ağ.
PHPStan5/lint/pattern/docs; before/after SHA-256 ve mevcut blob korunumu.
Başarısız koşular saklanır. FULL/Chromium/Actions ve güncel dependency audit iddia edilmez.
Migration yok. Rollback yalnız yeni P2 dosyaları ve iki test kaydını geri alma; kullanıcı dosyası korunur.
Başarılı kontrollerden sonra tek [skip ci] commit, exact parent ve fast-forward; yeni HEAD varsa STOP.
G-O03 OPEN, gerçek gate BLOCKED, effects/cutover false; gerçek WHMCS erişimi veya canlı operasyon yok.
