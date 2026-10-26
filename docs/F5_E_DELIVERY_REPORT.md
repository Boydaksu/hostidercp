# F5-E — native destek ekranları, test/mock teslim kaydı

Durum: geliştirme doğrulaması sürüyor; bağımsız teknik kabul verilmedi.
Başlangıç `eb7731d6b955ff2473f4322c7e0e3b8f29e4a0bb`; kabul edilmiş D uygulaması
`3404ddda79bc2017f05cb474b5546e23647910d8`, 51 suite /555 test.
Uygulama SHA/tree ve iki PHP CI sonucu doğrulama bitince bu kayda eklenecektir.

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
| SUP-001/002 | Korunan C unknown/review/proof/Message-ID testleri; E review paneli, public-only client projection |
| SUP-003/004 | C safe PDF/token/quarantine/owner denial; E dosya listesi/download header ve export unsupported |
| SUP-005/006 | Korunan B calendar/DST/exception/UNAVAILABLE/races; E Work snapshot/eligible/editor ve HTTP assign |
| SUP-007 | Native ticket/state/move/current department ACL; E list>50, body/note SQL projection, draft/confirmation/race |
| SUP-008 | Korunan D AST/causation/dedupe/LOOP_BLOCKED; E structured editor/simulation/results, browser editor açılışı |
| SUP-009 | Native public/private incident/RESOLVED CSAT/scoped reports; E create→assign→reply→SLA→incident→CSAT→report HTTP |
| SUP-010 | F6 bağımlılığı; import/archive checksum ve bütün WHMCS aktarımı kabul edilmedi |

Yeni `tests/support-e.php` gerçek MariaDB/native HTTP; eski555 test ve eski test
adları korunur. Eski migration test beklentileri yalnız054 eklenerek genişletilir.
1/4/8 bağımsız PHP send replays; draft-kind/owner/session/version/expiry/revoke,
forged confirmation, SQL failure rollback, pagination ve unsupported arama/export.
`support-e-state.cjs`: geç response/sequence/version/scope epoch kontrolleri.
`support-e-browser.php/cjs`: gerçek Chromium DOM, literal HTML, private note yokluğu,
server draft reload, tek confirmed send, note/reply ayrımı, native policy/AST ve mobil.

Browser fixture sentetik kullanıcılarla gerçek PHP/MariaDB HTTP sunucusunu kullanır.
Secure native cookie/CSP değiştirilmez. Yalnız test harness HTTPS origin isteklerini
loopback PHP transport'una aktarır; API payload/HTML yanıtı mock edilmez. Bu fixture
canlı TLS/mail/provider kabulü değildir. Playwright1.62.1 yalnız tools/browser altında
npm lock ile sabittir; production/shared-hosting runtime bağımlılığı değildir.

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
