# F4 Domain + Voyar Cloud — geliştirme ve izlenebilirlik planı

> F4-D güncel taban: `73468c0427e5f23553a7a8b534f7713011fafcc7`, 278 test, migration 001–043. F4-C test/mock kabul edildi; DOMAIN-002 otomatik failover PARTIAL ve MANUAL_REVIEW kalır. Yalnız F4-D kapsamı için [F4_D_PLAN](F4_D_PLAN.md) geçerlidir; aşağıdaki ilk planın sayıları tarihseldir.

24 Ekim 2026. Yetkili taban `c9eb0fcc87038ea3b3c58d9a2b5415af8be5d731`; dal `codex/f4-domain-cloud`. Bu belge kodlamadan önce oluşturulmuştur. Kapsam yalnız test/mock; F5/F6, canlı finans/registrar/Voyar/WHM ve main merge kapsam dışıdır. Önceki 238 test ve migration 001–038 korunacaktır.

## Kaynaklar ve karar sınırı

README, Master Specification, DECISION_TRACE_MATRIX, docs/01–15, F0–F3 raporları ve ADR-001–009 tasarım tabanıdır. Depoda ayrı Account Handoff veya Developer Package adlı belge bulunamadı; README geliştirici paketinin girişidir, `F2_F3_HANDOFF_CONTRACT.md` önceki faz devridir. Eksik harici belge içeriği varsayılmaz. Kullanıcının özgün Voyar ZIP'i salt okunur incelenir; SDK, canlı API kanıtı değildir.

Repository sahibi main için PR review, zorunlu `verify (8.3)` ve `verify (8.4)`, force-push/delete koruması ve bypass sınırlamalarını etkinleştirmelidir. Ayrı dalda geliştirme bu ayarın yerine geçmez; doğrudan main push/merge yapılmaz.

## Alt fazlar

| Faz | Gereksinim ve kabul | Dosya/modül ve additive migration | API/UI ve test |
|---|---|---|---|
| F4-A | FR-DOMAIN, R1-05, R7-07, DOMAIN-003, SEC-004 | `src/Domains/`, registrar hesap/TLD/provider rule/domain owner/contact/nameserver/capability/grace-redemption/history; 039 ile başlayan yeni migration | IDNA canonical name ve unique index; capability ve gerekli contact alanları; encrypted contact; owner izolasyonu ve immutable history MariaDB testleri. Domain sorgusu rezervasyon değildir. |
| F4-B | FR-DOMAIN, DOMAIN-003, OPS-001/005 | `src/Providers/Contract/`, `src/Providers/Mock/`; ayrı kalıcı registrar dünya/call/order tabloları, yeni additive migration | Availability/register/renew/transfer/status, NS/DNS/EPP/lock/privacy; aynı UUID+fingerprint replay; farklı içerik ret; transaction dışında provider call. ResellerClub/DomainNameAPI gerçek alanları eklenmez. |
| F4-C | DOMAIN-001/002/003, PROV-001/002/006, OPS-005 | Domain operation ve registrar attempt/reconciliation; ortak Services operation/lease/fencing ilkeleri; yeni additive migration | Timeout-before/after-create, expired lease, crash-after-effect; UNKNOWN otomatik failover/create/renew/charge üretmez. Kesin no-resource/no-money-effect kanıtı sonrası alternatif TLD/contact/quote/currency/balance/policy tekrar doğrulanır. Bağımsız PHP worker/MariaDB yarışları zorunlu. |
| F4-D | FR-VOYAR, R1-06, R5-07, PROV-001/002/006 | `src/Cloud/`, Compute contract ve durable Voyar mock; catalog/product/custom/location/OS/addon/resource/task/evidence/reconciliation için yeni migration | Catalog/custom quote ve kaynak sınırları; create/inspect/power/reinstall/extend/terminate/task poll. Custom upgrade/downgrade capability fail-closed. SDK'da bulunmayan yöntem gösterilmez. Task+resource kanıtı olmadan ACTIVE/TERMINATED olmaz. |
| F4-E | FIN-010, R5-04, R7-02/03/06, PROV-005 mock | Cloud exact usage/cost/reconciliation/price proposal; Finance Ledger ve Catalog application service sınırları; yeni migration | Müşterinin 30 günlük peşin satışı ayrı, provider saatlik oranı ve maliyet snapshot'ı ayrı. Kanıtlı resource başlangıç/bitiş, explicit rounding/minimum/addon/stop politikası; duplicate cost/overlap/currency/FX/price approval testleri. Paid bill değişmez. |
| F4-F | FR-ID/DOMAIN/VOYAR, SEC-004, OPS-001/005 | Domain/Cloud controller/views/panel/assets ve scoped permission/confirmation migration | Identity fresh session fence, scope, MFA/step-up, CSRF, replay authorization, pure plan, tek kullanımlık atomik confirmation. Müşteri domain/Cloud ve admin mapping/cost/proposal/UNKNOWN/reconciliation ekranları; gerçek HTTP/IDOR/secret leak/browser demo testleri. |

Her alt fazın uygulama raporu gerçek dosya/migration/test kanıtını verir. Planlanan tablo veya ekran uygulanmış kabul edilmez. Şema numaraları sıralı tahsis edilir; mevcut migration yeniden düzenlenmez. Tasarım çatışması docs/01 gereğince ADR ile kaydedilir; ticari karar değişikliği sessiz uygulanmaz.

