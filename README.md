# Hostider Automation — V1.0 Master Specification

**Durum:** Onaylı iş kararlarına dayanan teknik tasarım tabanı; henüz uygulama kodu değildir.  
**Tarih:** 22 Ekim 2026  
**Amaç:** Hostider'ın WHMCS'den kontrollü ayrılması. Sadece kurum içi kullanım.  
**Teknoloji hedefi:** PHP 8.3+, MariaDB 10.11+ tercih, paylaşımlı hosting, 1 dakikalık cron, modüler monolit.  
**Öncelikler:** finansal doğruluk > veri kaybını önleme > güvenlik > operatör deneyimi > performans > gösterişli özellikler.

## Okuma sırası
1. `docs/01_DECISIONS.md` — yedi turdan kesin iş kararları, öncelik ve çakışma çözümü.
2. `docs/02_REQUIREMENTS.md` — kapsam, aktörler, iş gereksinimleri ve V2 sınırları.
3. `docs/03_ARCHITECTURE.md` — modüler yapı, olaylar, kuyruk, güvenlik, dağıtım.
4. `docs/04_DATA_MODEL.md` — ana varlıklar, ilişkiler, veri bütünlüğü.
5. `docs/05_FINANCE_AND_PRICING.md` — döviz, ödeme, cüzdan, kasa ve faturalandırma.
6. `docs/06_WORKFLOWS.md` — durum makineleri, iptal, transfer, domain, yenileme.
7. `docs/07_PROVIDER_CONTRACTS.md` — PayTR, Voyar, cPanel, ResellerClub, DomainNameAPI.
8. `docs/08_SUPPORT_CENTER.md` — destek sisteminin ayrıntılı şartnamesi.
9. `docs/09_API_AND_SCREENS.md` — API ilkeleri, yetki matrisi, sayfa haritası.
10. `docs/10_OPERATIONS.md` — kurulum, staging, yedek, güncelleme, güvenlik.
11. `docs/11_MIGRATION.md` — WHMCS'den aktarım ve geri dönüş.
12. `docs/12_ROADMAP.md` — bağımlılıklar ve aşamalı teslim planı.
13. `docs/13_ACCEPTANCE_TESTS.md` — Gherkin benzeri kabul testleri ve yayın kapıları.
14. `docs/14_OPEN_VALIDATIONS.md` — sağlayıcı bazlı doğrulama listesi; varsayım ≠ gerçek.
15. `AGENTS.md` — geliştirici/AI için davranış sözleşmesi.
16. `sql/001_core_schema_blueprint.sql` — kritik tablolar için *başlangıç* DDL taslağı; tamamlanmış üretim migrasyonu değildir.
17. `contracts/` — örnek sağlayıcı yetenekleri ve hata sözleşmesi.
18. `prompts/` — geliştirmeyi fazlara ayıran başlangıç talimatları.
19. `references/voyar-api-client-user-provided.zip` — kullanıcının gönderdiği özgün Voyar PHP SDK referansı; canlı davranış ve güvenlik kontrolü bekliyor.

## Normatif dil
- **MUST / ZORUNLU:** V1 yayın engelleyici gereksinim.
- **SHOULD / ÖNERİLEN:** gerekçeyle ertelenebilir veya değiştirilebilir.
- **MAY / OPSİYONEL:** zorunlu olmayan geliştirme.
- **GATE / DOĞRULAMA KAPISI:** canlı sağlayıcı API'si/ortam/uyumluluk görülmeden kesin kabul edilemez.

## Kaynak hiyerarşisi
1. Kullanıcının 1–7. turda kesinleştirdiği iş kararları; sonraki karar aynı konuyu değiştiriyorsa en güncel karar.
2. Bu paketteki finansal/güvenlik değişmezleri (iş kararlarını ihlal etmeden uygulama yöntemi).
3. Resmi API belgeleri ve kullanıcıdan gelen Voyar SDK dosyası — entegrasyon davranışlarının teknik kanıtı, canlı kabiliyet garantisi değil.
4. Uygulama sırasında yazılı olarak kabul edilmiş ADR'ler ve test edilmiş özellikler.

## Sahiplik ve kullanış
Bu paket çalışma tasarımıdır; kullanıcı API anahtarlarını, canlı DB dump'ını ve şifrelerini bu dosyalara koymamalıdır. Kod üretimi, testler, şema doğrulaması ve canlı sağlayıcı onayı henüz yapılmış değildir.
