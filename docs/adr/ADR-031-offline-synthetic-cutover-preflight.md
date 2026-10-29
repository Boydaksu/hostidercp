# ADR-031 — Offline synthetic cutover preflight composition

28 Ekim 2026. STATUS: IMPLEMENTATION DECISION — SYNTHETIC P0 ONLY; REVIEW PENDING.

[F6-E-P0 planı](../F6_E_P0_PLAN.md) OPS-004 için offline sentetik gelişim yetkisini kaydeder.
Mevcut F6-C Inspector ve R2 RecoveryInspector doğrudan yeniden çağrılır; JSON/Report PASS
bayraklarını doğrulama yetkisi sayan yeni importer/reconciliation motoru oluşturulmaz.
Preflight iki source okumasını restored-target immutable root ve pinned context ile bağlar.
Checkpoint, money/currency, provenance, ownership/privacy kontrolleri mevcut Inspector'a aittir.
Yeni bileşen yalnız sonuç/pin bağlama, kısa lease/purpose, sentetik aktör/callback/delta engelleri
ve sabit gerçek GATE envanteri ekler. Fixture yalnız mevcut allowlist kaynaklarıdır.

SYNTHETIC_PREFLIGHT_COMPLETE, simülasyon kontrollerinin bitmesidir; gerçek GATE'ler her durumda
OPEN/BLOCKED kalır. Yerel named Simulation veya JSON flag'i gerçek kanıt/cutover izni değildir.
Yeni endpoint, native yetki delegasyonu, paid/provider/mail/WHMCS adapter veya kalıcı worker yoktur.
Report yalnız allowlist aggregate kod/hash içerir; ham evidence/payload/credential'lar dışarı çıkmaz.
Kapalı constructor/final typed reader'lar generic Report rehash/JSON injection yolunu dışlar.
App process/DB setup/root güveni ve global cross-DB atomiklik bu dar kapsamın dışında kalır.
Canlı gate evidence enrollment, instant fence/actor ownership/callback routing ve gerçek delta
mekanizmaları hazır değildir; bu yetenekler BLOCKED, bu geliştirmede uydurulmaz.

ADR-023'ün suite/FULL envanter tutarlılığı, ADR-026/028/029 güven sınırları korunur.
ADR-030 ve R2 teknik kabulü yalnız eski immutable SHA/evidence içindir; yeni aday için değildir.
Migration yok. Rollback yalnız yeni preflight/test/registration ekleri, kullanıcı değişiklikleri
ve kabul edilmiş immutable kaynaklar korunarak ayrıca incelenir.
