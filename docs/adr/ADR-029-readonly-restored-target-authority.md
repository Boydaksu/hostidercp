# ADR-029 — ayrı read-only restored-target authority

28 Ekim2026, [R2 planı](../F6_D_R2_PLAN.md).
R1 signed capsule backup/source'a bağlıdır, yeni target DB'nin actual içeriğini
tek başına kanıtlamaz. Original Sandbox'ı taklit etmek veya eski HMAC/marker rewrite
yerine private verified RecoveryContext + ayrı operator-pinned target policy +
SELECT-only RecoveryInspector kullanılır. New target identity eski source identity
ile karıştırılmaz; evidence fingerprint ikisini ve actual DB kökünü bağlar.

Old HMAC authenticity trusted enrollment üzerinden taşınır, cold aşamada tekrar
hesaplanmaz. Native runtime authority/one-time activation verilmez. Hiçbir accepted
F6-A/B/C/D/R1 source veya production migration değişmez.

Strict base-table definitions/read-only privileges, trigger/routine execution yolunu
kapatır. SELECT-only metadata inventory kısıtı açık NOT_VERIFIED'dır; views ret,
procedure CALL/DML yok. Bu fiziksel schema executable-object audit'i değildir.
Durable key custody/revocation/config anti-rollback ve runtime activation ayrı
contract/owner approval konusudur. Read-only reconciliation kapsamındaki başarı,
BLOCKED_IDENTITY_BOUND'ın yalnız bu alt kapsamını karşılar; genel runtime engeli sürer.
