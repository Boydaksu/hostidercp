# F0.1 hardening planı — 22 Ekim 2026

Başlangıç: incelenen 8683bea; çalışma HEAD'i 3a08b82 yalnız sonraki teslim belgelerini içerir. Kapsam FR-ID/FR-OPS/FR-NOTIFY, R4-01..07, R6-01, R3-07/R3-10; SEC-001/002/004, OPS-001/003/005 ve PROV-001'in altyapı düzeyi. F1 ve canlı sağlayıcılar kapsam dışıdır.

1. Yetki: staff bayrağı kapsam atlamaz; işlem başına explicit grant + customer/service/department birleşik AND kontrolü, impersonation için ayrı daraltma. Queue: sıralamadan bağımsız, tür/list/object ayrımını koruyan kanonik JSON. Dosyalar Principal/Authorization/CanonicalJson/Idempotency/Queue ve regresyon testleri.
2. Session: kısa kilitli snapshot/mutation API, sürüm kontrollü yazma; rotation/logout eski SID için tombstone, stale request tekrar oturum yaratamaz. Audit allowlist + actor/effective/correlation doğrulama ve transaction kanıtı. Mock production yasağı ve sonuç provenance sözleşmesi. F1 auth/revocation registry akışları oluşturulmaz.
3. Operasyon: rate-limit expiry ve sınırlı temizleme; tamamlanan job payload'ının güvenli redaksiyonu, dedupe tombstone/attempts korunur; UNKNOWN/FAILED silinmez. Dev-only PHPStan/CI. Son rapor, kurulum, izlenebilirlik ve gerçek kanıtlar.

Migration: eski migration checksum'u değiştirilmez. Gerekli expiry/payload-retention alanları ayrı 002 migration ile expand edilir; mevcut rate bucket'ları güvenli biçimde tutulur. İş/audit/idempotency kayıtları silinmez. Migration/readiness/concurrency testleri 001'e hardcode bağlanmadan korunur.

Test: önce mevcut 41 test baseline; aynı test adları/senaryoları korunur, yeni auth/queue/session multi-process/audit/production mock/cleanup regresyonları eklenir. Yerel PHP 8.4 + gerçek MariaDB 10.11; uzak CI PHP 8.3/8.4. PHPStan proje runtime'ına dahil edilmez.

Geri dönüş: cron ve trafik durdurulur; eski kod + doğrulanmış DB snapshot birlikte restore veya kontrollü forward-fix. Yeni kayıtlar oluştuysa mutabakat gerekir. Otomatik destructive downgrade yok. Her anlamlı grup test kanıtıyla GitHub'a gönderilir. Canlı GATE'ler OPEN kalır.
