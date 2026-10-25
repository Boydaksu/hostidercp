# 09 — API sözleşmesi ve sayfa haritası

## F4-F uygulanmış test/mock sınırı

HTML: `/client/domains`, `/client/cloud`, `/admin/domains`, `/admin/cloud`,
`/admin/cloud-costs`. Sayfanın kendisi de native oturum/MFA doğrular; impersonation kapalıdır.
JSON: `/api/v1/{client|admin}/provider-panel/{status,list,detail,costs}` GET.
Liste `kind=DOMAIN|CLOUD`, `offset=0..1000000` ile en fazla 50 kayıt; staff açık `customer`
kapsamı seçer; her kayıt ayrıca service grant ve native ownership kontrolünden geçer.
`costs` yalnız staff + scoped finance.view'dur. Raw provider response veya credential yoktur.

`prepare` ve `execute` POST, CSRF ve Idempotency-Key ister. JSON:
`{action,payload,customer?,confirmation?:{id,accepted:true}}`. Desteklenen action'lar:
`cloud-action` (`service_id,kind,data`), `inspect` (`service_id,operation_id`),
`resolve` (`service_id,operation_id,evidence_id,outcome`),
`proposal-approve` / `proposal-publish` (`service_id,proposal_id`). Her kritik aksiyon
fresh native step-up, scope ve ayrı state/session/payload-bound confirmation ister.
Cloud kind START/STOP/RESTART/REINSTALL/TERMINATE; REINSTALL yalnız satın alınan osid,
diğer data boş object. CREATE/UPGRADE/custom/transfer/credential endpoint'i yoktur.
Domain yenileme mevcut `/services/prepare|execute` üzerinden canonical Services.Renewals'a gider.

Staff-only `containment-prepare` POST: `{service_id,customer,kind,reason}`;
`containment-execute` POST: `{service_id,customer,confirmation,accepted:true}`.
Native Cloud.Containment dış kaynak inspect/session/one-use/waiting mekanizmasını korur;
idempotent cached response yerine duplicate execute 409'dur, ikinci mutation oluşturmaz.
STOP maliyet durdurma iddiası taşımaz. Bu iki route genel idempotency transaction'ına alınmaz.

Bağımsız `php bin/console.php panel:inspect` en çok 100 iş/20 saniye/64 MiB yeni iş kabulüyle
mevcut queue/outbox'a bağlı read-only inspection'ları işler; provider mutation yapmaz.
Queue teslimi, inspection kaydı ve fiziksel task completion ayrı durumlardır.
Detaylar: [F4_F_PLAN](F4_F_PLAN.md), [ADR-015](adr/ADR-015-provider-panel-http-boundaries.md).

## API ilkeleri
- `https://.../api/v1/...` JSON; `/admin` ve `/client` frontends farklı oturum ve yetki bağlamında.
- DB internal ID dışarı verilmez; opaque `public_id` ve pagination cursor / whitelisted sort.
- POST/PUT/PATCH/DELETE için CSRF (cookie session) veya uygun Bearer API credentials; tutarlı 401/403/404/409/422/429; client ID override yasak.
- Mutasyonlarda `Idempotency-Key`, `X-Request-ID`; eşzamanlı update `If-Match` / `version`, 409.
- Response envelope: `{data,meta,errors}`; errors `{code,message,details,trace_id}`. Hata kodları API sürümünde sözleşmeli.
- Mutation requests gizli bilgi loglamaz; servis activity timelines yalnız filtreli public veriyi gösterir.

## Örnek endpoint katalogu (tasarım; kodlanmış API değil)
| Kategori | Örnek endpoint | Erişim |
|---|---|---|
| Kimlik | `POST /auth/login`, `POST /auth/2fa/verify`, `POST /auth/logout-all` | owner/actor |
| Müşteri | `GET /customers/current`, `POST /customers/{id}/invites` | owner/admin |
| Katalog | `GET /catalog/products`, `POST /catalog/quotes`, `POST /checkout/orders` | müşteri/public belirli |
| Sipariş | `GET /orders/{id}`, `POST /orders/{id}/submit` | müşteri/admin |
| Ödeme | `POST /payments/paytr/sessions`, `POST /webhooks/paytr`, `POST /wallet/topups` | müşteri/gateway |
| Fatura | `GET /billing/payment-requests/{id}`, `POST /admin/billing/documents` | müşteri/admin |
| Cüzdan | `GET /wallet/balance`, `GET /wallet/entries`, `POST /wallet/allocations` | yetkili müşteri |
| Hizmetler | `GET /services`, `GET /services/{id}`, `POST /services/{id}/renew`, `/cancel`, `/transfer` | owner/role |
| Hosting | `POST /services/{id}/suspend`, `/unsuspend`, `/change-package` | izin/capability |
| Cloud | `GET /services/{id}/vm/status`, `POST /services/{id}/vm/restart` vb. | izin/capability |
| Domain | `POST /domains/check`, `POST /domains/{id}/renew`, `/nameservers`, `/unlock` | izin/TLD |
| Support | `GET /support/tickets`, `POST /support/tickets`, `POST /support/tickets/{id}/messages` | müşteri/department |
| Support admin | `POST /admin/support/tickets/{id}/assign`, `GET /admin/support/sla`, `POST /admin/support/incidents` | departman/role |
| Finans | `GET /admin/finance/cash`, `POST /admin/finance/expenses`, `GET /admin/finance/reconciliations` | finans izni |
| Sistem | `GET /admin/operations/health`, `/jobs`, `/backups`, `/updates` | privileged |
| Entegrasyon | `POST /admin/providers/{id}/test`, `GET /admin/providers/{id}/capabilities` | privileged |

