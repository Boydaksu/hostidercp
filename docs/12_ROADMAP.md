# 12 — Geliştirme fazları, bağımlılıklar ve çıkış kriterleri

## Ön koşul — F0: Mimari ve kanıt
- İş kuralları dondur, değişiklik ADR süreci, DB modelini review et.
- Toy/demo yerine test stratejisi, mock payment/provider contract test altyapısı, provider capability matrisi.
- Env ayrımı, staging deployment ve referans CI/lint/QA.
- **GATE:** Voyar ondemand gerçek maliyet, 2FA token yenileme; PayTR hesap para birimi ve wallet load; registrar API sürümü, WHM hesap yetkileri; müşteri ihtiyacıyla uyum.
- **Exit:** açık vendor GATE'lerin risk durumları yazılı, kritik arayüzler ve schema kabul edilmiş.

## F1: Çekirdek ve erişim
- PHP bootstrap, router, middleware, migrations, PDO repository/transactions, strict configuration, secret management.
- Users/customers/customer_users, roles scopes, admin 2FA, müşteri 2FA policy, devices/sessions, audit, impersonation, API keys.
- Basic admin/customer layout, auth API ve yetki testleri.
- **Exit:** roller ve müşteri ayırımı, CSRF, oturum sonlandırma ve impersonation testleri başarılı.

## F2: Finans + checkout
- Catalog, FX rates/quote snapshot, product strategies, tax & promos, invoice/payment request, PayTR callback, bank transfer, customer wallet, single cash UI, official PDF.
- Ledger invariants, mixed payment, refunds, staff approval, notification outbox.
- **Exit:** duplicate callback ve finansal mutabakat, FX 15 min, wallet race tests eksiksiz.

## F3: Hizmet motoru + cPanel
- Service state, provisioning ops/jobs, server pools, WHM create/inspect/suspend/unsuspend/changepackage/terminate.
- Manual activation, admin approvals, automated renewal and suspend; provider unknown reconciliation.
- **Exit:** mock + staging WHM test: aynı siparişle çift hesap yok, failover güvenli, imported account yeniden oluşturulmaz.

## F4: Domain + Cloud (paralel adaptörler)
- ResellerClub/DomainNameAPI capabilities/TLD/provider failover, contact & EPP, renew/transfer.
- Voyar catalog/custom quote, VM create/inspect/manage, 30 günlük müşteri satışı ile fiili saatlik maliyet mutabakatı.
- Cloud iptal/termination ve fiyat öneri onay pipeline.
- **Exit:** fiyat/ürün mapping kanıtlı, gerçek VM resource/ondemand maliyeti test edilmiş, registrar UNKNOWN failover koruması başarılı.

## F5: Gelişmiş Destek Merkezi
- Departman, ticket/reply/internal note, mail ingestion/sending, SLA calendars, assignment, rule engine, incident, CSAT, raporlar, dinamik UI.
- **Exit:** çift e-posta, departman gizliliği, SLA takvimleri ve eşzamanlı personel testleri başarılı.

## F6: Operasyon, aktarım ve canlı yayın
- Backup/update/recovery, monitoring, WHMCS import, attachments, official invoices, wallets and service mapping.
- Staging dry-run/reconciliation, cron/email/PAYTR sandbox, production cutover checklist and rollback drill.
- **Exit:** `docs/13_ACCEPTANCE_TESTS.md` release blockers kapanmış, restore testi başarılı, tek aktif otomasyon.

## Delivery kuralları
- Her faz için görev kartı: requirement IDs, code modules, migration, security threat check, API/spec diff, test, risk, evidence.
- F2 bitmeden canlı PayTR tahsilat ve otomatik service create birleşmez. F3/F4 contract testleri geçmeden gerçek otomatik provisioning açılmaz.
- Destek veri modeli F0/F1'de gözetilir; destek F5'e kadar ertelense bile çekirdek kullanıcı/izin/event sistemi yeniden yazılmaz.
- Faz bazında release demoları; eksik işleri gizleyip tamamlandı sayma yok.
