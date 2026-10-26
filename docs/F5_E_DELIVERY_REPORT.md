# F5-E — native destek ekranları, test/mock teslim kaydı

Durum: F5-E test/mock teknik teslimi; bağımsız teknik kabul bekleniyor.
Başlangıç `eb7731d6b955ff2473f4322c7e0e3b8f29e4a0bb`; kabul edilmiş D uygulaması
`3404ddda79bc2017f05cb474b5546e23647910d8`, 51 suite /555 test.
Uygulama SHA: `f226220847390ea3f5c69eef90ad968117d7edc7`.
Kaynak tree: `f7d00105c41f1e53d8f43551cb56df28865069f9`.
[Gerçek kaynak diff'i](https://github.com/Boydaksu/hostidercp/compare/eb7731d6b955ff2473f4322c7e0e3b8f29e4a0bb...f226220847390ea3f5c69eef90ad968117d7edc7).
[Gerçek tam CI SUCCESS](https://github.com/Boydaksu/hostidercp/actions/runs/40334349439)
ve [sanitized kanıt](evidence/f5-e/ci.json) son kaynak için doğrulandı.
Yerel kanıt aşağıdaki exact-tree CI başarısı yerine sunulmaz.

| Job | PHP suite/test/hata | Eski555 kayıp | Chromium | State | Tekrar |
|---|---|---|---|---|---|
| [PHP8.3.36](https://github.com/Boydaksu/hostidercp/actions/runs/40334349439/job/121611807173) |52 /579 /0|0|9 /0|3 /0|Services10, Commerce30, Session20:0 hata|
| [PHP8.4.27](https://github.com/Boydaksu/hostidercp/actions/runs/40334349439/job/121611807412) |52 /579 /0|0|9 /0|3 /0|Services10, Commerce30, Session20:0 hata|

Eski555 PASS adı birebir korundu;24 yeni gerçek MariaDB/native application/HTTP
regresyonu,9 gerçek Chromium ve3 deterministik state kontrolü ayrı sayılır.
Her iki job: PHPStan0, locked Composer audit temiz,383 PHP lint0 ihlal,
402 pattern scan0 bulgu, npm audit0 vulnerability. UI ve korunan worker yarışları
1/4/8 bağımsız PHP süreçlerini kullanır; HTTP tekrarları başarısızlığı yeniden
deneyerek gizlemez. Ham job log API bağlantıları `ci.json` içindedir; credential
içerebilen tam CI günlükleri repository'ye kopyalanmamıştır.

PR test checkout'u `216ee821ed855e6a2e25ef9fa3976618d84565b0`; GitHub tree'si
uygulamanın `f7d00105c41f1e53d8f43551cb56df28865069f9` tree'siyle eşittir.
Bu GitHub test checkout'udur; gerçek merge yapılmadı.15 dakikalık önceki tur
PHP52/579/0 sonrasında browser bağımlılık kurulumu sırasında iptal oldu ve tam
başarı sayılmadı. Yalnız iş süresi30 dakikaya genişletildi; nihai iki job bütün
browser ve10/30/20 tekrarları dahil SUCCESS tamamlandı. Uygulama/test kaynakları
önceki yerel9-browser doğrulamasıyla byte-eşittir; fark yalnız CI süre bütçesidir.

## Uygulanan kapsam ve sözleşme

`/admin/support`: inbox / görüşme / operasyon olmak üzere üç sütun; dar ekranda
tek sütun. `/client/support`: yalnız native current customer kapsamındaki talepler,
create/reply, yetkili hizmet seçimi, public timeline, public incident ve uygun CSAT.
HTML sayfaları da JSON gibi native session/role/MFA/current grant kontrolündedir.
Yeni login, rol, Queue, Audit veya ticket state machine yoktur.

SQL ACL, department/customer/state/mine/unassigned/kaydedilmiş SLA alert filtrelerinden
ve 50+has_more sayfalamasından önce uygulanır. Sıralama UUID ile deterministiktir;
offset sayfaları farklı zamanda değişen havuzun tek bir tarihsel snapshot'ı değildir.
Mesajlar SQL görünürlük filtresinden sonra son50+has_more; public timeline son20.
Şifreli subject/body/internal_note için plaintext indeks yoktur. `q`, boş olsa da
422 `SUPPORT_SEARCH_UNSUPPORTED`; export 422 `SUPPORT_EXPORT_UNSUPPORTED`.
Client için staff-only UI endpoint'leri kayıtlı değildir; 404 verir.

Public reply ve internal note farklı composer türleri ve farklı şifreli taslaklardır.
Taslak kapsamı native user/session/customer/ticket/kind; AEAD purpose=id, optimistic
draft version, base ticket version, erişim anında current ACL ve 1800s expiry.
Session başına32 taslak;10 encrypted saved filter,7 gün; presence8 ticket/session,
120s yaklaşık sayaç, görüntüleyen kimliği veya note typing bilgisi yoktur.
Browser localStorage/IndexedDB/sessionStorage içinde içerik tutulmaz.

Gönderim prepare/execute confirmation'ı actor/session/identity-version/action/payload
HMAC/state HMAC/expiry/unused koşullarına bağlıdır. Payload ticket version ve exact
draft id/version/body/kind eşleşir. Outer native Idempotency transaction'ı canonical
Support compose, mesaj/event/audit/SLA/mail-outbox ve yalnız gönderilen taslağın
silinmesini birlikte commit eder. Başarısızlıkta tamamı rollback; aynı key replay
yeni mesaj üretmez, tekrar güncel yetki kontrolü yapar. Public gönderim note taslağını
temizlemez. State/move aynı native State ve iki departman yetkisini kullanır;
departman kilitleri sıralıdır. Gerçek mail dispatch açılmaz.

Polling15s, tek uçuş; selection generation/request sequence/ticket version/scope epoch
kontrolü, gizli sayfada abort/pause, session revoke'da DOM/cache temizliği vardır.
Taslak yoksa yeni görüşme yüklenir; yazılmış taslak varsa eski base korunur ve açık
uyarı/güncel görüşmeyi inceleme gerekir.409 sessiz POST tekrarına dönüşmez. Ağ sonucu
belirsiz create/send, aynı payload ve aynı key ile kullanıcının açık tekrarını ister.
Risk/breach clock ekranı native anlık view sonucudur; list risk filtresi kaydedilmiş
alert'tir. Presence çevrimiçi garantisi değildir. SLA ekranı yeniden açılarak yenilenir.

Work policy/calendar/roster ve MANUAL/ROUND_ROBIN/LEAST_LOAD, priority integer load
weights, pause/risk/targets explicit native field'lerle hazırlanır. Ticari hedef veya
çalışma saati uydurulmaz. Yayın native Work confirmation/fresh step-up; snapshot'lar
değişmez. Roster izinleri mevcut kayıtla birlikte gösterilir ve açıkça yeniden girilir.

Automation builder yalnız allowlist AST v1 predicate/all/any/not ve dört action;
raw PHP/SQL veya executable expression alanı yoktur. Current rule spec okuması
automation.manage ve her action'ın kendi iznini ister. Simülasyon native rollback;
yayın native D confirmation; sonuç/LOOP_BLOCKED native executions projection'ıdır.
Unavailable customer/service predicates seçilmez. Canlı scheduler kabulü yoktur.

Incident public metni/private root ayrı; native link/update/priority/CSAT policy ve
customer RESOLVED/window rating kullanılır. Reports UTC half-open ticket created-at
kohortu ve kaydedilmiş clock/policy snapshot'larıdır. Actual work hours NOT_AVAILABLE;
kurum geneli tarihsel SLA doğrulaması OPEN. CSV/export desteklenmez.

Mail review, unknown sender, duplicate/reason ve proof alanları native C endpoint'lerini
kullanır. Raw MIME yalnız metin olarak görüntülenir; proof ham içerik SHA-256'sına
bağlıdır, URL'ye konmaz. HELD/mock outbound gerçek teslim sayılmaz. Dosyalar native
current ACL + kısa token/header ile indirilir. PDF sınırı16KiB; quarantine/test-clean
durumları gerçek antivirus sonucu olarak sunulmaz. Token/proof güvenli içerik olup
log veya browser storage'a yazılmaz.

## Bütünleşik doğrulama matrisi

| Gereksinim | Native sınır ve regresyon |
|---|---|
| SUP-001 Unknown sender | Korunan C unknown/review/proof testleri; E unknown review kuyruğu ve reason; private ticket'a zorla bağlama yok |
| SUP-002 Duplicate Message-ID | Korunan C exact Message-ID dedupe; E queue detail/status/reason ve native review kararı |
| SUP-003 Internal note | E SQL public-only messages/timeline, client note draft yasağı, browser DOM; native C public-only dosya/mail projection; export422 |
| SUP-004 Department ACL | SQL ACL-before-pagination, subject/message/current grant, C token/download denial; E HTML guard ve revoked draft/poll/presence |
| SUP-005 SLA calendar | Korunan B calendar/DST/exception/UNAVAILABLE; E native policy revision, target/recorded consumption/remaining ayrımı ve warning |
| SUP-006 Assignment concurrency | Korunan B 1/4/8 atomic RR/weighted eligibility; E uygun personel ve native manual/auto/enroll confirmation, HTTP assign |
| SUP-007 Conflict | Native version409; E stale draft/nonce/body/kind reddi, same-key1/4/8 exact send, rollback ve late response fence |
| SUP-008 | Korunan D AST/causation/dedupe/LOOP_BLOCKED; E structured editor/simulation/results, browser editor açılışı |
| SUP-009 | Native public/private incident/RESOLVED CSAT/scoped reports; E create→assign→reply→SLA→incident→CSAT→report HTTP |
| SUP-010 | F6 bağımlılığı; import/archive checksum ve bütün WHMCS aktarımı kabul edilmedi |

Güncel kaynak/satır ve36 yeni case indeksi [source-index.json](evidence/f5-e/source-index.json):
`Context.php:10` native session/MFA/grant; `Views.php:16` SQL list ACL;
`Views.php:21` SQL note projection; `StateStore.php:16` exact session draft;
`StateStore.php:25` AEAD/expiry/version; `Commands.php:23` confirmation/idempotency/
draft clear; `Support/Service.php:30` canonical compose; `Controller.php:19`
CSRF/rate; `support-state.js:3` generation/sequence/version/scope fence.
Bu referanslar `src/Support/UI/` ve `public/assets/` yollarındadır; mevcut Work/Mail/
Advanced algoritmaları [koruma manifestinde](evidence/f5-e/preservation.json) byte-eşittir.

Yeni `tests/support-e.php` gerçek MariaDB/native HTTP; eski555 test ve eski test
adları korunur. Eski migration test beklentileri yalnız054 eklenerek genişletilir.
1/4/8 bağımsız PHP send replays; draft-kind/owner/session/version/expiry/revoke,
forged confirmation, SQL failure rollback, pagination ve unsupported arama/export.
`support-e-state.cjs`: geç response/sequence/version/scope epoch kontrolleri.
`support-e-browser.php/cjs`: gerçek Chromium DOM, literal HTML, private note yokluğu,
server draft reload, tek confirmed send, note/reply ayrımı, native policy/AST ve mobil.
Ek browser kontrolü roster'ı açık native UTC tarih biçimi ve sunucu confirmation'ıyla
gerçekten yayınlar; yalnız editörü açmak yayınlama testi olarak sayılmaz.
Scope epoch değişiminde browser polling durur; inbox/conversation/operations,
pending request/draft cache ve kapatılmış confirmation dialog DOM'u da temizlenir.
Bekleyen onay iptal edilir; eski response veya ağ sonucu belirsiz callback bu içeriği
tekrar dolduramaz. Bu sınır gerçek native department version değişimiyle browser'da
ayrıca doğrulanır.

[Yerel doğrulama](evidence/f5-e/local-verification.json): PHP8.4.24/MariaDB10.11.18.
İlk52-suite turunda iki eski upgrade listesi054'ü beklemediği için test gövdesinden
önce durdu; listeler054 ile genişletildi. Bu iki suite final kaynakta12+12/0 yeniden
geçti. İlk tur+hedefli tekrarların579 farklı PHP PASS adı eski555'in tamamını ve
yeni24'ü içerir; kayıp ad yoktur. İlk tur tek başına52/579/0 exact-tree run diye
sunulmaz. Son kaynakla gerçek Chromium9/0 ve deterministik state3/0 geçti.
Yalnız bu görevde oluşturulan test şeması temizlendi; görevde açılan yerel DB
instance'ı kapatıldı. Önceki test şemaları/data dizini silinmedi.

Browser fixture sentetik kullanıcılarla gerçek PHP/MariaDB HTTP sunucusunu kullanır.
Secure native cookie/CSP değiştirilmez. Yalnız test harness HTTPS origin isteklerini
loopback PHP transport'una aktarır; API payload/HTML yanıtı mock edilmez. Bu fixture
canlı TLS/mail/provider kabulü değildir. Playwright1.62.1 yalnız tools/browser altında
npm lock ile sabittir; production/shared-hosting runtime bağımlılığı değildir.

## Demo / yeniden üretim

Yalnız ayrı `hostider_f0_test...` MariaDB şeması, APP_ENV=test ve kapalı mail/provider
GATE'leriyle çalıştırılır. `php tests/support-e.php` sentetik kullanıcı/rol/grant/
MFA/oturumları kendi kurar; native HTTP CSRF/confirmation ve1/4/8 bağımsız PHP süreç
denemelerini gerçekleştirir. `node tests/support-e-state.cjs` üç deterministik state
kontrolüdür; browser veya HTTP testi diye sayılmaz.

`tools/browser` klasöründe `npm ci --ignore-scripts`, ardından kilitli Playwright ile
Chromium kurulumu yapılır. Proje kökünde F5_E_PLAYWRIGHT değişkeni bu klasördeki
`node_modules/playwright` mutlak yoluna ayarlanıp `php tests/support-e-browser.php`
çalıştırılır. Yerel kurulu Chromium kullanılırsa F5_E_BROWSER açık executable yoludur.
Fixture hesapları/parolaları stdin üzerinden yalnız child sürece verilir; rapor/log/
repository'ye kaydedilmez. Ekran görüntüleri `storage/f5-e-browser/` altında sentetiktir.
[Operatör ekranı](evidence/f5-e/ui-admin.png),
[mobil müşteri portalı](evidence/f5-e/ui-client-mobile.png) ve
[capture/hash kaydı](evidence/f5-e/ui-captures.json) inceleme için korunur.
Görüntüler son runtime/test kaynaklarıyla alınmıştır; sonraki tek fark CI süre
bütçesinin15'ten30 dakikaya çıkarılmasıdır. Bu bütçe değişimi hiçbir assertion'ı
değiştirmez; 15 dakikada iptal edilen işler tam CI başarı kanıtı sayılmaz.

Elle demo: native müşteri hesabıyla kendi ticket'ını oluştur, yetkili hizmetini seç;
operatörde current department grant+MFA/fresh step-up ile aynı kaydı aç. Public ve
note taslaklarını ayrı kaydet/yenile, onaylı public yanıt gönder. Aynı sürümlü ikinci
yanıt409 verir ve taslak korunur. Açık calendar/policy/roster ile native atama yap;
incident public/private alanlarını ayrı yayınla ve yalnız yetkili ticket'a bağla.
RESOLVED ve açık CSAT penceresinde müşteri puanını gönder; UTC created-at kohort
raporunda kaydedilmiş clock/CSAT sonuçlarını kontrol et. Unknown mail review/proof,
quarantine ve HELD ekranları gerçek teslim/AV gibi sunulmaz. Ayrı müşteri/departman
oturumuyla subject/message/note/file/review erişimi reddedilir. Gerçek mail/provider
işlemi, staging, production ve merge bu demoda da yapılmaz.

## Migration, geri dönüş ve açık sınırlar

001–053 korunur; yalnız054 additive draft/filter/presence/confirmation tabloları ve
scope identity trigger'ları. Empty/replay/053 upgrade/failed-DDL/checksum testleri;
eski history/audit/policy/cycle/receipt sözleşmeleri değişmez. Core/Identity (yalnız
Panel navigation link'i hariç)/Queue/Outbox/Finance/Domain/Cloud/Mail/Work/Advanced
kaynakları korunur. Support.Service'de yalnız canonical outer-transaction compose
seam eklenir; bootstrap native route kaydı ekler.
Rollback tutarlı code+DB+key/private-storage snapshot restore veya reviewed forward
fix; destructive down veya immutable history silme yoktur.

F4/F5-A/B/C/D sınırlı mock kabulleri geçerlidir. C'nin dört canlı istisnası OPEN:
mailbox recipient/department routing+sender auth; kesin retention cutoff/scheduler;
antivirus/gerçek SMTP/IMAP/genel MIME; HELD/ACCEPTED mock gerçek teslim değildir.
Auto-close R2-06 DEFERRED; merge/split kabul dışı; kurum geneli tarihsel SLA raporu,
F6/SUP-010, DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED, PROV-005 ve tüm canlı
GATE'ler OPEN. EXTERNAL_MAIL_ALLOWED=false. PR3 OPEN/DRAFT; merge, staging,
production veya gerçek provider işlemi yoktur. F5-E DONE/FULL ACCEPTED değildir.
Doğrulama ve teslim tamamlanınca bağımsız teknik kabul istenir ve çalışma durur.
