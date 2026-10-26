# F6 — Aktarım ve operasyon teknik planı

## Yetki ve mevcut taban

Bu belge yalnız plan teslimidir. F6-A kodu dahil hiçbir F6 uygulaması, gerçek WHMCS
erişimi, aktarım, staging veya cutover yetkilendirilmedi. Her alt faz için ayrıca proje
sahibi talimatı ve bağımsız kabul gerekir. SUP-010 **PLANLANDI / NOT PASS**.

Başlangıç belge HEAD'i `fb8a217619196b650d82a89a16b3d2680736cf2d`;
kabul edilmiş F5-E uygulaması `e0d460d0aea05f496135d169ecbb64ce94659cc9`.
F4 ve F5-A/B/C/D/E yalnız sınırlı test/mock kabulleriyle korunur. PHP8.3/8.4
52 suite/582 test, Chromium11, state3 ve10/30/20 tekrar tabanı korunacaktır.
001–054 migration dosyaları immutable; bu plan migration eklemez.
Gelecekte gerekirse yalnız055+ additive, ayrıca tasarım ve yetki sonrasında.

Dayanaklar: [aktarım](11_MIGRATION.md), [yol haritası](12_ROADMAP.md),
[kabul](13_ACCEPTANCE_TESTS.md), [açık kapılar](14_OPEN_VALIDATIONS.md),
[operasyon](10_OPERATIONS.md), [ertelenmiş CRUD](F4_F_MAPPING_IMPORT_SCOPE.md),
[F3](F3_REPORT.md), [F3 operasyon sınırları](F3_POST_DELIVERY_REVIEW.md),
[F4 kabul](F4_REPORT.md), [F5 plan/kabulleri](F5_PLAN.md),
[F5-C istisnaları](F5_C_DELIVERY_REPORT.md), [F5-E kabul](F5_E_DELIVERY_REPORT.md).

## Alt faz bağımlılık ve kabul matrisi

| Faz | Bağımlılık ve önerilen dosya kapsamı | Ayrı kabul kapısı / teslim | Şimdiki durum |
|---|---|---|---|
| F6-A discovery/sözleşme | Ayrı geliştirme yetkisi; docs/F6_A_PLAN ve sözleşme/sentetik fixture tasarımı; gerçek source adaptörü yok | Sürüm/şema belirsizlikleri, extract/mapping/provenance/idempotency ve güvenlik sözleşmesi bağımsız incelenir | PLAN ONLY, NOT STARTED |
| F6-B izole import/dry-run | A test/mock sözleşme kabulü; native application service uygunluğu; ayrı sentetik test şeması | Tekrar/yarış/yarım batch recovery; ikinci provider CREATE sıfır; tüm entity fixture eşlemeleri | PLAN ONLY, NOT STARTED |
| F6-C mutabakat/güvenlik | B izole başarı ve manifest; gerçek kaynak doğrulaması ayrı G-O03 kapısı | Açıklanamayan para/sahiplik/dosya farkı sıfır; SUP-010 ve PROV-004 sentetik kanıtı ayrı etiketli | PLAN ONLY, NOT PASS |
| F6-D operasyon hazırlığı | A–C test/mock incelemesi; backup/key/runtime olanaklarının envanteri | İzole restore, migration failure, overlap/DLQ ve rollback/forward-fix provası | PLAN ONLY; staging izni değil |
| F6-E canlı geçiş kapıları | A–D kabulü, G-O03 ve tüm ilgili vendor/operasyon kapıları; ayrıca açık canlı yetki | İmzalı cutover/fence/delta/callback/single-actor kontrol listesi | PLAN ONLY; canlı işlem yok |

Önerilen kod konumları bağlayıcı şema kararı değildir: ancak ayrı yetkiden sonra
`src/Migration/`, `tests/migration-*`, `docs/F6_*` değerlendirilir. Native Core,
Identity, Finance, Services, Support ve transactional outbox sözleşmeleri korunur.
Controller'dan başka modül tablolarına yazma veya ikinci Identity/Queue motoru yoktur.
Her uygulama fazı önce kapsam/dosya/migration/test/rollback planını güncellemelidir.

