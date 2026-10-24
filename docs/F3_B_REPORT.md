# F3-B canonical claim ve lease

FR-SERVICE, PROV-003/004, OPS-005: 025 yeni operation/provider-operation/append-only attempt tabloları. CREATE unique item/kind/generation ve service pointer, şifreli request/HMAC, aynı transaction outbox. Test-only canonical order/bill/item discovery queue kaybından bağımsız; hosting dışı ürün atlanır. Dispatch öncesi ödeme/refund/cancel tekrar kontrol edilir. Order→service→operation kilit sırası, tek lease, expiry UNKNOWN; eski lease yazamaz veya yeniden CREATE yapamaz. Bounded cron discover/expire komutları eklendi. Provider worker sonraki C fazında bağlanır.

Gerçek MariaDB + dört bağımsız PHP süreci: aynı order discovery 3 unit için tam 3 service/operation; dört claim'de tek fence; queue kaybı, source cancel, manual hold, expired lease, immutable attempts. 4/4 test, A 4/4, 187 PHP dosyası/0 ihlal, PHPStan başarılı. İlk koşuda fixture cleanup allowlist yeni dar service-test prefix'ini tanımıyordu; eklendi. Tekrarlı koşuda global expiry sayısı eski test işlerini de kapsadı; test hedef operation ile bağımsızlaştırıldı. Başarısız koşular gizlenmedi.

001–024 değiştirilmedi. Geri dönüş tutarlı snapshot veya forward-fix. Canlı provider/production yok; GATE değişmedi. Tüm F3 kabulü değildir.
