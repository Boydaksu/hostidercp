# AGENTS.md — Hostider Automation mühendislik kuralları

## Görev
Yalnızca Hostider için paylaşımlı hosting uyumlu, PHP 8.3+ / MariaDB tabanlı, güvenli hosting otomasyonu oluştur. Master Specification dışına izinsiz yeni ticari ürün, lisans sunucusu, SaaS multi-tenancy, SPA zorunluluğu, Node worker veya ağır framework ekleme.

## Başlamadan
- README ve tüm `docs/` dosyalarını oku. Geliştirdiğin fazın gereksinim kimliklerini işaretle.
- Her faz için **kapsam, dosya değişiklikleri, migration, test, geri dönüş** planını yaz.
- Doğrulanmamış API cevabı/alanı/işlemi uydurma. Mock ve contract test kullan. Gerçek entegrasyon için GATE maddelerini kapat.
- Tasarım kararı çatışması çıkarsa sessizce değiştirme; `docs/01_DECISIONS.md` kuralını uygula, ADR aç.

## Kırmızı çizgiler
1. Para: float kullanma; ISO para birimleriyle minor-unit integer veya kesin decimal mantığı, açık yuvarlama ve immutable kayıtlar kullan.
2. Sipariş ≠ ödeme ≠ fatura ≠ hizmet ≠ sağlayıcı işlemi. Ayrı durum makineleri ve işlem kimlikleri.
3. Ödeme callback'i yeniden gelebilir. Tekil dış referans ve idempotency; ödeme tam değilse otomatik provisioning yok.
4. Dış sağlayıcı POST timeout'unda kör tekrar yok: `UNKNOWN` / reconciliation ve gerekirse insan müdahalesi.
5. Domain kayıt/yeni VM/yenileme/terminate isteklerini güvenli olmayan tekrar mekanizmasıyla çalıştırma.
6. Aynı DB transaction içinde kalıcı iş kaydı + transactional outbox; harici API çağrısını transaction açıkken yapma.
7. Yönetici 2FA, müşteri kapsamı/rolü, oturum sonlandırma, izin matrisi, impersonation audit zorunlu.
8. Staging canlı sağlayıcıya komut göndermez, gerçek müşteriye mail veya ödeme oluşturmaz.
9. Ayrıcalıklı işlemde kimlik doğrulama tazele, geri alınamaz silme için onay+audit+bekleme/safeguard uygula.
10. Eklenti/lisans pazaryeri yok; sağlayıcı arayüzleri ve kendi dahili modüllerimiz var.
11. Gece yedeği, dış şifreli kopya, geri yükleme provası ve üretim güncelleme öncesi backup şart.
12. Hiçbir gizli anahtar, şifre, müşteri PII, token veya VNC credential log'a düşemez.
13. Modüller birbirinin tablo yapısına doğrudan müdahale edemez; application service / domain event kullan.
14. `DONE` yalnız testler + migration + dokümantasyon + demo senaryosu + kabul kontrolü sonrası yazılabilir.

## Teslim biçimi
Her PR/faz sonunda: (a) değişen özellik kimlikleri, (b) migration/rollback notu, (c) test raporu ve kanıtı, (d) başarısızlık ve sınırlar, (e) sonraki bağımlılıkları bildir.

## V1 sınırı
PayTR + manuel havale, cüzdan, tek kasa, proforma/manual resmi PDF; cPanel, Voyar, ResellerClub, DomainNameAPI; gelişmiş destek; WHMCS veri aktarımı. V2: iyzico, kayıtlı kart, Plesk, Proxmox ve kapsamda sayılan ileri eklentiler.