**PayTR webhook** müşteri oturumu kullanmaz; callback doğrulaması HMAC ve pending payment request üstünden yapılır. Bütün iç API endpointleri aynı public haklara sahip değildir.

## Personel ekran haritası
- `/admin/dashboard`: ana operasyon göstergeleri, SLA risk, başarısız provisioning, tahsilat ve cron/backup sağlığı.
- `/admin/search`: müşteriler, hizmetler, domainler, faturalar, ticket'lar, siparişler genel arama (izin filtreli).
- `/admin/customers`: liste, müşteri detay, kullanıcılar/davetler, bakiye, finans, service/ticket timeline.
- `/admin/products`: kategoriler, ana ürün, varyant, opsiyon, addon, döngüler, fiyat yayınları, vergi, promosyon.
- `/admin/orders`: siparişler, manuel onay, ödeme mutabakatı, oluşturma işleri.
- `/admin/services`: hosting/cloud/domain birleşik görünüm, sağlayıcı resource bağlantısı, renewal, transfer, operasyon geçmişi.
- `/admin/providers`: WHM server pool, Voyar account, registrar accounts, ödeme sağlayıcısı, credential audit, health.
- `/admin/domains`: TLD ve registrar politikaları, kayıt/transfer/yenileme işleri, belirsiz statü.
- `/admin/support`: inbox, saved queues, ticket view/compose, departman, SLA/takvim, rules, incidents, CSAT, mail review.
- `/admin/finance`: tek kasa, kategori/gelir/gider/tekrarlayan ödemeler, cüzdan ve ödeme defteri, e-belge yüklemeleri, reconciliation.
- `/admin/system`: roller/permissions, impersonation, sessions, API keys, cron/jobs, dead letters, logs, backups, restore, updates, settings.
- `/admin/migration`: import dry-run, eşleşmeler, sayım karşılaştırması, son geçiş checklisti.

## Müşteri ekran haritası
- `/client/dashboard`: aktif hizmetler, son ödeme, açık talepler, yenileme uyarıları.
- `/client/catalog` + `/client/checkout`: varyant, özel Cloud config, opsiyon, kur/quote/countdown, ödeme.
- `/client/services`: hosting, cloud, domain hizmet listesi, servis detay/işlem, ileri tarihli iptal, auto renew, transfer.
- `/client/billing`: ödeme talepleri/proforma, geçmiş finans belgeleri ve gerçek PDF'leri, ödeme geçmişi.
- `/client/wallet`: hesap para birimi bakiye, yükleme, hareketler, otomatik yenilemeler.
- `/client/support`: ticket listesi, talep aç/cevap/ek/CSAT, bağlantılı incident.
- `/client/account`: profil, alt kullanıcı davetleri/izinler, 2FA, cihazlar, para birimi değişiklik talebi.

## Arayüz davranış normları
- Form başarılı olunca tüm sayfa yenilenmez; ilgili resource JSON döner ve widget/state güncellenir.
- Arka plan sağlayıcı işlemi `PENDING/PROVISIONING` görünür; kaynak kanıtlı ACTIVE olmadan 'hazır' mesajı gösterme.
- Uzun işlerde kullanıcıya progress/son olay/tekrar dene seçeneği (yalnız güvenli işlemler), personel escalation.
- Kritik delete / suspend / refund / service transfer için step-up auth, onay ve özet.
- Düşük bant genişlikli mobil panel işlevsellik korur; erişilebilir odak sırası, klavye kısayolları, ARIA, alternatif uyarılar.
# F3 uygulanmış test/mock hizmet arayüzü

`/admin/services` ve `/client/services` mevcut Identity/Commerce panellerine bağlıdır. API: `/api/v1/{admin|client}/services/{status|list|detail|pools}` GET; `prepare` ve `execute` POST. Liste sayfası en fazla 50 satır döndürür. Pools yalnız yetkili yöneticiye açıktır.

Mutation doğrudan uygulanmaz: prepare salt plan ve beş dakikalık nonce üretir; execute aynı action/payload/customer, `confirmation: {id, accepted:true}`, CSRF ve Idempotency-Key ister. Güncel scope/freshness ve plan durumu yeniden doğrulanır. `inspect` HTTP transaction'ında provider çağırmaz; ayrı durable iş açar. Tam davranış, demo ve sınırlar `F3_REPORT.md` içindedir. Bu ek, canlı endpoint/WHM vendor cevabı tanımlamaz.
