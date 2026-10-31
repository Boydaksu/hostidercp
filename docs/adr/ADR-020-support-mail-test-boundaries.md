# ADR-020 — F5-C sender evidence, canonical events ve quarantine

Test/mock teknik kararı; bağımsız denetim bekler. Plan F5_C_PLAN.md normatif sınırları
belirtir. Gerçek SMTP/IMAP veya DKIM/SPF doğrulaması varmış gibi davranılmaz.
Native customer session ile üretilmiş exact-raw proof test sender authentication
sınırıdır; verified address/current membership, ticket ownership ve scoped reply
token/references birbirinin yerine geçmez. Native current session revocation bütün
proof/review/download işlemlerini keser. Header/subject tek başına authority değildir.

Canonical Support application seam kendi eski field/auth/create/reply/event/SLA
yolunu kullanır; nested idempotency transaction açılmaz. Mail Message-ID/variant row
kilidi outer idempotency'dir. Immutable mail provenance native support event'e
bağlanır; mevcut TEST_PANEL canonical executor alanları ve F5-A exact receipt aynı
kalır. Mail origin ayrı kayıtta TEST_ADAPTER olarak açıkça saklanır.

Mail outbound ayrı support.mail.ready event/job/receipt ile native Outbox üstündedir;
eski support.changed consumer değiştirilmez. Mock transport stable delivery UUID
ile yalnız DB kabulü üretir; network call yoktur. Accepted ≠ delivered; bilinmeyen
gerçek transport retry güvenliği canlı tasarım/GATE gerektirir.

PDF-only, arşivsiz, aktif içeriksiz subset kaynak bütçesini sınırlar. Dosya encrypted,
webroot dışı; karantina varsayılan. Exact synthetic test fixture için TEST_CLEAN
gerçek AV değildir. Authenticated short token current ACL'yi bypass etmez. Retention
metadata/history silmeden content purge yapar; hold ve audit korunur. Dosya yazımı
DB commit'inden önce olduğundan rollback orphan'ı yetkisiz/indirilemez; bounded
orphan cleanup yalnız DB'de referansı olmayan encrypted UUID dosyalarına uygulanır.