## Yaşam döngüsü ve finans bağımlılıkları

Identity, Commerce, Ledger ve Services yeniden yazılmaz. Canonical paid order/item, unit claim, immutable price/period, current operation pointer, generation, lease/fence, outbox ve reconciliation ilkeleri korunur. Domain ve Cloud'a özgü provider verileri kendi modüllerindedir. Modüller diğer modül tablolarına yazmaz; finansal sipariş/yenileme Quote/Order/Billing/Finance application service üzerinden yapılır. Provider failure para/belge geçmişini silmez.

Her mutasyon için transaction içinde aggregate kilidi, tek in-flight operation ve durable iş/outbox; commit sonrasında provider etkisi; token+expiry+operation identity ile sonuç kabulü gerekir. Worker crash veya expired lease, unsafe işi UNKNOWN yapar. Reconciliation inspect/discovery çalıştırır; kör POST tekrar etmez. Birincil belirsizken alternatif registrar dispatch kapalıdır. Aynı name başka provider/customer üzerinden de ikinci register alamaz. Kesin failover denemesi ayrı attempt UUID taşır ve ilk operasyonla bağlanır; içerik değişen eski UUID tekrar kullanılamaz.

## Tehdit modeli

* Unicode/punycode eşdeğerliği: tek canonical ASCII kayıt anahtarı; invalid/context/bidi/uzunluk/TLD hatası ret. IDNA desteği yoksa IDN fail-closed; Unicode kaydı ASCII'den ayrı yaratılmaz.
* Contact/registrar response/müşteri kimliği ayrı; context-bound AEAD. API ve hata/audit allowlist'i contact, credential, external resource ve VNC secret sızdırmaz.
* Premium veya provider fiyat değişimi ve expired quote checkout öncesi yeniden onay ister. Availability kesin rezervasyon sayılmaz. Paid finans belgesi sonradan fiyatlandırılmaz.
* Cross-customer, alt kullanıcı scope, impersonation, session revoke ve replay yetkisi gerçek HTTP testinde denetlenir. Staff bayrağı global müşteri yetkisi değildir.
* Irreversible terminate/reinstall/EPP/credential işlemleri explicit etki+fresh step-up+onay+policy safeguard ister. Secret sonuçlar genel idempotency cache/log/history'ye girmez; özel şifreli tek-seferlik mekanizma tamamlanmadan işlem açılmaz.
* Staging/production durable mock veya gerçek transport çalıştıramaz. Bütün test fixture'ları yapaydır; gerçek müşteri/mail/payment/provider komutu yoktur.

## Performans ve test kanıtı

Her alt faz: PHP lint/mimari tarama, PHPStan, ilgili unit/contract/MariaDB testleri; finalde önceki 238 test ve yeni testlerin tamamı. PHP 8.3/8.4 CI başarıları gerçek run URL/SHA ile raporlanır; yerel sürüm diğerinin kanıtı değildir.

Gerçek MariaDB üzerinde 1/4/8 bağımsız PHP worker, duplicate domain register/renew, failover/UNKNOWN, VM duplicate create/task polling, lease kaybı, blocked queue ilerlemesi ve cron overlap ölçülür. Fixture hacmi, örnek sayısı, duvar süresi, throughput, p50/p95, lock wait/deadlock, backlog ve domain lookup/client ekranı süreleri ayrı ham kanıttır. Ağsız mock sonuçları production kapasite veya hosting SLA iddiasına çevrilmez. Bounded cron F3'ün 100 iş/20 saniye yeni iş kabulü sınırlarını korur; in-flight I/O için hard deadline iddia edilmez.

## Migration ve geri dönüş

001–038 checksum karşılaştırması, boş DB migrate/replay ve önceki F3 snapshot'tan additive upgrade doğrulanır. DDL implicit commit riski nedeniyle hata APPLYING bırakılır, otomatik destructive down yapılmaz. Worker durdurma ve tutarlı kod+DB+APP_KEY+korumalı dosya snapshot'ı gerekir; rollback doğrulanmış izole snapshot restore veya forward-fix'tir. Finansal/provider kanıtlar silinmez. F6 üretim restore provası bu fazın testleriyle kapanmaz.

## Açık kapılar ve teslim

G-V01–04, G-R01, G-D01, G-C01, G-O01–04, G-P01–02, G-X01–02, G-T01 OPEN kalır. G-R01/G-D01 kapanmadan gerçek registrar mutation; G-V02/V03 kapanmadan canlı Cloud dispatch; G-V01 kapanmadan otomatik Cloud satış yoktur. PROV-005 canlı kabulü, gerçek 2 saat ondemand maliyet ve termination kanıtı olmadan PASS değildir. Custom upgrade G-V04'e bağlıdır.

`F4_REPORT.md` çalışan/mock-only/SDK-only/eksik akışları, UNKNOWN/failover güvenliğini, maliyet/ledger/price/izolasyon regresyonlarını, migration/rollback, demo, ölçüm ve CI kanıtlarını ayrı gösterecektir. F4 mock teknik kabulü gerçek roadmap F4 exit değildir. DONE yalnız test+migration+dokümantasyon+demo+kabul kontrolünden sonra yazılır; eksik teslim DONE sayılmaz. F4 mock sonunda durulur, F5 başlamaz.
