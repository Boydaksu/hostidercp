# F4-D kalıcı Voyar Cloud mock planı

Taban: `73468c0427e5f23553a7a8b534f7713011fafcc7`, 278 başarılı test; 24 Ekim 2026.
Kapsam: FR-VOYAR, R1-06, R5-07, PROV-001/002/006, OPS-001/005, SEC-004.
F4-C test/mock kabul edildi; DOMAIN-002 otomatik failover PARTIAL kalır.

## Kapsam / dosyalar
`src/Cloud/`: katalog/spec doğrulama, instance/operation/task/evidence, kalıcı dispatch fence,
bounded worker, polling ve operator reconciliation. `Providers/Mock/DurableVoyar`: ayrı kalıcı
sentetik dünya; SDK sonuç alanları uydurulmaz. Services application gateway canonical CLOUD
kaynağını ve service state yazılarını sahiplenir. Mevcut VoyarMutationFence callback ile kalıcı
send-once admission'a bağlanır; özgün SDK AuthenticationException replay testi bu callback'i kullanır.
CLI/outbox yolları test-only. Public HTTP/panel/credential teslimi F4-F'ye bırakılır.

## Migration
Yalnız additive 044 ve gerekirse devamı: mock account/catalog, Cloud instance/operation, dispatch,
task/resource evidence, confirmation ve usage provenance. 001–043 byte/blob eşliği korunur.
Immutable request/binding/history ve unique operation/task/resource keys zorunlu.

## SDK eşlemesi ve belirsizlik
services.create; service.get; services.tasks(serviceid); vm.status/start/stop/restart;
service.reinstall/upgrade/terminate. Custom upgrade ve genel downgrade UNSUPPORTED.
SDK yalnız array döndürür; create/task başarı DTO'su doğrulanmamış olduğundan canlı mapper yoktur.
Sentetik normalized task durumları gerçek Voyar durum adları diye sunulmaz. Task+resource bağımsız
kanıtı olmadan ACTIVE/TERMINATED yoktur; UNKNOWN asla otomatik POST replay'e açılmaz.
Plan değişimi yalnız explicit sentetik ücretsiz hedef/capability politikasında gösterilir;
ek ücret/fiyat değişimi MANUAL_REVIEW, yeni tahsilat veya F4-E maliyet muhasebesi açılmaz.

## Test
Gerçek MariaDB: 043→044 upgrade/replay, paid kaynak/unit, imported create yasağı, timeout before/after,
body loss, auth POST replay, crash-after-effect, stale lease/task completion, duplicate task, yanlış
account/resource/fingerprint, secret/projection allowlist; 1/4/8 bağımsız PHP process ve CLI/outbox.
Önceki 278 + yeni testler; PHPStan, Composer audit, lint/mimari, HTTP/session regresyonu;
PHP 8.3/8.4 CI ve gerçek commit/job bağlantıları. Sayım günlüklerden çıkarılır.

## Geri dönüş / sınırlar
Cron durdur; UNKNOWN mutation replay yok. Tutarlı kod/DB/APP_KEY snapshot restore veya forward-fix;
DDL implicit commit nedeniyle destructive down yok. Gerçek provider/SMTP/ödeme/production kapalı.
G-V01–04 ve diğer tüm GATE'ler OPEN. Usage provenance F4-E için tekil kanıt kaynağıdır, gerçek
saatlik ücret veya stop/terminate maliyet kesimi kanıtı değildir. F4-D mock tesliminde dur; F4-E/F,
F5, merge veya production'a geçme. Bağımsız kabul olmadan tam F4 DONE yok.
