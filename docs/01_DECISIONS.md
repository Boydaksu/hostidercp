# 01 — Nihai karar defteri ve çelişki çözümü

Tüm maddeler kullanıcı onaylıdır; metinde `tasarım çıkarımı` diye belirtilen alt kurallar ise uygulama için önerilen teknik normalleştirmedir. Sonradan verilen karar, aynı konudaki önceki kararı değiştirir.

## D1 — Proje, çalışma ortamı, sınırlamalar
- Sadece Hostider kullanacak; dışarıya lisans/SaaS/multi-tenant satış yok.
- Native PHP 8.3+ ve MariaDB, paylaşımlı hosting üzerinde 1 dakikalık cron.
- API tabanlı, yenilemesiz UI; ayrı müşteri ve admin paneli; ana Hostider PHP sitesi API ile bağlanır.
- V1 sağlayıcıları: PayTR, ResellerClub, DomainNameAPI, cPanel/WHM, Voyar. V2: iyzico, kayıtlı kart, Plesk, Proxmox.

## D2 — Finans, ödeme ve vergi
- PayTR öncelikli; EFT/havale ve manuel onay; V1 müşteri kredisi/cüzdanı, kısmi+karma ödeme.
- Tam ödeme olmadan otomatik aktivasyon yok. Manuel kurulum kararı ürün bazında ayrıca bulunur.
- Resmi e-belge harici kesilip müşterinin belge detayına güvenli PDF yüklenir; uygulama ödeme talebi/proforma oluşturur.
- Birden çok para birimi; müşteri **hesap para biriminde** sipariş/ödemeye devam eder. Hesap USD ise USD, TRY ise TRY tahsil edilir (ödeme sağlayıcısının gerçek para birimi kabiliyeti GATE).
- Ürünün tanımlı temel parası USD olabilir; TRY fiyatı otomatik güncel kura çevrilir, özgün USD fiyatı değişmez.
- Her ürünün fiyatlama stratejisi ayrı seçilir: kurdan dönüşüm, sabit müşteri-parası fiyatı veya kaynak para biriminde ayrı döngü fiyatı. Aynı stratejinin vergi dahil/hariç kuralı ürün/kategori önceliklidir.
- Tek müşteri cüzdanı, **hesap parası** ile tutulur. Farklı para yatırıldığında o andaki kur+farkla dönüştürülür; iki tarafın özgün değerleri immutable kayda girer.
- Müşteri hesabının para birimi sadece yönetici onayıyla değiştirilir, açık işlemler uzlaştırılır, cüzdan dönüştürülür; eski ödeme talepleri ve faturalar değişmez.
- 15 dakika geçerli teklif; **ödeme talebi oluştuğunda tutar/kur/vergi sabitlenir.** Kısmi ödeme aynı ödeme talebi üzerinde kalır, fiyat tekrar yazılmaz.
- İadede orijinal tahsilat para birimi ve tutarı ile ürünün iade koşulları esas; cüzdana farklı para girişinde ayrı anlık dönüşüm kaydı.
- Satış fiyatları yeni maliyetle otomatik önerilir fakat onaysız yayınlanmaz. Mevcut abonelik fiyatı/koruma/bildirim politikası ürün veya hizmet bazında.
- Cüzdan harcamaları ve diğer ödeme yolları aynı ödeme talebine dağıtılabilir; çift tahsilat kayıtları engellenir.
- Tek kasa görünümü, kategorili gelir/gider, tekrarlayan gider, manuel işlemler; muhasebe defteri / ödeme ledger / müşteri yükümlülüğü verileri mantıken ayrıdır.

## D3 — Ürün, sipariş ve hizmet
- Ana ürün + varyant + seçenekler + bağımlı/bağımsız, tek seferlik/tekrarlayan eklentiler.
- Her döngünün fiyatı bağımsız olabilir: 30 gün, takvim ayı, özel süre; tanım ürün bazında.
- Siparişin otomatik/manüel aktivasyonu ürün/kategori politikasıyla belirlenebilir.
- Yenileme/askıya alma/silme politikası alan bazında global → kategori → ürün → hizmette izin verilen istisna; kural mirası NULL=inherit anlamıyla çalışır.
- Hizmet bazında cüzdanla otomatik yenileme müşteri tercihine bağlı.
- Uyumluluk varsa kalan süreye göre yükseltme farkı ve tahsilat sonrası uygulama; düşürme anında veya dönem sonunda; kredi politikası yapılandırılır.
- Promosyon: ürün, kategori, müşteri, süre, kullanım limiti, ilk/tekrar eden indirim ve birleştirme.
- Transfer: talep gönderen ve alıcı karşılıklı onaylar, **açık borçlu hizmet devredilemez**, bildirim+denetim+sahiplik tarihi korunur.
- Cloud: hazır/özel konfigürasyon, müşteri yalnız yönetici izinli OS/lokasyon/kaynak sınırlarında seçim yapar.
- Cloud: aylık/ürün döngüsüyle müşteri satış, sağlayıcıda hedef saatlik/fiilî maliyet, özel iptal/iade kuralı, hemen veya dönem sonu iptal.
- Gecikme: bildirim → grace → doğrulamalı kapatma; sağlayıcının gerçekten faturalamayı durdurduğu teyit edilmelidir.
- Domain: uzantı bazında birincil/alternatif registrar; yalnız kesin başarısızlık + fiyat/TLD/contact uyumu sonrası alternatif; belirsiz sonuçta otomatik ikinci deneme yok.

