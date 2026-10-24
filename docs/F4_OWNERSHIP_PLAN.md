# F4 sahiplik ve ödeme sınırı — ara denetim planı

Kapsam SEC-004, G-T01, FR-DOMAIN. Önce genel Services transferi start/accept ortak application lock'ında yalnız HOSTING kabul eder. DOMAIN/CLOUD allow_transfer=true olsa bile reddedilir. UI kontrolüne güvenilmez; modüller arası atomik devir tamamlanmadan sınır kaldırılmaz. 039 customer trigger'ı ve 001–040 korunur; bu güvenlik düzeltmesi migration gerektirmez.

Domain attach: canonical order → immutable item → service → billing-state kilitleri altında müşteri/product/item/unit, test provenance, tam ödeme, refund/cancel, immutable snapshot ve QUEUED/AUTO koşulları yeniden doğrulanır. Attach/history/audit/outbox aynı transaction içinde kalır. Gelecek dispatch/claim/result kabulü aynı uygunluğu tekrar doğrulamalı; renewal ayrı yeni bill/period kaynağı kullanmalıdır.

Dosyalar Services/Transfers, Services/RegistrationEligibility, Domains/Registry ve yeni domain-boundaries testleri. Test gerçek izole MariaDB'de mevcut suite ve yeni source/transfer retleri; geri dönüş kod snapshot/forward-fix, DDL yok. Atomik module ownership, iki gerçek sahip onayı, debt/in-flight/contact/registrar kısıtları ve geçmiş contact dönemleri henüz uygulanmış sayılmaz. G-T01/G-R01/G-D01 ve bütün Cloud GATE'leri OPEN. Domain motoru ve Cloud/panel kapsamı düşürülmez.
