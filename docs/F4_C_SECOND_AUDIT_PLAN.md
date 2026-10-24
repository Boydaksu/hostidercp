# F4-C ikinci denetim düzeltme planı

24 Ekim 2026. Başlangıç: `72e35a88ca381a5a00e0daf68e798ccb766663d7`.
Durum: IN PROGRESS; bağımsız kabul beklenir. Yalnız F4-C test/mock.

## Kapsam ve gereksinimler
FR-DOMAIN, FR-SERVICE, FR-CHECKOUT; DOMAIN-001/002/003, OPS-005, SEC-004.
1. Harness bootstrap ve 12 mevcut senaryoyu gerçekten çalıştırma.
2. Bounded cron fairness, transaction içinde hold kaydı, SKIP LOCKED ve UNKNOWN replay yasağı.
3. Ayrı PHP CLI süreci, bounded outbox/queue tüketimi ve fiziksel etki sayımı.
4. Attempt/account/action/resource/fingerprint/status/no_effect bütünlüğü; yokluk failure kanıtı değildir.
5. Services application sınırından oluşturulan immutable renewal order/period/price bağını enqueue, claim ve sonuçta doğrulama.
6. Registration/renewal başarılarını ayrı uygulama; renewal başlangıcını önceki bitişten alma ve tek uzatma.
7. Güncel fiyat/bakiye kanıtı bulunmadığından registrar failover'ı manuel incelemeye kapatma. Ticari karar değişmez.
8. Kısa snapshot transaction, dış inspect, generation/attempt/state fence ile kayıt.

## Dosya değişiklikleri
Domains engine/runner/reconciliation, mock kanıt sınırı; Services domain period application servisi;
domain operation testleri ve yeni negatif/concurrent testler; bu plan ve post-audit raporu.

## Migration
001–042 değişmez. Gerekirse yalnız additive 043: inspection fence ve immutable renewal bağının korunması.
DDL implicit commit: disposable MariaDB üzerinde migrate + ikinci migrate no-op doğrulanacak.

## Test ve kanıt
Önce mevcut fatal hatayı yeniden üret. MariaDB 10.11 ve PHP 8.4 yerel ortamında tüm assertion'lar;
101 bloke + 1 sağlıklı, bounded tekrarlar, SKIP LOCKED, rollback, UNKNOWN replay, sahte kanıt,
başka DOMAIN faturası, iki renewal ve ikinci timeout-after-effect; gerçek CLI süreçleri.
Önceki 254 test tanımı korunur. Lint, mimari, audit, PHPStan; sonra PHP 8.3/8.4 Actions.
Her bulgu PASS/FAIL/PARTIAL/NOT TESTED, kaynak satırı, test ve kanıtla raporlanır.

## Geri dönüş
Cron durdurulur; UNKNOWN işler yeniden gönderilmez. Kod ve doğrulanmış DB yedeği birlikte geri alınır;
yeni mali/provider etki varsa önce mutabakat. Destructive down yok. 041/042 yeniden yazılmaz.
F3 mock kabulü, DOMAIN/CLOUD transfer engeli ve açık GATE'ler korunur. F4-D/F5, main merge,
production ve canlı registrar/WHM/PayTR/Voyar mutation yasaktır. Bağımsız kabul olmadan DONE yok.
