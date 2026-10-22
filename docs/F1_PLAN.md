# F1 planı — 22 Ekim 2026

Yetki: kullanıcı F0.1'i kabul etti ve F1-A..F için açık geliştirme izni verdi. Başlangıç main c1645a9; canlı sağlayıcılar ve F2 kapsam dışı. Gereksinimler FR-ID, R4-01..07, R1-07, R6-01, SEC-001..004, OPS-001/003/005.

| Alt faz | Dosya/migration | Test ve kabul |
|---|---|---|
| A | 003 identity şeması, Identity repository/policies, JSON object tür koruması | ilişkiler, scope, eski 64 test; PHPStan/CI |
| B | parola/tek kullanımlık token, auth/session registry, encrypted mail outbox | gerçek login/reset/verify, enumeration/rate, revoke yarışı |
| C | TOTP/recovery/trusted device | replay/tek kullanım/step-up ve MFA zorunluluğu |
| D | kalıcı role/grant, authoritative resource ownership, impersonation | deny-by-default, müşteri/departman/hizmet, kritik commit/revoke yarışı |
| E | scoped/expiring/revocable API credentials | hash-only, güncel owner izinleriyle kesişim ve audit |
| F | ayrı admin/client JSON API ve HTML/CSS/fetch paneller | gerçek hesaplı HTTP ve responsive görsel inceleme |

Her grup yerel test, PHPStan ve PHP 8.3/8.4 MariaDB CI ardından GitHub'a gönderilir. Eski migration'lar değişmez. 003 ve gerekiyorsa sonraki expand migration'lar ile yeni Identity tabloları oluşturulur. Kalıcı auth ve business write aynı transaction'da user→session lock sırasıyla yetki/version doğrular; revoker aynı user kilidini alır. Session file kilidi DB/network sırasında tutulmaz.

Rollback: cron/traffic durdur → doğrulanmış DB+dosya snapshot ve önceki kod birlikte restore veya forward-fix; hiçbir otomatik destructive down yok. İlk admin yalnız CLI bootstrap, sabit parola yok, secret stdin'den alınır. Yapay demo isolated test DB'de üretilir. Canlı mail kapalı; mail içeriği şifreli notification kaydı, outbox/job yalnız opaque ID taşır. Nihai F1_REPORT ve kanıtlar, açık GATE'ler ve F2 hazırlık değerlendirmesiyle durulur.
