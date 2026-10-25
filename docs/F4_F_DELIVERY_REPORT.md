# F4-F — Domain / Cloud panel ve HTTP güvenlik teslimi

25 Ekim 2026. Repository `Boydaksu/hostidercp`, dal `codex/f4-domain-cloud`.
Yalnız test/mock; bağımsız yeniden kabul istenir. Tam F4 canlı çıkışı veya production kabulü değildir.
Kullanıcı F4-C/D/E tabanlarını kabul etti: F4-E uygulama `9cc105c6ed4d00099f5892a894d2b772ec52ba52`,
başlangıç HEAD `7135e7c37dea1d7889686cf06333736706a81a27`, 48 suite / 355 test.

## Gerçek kaynak ve CI

GitHub uygulama SHA: `131859cbb5c02e5e6d2f63b8af928c63edc78782`.
Yerel metadata commit `9c6cfc47f52186689447062f1746c551cc17c938` ile aynı
`4f876496d6ea86269ed87820badca5abda8b00d2` tree ve sıfır dosya farkı doğrulandı;
yerel HEAD gerçek GitHub SHA'ya hizalandı. [Kaynak commit](https://github.com/Boydaksu/hostidercp/commit/131859cbb5c02e5e6d2f63b8af928c63edc78782),
[taslak PR #2](https://github.com/Boydaksu/hostidercp/pull/2).
32 kaynak/plan/fixture/kanıt dosyasının blob/SHA256 manifesti [publication.json](evidence/f4-f/publication.json).

Tam PR CI [40172108437](https://github.com/Boydaksu/hostidercp/actions/runs/40172108437):

- [PHP 8.3.36 / job 121104941027](https://github.com/Boydaksu/hostidercp/actions/runs/40172108437/job/121104941027): **49 suite / 383 test / 0 hata**, SUCCESS.
- [PHP 8.4.27 / job 121104940912](https://github.com/Boydaksu/hostidercp/actions/runs/40172108437/job/121104940912): **49 suite / 383 test / 0 hata**, SUCCESS.

Branch CI [40172103606](https://github.com/Boydaksu/hostidercp/actions/runs/40172103606) da
[PHP8.3 job121104926180](https://github.com/Boydaksu/hostidercp/actions/runs/40172103606/job/121104926180) ve
[PHP8.4 job121104926430](https://github.com/Boydaksu/hostidercp/actions/runs/40172103606/job/121104926430)
için ayrı ayrı 49/383/0 SUCCESS. MariaDB10.11.19. Dört ham job günlüğünden sayım doğrulandı.
Composer audit advisory yok; PHPStan hata yok; 309 PHP lint / 0 ihlal; 321 pattern dosyası / 0 bulgu.
10 hizmet HTTP, 30 Commerce HTTP ve 20 session concurrency tekrarı iki sürümde de sıfır failure;
başarısız tekrar gizlenmedi. Üç JS syntax kontrolü de başarılıdır. [Ayrı job/suite/worker kanıtı](evidence/f4-f/ci.json)
ham log API adreslerini, 28 yeni HTTP PASS satırını ve mevcut 1/4/8 worker ölçümlerini içerir.

PR test checkout'u `0c83c18859e8443348654937b98dea1544ace503` fetch edilerek aynı
`4f876496d6ea86269ed87820badca5abda8b00d2` tree ve sıfır dosya farkı doğrulandı.
Branch job checkout'u doğrudan uygulama SHA'sıdır. Bu doğrulama target/main merge değildir;
PR taslak ve birleştirilmemiştir. Sonraki rapor/kanıt commit'i uygulama kaynaklarını değiştirmez.

## Gereksinim / ekran envanteri

FR-ID/DOMAIN/VOYAR; SEC-001–004, DOMAIN-001/003, PROV-001/002/006,
FIN-010, OPS-001/005; R1-05/06/07, R4-01/02/04/05/07/10, R5-04/07, R7-06/07.
Plan [F4_F_PLAN](F4_F_PLAN.md); karar [ADR-015](adr/ADR-015-provider-panel-http-boundaries.md).

| Ekran | Uygulanan işlev |
|---|---|
| `/client/domains` | Kendi domain listesi, canonical name, nameserver, expiry, kilit, hizmet/dönem/yenileme ve REGISTER/RENEW/UNKNOWN/MANUAL_REVIEW ayrımı. Mevcut hizmet yenileme paneline bağlantı. |
| `/client/cloud` | Fiziksel VM ile finansal hizmet durumu ayrı; exact configuration, 30 DAYS prepaid satış fiyatı; izinli start/stop/restart, aynı OS reinstall ve beklemeli terminate. Task kabulü başarı değildir. |
| `/admin/domains` | Açık müşteri kapsamı ve kayıt başına service grant; operation/attempt/account/lease/hold, UNKNOWN inceleme işi ve ayrı kanıtla resolve. |
| `/admin/cloud` | Scoped resource/account/task/generation/lease/observation; finans kaynağı geçerliliği; UNKNOWN/reconciliation; native güvenli finansal istisna STOP/TERMINATE. |
| `/admin/cloud-costs` | Scoped cost work, COSTED_MOCK/UNVERIFIED/REVIEW, exact native currency/net/tax/total ve raporlama FX; invoice difference/MATCHED_MOCK; öneri, ayrı onay, ayrı Commerce yayını. |

Sayfa HTML'i native authentication/MFA ister; yalnız boş bir public shell üzerinden yetki
varsayılmaz. Identity paneline yeni ekran bağlantıları eklenmiştir. Veriler fetch ile güncellenir;
tam sayfa refresh gerekmez. Dinamik içerik textContent/DOM ile yazılır; inline script/innerHTML yoktur.
UI erişilebilir form label'ları, modal explicit confirmation, aria-live ve dar ekran stilleri kullanır.
Liste 50 kayıt ve offset sayfalama ile sınırlıdır. Operation/history alt listeleri de bounded'dır.

Transfer, EPP/contact export, DNS/nameserver mutation, credential/root/VNC retrieval ve ücretli
plan/custom yapılandırma için bu panelde yeni iş akışı **uygulanmadı ve kapalıdır**. Katalogda PID/OS
bulunması satın alma hakkı değildir. UPGRADE panelde sunulmaz; endpoint izinli kind listesinde yoktur.
Domain yenileme mevcut Services.Renewals fiyat/order/period ve ödeme akışını kullanır; yeni registrar
renew/transfer POST'u controller'dan gönderilmez. Yeni catalog mapping/import CRUD ekranı yoktur;
mevcut account/resource/provenance bağları okunur. Imported kaynağa yeni CREATE yolu açılmaz.

## Endpoint ve izin matrisi

API prefix: `/api/v1/{client|admin}/provider-panel/`. Body/query customer override müşteri için
yasaktır; staff'ın customer seçmesi global erişim vermez. Native identity_resource_scopes ile
gerçek service ownership doğrulanır. Opaque UUID dışında serbest SQL sort veya tablo seçimi yoktur.

| Route / method | Permission / kontrol |
|---|---|
| `status` GET | Native session, MFA ve services.view; impersonation ret |
| `list?kind=DOMAIN|CLOUD&offset=…` GET | Her dönen satırda services.view + customer/service ownership; staff exact grant |
| `detail?id=…` GET | services.view + ownership; müşteri projection'ında provider account/resource/task/secret/contact yok |
| `costs?id=…` GET | Staff + scoped finance.view; customer 403 |
| `prepare` POST | Native fresh step-up, CSRF, Idempotency-Key, strict action/payload alanları; salt yerel plan + confirmation |
| `execute` POST | Yukarıdakiler + accepted=true, actor/session/identity/customer/service/action/payload/state bound tek kullanımlık confirmation; replay güncel yetki kontrolü |
| `containment-prepare` POST | Yalnız native admin MFA/fresh step-up + scoped services.manage; gerekçe + mevcut fiziksel kaynak inspect; native bound confirmation |
| `containment-execute` POST | Aynı native personel oturumu, resource/account/version fence, one-use/explicit irreversible acceptance, policy waiting; yalnız STOP/TERMINATE |

Cloud müşteri `cloud.power` yalnız kendi hizmetinde START/STOP/RESTART/REINSTALL/TERMINATE içindir;
staff normal mutation `services.manage` ister. CREATE/UPGRADE, finance ve reconciliation yetkisi
vermez. Normal mutation'da canonical kaynak geçerli, service ACTIVE, VM actionable ve başka
in-flight operation yok olmalıdır. REINSTALL payload'ı satın alınan aynı osid olmalıdır.

Staff inspect/resolve ayrıca scoped services.reconcile + services.manage ister. Proposal approval
scoped finance.approve/finance.view ve native pricing.manage; publish ayrıca catalog.manage ve
mevcut Commerce state-bound confirmation ister. Approval fiyatı yayımlamaz. auto_publish_allowed
false kalır; geçmiş paid order/item/price/bill/entitlement değişmez.

Mutation GET/DELETE ile açılamaz (405); API-key/Bearer üzerinden bu yeni cookie endpoint'lerine
ayrı erişim açılmadı. Tüm verili sayfa/JSON cevapları no-store/CSP/nosniff/referrer-policy kullanır.
Containment genel idempotency transaction'ında çalışmaz: native read/verify sınırları korunur;
duplicate execute 409'dur ve ikinci operation/POST oluşturmaz. Queue/task polling endpoint'i
tarayıcı yenilemesiyle fiziksel mutation tekrarlamaz; yeni operasyon ancak ayrı açık onayla oluşur.

## Application service ve transaction sınırları

Yeni `src/ProviderPanel/{Access,Views,Commands,Confirmations,Controller,Panel,Inspections}.php`.
Controller doğrudan registrar/Voyar mutation yapmaz, Finance/Ledger/Catalog tablolarına yazmaz.
Commands mevcut Cloud.Engine, DomainReconciliation ve CloudCostProposals/Commerce servislerini
çağırır. Normal execute: mevcut Idempotency transaction'ı + native Identity kilidi + state/nonce
kontrolü + canonical operation/outbox + nonce consume atomiktir. Sonuç cache'i encrypted'dır.

Cloud prepare/enqueue/resolve ve Domain resolve yalnız yerel işlem composition'ı için açık
transaction'a katılır. Worker claim/send/poll/inspect transaction sınırları değiştirilmez.
Cloud native confirmation, generation, dispatch lease/fence, task/resource hesabı ve entitlement
korumaları alt katmanda tekrar uygulanır. Native termination confirmation TTL waiting+300'dür;
policy wait >300 saniye olduğunda confirmation daha bekleme bitmeden sona ermez. Çalışan provider
task'ı ACCEPTED diye ACTIVE/TERMINATED yapılmaz; eski doğrulanmış power state ayrı kalabilir.

Read-only inspection yeni `panel_inspections` metadata'sıyla mevcut queue/outbox'a bağlıdır.
HTTP yalnız durable isteği kaydeder. `panel:inspect` bağımsız kısa cron'dur; native current session,
identity version, scope ve canonical state'i yeniden doğrular; provider read transaction dışında,
evidence commit mevcut modül fence'leriyle yapılır. Inspection lease kaybı REVIEW'dur; unsafe
mutation retry yoktur. Mevcut queue handler'ı teslimi sonuçlandırabilir; event receipt,
inspection sonucu ve provider completion birbirinin yerine geçmez. PENDING iş ayrı cron'da
sürer; eski Domain/Cloud receipt ve 1/4/8 worker regresyonları ayrıca aynen çalışır.

Financial containment native Cloud.Containment uygulama servisidir. Gerekçe/session referansı
encrypted kalır; history finansal istisnayı ve aktörü kaydeder. Refund sonrası normal işlem
reddedilir; yetkili STOP mevcut kaynağı durdurabilir, service SUSPENDED kalır. UNKNOWN kör tekrar
açmaz. STOPPED sağlayıcı maliyeti sıfır değildir; doğrulanmış termination da final invoice veya
payable settlement kanıtı değildir. Finans kayıtları hash ile değişmeden doğrulanır.

## Testler ve gerçek HTTP kanıtı

Yerel PHP8.4.24 / MariaDB10.11.18 / loopback interpreter: **49 suite / 383 test / 0 hata**.
355 eski + 28 yeni HTTP regresyonu. PHPStan başarılı; Composer locked audit 0 advisory;
309 PHP lint/mimari dosyası 0 ihlal; 321 source/config/test pattern scan 0 bulgu; JS syntax başarılı.
Pattern scan tam secret/vulnerability denetimi değildir. [Yerel sayım](evidence/f4-f/local.json),
[28 ham HTTP PASS satırı](evidence/f4-f/local-http.txt).

Yeni suite gerçek iki ayrı HTTP server, cookie/session, native MFA/step-up, cURL request/response,
MariaDB ve bağımsız PHP HTTP/cron süreçleri kullanır. Sadece class metodu çağrısı değildir:

1. HTML/API authentication ve yanlış method; native login/MFA/step-up zinciri.
2. Müşteri projection'ında power/financial durum ve 30 günlük satış ayrımı; secret alanları yok.
3. Farklı müşteri/service ID ve override; exact staff service grant; customer cost projection reddi.
4. CSRF, false acceptance, strict payload, satın alınmayan OS/PID/custom field retleri.
5. Salt prepare; payload tamper; stale service version ve expired confirmation.
6. Duplicate submit ve replay; tek operation/POST; WAITING_TASK ayrı, sonra task/resource doğrulaması.
7. İki bağımsız HTTP server: aynı confirmation ile 200/409; aynı key ile aynı operation ve 200/200.
8. Stale step-up, session revocation, identity version ve permission revocation.
9. FULL refund: normal mutation ret, native scoped staff STOP; SUSPENDED/STOPPED ayrı, finans hash aynı.
10. STOP timeout-after-effect → UNKNOWN → durable inspect → ayrı PHP cron → evidence resolve; ikinci POST yok.
11. Domain UNKNOWN/attempt/nameserver/contact gizliliği; bağımsız inspect/resolve sonrası REGISTERED.
12. UNVERIFIED sıfır değildir; invoice difference; öneri ≠ onay ≠ Commerce-confirmed yayın.
13. Meşru aynı OS reinstall ve START/STOP/RESTART; native TERM wait + paralel çift istek/tek terminate.
14. Prepare sonrası refund; XSS/no-store/CSP; CREATE task ACCEPTED iken service/VM PROVISIONING.
15. Impersonation ret; stale staff worker incelemeyi REVIEW tutar; MATCHED_MOCK ödeme değildir;
    ikinci ayrı confirmation ilk istek kazanınca stale olur.

[Gerçek sentetik HTTP çıktıları](evidence/f4-f/http-examples.json): client Cloud, client Domain,
admin Domain UNKNOWN, refund sonrası admin containment, admin cost/invoice ve authentication'lı
client HTML. Cookie, CSRF, session reference, password, auth header ve canlı PII içermez.
Görseller üretilmedi; sağlanan kanıt gerçek HTTP çıktı/projection'larıdır.

Örnek güvenli istek gövdeleri (gerçek oturum/CSRF/Idempotency-Key değerleri yayımlanmaz):

```json
{"action":"cloud-action","payload":{"service_id":"<owned-service-uuid>","kind":"STOP","data":{}}}
```

Prepare id'siyle ayrı execute `confirmation:{id,accepted:true}` ister; response PENDING operation
UUID'sidir. GET detail WAITING_TASK/UNKNOWN gösterebilir; bu yanıt fiziksel başarı vaadi değildir.
Staff inspect örneği `action:inspect,payload:{service_id,operation_id},customer` → inspection_job_id;
cron sonrası result_id ile ayrı resolve hazırlanır. Kaynak/kanıt alanı farklı VM'ye taşınamaz.

## Migration, eski testler ve geri dönüş

Yalnız additive `048_provider_panel.php`: dar cloud.power izni, panel_confirmations ve
panel_inspections. 001–047 **47/47 byte/hash eşliği** [manifestte](evidence/f4-f/migrations.json).
Eski Cloud/Domain/maliyet migrate-upgrade/replay testleri 048'i de uygular; exact listelerine
yalnız yeni migration adı eklendi. Eski assertion/test silinmedi veya ignore edilmedi.
Domain fixture'a optional sharedConfig eklendi; eski default davranış korunur. Yeni ortak HTTP
fixture'ı Cloud/Domain için aynı test APP_KEY'yi materialization öncesinde kullanır.

Rollback: panel ve panel:inspect cron kapatılır. UNKNOWN mutation replay edilmez. Canonical
provider operation/financial evidence silinmez. Tutarlı kod/DB/APP_KEY snapshot restore veya
forward-fix; otomatik DROP/downgrade yoktur. Üretim restore/backup provası bu fazda yapılmadı.

## Başarısızlık ve düzeltme kayıtları

- İlk migration taslağındaki toplu identity_version/session revoke adımı otomatik onay denetiminde
  genel F4-F iznini aştığı için reddedildi. Bu adım kaldırıldı; 048'de toplu iptal yoktur.
- İlk HTTP testinde yanlış method için 404 beklenmişti; mevcut Router sözleşmesi 405'tir.
  Test doğru sözleşmeye düzeltildi; uygulamanın method koruması değiştirilmedi.
- Çok sayıda bağımsız fixture aynı native session'da customer switch yaptığı için 429 görüldü.
  Test senaryolarının sentetik rate-limit sayaçları fixture sınırında ayrıldı; uygulamanın
  Identity veya panel hız sınırı gevşetilmedi.
- İlk birleşik Domain/Cloud fixture'ında farklı APP_KEY ile önceden encrypted service materialize
  edilmişti; ciphertext authentication hatası alındı. Optional sharedConfig materialization
  öncesine taşındı; şifreleme/authentication kontrolü kaldırılmadı.
- PHPStan bir kullanılmayan closure capture buldu; capture kaldırıldı. Ignore/baseline eklenmedi.
  Kısıtlı yerel araç çağrısındaki dosya erişim hatası test PASS sayılmadı; izinli yerel PHP
  ortamında denetim yeniden çalıştırılıp başarı doğrulandı.
- Son yerel tam koşu ve yayımlanan kaynak üzerindeki dört CI job'u sıfır hatadır. GitHub kaynak
  CI'ında başarısız adım/rerun olmadı. Yerel düzeltmeler ve ilk başarısız denemeler yukarıda ayrıdır.

## Açık sınırlar ve durma noktası

G-V01–04, PROV-005 gerçek iki saatlik Voyar kullanım/maliyet/termination kanıtı, gerçek registrar
G-R01/G-D01, G-C01, G-P01/02, G-X01/02, G-O01–04, G-T01 ve diğer canlı GATE'ler **OPEN**.
DOMAIN-002 **PARTIAL/MANUAL_REVIEW**; otomatik registrar failover bu panelle açılmaz.
APP_ENV=test dışında yeni panel/operation/inspection yolları kapalıdır. Gerçek Voyar/registrar/
WHM/PayTR/SMTP, production credential veya gerçek müşteri kullanılmadı.

Yeni güvenli tek kullanımlık credential retrieval ve gerçek vendor field mapping uygulanmadı;
API/UI bunu varmış gibi sunmaz. UI kanıtı native HTTP'dir; bağımsız browser erişilebilirlik ve
production kapasite kabulü iddia edilmez. Para minor-unit string olarak açık currency ile sunulur;
mock forecast gerçek kârlılık/maliyet veya Hostider ticari marj kararı değildir.

F4-F mock tesliminde durulur. F5/F6 geliştirmesi, main/target merge, staging/production veya
GATE kapatma yapılmaz. Bu teslim için bağımsız kabul istenir.
