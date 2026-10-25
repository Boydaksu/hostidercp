# Hostider Automation — V1.0 Master Specification

> **F5-B P1 hedefli test/mock düzeltmesi — bağımsız yeniden kabul bekliyor.**
> Kaynak[`5869483`](https://github.com/Boydaksu/hostidercp/commit/5869483fd0d977ede646cbef02bdf4a9f2b12bc8);
> [gerçek PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40234838426):
> **51 suite /472 test /0 hata**; eski461 + yeni11. Staff-reopen FIRST sonucu ve
> SLA calendar publication/failure isolation düzeltildi. [Denetim raporu](docs/F5_B_POST_AUDIT_REVIEW.md).
> 001–050 korunur, yalnız additive051. F4/F5-A mock kabulü ve canlı GATE'ler değişmez;
> PR #3 açık/taslak, sonraki fazlar ve canlı işlemler kapalıdır. Aşağıdaki ilk F5-B
> teslim kaydı tarihseldir; F5-B bağımsız kabul edilmiş sayılmaz.

> **F5-B test/mock geliştirmesi yetkilendirildi; bağımsız teknik kabul bekliyor.**
> SLA takvimleri ve manuel/RR/ağırlıklı atama kapsamı:
> [plan](docs/F5_B_PLAN.md), [ADR-018](docs/adr/ADR-018-support-calendars-assignment.md),
> [teslim ve doğrulama raporu](docs/F5_B_DELIVERY_REPORT.md).
> Uygulama`4d58b81823deb2b44ce52ef89bae77dce468ef3b`;
> [gerçek PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40226687244):
> **51 suite / 461 test / 0 hata**; eski433 + yeni28. Additive050 dışında
> 001–049 korunur. Ticari SLA/roster değerleri onaylanmış sayılmaz. F5-A/F4 sınırlı
> mock kabulleri ve bütün canlı GATE'ler korunur. PR #3 açık/taslak kalır;
> F5-C/D/E/F6, gerçek mail, merge, staging veya production başlatılmaz.
> Aşağıdaki önceki faz notları tarihsel kabul kayıtlarıdır.

> **F5-A LIMITED MOCK TECHNICAL BASELINE — ACCEPTED.** Proje sahibinin son bağımsız
> kabul kararı yalnız F5-A test/mock çekirdeğini kapsar. Kabul edilen uygulama
> [`d3712eda6b3d9156187727ced85fff04de7457c5`](https://github.com/Boydaksu/hostidercp/commit/d3712eda6b3d9156187727ced85fff04de7457c5);
> [PHP8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40212210626)
> her sürümde **51 suite / 433 test / 0 hata**. Eski 428 test korunur, yeni 5 regresyon PASS.
> P1-1 tekil event UUID/outbox/job/receipt ve P1-2 açık 422 unsupported arama sözleşmesi
> bağımsız denetimde kapatıldı. [Kabul kaydı](docs/F5_A_DELIVERY_REPORT.md),
> [hedefli düzeltme kanıtı](docs/F5_A_POST_AUDIT_REVIEW.md).
> Gerçek mail, SLA, assignment, attachment, rule engine, incident, CSAT ve gelişmiş UI
> tamamlanmış sayılmaz. F4 mock kabulü, 001–049 ve bütün canlı GATE'ler korunur.
> F5-B ayrı yetki bekler; yeni geliştirme, F5-C/D/E/F6, merge, staging veya production yoktur.
> [PR #3](https://github.com/Boydaksu/hostidercp/pull/3) açık ve taslak kalır.

> **F5-A ilk teslim (tarihsel):** Ticket, departman, immutable mesaj/dahili not,
> native Identity kapsamı ve minimal HTTP çekirdeği ayrı `codex/f5-support-center`
> dalındadır. [Plan](docs/F5_PLAN.md), [A sözleşmesi](docs/F5_A_PLAN.md),
> [teslim ve doğrulama durumu](docs/F5_A_DELIVERY_REPORT.md),
> [taslak PR #3](https://github.com/Boydaksu/hostidercp/pull/3).
> Kaynak `a38e8da`: [PHP 8.3/8.4 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40208255513)
> her sürümde **51 suite / 428 test / 0 hata**; önceki 394 test korunur, 049 additive.
> Bu ilk teslim notları tarihseldir; güncel sınırlı kabul kararı üsttedir.
> F5-B/C/D/E, F6 ve canlı işlemler için ayrı yetki gerekir.

> **F4 bütünleşik mock kabul paketi:** C/D/E/F teknik mock kabulleri ve 49 suite / 394 test
> [F4_REPORT](docs/F4_REPORT.md) altında birleştirildi. Bağımsız karar: **SINIRLANDIRILMIŞ MOCK
> TEKNİK GO**. Sabit uygulama: `6e25a8b98de6e0c9da5e84df6b78e46cd47854f2`.
> Tam kapsam/live exit kabul edilmedi; F4 DONE/production-ready değildir.
> Mapping/import CRUD NOT ACCEPTED; DOMAIN-002 PARTIAL; bütün canlı GATE'ler OPEN.
> Aşağıdaki faz teslim notları tarihsel kayıttır; güncel kabul sınırı F4_REPORT'tadır.


> **F4-F son denetim düzeltmesi:** Native doğrulanmış kanıt özeti ve Domain no-effect FAILURE
> onayı [F4_F_POST_AUDIT_REVIEW](docs/F4_F_POST_AUDIT_REVIEW.md) ile teslim edildi.
> PHP8.3/8.4 **394/394**; 001–048 korunur, yeni migration yoktur. Bağımsız yeniden kabul bekler.
> Mapping/import CRUD proje sahibinin açık onayıyla ayrı işe ertelendi; uygulanmış/DONE değildir.
> DOMAIN-002 PARTIAL ve canlı GATE'ler OPEN; F5/F6, merge veya production başlatılmaz.

> **F4-F test/mock teslimi; bağımsız kabul bekliyor:** Domain/Cloud müşteri ve personel panelleri,
> native Identity HTTP güvenliği ve FIN-010 ekranları uygulandı. PHP8.3/8.4 **383/383**;
> [teslim raporu](docs/F4_F_DELIVERY_REPORT.md), [plan](docs/F4_F_PLAN.md).
> F4-C/D/E kabul edilen 355 testlik taban ve 001–047 migration geçmişi korunur;
> additive 048; gerçek sağlayıcı GATE'leri açık, DOMAIN-002 PARTIAL kalır.
> F4-F sonrası bağımsız kabul olmadan F5/F6, merge veya staging/production başlatılmaz.

> **F4-E test/mock teslimi; bağımsız kabul bekleniyor:** F4-D güvenlik tabanı bağımsız kabul edildi (325 test, HEAD dc70fa5). Kullanım/sağlayıcı maliyeti, mevcut Ledger ve manual fiyat önerisi kapsamı [F4_E_PLAN](docs/F4_E_PLAN.md) ve [F4_E_DELIVERY_REPORT](docs/F4_E_DELIVERY_REPORT.md) ile belgelenir (355 test). 30 günlük peşin müşteri satışı ayrı kalır; PROV-005 gerçek kabulü ve tüm canlı GATE’ler açıktır. F4-F/F5, merge ve production başlatılmaz.


> **F4-D geliştirme:** F4-C’nin 278 testlik güvenlik tabanı bağımsız kabul edildi; DOMAIN-002 otomatik registrar failover PARTIAL kalır. Kalıcı Voyar Cloud operation/task/mock kapsamı [F4_D_PLAN](docs/F4_D_PLAN.md) ile yürütülür. Canlı GATE’ler açık; F4-E/F, F5 ve production başlatılmaz.

> **F4 geliştirme başladı; tamamlanmadı:** kabul edilen F3 tabanı üzerinde [F4 planı](docs/F4_PLAN.md), [Voyar SDK incelemesi](docs/F4_VOYAR_SDK_REVIEW.md), Domain model/kalıcı registrar mock temeli ve SDK POST replay transport koruması eklendi. [Taslak PR #2](https://github.com/Boydaksu/hostidercp/pull/2), PHP 8.3/8.4 için [251/251 CI](https://github.com/Boydaksu/hostidercp/actions/runs/40071562182). [F4 ilerleme raporu](docs/F4_REPORT.md) eksik Domain lifecycle/failover, Cloud ve panel kapsamını açıkça ayırır. Bütün canlı GATE'ler OPEN; F5 başlamadı. Aşağıdaki önceki faz notları tarihseldir.

> **F2 CI kararlılık kapanışı:** PHP 8.3 built-in server JIT SIGSEGV yeniden üretildi; test server interpreter moduna alındı, lifecycle/transport tanıları ve bounded Windows fixture cleanup eklendi. Önceki 175 + 5 yeni regression ve tekrarlı CI kanıtı: [F2_CI_STABILITY_REPORT](docs/F2_CI_STABILITY_REPORT.md). F3 ve canlı işlemler başlatılmadı.

> **F2 teslim sonrası denetim:** kanıtlı mutabakat kapanışı, daraltılmış onay kilitleri, idempotency replay yetki kontrolü ve güvenli F3 devir sözleşmesi: [F2_POST_DELIVERY_REVIEW](docs/F2_POST_DELIVERY_REVIEW.md). Production ve F3 başlatılmadı; önceki F2 raporu tarihsel teslim kaydıdır.

> **F2 finans ve satış çekirdeği:** katalog, kesin dövizli fiyatlandırma, teklif/sipariş, cüzdan/karma ödeme, proforma/korumalı PDF ve yönetici finans panelleri test ortamında tamamlandı. [F2_REPORT](docs/F2_REPORT.md), [F2_SETUP](docs/F2_SETUP.md), [F2_PLAN](docs/F2_PLAN.md). 164 test ve PHP 8.3/8.4 CI başarılı. Kullanıcı kabulü beklenir; F3 başlamadı. Production kullanımı ve canlı işlemler kapalı; bütün doğrulama GATE'leri OPEN. Aşağıdaki eski faz notları tarihsel kayıtlardır.

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
Bu paket çalışma tasarımıdır; kullanıcı API anahtarlarını, canlı DB dump'ını ve şifrelerini bu dosyalara koymamalıdır. Uygulanan test/mock kapsamı ve gerçek kanıtlar aşağıdaki güncel raporda belirtilir; canlı sağlayıcı onayı açık GATE gerektirir.

## Uygulama durumu — F3 test/mock

Güncel uygulama ve doğrulanmış sınırlar [F3 teslim raporunda](docs/F3_REPORT.md) bulunur. Hizmet motoru ve Identity/Commerce ile bağlı müşteri/yönetici hizmet panelleri test/mock ortamında uygulanmıştır; canlı sağlayıcı onayı ve production kullanımı açık GATE'lere bağlıdır. F4 başlatılmadı.

F3 son denetiminde Runner kuyruk açlığı ve cron batch işleme düzeltildi; yeniden üretim, gerçek CLI ölçümü ve kabul kararları [F3_POST_DELIVERY_REVIEW](docs/F3_POST_DELIVERY_REVIEW.md) içindedir. Migration 038 additive'dir; 001–037 korunur. F4 kullanıcı kabulü bekler, canlı GATE'ler açıktır.
