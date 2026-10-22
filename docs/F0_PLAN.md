# F0 uygulama planı — 22 Ekim 2026

Durum: çalışılıyor; DONE veya üretim onayı değildir.

## Kapsam ve gereksinimler
Güncel kickoff A–E talimatları F0 kapsamıdır. FR-OPS, FR-ID (sözleşme), FR-NOTIFY (outbox), FR-PAY/FR-SERVICE (idempotency temeli), FR-CPANEL/FR-DOMAIN/FR-VOYAR (mock sözleşme); R1-07, R3-07, R3-10, R4-01..07, R6-01, R6-03, R7-07. OPS-001/003/005, SEC-001/002/004 ve PROV-001 yalnız çekirdek seviyesinde doğrulanır; tüm ürün kabul testlerinin geçtiği iddia edilmez.

## Dosya değişiklikleri
- `src/Core`: configuration, DI, HTTP, PDO, migration, queue/outbox/idempotency, security.
- `src/Providers`: beş sağlayıcı için ağsız internal contracts/mock.
- `bootstrap/`, `public/`, `bin/`, `migrations/`, `tests/`, `tools/`, `.github/workflows/`.
- `docs/adr/`, kurulum, kanıt, izlenebilirlik ve açık GATE kartları.

## Migration
Blueprint kopyalanmaz. Yalnız F0 altyapı tabloları: outbox/jobs/attempts/dead letters/inbox, audit, idempotency ve rate limit. Apply-once checksum, advisory lock, DIRTY kaydı; MariaDB DDL implicit commit sebebiyle atomik downgrade sözü yok.

## Test
PHP unit/contract/HTTP/security; gerçek MariaDB integration, migration tekrar/checksum/yarım kalma, rollback/outbox, iki worker claim/fencing, UNKNOWN ve idempotency. CI PHP 8.3/8.4 + MariaDB 10.11. Lint ve mimari/statik kontroller. Çalıştırılamayan doğrulamalar açıkça kaydedilir.

## Geri dönüş
Cron'u durdur, bakım moduna al; deployment öncesi doğrulanmış snapshot ve önceki kodu birlikte geri yükle. F0 migration veri içeren tablolara otomatik DROP uygulamaz. Yeni olay/iş varsa önce reconciliation; yalnız kodu geri almak yeterli değildir.

## GitHub
Kullanıcı talebi: her tamamlanan anlamlı işlem grubu test/durum kanıtıyla Boydaksu/hostidercp deposuna commit edilir. Sırlar ve çalışma verisi gönderilmez. İlk repo boş; mevcut dosyalar korunur.
