# 02 — Ürün Gereksinimleri (PRD)

## Paydaşlar ve aktörler
- **Müşteri**: bireysel/kurumsal hesabın fatura sorumlusu; hesabın para birimi ve cüzdanı vardır.
- **Alt kullanıcı**: bir veya birden çok müşteri hesabına belirli izinlerle bağlı gerçek kişi; fatura/ödeme/destek/hizmet alanları ayrışır.
- **Hostider personeli**: görev kapsamı ve departman yetkili, auditable; 2–5 personel ilk ölçek.
- **Süper yönetici**: ayarlar, güvenlik, geri alma, riskli işlemler.
- **Otomasyon**: sistem aktörü; işlemleri orijinal tetikleyiciye `causation_id` ile bağlar.
- **Sağlayıcı**: cPanel, domain registrarı, Voyar veya ödeme kaynağı; tekil harici işlem ve kaynak id'leri.

## P0 yayın engelleyici kapsam
- FR-ID: kayıt, giriş, parola sıfırlama, 2FA politika, yetki, alt kullanıcı, personel oturumları, şüpheli giriş, auditable impersonation.
- FR-CATALOG: ürün/varyant/opsiyon/addon, bağımsız fiyat döngüleri, döviz kuru ve vergiler, promosyon, müşteri hesap parası checkout.
- FR-CHECKOUT: 15 dk teklif, ödeme talebi snapshot, kısmi/karma ödeme, ödeme mutabakatı.
- FR-PAY: PayTR iFrame/token/callback, manüel havale onayı, cüzdan yükleme/harcama/iadeler; idempotency.
- FR-BILL: proforma/ödeme talebi, masraf/kasa, resmî PDF belgesi yükleme, fatura geçmişi, para birimi ve yasal metaveri.
- FR-SERVICE: ürünle ilişkilendirilmiş hizmet yaşam döngüsü, aktivasyon onayı, sağlayıcı kaynak eşleşmesi, planlı yenileme, gecikme/suspend/terminate.
- FR-CPANEL: birden fazla WHM, hesap oluştur/askıya al/aç/paket değiştir/sonlandır, dış kaynak senkronizasyonu, kapasite+sağlık+ağırlık atama.
- FR-DOMAIN: uzantı bazında registrar, sorgu/kayıt/yenileme/transfer, nameserver, EPP/kilit (TLD özelliklerine göre), kayıt sonrası durumu doğrulama.
- FR-VOYAR: katalog, hazır/özel sunucu quote, oluştur, durumu göster, güç yönetimi, OS reinstall, yenileme, iptal; hesap/capability doğrulaması şart.
- FR-SUPPORT: panel ve e-posta ticket, mesaj/ek/dahili not, SLA, departman takvimleri, personel atama, rule engine, incident, CSAT, raporlama, çakışma engeli.
- FR-NOTIFY: bildirim şablonları, e-posta ve panel bildirimleri, kuyruk, şablon versiyonu, hata/retry.
- FR-OPS: staging-prod izolasyonu, installer, backup, updater, izleme, maintenance mode, WHMCS aktarımı.

## P1 (V1'i tamamlayan uygulama içi kalite)
- Müşteri ve hizmet zaman akışı, güvenli dosyalar, sekmeli paneller, güçlü arama ve filtreler.
- İşlem loglarında correlation/causation ID; provider request/response maskelemesi.
- Müşteri hizmet transferinde bildirim, tek kullanımlık süreli onay ve borç denetimi.
- Yenileme onay uyarıları, maliyet marj raporları, ödeme defteri mutabakatı.

## Kesin kapsam dışı (V2)
- iyzico, kayıtlı kart/tekrarlayan kart tahsilatı, Plesk, Proxmox; genel dağıtım/lisanslama, eklenti marketplace.
- WhatsApp/SMS/canlı sohbet destek kanalı; API dışı web scraping işlerini V1 ile karıştırma.
- Harici e-fatura/e-arşiv oluşturma entegrasyonu; kullanıcının manuel resmî PDF akışı korunur.

## Kullanıcı hikayeleri
- **Müşteri:** USD temel fiyatlı ürünü TRY hesapta güncel kurdan görürüm; 15 dk teklifi onaylar, kesinleşmiş TL ödeme talebini PayTR + cüzdanla kapatırım.
- **Müşteri:** Destek talebime hizmet bağlarım; gelen personel cevabını panelden ve e-postadan görürüm.
- **Müşteri:** Bir hizmeti başka hesaba devrederim; alıcı süreli token'la kabul eder; borç varsa transfer engellenir.
- **Müşteri:** Otomatik yenilemeyi hizmet bazında açar, cüzdan yeterliyse yenilemenin gerçekleştiğini görürüm.
- **Operatör:** cPanel / Voyar işleminde sonucu belirsiz işi inceleme ekranından görürüm, tekrar denemeden önce dış kaynağı sorgularım.
- **Operatör:** Tek support ekranında ticket, SLA, müşteri geçmişi ve hizmet durumuna ulaşırım.
- **Finans:** PayTR tahsilatına ait sipariş, cüzdan hareketi, proforma ve resmî PDF birbirine bağlıdır; kasa gelirini iki kez yazmam.
- **Yönetici:** Güncel Voyar maliyetlerinden önerilen fiyatları gözden geçirip yeni sürümü onaylarım; mevcut ödenmiş döneme dokunmam.
- **Yönetici:** Güncellemeden önce geri yüklenebilir yedek ve staging doğrulaması görürüm.

## Kabul koşulu önceliği
Finansal tutarsızlık, yetki açığı, tekrar provisioning, veri kaybı veya canlı ortamdan test sağlayıcısına yanlış çağrı = **release blocker**. Görsel kozmetik ve ileri rapor filtreleri release blocker değildir; destek sisteminin çekirdek fonksiyonları blocker'dır.
