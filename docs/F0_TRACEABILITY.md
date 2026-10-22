# F0 gereksinim ve kabul izi

F0.1 ilave iz: F01-AUTH → Principal/AccessGrant/Authorization + hardening-unit (FR-ID, R4-01..07, SEC-004); F01-JSON/QUEUE → CanonicalJson/Queue + integration (OPS-005); F01-SESSION → Session/Snapshot/Guard + process ve HTTP concurrency (R4-07); F01-AUDIT → AuditEvent/Audit + transaction testleri; F01-PROVIDER → EffectPolicy/ProviderResult/ResultAcceptance + 5 adapter production reddi (OPS-001); F01-RETENTION → migration 002/Retention/RateLimiter + integration (FR-OPS); PHPStan → phpstan.neon/tools/analysis + CI. Ayrıntı: F0_1_REPORT.md ve ADR-002.

Mevcut R/FR kimlikleri iş kararlarıdır. Aşağıdaki F0-* kimlikleri kickoff teslim bileşenlerini izlemek için eklenmiştir; yeni ticari kural değildir. Uygulama temeli ile tamamlanmış ürün özellikleri birbirinden ayrılır.

| F0 | Kaynak | Dosyalar | Kanıt |
|---|---|---|---|
| F0-A lifecycle/config/DI/router/envelope | R1-07, FR-OPS, docs/03,09 | bootstrap; Core/Config,Container,Http | unit router/JSON/errors; HTTP 200/404/405/503 |
| F0-B migrations/PDO/outbox | R6-03, FR-OPS, OPS-003 | Database; migrations/001; Queue/Outbox | MariaDB apply/replay/checksum/lock/dirty; business+outbox rollback |
| F0-B queue/idempotency | R3-07, R3-10, R7-07, OPS-005, PROV-001 | Queue/Queue,Worker; Idempotency | iki ayrı process claim/idempotency; UNKNOWN/fencing/backoff/dead letter |
| F0-C auth/ACL/session sözleşmesi | R4-01..07, FR-ID, SEC-001/002/004 | Security/Principal,Authenticator,Authorization,Session,Csrf | unit deny/MFA/step-up/scope/department; session expiry/rotation/cookies |
| F0-C crypto/audit/validation/rate | docs/10, FR-OPS | Security/Crypto,Audit,RateLimiter; Validation; SafeLogger | AEAD tamper/context; audit actor/effective; rate bound; log leak testi |
| F0-C staging fail-closed | R6-01, OPS-001 | Config; Security/EffectPolicy | test/staging/production flags; tüm canlı efektler engelli |
| F0-D 5 mock provider | FR-PAY/CPANEL/DOMAIN/VOYAR, docs/07 | Providers/Contract ve Mock | her action × 4 sonuç; schema anahtar/enum kontrolü; mock replay; gerçek callback reddi |
| F0-E lint/CI | AGENTS test ve kanıt koşulu | tools/check; .github/workflows/f0.yml; tests | lint/token/mimari + yerel test çıktısı; uzak CI ayrıca izlenir |

SEC-001 tam TOTP protokolü, SEC-002 tam impersonation lifecycle ve SEC-004 gerçek entity repository erişimi bu fazda tamamlanmış değildir; yalnız altyapı policy test edilmiştir. FIN-001..010, diğer PROV/DOMAIN/SVC/SUP, backup/restore/cutover kabul testleri sonraki fazlarda kalır. HTTP auth Bearer istisnası yoktur; authenticated API ve callback route henüz yoktur.

## Modül/veri sahipliği
Core yalnız altyapı tablolarının sahibidir. Provider modülü Core'a ve kendi Contract namespace'ine bağımlıdır; Core Provider'a bağımlı olamaz (statik kontrol). Gelecek Identity/Catalog/Billing/Payments/Services/Support/Notifications/Operations modülleri yalnız kendi repository'si üzerinden yazar; çapraz modül uygulama servisi veya domain event/outbox kullanır. Yeni modül eklenirken allowlist mimari kontrolüne sahibiyle birlikte eklenmeli. F0'da henüz oluşmamış modüllerin tablo erişimi test edildi diye beyan edilmez.