## F6-A — Discovery ve sözleşmeler

Gerçek WHMCS sürümü, kurulu modüller, custom field'lar, şema/kolasyon, dosya
dizinleri, zaman dilimi ve parola formatı henüz doğrulanmadı. Kaynak tablo/alan adı,
hash algoritması veya provider API cevabı uydurulmayacak. **G-O03 OPEN** kalır.
Şimdilik yalnız sentetik, açıkça etiketlenmiş veri sözleşmesi tasarlanır.

İleride ayrıca yetkilendirilecek read-only discovery çıktısı: sürüm/schema fingerprint,
extract zamanı ve watermark, source snapshot SHA-256, entity sayımları, dosya manifest'i,
kaynak timezone/encoding, ilişki ve bilinmeyen alan listesi. Gerçek bağlantı/dump bu
talimatta alınmaz. Extract tutarlı snapshot olmalı; değişen kaynakla sessiz karma veri yoktur.

Kaynak kimliği: source-system-instance + doğrulanmış entity türü + değişmez source ID.
Mapping ve provenance source snapshot/run/transform sürümü/hedef ID/karar kanıtını bağlar.
Aynı source ID + aynı içerik replay'i yeni kayıt/para/provider işi üretmez; değişmiş
içerik sessiz overwrite yerine delta/çatışma incelemesine gider. Mapping anahtarının
tekilliği, transaction sınırı ve kesilen batch checkpoint'i sözleşmede tanımlanır.

Sınıflandırma: PII, finansal tarihçe, credential/hash, private note, müşteri görünür
metin, dosya, operasyon/audit. Sentetik fixture dışında gerçek PII kullanılmaz.
Kaynak credential/token/session taşınmaz; loglarda PII/hash/secret yoktur. Kaynak
staff kimliği otomatik hedef admin/rol/MFA yetkisi sağlamaz. Güncel native Identity,
department/customer scope, MFA/fresh step-up ve impersonation sınırları korunur.
Parola doğrulayıcı yalnız gerçek format ayrıca doğrulanırsa tasarlanabilir; aksi
reset gerekir. Hash uyumu veya rehash-on-login şu anda uygulanmış sayılmaz.

Kabul: eksik/çatışan source ID, farklı snapshot, bilinmeyen hash ve ownership için
fail-closed sentetik sözleşme testleri; sıfır gerçek bağlantı. G-O03 kapanışı değildir.

## F6-B — Sentetik izole import/dry-run

Her prova ayrı, private, geçici test şeması ve web dışı dosya alanıyla tasarlanır.
APP_ENV tek başına yeterli değildir: provisioning/payment/external mail fail-closed,
gerçek endpoint/credential yok, mock transport ve egress izin listesi gerekir.
Dry-run kalıcı hedef entity veya provider işi üretmez; öneri ve hata manifest'i verir.
Load için validate → mapping claim → native service → provenance/checkpoint aynı
transaction sınırında tasarlanır; dış I/O açık transaction'a alınmaz.

| Entity | Korunacak ilişki/kanıt | Engelleyici belirsizlik |
|---|---|---|
| Customer/user | Source ID, üyelik, aktif/pasif durum, doğrulanmış ownership | Çakışan kimlik/e-posta; eski staff yetkisinin mirası yasak |
| Order/service | Order ≠ payment ≠ service ≠ provider operation; dönem/sahiplik/origin | Ödeme veya aktif status bilinmiyorsa MANUAL_REVIEW |
| cPanel/Voyar/domain | Mevcut external resource + account/provider + hedef owner eşliği, imported origin | Duplicate resource, yanlış hesap, UNKNOWN/in-flight: MANUAL_REVIEW; CREATE yasak |
| Wallet | Hesap para birimi, immutable kaynak fişleri ve bakiye mutabakatı | Tutar/para/kur belirsizliği; float ve tahmini FX yok |
| Payment/allocation/refund | Özgün dış referans, tutar, currency, tarih, kısmi dağıtım | Tahsilat kanıtı yoksa paid/provisioning varsayılmaz |
| Official document/proforma | Ayrı belge türleri, eski numara, immutable tutar/tarih, SHA-256 | Eksik PDF/yanlış sahip; yeni resmi belge numarası üretilmez |
| Ticket/message | Orijinal ID, kronolojik sıra, timezone/tarih, yazar ilişkisi ve visibility | Belirsiz author/private flag: müşteri görünürlüğüne açma |
| Attachment/file | Owner/ticket/message bağları, boyut/MIME/özel storage, SHA-256 | Eksik dosya, traversal/symlink, bozuk hash, belirsiz erişim |

