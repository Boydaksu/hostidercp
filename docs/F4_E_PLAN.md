# F4-E test/mock kapsam ve geri dönüş planı

Taban dc70fa50e969ab1822723fdf9df4c89b657dacb4; 325 test; 001–045 korunur.
FIN-010, R5-04, R7-02/03/06, PROV-005 yalnız mock. F4-D kabul edildi.

1. Mevcut Finance Decimal/Money/FxSnapshot/TaxCalculator ve Ledger yeniden kullanılır.
   Provider cost accrual mevcut Ledger'da provider_cost debit / provider_payable credit'tir;
   ödeme, müşteri 30 günlük prepaid satış/period ve belge ayrı kalır. Paralel muhasebe yok.
2. 046 additive: immutable MOCK tariff ve metering/invoice envelopes, durable cost work,
   immutable cost/journal links ve invoice reconciliation; mevcut catalog_cost_proposals'a
   immutable evidence/approval/publication bağlantıları. Source/account/resource/task/spec,
   usage reference ve sequence bağları. observation/effect saati kullanım saati değildir.
3. Eksik kanıt UNVERIFIED, geçersiz/overlap/stale/mismatch REVIEW, tam sentetik kanıt COSTED_MOCK.
   Instance lock + unique account/usage + tek TX ledger/cost/outbox; local-only work'te crash rollback.
   Bounded 100/20s cron, SKIP LOCKED, checked_at fairness; 1/4/8 PHP process ölçümü.
4. Exact quantum/minimum/tax/addon/FX politikaları açık sentetik snapshot'tır; gerçek Voyar
   tarife/kullanım/stop/end/final invoice politikası varsayılmaz. STOP/TERMINATE maliyeti kapatmaz.
5. Mevcut Catalog cost-propose ve price-publish, Commerce confirmation ve gerçek Identity
   MFA/step-up üzerinden FIN-010. Maliyet artışı yalnız öneri üretir; ayrı manual mock approval ve
   explicit publication gerekir. auto_publish_allowed daima false. Eski order/bill/price değişmez.
   Fixed Cloud config yeni price'a aynı spec ile taşınır. 30 DAYS dışı Cloud teklifleri kapalıdır.

Dosyalar: src/CloudCosts, Providers/Mock/Metering, Finance ProviderCosts + Ledger hesabı,
Catalog CloudCostProposals/CloudOffers, Services Cloud gateway, CLI, 046 migration,
yeni cost/worker testleri; eski exact migration listeleri yalnız 046 eklenerek güncellenir.
Yeni HTTP/panel/F4-F yok. Eski 325 assertion silinmez/gevşetilmez.

Test: duplicate/overlap/sequence/invalid interval/source/task/stale/UNKNOWN, partial hour/minimum,
FX/tax/addon precision, STOP devam/late final invoice, proposal/approval/publication/historical
immutability, rollback, 1/4/8 concurrency ve blocked101 fairness. PHP8.3/8.4 full CI,
PHPStan, audit, migration045→046 replay ve HTTP/session tekrarları. Ham ölçümler rapora eklenir.

Rollback: cron durdur; provider POST replay yok. Tutarlı DB/kod/APP_KEY restore veya forward-fix;
immutable journals/evidence silinmez, destructive down yok. PROV-005 gerçek iki saat kanıtı
NOT PROVEN; G-V01–04 ve diğer GATE OPEN, DOMAIN-002 PARTIAL/MANUAL_REVIEW.
F4-E mock tesliminde dur; F4-F/F5/merge/production yok.
