# F3-C mock hosting ve pool sınırı

FR-HOSTING, OPS-001. 026 pool/server/mapping/reservation ve kalıcı sentetik provider dünya/call tabloları; 027 seçim hash'i ve reservation binding guard. APP_ENV=test dışında adapter/setup kapalı. Pool seçiminde enabled, health freshness, capacity, weight, create+inspect capability ve variant/options/addons seçiminin tam hash'i gerekir. Reservation aynı claim transaction'ında dondurulur. Credential AEAD, request HMAC/AEAD. Provider çağrısı açık application transaction içinde reddedilir.

Altı HostingProvider metodu kalıcı mock dünyada çalışır; aynı UUID farklı request ile kullanılamaz. SUCCESS sonrası ayrı inspect ile resource/service/server/create-operation/period bağları doğrulanmadan ACTIVE/VERIFIED yazılmaz. Timeout sonrası UNKNOWN ve kapasite korunur; kesin başarısızlıkta reservation bırakılır. Worker CLI bounded --once; canonical iş tablosundan devam eder. Gerçek WHM transport yok: environment ve G-C01 kapıları bayraklarla aşılamaz. TLS/ACL/vendor response/gerçek cron kanıtı üretilmiş sayılmaz.

5/5 MariaDB test: altı metod/replay, paid çoklu-unit aktive/immutable period/ledger sabit, timeout-after-create tek fiziksel hesap, capacity/stale health/capability retleri, definitive failure/canlı ret. PHPStan başarılı, 194 PHP dosyası/0 ihlal (027 sonrası sayı finalde tekrar ölçülür). Statik denetimde yalnız yazılan config alanı gerçek environment kontrolüne bağlandı. A/B dal CI: https://github.com/Boydaksu/hostidercp/actions/runs/39992484837 başarılı; C henüz bu koşuda yoktu.

Migration geçmişi korunur; geri dönüş tutarlı snapshot/forward-fix. Henüz D reconciliation, E lifecycle, F HTTP/UI ve final performans kabulü yoktur. GATE değişmedi; F4 yok.
