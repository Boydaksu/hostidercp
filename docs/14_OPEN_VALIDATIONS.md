# 14 — Açık teknik doğrulamalar ve varsayım riski

İş kuralları yedi turda karara bağlandı. Ancak aşağıdaki **teknik kapılar** canlı API/sözleşme/ortam doğrulaması gerektirir; iş kapsamındaki keyfi yeni sorular değildir.

| ID | Konu | Doğrulanacak kanıt | Canlıya etkisi |
|---|---|---|---|
| G-V01 | Voyar `ondemand` ücretlendirme | Gerçek test VM, saatlik tick/billing report, stop vs terminate etkisi, minimum ücret | Cloud otomatik satış blocker |
| G-V02 | Voyar account auth | 6s IP-bound token 2FA'lı headless cron yenilemesi, güvenilir cihaz desteği | Cloud cron blocker |
| G-V03 | Voyar create bilinmez durum | Task/service list ve external id çözümü, duplicate create guard | Cloud otomatik create blocker |
| G-V04 | Voyar custom upgrade | SDK özel paket kısıtları ve downgrade/upgrade gerçek support | Cloud plan değişim UI |
| G-P01 | PayTR merchant currency | TRY/USD hesapta iFrame, callback currency, commission, refunds, wallet top-up | Para birimli ödeme blocker |
| G-P02 | PayTR durum mutabakatı | Callback hash, tekrar bildirim, iade idempotency ve tutar alanları | Finans blocker |
| G-C01 | WHM API credential ACL | Token least privilege, WHM/cPanel versiyonları ve health metrics | Hosting create blocker |
| G-R01 | ResellerClub hesap/API | Güncel/legacy API sürümü, TLD contact EPP ve order status | Domain create blocker |
| G-D01 | DomainNameAPI OT&E | Gerçek method/field mapping, transfer/kayıt/renew price ve statuses | Domain create blocker |
| G-X01 | Döviz kuru kaynağı | Kaynak, ticari kullanım hakkı, güncellik, fallback, spread & rounding | FX checkout blocker |
| G-X02 | Resmî e-belge | Mali müşavir teyidi: proforma vs resmî PDF, vergi/kredi/iade süreçleri | Finans compliance gate |
| G-O01 | Hosting cron/runtime | 1dk scheduler, uzun request timeout, concurrent CLI, ZIP/imagick/SMTP, disk/memory | Release blocker |
| G-O02 | PITR | MariaDB binlog veya saatlik DB backup erişimi; RPO provası | RPO beyanı |
| G-O03 | WHMCS şeması ve hash | Gerçek sürüm DB, dosyalar, parola algo ve attachment klasörleri | Göç yöntemi blocker |
| G-O04 | Veri saklama ve PDF erişimi | KVKK saklama, silme, destek eki erişimi ve yedek retention | Compliance |
| G-T01 | Hizmet devri edge | Borç sınırı (açık tahsilat talebi vs kalan borç), yeni sahibin başlangıç hakkı | Transfer release blocker |

## Beklenen kanıt formatı
- Test ID / tarih / ortam (test vs production) / provider plan-ID / request type (secret/PII maskeli) / normalized response / masraf/period / reviewer / PASS-FAIL.
- Hiçbir canlı provider credential paket içine konmaz.
- Doğrulama başarısızsa davranış: ilgili hizmet türünü manuel onaylı veya satışa kapalı tut, çekirdeğin finans kayıtlarını koru.

## Harici referanslar
- Voyar PHP SDK: Kullanıcının sağladığı `voyar-api-client` ZIP, `README.md`, `docs/services.md`, `docs/resources.md`, `docs/auth.md`.
- Voyar web: https://wiki.voyar.net/api/ (web metin çıkarımında içerik dönmedi; yanlış kesinlik üretilmemeli).
- PayTR: https://dev.paytr.com/iframe-api/iframe-api-1-adim ve https://dev.paytr.com/iframe-api/iframe-api-2-adim .
- cPanel: https://api.docs.cpanel.net/whm/tokens ve https://api.docs.cpanel.net/ .
- ResellerClub: https://developer.resellerclub.com/ .
- DomainNameAPI: https://www.domainnameapi.com/tr/domain-name-api-test-platformu-bilgileri .