Imported cPanel/Voyar/domain load, replay, queue replay ve worker yarışları
**sıfır ikinci CREATE** sağlamalıdır. Bir mapping eklemek paid order keşfini veya
notification dispatch'ini tetiklememelidir. Bu sözleşme gelecekte native imported
origin guard ve uygulama servisleriyle doğrulanır; CRUD ayrı yetki bekler.

Kabul: aynı snapshot iki load, kesilmiş batch resume, farklı içerikle aynı ID,
1/4/8 bağımsız süreçte tek target/mapping; stale/revoke/IDOR/CSRF/idempotency.
Kaynak/finans geçmişi değişmez. Test/mock sonucu gerçek aktarım sayılmaz.

## F6-C — Mutabakat ve güvenlik

Manifest alanları: entity/source_count/target_count/unmatched_count,
currency bazında original/debit/credit/allocation/refund totals, snapshot/run/transform
provenance, owner/resource mismatch, SHA-256 mismatch, reviewer ve zaman.
Toplamlar para birimleri arasında toplanmaz; minor-unit integer/kesin decimal ve
açık yuvarlama kullanılır. Bilinmeyen tarihsel kur ayrı legacy_unknown kanıtıyla
**MANUAL_REVIEW** olur; yeni kurla geçmiş yazılmaz. Bilinmeyen payment/provider
status/ownership de MANUAL_REVIEW; iş sahibi kanıtsız paid/active kabul edemez.

| Kabul ID | Planlanan izole kanıt | Canlı/gerçek kabul için ek kapı | Şimdiki sonuç |
|---|---|---|---|
| SUP-010 | Ticket/mesaj sayımı, aynı orijinal sıra/tarih; ek SHA-256; private note customer API/export/notification/attachment'ta görünmez; native staff scope | Gerçek kaynak şeması/dosyaları G-O03; gerçek arşiv mutabakatı | NOT PASS |
| PROV-004 | Imported cPanel/Voyar/domain replay/yarış sonrası tek mapping, provider CREATE sayısı0; native yönetim yetkileri | Read-only gerçek provider identity/resource doğrulaması ayrıca yetkili | F3 mock guard korunur; F6 import NOT PASS |
| OPS-002 | Tutarlı DB+attachment+official PDF manifest'i; authenticated encrypted copy; ayrı anahtar erişimi; izole restore checksum | Gerçek dış hedef/hosting, RPO/RTO provası ve sahibin kabulü | F6 NOT PASS |
| OPS-003 | Preflight/backup doğrulaması; bozuk DDL APPLYING/readiness ret; restore veya incelenmiş forward-fix | Gerçek update/runtime ve geri dönüş provası | F6 NOT PASS |
| OPS-004 | Sentetik kaynak/hedef delta/count/currency reconciliation; çift actor/fence/callback senaryoları | WHMCS fence, final delta ve gerçek cutover için açık yetki | NOT PASS |

Açıklanamayan finans farkı0, eksik aktif service mapping0, eksik resmi PDF/ek0,
checksum/ownership farkı0 planlanan kapılardır; bugün ölçülmüş başarı değildir.
Kaynak yazar UUID'si müşteriye dönmez; yeni/legacy client projection redaksiyonu
ve can_attach server authorization sınırı korunur. Import edilmiş message order
aynı timestamp durumunda doğrulanmış source sequence/ID ile deterministik olmalıdır;
bilinmeyen timezone sessiz dönüştürülmez.

## F6-D — Operasyon hazırlığı

