# F4-D — Son hedefli denetim düzeltmesi

24 Ekim 2026. Yalnız test/mock; bağımsız yeniden kabul bekler. Tam F4 DONE değildir.
İncelenen eski uygulama 3296eca0631e41d49ad78545d49a6ac7fa9ec5f1; çalışma tabanı
4bde80fd34adfd390fc9ff2f0110e957dd2ad1b2. P0-1 entitlement ve P1 Cloud receipt kabulü korunur.

## Gerçek kaynak / kanıt

GitHub kaynak SHA: `19826883fab6361b169be72b2633cdf18e0419cb`.
Yerel commit `3d0dcb5777593be6822cc139949c8e7c465b21ac` ile aynı
`d9f9c2208c77fc5e7cc43bae59db2732abfeeab6` tree; yedi değişmiş dosya blob/hash eşliği ve
001–045 SHA256 manifesti [publication.json](evidence/f4-d-final-audit/publication.json) içindedir.
[Kaynak commit](https://github.com/Boydaksu/hostidercp/commit/19826883fab6361b169be72b2633cdf18e0419cb),
[taslak PR #2](https://github.com/Boydaksu/hostidercp/pull/2).

Tam branch CI: [Actions 40112735157](https://github.com/Boydaksu/hostidercp/actions/runs/40112735157).
- [PHP 8.3.36 / job 120927666901](https://github.com/Boydaksu/hostidercp/actions/runs/40112735157/job/120927666901): **46 suite / 325 test / 0 hata**, SUCCESS.
- [PHP 8.4.27 / job 120927666834](https://github.com/Boydaksu/hostidercp/actions/runs/40112735157/job/120927666834): **46 suite / 325 test / 0 hata**, SUCCESS.

MariaDB 10.11.19. İki job'da 313 mevcut + 12 yeni test geçti; Composer locked audit,
PHPStan, lint/mimari/pattern taraması, 10 service HTTP, 30 Commerce HTTP, 20 session
concurrency tekrarı ve JS kontrolleri başarılıdır. Her iki ham günlükte checkout doğrudan
`19826883fab6361b169be72b2633cdf18e0419cb` kaynak SHA'sıdır; PR merge checkout'u değildir.
Ham job çıktıları yukarıdaki iş bağlantılarındadır; [çıkarılan CI kanıtı](evidence/f4-d-final-audit/ci.json)
her suite'in sayısını, yeni 12 PASS satırını, worker ölçümlerini ve ham log API adreslerini içerir.

## P0 — Tarihsel audit güncel yetkilendirme değildir

Önceki apply(), güncel kaynağı kontrol ettikten sonra finansal istisnada service UNKNOWN ise
eski financial_context.service_status=ACTIVE değerini geri yükleyebiliyordu. Refund/cancel
sonrasında bu snapshot yetki kanıtı değildir. Snapshot immutable tarihsel audit olarak saklanır;
apply artık bu kaydı okumaz veya service state üretmek için kullanmaz.

Engine.apply güncel canonical order/billing/refund/cancellation ve immutable entitlement/price
bağını Store.source → Entitlements.valid → CloudServices.purchase → RegistrationEligibility
üzerinden yeniden doğrular. Bu kaynak okumaları ve current service status FOR UPDATE aynı
uygulama transaction'ındadır. Dış inspect hâlâ transaction dışındadır.

Financial containment sırasında unknown() yalnız operation ve VM power görünümünü UNKNOWN yapar;
service'in bağımsız durumunu ezmez. Normal provisioning UNKNOWN davranışı değiştirilmez.
Bu ayrım, mevcut doğrulanmış VM için geçerli ACTIVE müşteri hizmeti ile bilinmeyen fiziksel power
state'in farklı kavramlar olduğunu gösterir; yeni ve doğrulanmamış VM ACTIVE yapılmaz.

Başarılı STOP task/resource doğrulamasında:
- Güncel ACTIVE ve halen geçerli canonical kaynak → service ACTIVE korunur; VM STOPPED olur.
- Sonradan FULL refund veya order cancellation → service SUSPENDED; VM STOPPED olur.
- Bağımsız SUSPENDED/CANCEL_SCHEDULED/TERMINATING/TERMINATED engelleri korunur.
- Legacy service UNKNOWN → audit ACTIVE olsa ve ödeme geçerli kalsa dahi SUSPENDED;
  otomatik reactivation yoktur. Ayrı yetkili değerlendirme gerekir.

Task/resource/account/ref/generation/fingerprint/lease/version kanıtı, finans kaynağı bozulsa da
kaybedilmez. Yeterli kanıt varsa fiziksel STOP veya TERMINATE mevcut operation üzerinde sonuçlanır.
Yeterli task sonucu yoksa UNKNOWN kalır; yeni mutation gönderilmez. Doğrulanmış termination'ın
TERMINATED sonucu ve mevcut onay/waiting/dispatch fence kontrolleri aynen korunur.
Finans günlükleri değiştirilmez. STOPPED, sağlayıcı maliyetinin durduğu anlamına gelmez.

## Karşı-örnekler ve yeni ayrı MariaDB regresyonları

Yeni `tests/cloud-final-audit.php`: **12 test**. Gerçek native Identity admin session,
MFA/step-up ve exact customer/service grant ile çalışır; sahte staff Principal ile containment
başlatılmaz. Provider task/resource sentetiktir; PHP/MariaDB süreçleri gerçektir.

| # | Senaryo | Sonuç |
|---|---|---|
| 1 | Önceden FULL refund + SUSPENDED → STOP/task success | STOPPED; service SUSPENDED; tek POST/effect; finans hash aynı |
| 2 | Ödenmiş ACTIVE → STOP timeout-after-effect → fiziksel STOPPED → sonradan FULL refund → inspect/reconcile | STOPPED; service SUSPENDED; ikinci STOP yok; finans hash aynı |
| 3 | ACTIVE → STOP UNKNOWN → sonradan order CANCELLED → reconcile | Eski ACTIVE geri gelmez; service SUSPENDED |
| 4 | STOP UNKNOWN → pending/unverified task → refund → resolve denemesi | Resolve reddi; operation UNKNOWN; sıfır uygulanmış effect, tek POST |
| 5 | Kaynak geçerli, bağımsız suspension yok → UNKNOWN/reconcile | Mevcut service ACTIVE korunur; fiziksel STOPPED ayrı; billing_stopped iddiası yok |
| 6 | Finans geçerliyken STOP öncesi bağımsız SUSPENDED → timeout | Suspension UNKNOWN ile ezilmez; reconcile sonrası SUSPENDED |
| 7 | STOP UNKNOWN sonrasında bağımsız SUSPENDED → reconcile | SUSPENDED korunur |
| 8 | Legacy UNKNOWN, tarihsel audit ACTIVE, halen ödenmiş kaynak | Fail-closed SUSPENDED; snapshot'tan reactivation yok |
| 9 | Aynı observation/confirmation ile ikinci resolve ve yeniden inspect | Reddedilir; tek POST/effect, değişmeyen resource version ve finans hash |
| 10 | Cloud receipt/job SUCCEEDED; operation WAITING_TASK | Ayrı PHP `cloud:work --once` tamamlar; tek CREATE |
| 11 | Cloud receipt/job SUCCEEDED; geçici approval hold nedeniyle PENDING | Approval hold kaldırılınca iki ayrı PHP cron claim/poll ile tamamlar; tek CREATE |
| 12 | Domain receipt/job SUCCEEDED; approval hold nedeniyle PENDING | Uygun PROVISIONING durumuna döndüğünde ayrı PHP domain cron tamamlar; ikinci register yok |

Refund/cancel testleri canonical billing/order state'ini sentetik test verisinde değiştirir;
canlı ödeme iadesi veya banka/PayTR akışı çalıştırmaz. Finans hash'i journals/payments,
immutable billing requests, billing states ve orders'ın reconcile öncesi/sonrası eşliğini kapsar.
Task advance, mevcut provider task'ını tamamlar; yeni mutation veya uydurulmuş task ID oluşturmaz.

## P1 — Receipt, completion ve cron

Queue teslimi, durable operation'ın tamamlanması değildir. Cloud PENDING/WAITING_TASK için
başarılı receipt sonrası cron aynı source/lease/fence/task kapılarıyla ilerler. İş bekliyorsa
receipt'i geri almak veya aynı POST'u yeniden göndermek gerekmez.
Domain EventSink yalnız sağlıklı PENDING için FAILED üretmek yerine receipt kaydeder ve SUCCEEDED
döner. Domain'in UNKNOWN/MANUAL_REVIEW için mevcut attention davranışı korunur; bu fiziksel
belirsizlik sağlıklı PENDING ile karıştırılmaz. F4-C source/claim/failover/provider sözleşmeleri
ve eski UNKNOWN queue regresyonu değişmez. Domain modeline hayali WAITING_TASK durumu eklenmez.
Üç cron devam testi gerçek ayrı PHP process üzerinden hem completion hem tek effect'i doğrular.

## Testler / başarısızlık kayıtları

Yerel PHP 8.4.24 / MariaDB 10.11.18: yeni 12/12; mevcut cloud-audit 15/15, cloud 12/12,
cloud-races 7/7, domains-audit 12/12, cloud-sdk 1/1; toplam 59 ilgili regresyon başarılı.
PHPStan, Composer locked audit, 284 PHP lint/mimari ve 294 dosya pattern taraması başarılıdır.
Pattern taraması tam secret/vulnerability denetimi değildir.

- Eski kaynakta yeni 12 testte **6 failure**: sonradan refund, cancel, önceden suspension,
  legacy UNKNOWN, repeated reconciliation'da ACTIVE ve Domain PENDING receipt reddi.
  [Önceki ham karşı-örnek çıktısı](evidence/f4-d-final-audit/before.txt).
- İlk düzeltme denemesinde Cloud testleri geçti; Domain devam testi QUEUED'e geri dönüyordu.
  Mevcut Domain claim sözleşmesi PROVISIONING/UNKNOWN ister. Fixture doğru PROVISIONING'e
  düzeltildi; claim veya source koruması gevşetilmedi. Sonuç **12/12**.
  [Son ham karşı-örnek çıktısı](evidence/f4-d-final-audit/after.txt).
- Önceki 313 testin hiçbir dosyası, assertion'ı veya fixture'ı değiştirilmedi; test silme/ignore yok.
  Yeni suite tam CI'a eklenir: beklenen toplam 46 suite / 325 test.
- Kaynak branch CI iki job'da SUCCESS; başarısız kalite/test adımı yoktur. PR tetiklemeli
  paralel koşum 40112739546'nın jobs/steps sorguları ReadTimeout verdi; bu erişim hatası test
  hatası veya PASS sayılmadı. Alternatif PR check bağlantısı doğru source SHA'yı doğruladı;
  aynı SHA'nın tamamlanmış branch CI günlükleri okunup 46/325/0 sonucu ayrı ayrı çıkarıldı.

## Dosyalar / migration / geri dönüş

Kaynak commit'inde yalnız:
- `src/Cloud/Engine.php`: tarihsel state restore kaldırıldı; financial containment UNKNOWN ayrımı.
- `src/Services/CloudServices.php`: güncel status transaction + FOR UPDATE ile okunur.
- `src/Domains/EventSink.php`: sağlıklı PENDING teslimi; F4-C güvenlik davranışı korunur.
- `tests/cloud-final-audit.php`: 12 yeni regresyon ve ayrı PHP cron süreçleri.
- `.github/workflows/f0.yml`: yalnız yeni suite satırı.
- `docs/F4_D_FINAL_AUDIT_FIX_PLAN.md`, `docs/adr/ADR-013-cloud-current-service-state.md`.

Yeni tablo/migration gerekmiyor; **001–045 byte/hash eşliği korunur**, 046 eklenmedi.
Geri dönüş: cron durdur, UNKNOWN replay yapma; tutarlı kod/DB/APP_KEY restore veya forward-fix.
Eski snapshot→ACTIVE hatalı sürümüne otomatik rollback yok. Şema geri alma işlemi yoktur.
Plan ve karar: [plan](F4_D_FINAL_AUDIT_FIX_PLAN.md), [ADR-013](adr/ADR-013-cloud-current-service-state.md).

## Açık sınırlar / durma noktası

Yalnız test/mock kanıtıdır. Gerçek Voyar/registrar/PayTR/WHM/SMTP veya production açılmaz.
Gerçek saatlik maliyet veya STOP'un maliyet durdurması kanıtlanmış değildir. Yeni HTTP/operatör
arayüzü veya ücretli işlem eklenmez. Legacy UNKNOWN için konservatif suspension, otomatik
reactivation yerine ayrı yetkili inceleme gerektirir. DOMAIN-002 **PARTIAL/MANUAL_REVIEW**,
G-V01–04 ve diğer GATE'ler **OPEN** kalır. F4-E/F/F5, merge ve production'a geçilmedi.
Bu hedefli F4-D düzeltmesi için bağımsız yeniden kabul talep edilir.
