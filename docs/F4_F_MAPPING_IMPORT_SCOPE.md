# Mapping / import CRUD — açık kapsam istisnası

25 Ekim 2026: proje sahibi ayrı işe ertelemeyi açıkça onayladı. Bu fonksiyonlar uygulanmadı;
F4-F DONE veya canlı kabul kapsamına eklenmez. F5/F6'ya otomatik geçiş izni değildir.

Ayrıca yetkilendirilecek test/mock işinde önce salt okunur mapping/resource envanteri ve
explicit synthetic account/catalog/price binding planlanmalıdır. Native staff MFA/fresh
step-up, ayrı least-privilege mapping/import izinleri, exact customer/service scope,
impersonation ret, CSRF/idempotency ve state/payload/price/resource-bound one-use confirmation
gerekir. Mevcut application service'ler kullanılmalı; provider/finance tablolarına controller
yazısı veya yeni identity/queue/dispatch motoru eklenmemelidir.

Mapping değişikliği eski immutable entitlement/order/price'ı değiştiremez. Imported VM yalnız
aynı mock account/resource kanıtı ve mevcut service ownership ile bağlanabilir; yeni CREATE
üretilemez. Unknown/pending resource bağları manuel incelemede kalmalıdır. Silme/yeniden bağlama
fiziksel terminate sayılmaz; geçmiş provenance korunmalıdır. Gerçek vendor mapper ve credential
işlemleri GATE kanıtı olmadan kapalı kalır.

Kabul: native HTTP permission/IDOR/revoke/stale/payload/duplicate/iki worker yarışı; exact
price/account/resource mismatch; imported VM ikinci CREATE yasağı; eski entitlement ve finans
hash eşliği; additive migration/upgrade/replay/rollback ve PHP8.3/8.4 tam CI. Şema ihtiyacı bu
ayrı kapsam incelenmeden belirlenmez. Uygulama başlatılmadı; bağımsız kapsam onayı gerekir.