Tutarlı DB/dosya/manifest snapshot'ı, authenticated encryption, web dışı anahtar
yönetimi ve ayrı izole restore planlanır. Gece yedek + dış şifreli kopya,
14 günlük/8 haftalık/12 aylık retention master kararı korunur. PITR/saatlik erişim
G-O02 doğrulanmadan RPO sözü verilmez. Restore sonrası auth, finans, özel dosyalar,
source mapping ve queue/outbox tekilliği doğrulanır.

Scheduler overlap ve worker claim/lease/fence bağımsız süreçlerle sınanır; stale lease
ve UNKNOWN kör tekrar edilmez. DLQ/poison batch, bounded time/memory/batch, disk dolması,
DB kesintisi, clock/timezone ve şifreli dosya/anahtar kaybı için kontrollü duruş
tasarlanır. Heartbeat, oldest job, UNKNOWN, reconcile farkı, backup yaşı ve son restore
izlenir; alarmlar test alıcıları/mock ile, PII'siz ve dedupe'lıdır.

Rollback: yalnız izole synthetic DB/files aynı tutarlı snapshot'a döner. Immutable
finans geçmişi silerek düzeltme yoktur. Gerçek cutover sonrası yeni ödeme/provider
etkisi varsa eski DB'ye kör restore güvenli sayılmaz: delta reverse-import ve state
reconciliation veya incelenmiş forward-fix gerekir. Owner ve karar penceresi ayrıca
belirlenir; destructive down otomatik değildir. Bu faz staging izni değildir.

## F6-E — Canlı kapılar ve kontrol listesi (uygulanmayacak)

- [ ] G-O03 gerçek WHMCS sürümü/şeması/hash/dosya ilişkileri bağımsız doğrulandı.
- [ ] Kaynak/hedef kapsam, maintenance penceresi, sorumlu/reviewer ve rollback eşiği onaylı.
- [ ] Restore provası ve anahtar erişimi; DB/dosya snapshot bütünlüğü; kapasite/cron runtime kanıtı.
- [ ] WHMCS cron, provisioning, billing/notification mutation teknik fence'i doğrulandı; eski erişim read-only.
- [ ] In-flight create/renew/transfer/terminate, UNKNOWN ve kısmi ödeme ayrı reconcile edildi.
- [ ] Final delta watermark/snapshot ve mapping tekilliği; sayım/tutar/hash/owner farkı0.
- [ ] PayTR callback routing, eski dış referanslar, retry/idempotency ve geri dönüş rotası doğrulandı.
- [ ] Tek aktif automation actor ve fencing kanıtı; çift sağlayıcı işlemi/bildirim yok.
- [ ] Gerçek mailbox/retention/AV/provider/finans kapıları kapandı; notification dispatch ayrı açık onaylı.
- [ ] WHMCS arşiv erişimi, lisans/saklama ve güvenli dosya indirmeleri doğrulandı.
- [ ] main protection ve bağımsız release incelemesi tamamlandı; proje sahibi canlı cutover'ı açıkça yetkilendirdi.

Hiçbir kutu bu belgeyle işaretlenmez. Şu anda gerçek kullanıcı verisi/credential,
PayTR/WHM/Voyar/registrar/SMTP/IMAP çağrısı, gerçek restore/cutover veya deploy yoktur.

## Açık bağımlılıklar ve durma noktası

DOMAIN-002 **PARTIAL/MANUAL_REVIEW**; mapping/import CRUD **DEFERRED** ve ayrı
yetki gerektirir. PROV-005, G-V01–04/G-P01–02/G-C01/G-R01/G-D01/G-X01–02,
G-O01–04 ve G-T01 dahil canlı kapılar OPEN kalır. Özellikle G-O03 OPEN;
SUP-010 henüz PASS değildir. F5-C'nin dört canlı istisnası (gerçek routing/sender
auth; kesin retention cutoff/scheduler; AV/IMAP/SMTP/genel MIME; mock HELD/ACCEPTED'in
teslim olmaması), auto-close, merge/split ve tarihsel SLA rapor doğrulaması açıktır.

Plan kabulü uygulama kabulü değildir. PR3 OPEN/DRAFT; main protection mevcut kapalı
durumuyla korunur. CI/F6 planı bağımsız incelemeye sunulduktan sonra DURULUR;
F6-A kodu, gerçek extract/import, merge/staging/production için yeni talimat beklenir.
