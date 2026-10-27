# ADR-025 — F6-B fiziksel DB izolasyonu ve sentetik facts

27 Ekim 2026. IMPLEMENTATION / bağımsız kabul bekler. D2/D4/D5/D6 ve
FR-OPS/SEC-004/OPS-001/005; [F6_B_PLAN](../F6_B_PLAN.md) kapsamı normatiftir.

Native tabloları test import'u için kullanmak, paid/provisioning/notification
consumer'larını tetikleyebilir. Bu faz ayrı geçici MariaDB schema'sında typed
synthetic facts ve F6-A MappingEvidence saklar; native Identity/Finance/Services/
Support adapter'ı değildir. Native Database/Migrator/CanonicalJson ve Money kuralları
tekrar kullanılır; yeni genel job/auth/ledger engine yoktur.

Schema adı veya SYNTHETIC etiketi authority değildir. Sabit fixture byte digest'i,
kod allowlist'i, iki environment flag, loopback-only bağlantı, gerçek CURRENT_USER/
DATABASE/role/grant denetimi, süreli secret-bound marker ve exact tablo seti birlikte
gerekir. Runtime SELECT/INSERT ve yalnız checkpoint UPDATE haklarına sahiptir;
başka schema/DDL/native tablo hakları kabul edilmez. Grant/credential loglanmaz.

Batch global sandbox mutex'i ve native transaction, source/target unique claim,
provenance/history ve HMAC checkpoint'i atomik tutar. Crash/connection-loss fixture'ı
yalnız test modülündeki allowlisted fault enum'dur; caller callback/SQL/URL almaz.
Gerçek dış etki yolu yoktur. Named lock genel Queue/lease yerine geçmez.

F6-A review sonuçları load sırasında bypass edilmez: refund dahil review-only
facts otomatik APPLIED yapılmaz. Bu finansal kapsam daraltması değil, kabul edilmiş
F6-A güvenlik kararını korumadır; gerçek mutabakat ve policy F6-C açık bağımlılığıdır.
Private archive facts client projection/attachment authorization değildir.

Teknik DDL yalnız tests fixture dizininde native Migrator ile kurulur;001–054 ve
production migration dizini korunur. Üretime uygulama yetkisi verilmez. Rollback
yalnız test schema teardown + code revert; immutable source evidence yeniden yazılmaz.
