# F4-D hedefli denetim düzeltme planı

Taban b36537907c28dc40fdd793ef5cd7a513711da6e5; 298 test. Yalnız P0-1/P0-2/P1.
FR-VOYAR/FR-CHECKOUT/FR-SERVICE, SEC-004, OPS-005. F4-E/F, F5, canlı/merge yok.

## Kapsam ve dosyalar
1. Catalog fixed offer immutable catalog_price + exact VM spec + provider policy mapping.
   QuoteService bunu server-side price line'a dondurur; Cloud attach ve CREATE claim/send aynı
   order/item/service/unit/price/bill bundle'ını tekrar doğrular. Eski entitlement'sız siparişler
   backfill edilmez. Ücretli/serbest custom/plan seçimi kapalı.
2. Cloud Containment: gerçek Identity admin session, fresh step-up/scope, state-bound açık
   confirmation, dış resource inspect, terminate waiting, encrypted reason ve finansal istisna
   audit. Yalnız STOP/TERMINATE; UNKNOWN/pending/busy durumda yeni mutation yok.
3. Outbox delivery receipt ile operation/task durumu ayrılır; WAITING_TASK queue UNKNOWN değildir.

## Migration
Yalnız 045 additive: fixed offers, purchased entitlement binding, safety confirmations,
financial containment audit + operation exception flag. 001–044 byte eşliği korunur.

## Test
298 tanım korunur; eski serbest seçim fixture'ları satın alınmış fixed offer'a geçirilir,
güvensiz beklentiler yeni sınırda negatif doğrulanır. PID/product/custom/customer/item/unit,
refund/cancel/price/source değişimi, canonical success; emergency Identity/scope/revoke/step-up,
stale confirmation, parallel terminate, timeout/reconciliation/no second POST, account/resource
mismatch, STOP cost-unknown; pending task delivery regression. MariaDB/ayrı PHP süreçleri,
PHP 8.3/8.4 tam CI, PHPStan, Composer audit ve HTTP/session tekrarları.

## Geri dönüş
Cron durdur; UNKNOWN replay yok. Tutarlı kod+DB+APP_KEY restore veya forward-fix; destructive down
yok. Yeni offer yoksa eski Cloud CREATE kapalı kalır. GATE'ler ve DOMAIN-002 PARTIAL korunur.
docs/F4_D_POST_AUDIT_REVIEW.md gerçek kaynak/test/CI/başarısızlık/sınır kanıtıyla teslim edilir.
