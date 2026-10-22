# Hostider Automation — V1.0 Master Specification

> **F1.1 güvenlik kapanışı:** pending MFA ile password+MFA step-up ayrıldı; kritik Identity işlemlerine sunucuya bağlı ayrıntılı onay ve scope delegasyon kontrolü eklendi. [F1_1_REPORT](docs/F1_1_REPORT.md), [F1_1_PLAN](docs/F1_1_PLAN.md), [ADR-004](docs/adr/ADR-004-f1-1-confirmations.md). F2 başlamadı; bütün canlı GATE'ler OPEN.

> **F1 Identity ve paneller:** çalışan admin/client hesapları, MFA, kalıcı roller/kapsamlar, davetler, oturum iptali, API anahtarları ve responsive paneller eklendi. Teslim ve sınırlar: [F1_REPORT](docs/F1_REPORT.md); kurulum, güvenli ilk yönetici ve yapay demo: [F1_SETUP](docs/F1_SETUP.md); plan: [F1_PLAN](docs/F1_PLAN.md). F2 başlatılmadı; bütün canlı GATE'ler OPEN. Aşağıdaki F0/F0.1 notları tarihsel teslim kayıtlarıdır.

> **F0.1 hardening:** kapsamlı sonuç ve açık sınırlar [F0_1_REPORT](docs/F0_1_REPORT.md), güvenlik/session/retention sözleşmeleri [F0_1_OPERATIONS](docs/F0_1_OPERATIONS.md), plan [F0_1_PLAN](docs/F0_1_PLAN.md).

> **Uygulama durumu (F0, 22 Ekim 2026):** PHP çekirdeği, MariaDB migration/outbox/queue, güvenlik sözleşmeleri ve ağsız sağlayıcı mock'ları eklendi. Kurulum: [F0_SETUP](docs/F0_SETUP.md); kapsam: [F0_PLAN](docs/F0_PLAN.md); test ve sınırlar: [F0_REPORT](docs/F0_REPORT.md); gereksinim izi: [F0_TRACEABILITY](docs/F0_TRACEABILITY.md); canlı kapılar: [F0_GATE_TASKS](docs/F0_GATE_TASKS.md). F1 başlamadı. Aşağıdaki şartname iş kurallarının referansıdır. `SHA256SUMS.txt` ilk geliştirici paketinin manifestidir; güncel uygulama manifesti `docs/evidence/F0_SHA256SUMS.txt` içindedir.

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