## D4 — Destek Merkezi
- Tamamen özelleştirilebilir departmanlar, varsayılan şablon.
- Departman bazında çalışma takvimi/tatil/SLA, manuel/round-robin/en az iş yükü atama.
- Doğrulanmış müşteri e-postaları otomatik, bilinmeyenler inceleme kuyruğuna.
- Müşteri öncelik seçebilir; personel ve kurallar değiştirebilir.
- Departman bazında otomatik kapanma ve yeniden açılma.
- Toplu arıza/incident ile bağlantılı ticket'lar; çözüm sonrası puan+opsiyonel yorum.
- Koşullu kural motoru: departman/öncelik/saat/etiket/müşteri/hizmet.
- V1 kanalları müşteri paneli + iki yönlü e-posta; personel 2–5.
- Departman/rol bazlı görme, yanıtlama, yönetme ayrı izinler; canlı güncelleme ve operatör dostu arayüz.

## D5 — Güvenlik, kimlik ve arayüz
- Hazır roller+özelleştirilmiş rol+işlem bazlı RBAC; müşteri alt kullanıcılarına davet ve kapsamlı yetki.
- Yönetici 2FA zorunlu; müşteride müşteri hesabı bazında 2FA politikası (zorunlu/opsiyonel).
- Yetkili personel müşteri şifresi olmadan denetimli destek oturumu açabilir; impersonation aktörü loglanır.
- Kritik yönetici işleminde yeniden doğrulama ve ayrıntılı onay. İkinci kişi onayı varsayılan gereklilik değildir.
- API anahtarları yetki kapsamlı, süresi sınırlı ve iptal edilebilir; oturumlar çoklu cihaz, liste/uzaktan çıkış/şüpheli giriş uyarısı.
- Modern admin: global arama, hızlı işlemler, sekmeler, kişiselleştirilebilir görünüm.

## D6 — Kurulum, yedek, aktarım
- Staging ve production veri/dosya/anahtar düzeyinde ayrı; staging gerçek sağlayıcılara mali sonuç üretmez.
- Web kurulum sihirbazı ve opsiyonel CLI.
- Yönetici onaylı imzalı/sürümü tanımlı güncelleme; ön kontrol, yedek, bakım modu, kontrollü migration, doğrulama.
- Gece tam yedek; yerel + seçilebilir dış hedef (Google Drive, S3 vb.), şifreli; ayarlanabilir 14 günlük/8 haftalık/12 aylık retention.
- Kurtarma noktası: teknik olarak mümkünse saatlik veritabanı kopyası veya PITR; bütünlük ve izole geri yükleme testleri.
- Sağlık uyarısı: panel/e-posta/isteğe bağlı webhook.
- WHMCS: salt-okunur DB/dump + ekler/dosyalar; API ile uygun doğrulama; parola hash doğrulanabiliyorsa rehash-on-login, değilse sıfırlama.
- Prova → fark analizi → kısa dondurma → son veri aktarımı → tek aktif provisioning/billing motoru.
- WHMCS geçici, otomasyonu/cron'u kapatılmış ve erişimi sınırlandırılmış arşiv (lisans ve veri saklama uygunluğu kontrol).

## Çelişki çözümü — kesin normalleştirme
- **Tek cüzdan vs çoklu para:** tek hesap para biriminde finansal yükümlülük; yabancı tutar dönüşümde kalıcı FX fişiyle izlenir. Kur farkı geçmiş cüzdan kaydını geriye dönük değiştirmez.
- **USD kaynak fiyat vs müşteri hesap para birimi:** ürün kaynak para birimi farklı olabilir; checkout yalnız müşterinin hesap para biriminde; ekran alternatif döviz gösterimi bilgilendirme amaçlı olabilir.
- **15 dk quote vs sabit fatura:** quote 15 dk; oluşturulmuş ödeme talebi immutable; sonraki dönem farklı hesaplanır. Tam ödenmemiş ödeme talebi için iptal/yeniden düzenleme yalnız kayıtlı düzeltme iş akışıyla yapılır.
- **Provider 'prepaid' vs hedef 'ondemand':** Voyar SDK iki tipi örneklendiriyor. Saatlik maliyetin gerçekten oluşması ve sonlandırmayla durması canlı GATE; ön ödemeli sağlayıcı hesabıyla bu ticari model varsayılamaz.
- **İade kaynağı vs cüzdan:** PayTR iadesi orijinal para/ödeme referansıyla; cüzdan kredisi verilen tutar dönüştürülürse ikinci FX olayı izlenir.
- **Staging vs gerçek içeri aktarılan veriler:** test aktarımında canlı PII maskeleme ve dış bağlantıları devre dışı bırakma.
- **Ürün kapsamı vs sağlayıcı kapasitesi:** capabilities matrisi operasyon başlamadan kontrol edilir; desteklenmeyen yükseltme/silme/dönüşüm müşteri panelinden doğrudan çalıştırılmaz.
- **Sahiplik transferi vs eski kayıtlar:** müşteri sahipliği zaman dilimli; eski finans belgesi eski müşteriyle kalır; destek gizliliği de geçmiş sahipliğe göre denetlenir.
