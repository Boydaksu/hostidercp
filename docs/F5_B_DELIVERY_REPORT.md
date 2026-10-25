# F5-B — SLA takvimleri ve personel atama motoru

Son iki P1'in hedefli düzeltmesi,5869483 kaynak/472 test kanıtı ve bağımsız yeniden
kabul sınırı [F5_B_POST_AUDIT_REVIEW](F5_B_POST_AUDIT_REVIEW.md) içindedir.
Aşağıdaki461 testlik ilk teslim kaydı tarihsel olarak korunur; kabul kararı değildir.

25 Ekim 2026. **TEST/MOCK — bağımsız teknik kabul bekliyor.** F5-A'nın
LIMITED MOCK TECHNICAL BASELINE kabulü ve F4'ün sınırlı mock kabulü korunur.
Yetki tabanı563b79e5ab201fb73ba38e37d2a6b86506df6e9b; kabul edilmiş F5-A
uygulamasıd3712eda6b3d9156187727ced85fff04de7457c5. Bu teslim SUP-005 ve
SUP-006'nın aşağıdaki teknik kapsamıdır; V1 veya production kabulü değildir.

## Kaynak ve doğrulama kaydı

Uygulama SHA:
[`4d58b81823deb2b44ce52ef89bae77dce468ef3b`](https://github.com/Boydaksu/hostidercp/commit/4d58b81823deb2b44ce52ef89bae77dce468ef3b).
[Kabul tabanından kaynak diff'i](https://github.com/Boydaksu/hostidercp/compare/563b79e5ab201fb73ba38e37d2a6b86506df6e9b...4d58b81823deb2b44ce52ef89bae77dce468ef3b).
Gerçek CI her sürümde **51 suite / 461 test / 0 hata**:
[PHP8.3.36](https://github.com/Boydaksu/hostidercp/actions/runs/40226687244/job/121269441131),
[PHP8.4.27](https://github.com/Boydaksu/hostidercp/actions/runs/40226687244/job/121269440672).
PR CI'nın sentetik checkout'ucac59f3913cbe7e1e16420aa132b581133870d9f,
source tree5c67316186a85542b0d717a7bf4a171db0a1501f ile eşittir; bu gerçek merge değildir.

İlk uygulama SHA'sıce994e395fb85027fbc74feb01bd0f872702c381 aynı51/461 tam CI'ı
geçti: [ilk CI kanıtı](evidence/f5-b/initial-ci.json). Ardından yalnız worker testine
actual child progress assertion'ı eklendi; uygulama/migration kodu değişmedi.
Son kaynak4d58b8, tek worker'ın8 ticket'ı işlemesini ve4/8 süreçte gerçek child
ilerlemesini zorunlu tutar; busy ticket'lar kontrollü drain ile tamamlanır.
Son kaynağın tam CI'ı yukarıdadır. Bu belge/kanıt kaydı yalnız README/docs değiştirir.

Yerel PHP8.4.24 / MariaDB10.11.18 ilk kaynakta51 suite /461 test /0 hata; güçlendirilmiş
son worker testi ayrıca1/0 geçti: [worker kanıtı](evidence/f5-b/race-check.txt). Eski kabul edilmiş
GitHub günlüğündeki433 test adı iki yeni CI günlüğünde ve yerel sonuçta eksiksizdir;
yeni28 regresyon PASS. PHPStan0, lint338 dosya/0 ihlal, pattern scan350 dosya/0 bulgu,
Composer locked audit temiz; service HTTP10, commerce HTTP30 ve session concurrency20
tekrarının her biri iki sürümde0 hata. Pattern scan tam güvenlik denetimi garantisi değildir.
Kanıtlar: [CI](evidence/f5-b/ci.json), [yerel özet](evidence/f5-b/local.json),
[yerel PASS günlüğü](evidence/f5-b/local-tests.txt), [koruma](evidence/f5-b/preservation.json),
[kontroller](evidence/f5-b/checks.txt). Ham CI logları diske/projeye kaydedilmedi;
yalnız test adları/özetler ve GitHub log API bağlantıları saklandı. Plan:
[F5_B_PLAN](F5_B_PLAN.md); karar: [ADR-018](adr/ADR-018-support-calendars-assignment.md).
[Taslak PR #3](https://github.com/Boydaksu/hostidercp/pull/3), mevcut F4 dalı üstünde
açık kalır; main merge veya ortam yayını yapılmaz.

## Uygulanan sözleşme

Calendar yerel duvar aralıklarını IANA offset transition segmentleriyle gerçek UTC
aralıklarına çevirir ve union alır. Integer mikrosaniye, yarı açık aralıklar, haftalık
vardiyalar, özel tarih/tatil/yarım gün ve overnight carry desteklenir. Tarih exception'ı
günün tamamını, önceki geceden taşan süre dahil, override eder. Spring gap çalışma
süresi değildir; fall fold'daki iki ayrı gerçek UTC dakika birer kez sayılır.

Versioned policy bütün LOW/NORMAL/HIGH/CRITICAL için FIRST/NEXT/RESOLUTION hedefi,
metric pause allowlist'i, risk yüzdesi ve assignment ağırlıklarını explicit ister.
Ticari süre, tatil veya vardiya seed edilmez. Policy/calendar immutable snapshot
cycle'a bağlanır. Yeni policy eski ticket'ı değiştirmez. Eski unconfigured ticket
yalnız onaylı enroll ile o andan itibaren yeni cycle açar; geçmişe dönük SLA uydurulmaz.
Move eski cycle'ı MOVED ile kapatır, eski atamayı kaldırır, hedef departmanın güncel
policy'si varsa yeni cycle başlatır. Reopen yeni cycle'dır; eski sonuçlar korunur.

Gerçek public staff_reply pending FIRST/NEXT clock'larını tamamlar. Internal note
hook'tan erken çıkar; held acknowledgement, worker receipt veya sistem olayı yanıt
sayılmaz. Her customer_reply ayrı NEXT clock açar; önceki bekleyen müşteri mesajının
deadline'ı ileri itilmez. ON_HOLD/WAITING_CUSTOMER yalnız ilgili metric policy'si
izin verirse duraklar. RESOLVED resolution'ı tamamlar, CLOSED/MOVED iptal eder;
yanıtsız FIRST/NEXT bu durumlarda tamamlanmış sayılmaz. History/alerts/work events
SQL append-only; clock kimliği/hedefi ve tamamlanmış sonuçlar değiştirilemez.

Manual, ROUND_ROBIN ve LEAST_LOAD desteklenir. RR eligible user UUID sırası ve
departman cursor'uyla çalışır. LEAST_LOAD aynı departmandaki açık işlerin kaydedilmiş
weight toplamını kullanır; eşitlikte UUID seçilir. Yeni policy eski işin weight'ini
sessizce değiştirmez. Manuel atama/unassign, explicit auto komutuna kadar override'dır.
Geçerli atama her cron'da değiştirilmez. Erişim kaybı sonraki değerlendirmede atamayı
kaldırır; uygun personel yoksa ticket departman unassigned queue'da kalır.

Roster kaynağı bu fazda yönetilebilir, versioned **manuel native-admin kaydıdır**:
shift calendar, availability, UTC leave aralıkları, priority allowlist ve expires_at.
Harici HR/izin entegrasyonu yoktur. Eksik, expired, off-shift, izinli veya öncelik
yetkisi olmayan roster fail-closed'dur. Hedef active native staff, MFA enrollment,
enabled departman üyeliği ve native view/reply customer/department grant ister.
Hedef adına session açılmaz. Atayan gerçek native session, scoped ticket.assign ve
fresh MFA kullanır; config ayrıca administrative identity.staff.manage ister.

Prepare yalnız sunucuya bağlı confirmation ve audit üretir. Execute actor/session/
identity version, payload HMAC, ticket/head/roster versiyonu, mevcut eligibility ve
explicit accepted=true değerini yeniden doğrular. CSRF, idempotency, optimistic
conflict ve audit/event/outbox aynı transaction içinde korunur. Native staff
locking-read/NOWAIT fence'i, ardından department ve ticket kilitleri kullanılır.
Revocation ile sıralama belirsizliğinde API409 veya sonraki cron tercih edilir.

GET admin/support-work/detail yalnız güncel ticket ACL ile kalan çalışma dakikası,
risk ve assignment projection verir. settings administrative/fresh erişimlidir;
session ID veya private body döndürmez. unassigned native ACL'yi pagination'dan
önce uygular. Customer support-work route'u yoktur. F5-A q parametresi hâlâ açık422
SUPPORT_SEARCH_UNSUPPORTED; plaintext subject indeksi veya note/body araması yoktur.
Detail bir özettir: güncel cycle'ın FIRST/RESOLUTION ve en çok100 pending NEXT
clock'u, ayrıca yalnız güncel departmanın son20 cycle'ı gösterilir; tam geçmiş
export API'si değildir. Immutable geçmiş DB'de korunur. Unassigned sayfa boyutu50'dir.

`support.work.held` yalnız test consumer'ıdır: immutable event UUID, ticket,
published outbox, native outbox:<uuid> job, canonical payload ve fingerprint beraber
doğrulanır. Native Outbox.consume tekil receipt transaction'ı kullanılır. Unpublished,
forged, farklı ticket ve ek alan reddedilir. support.changed tüketicisi ve native
Domain/Cloud/Finance Queue/Outbox sözleşmeleri değiştirilmez. Held receipt mail veya
notification teslimi değildir; gerçek dispatch açılmaz.

## SUP-005 / SUP-006 test matrisi

Yeni testler tests/support-b.php, fixture ve bağımsız child process dosyasındadır;
mevcut support-http girişinden CI'da çalışır. Workflow değişmez.

| İstenen senaryo | Gerçek test/kanıt |
|---|---|
| 1 Cuma17:30+120→Pazartesi10:30 | IANA Europe/Istanbul ve integer UTC calendar |
| 2 tatil | Pazartesi exception sonrası Salı10:30; yarım gün |
| 3 DST ileri/geri | New York spring120/fall240 gerçek dakika, union |
| 4 overnight/yarım gün | Gece carry, tatil override, sınır/mikrosaniye |
| 5 ack / 6 note | Gerçek outbox/worker held receipt ve native note; FIRST açık |
| 7 gerçek yanıt | Native staff reply FIRST ve bütün pending NEXT'i kapatır |
| 8 pause | Policy izinli/izinsiz ON_HOLD, WAITING_CUSTOMER ve customer resume |
| 9 policy history | Yeni revision eski target/result'u değiştirmez; SQL immutable guards |
| 10 ACL | Gerçek HTTP başka customer/departman404; settings/queue projection |
| 11 RR yarış | Gerçek1/4/8 PHP process,24 ticket, tek ASSIGNED/ticket |
| 12 weighted | HIGH/NORMAL ağırlıkları, stabil UUID tie; geçerli atama değişmez |
| 13 no eligible | Missing/expired/leave/priority/off-shift; unassigned/message korunur |
| 14 revoke | Native member/role/staff revoke; MFA/active/grant negatifleri; HTTP session version |
| 15 staff yarış | İki gerçek HTTP server/personel aynı version:200/409, tek atama |
| 16 tekrar | Aynı cron zamanı, tekil risk/breach ve held receipt |
| 17 rollback | SQL trigger hata enjeksiyonu; assignment/cursor/audit/outbox tam geri dönüş |
| 18 isolation | Customer değişmez; move eski history'yi korur/yeni department cycle |
| Ek sınırlar | Invalid config, horizon, roster-bound confirmation, unpublished/forged/extra held payload |

Eski433 test adı korunur; migration beklenen listeleri yalnız050 eklenerek genişler.
Test silme, assertion gevşetme, skip/ignore veya mock'u gerçek teslim gibi sunma yoktur.

## Migration, geri dönüş ve demo

Yalnız050_support_sla_assignment.php additive'dir.001–049 byte/hash korunur.050
ayrı policy/roster/head/work/cycle/clock/history/alert/event/confirmation tabloları
ve support.ticket.assign permission'ını ekler. Permission rol eşlemesi tek başına
grant vermez. Empty001→050,049→050, replay/no-op, eski checksum eşliği ve mevcut
failed-DDL/readiness testleri çalışır. Native Identity/Core Queue/Outbox değiştirilmez.
Rollback tutarlı code/DB/key backup restore veya gözden geçirilmiş forward-fix'tir;
immutable geçmiş silinmez ve destructive down verilmez. Cron/routes kapatılabilir.

Yapay demo için yalnız APP_ENV=test ve izole MariaDB kullanılır. tests/support-b.php
native Identity hesap/MFA/grant yaratır; fixture policy/roster'ı prepare/execute ile
yayınlar. Ticket açılır; `php bin/console.php support:work --max-tickets=1 --max-seconds=1`
ile değerlendirilir. Native reply/note/pause akışı ve HTTP detail doğrulanır. Bu
fixture'ların NORMAL=1/HIGH=2/CRITICAL=4 ve süreleri ticari kabul değildir.

## Açık sınırlar ve durma noktası

İlk yerel ön denemede izole DB henüz oluşturulmadığı için19 DB senaryosu
başlatılamadı; bunlar PASS sayılmadı. İzole DB hazırlandıktan sonra26 ilk regresyon,
ardından51/461 tam tur hatasız geçti. Son worker assertion'ı ayrıca gerçek1/4/8
süreçte doğrulandı. Bu test DB'si temizlendi, açılan özel MariaDB süreci kapatıldı.

2000–2099 tarih,3660 yerel gün hesap ufku,7 açık weekday, günde8 interval,
3660 exception, roster başına100 leave,64 native staff ve cycle başına100 pending
NEXT sınırı vardır. Pending kapasite aşımı tüm reply transaction'ını409
SLA_CLOCK_CAPACITY ile geri alır. Takvim ufku aşımı422 ile açıkça reddedilir.
Cron en çok25 ticket;20s/64MiB yeni işe başlama sınırı ve DB lock wait2s kullanır;
başlamış transaction için hard real-time deadline iddiası yoktur. Global staff
fence büyük ekipler için ayrıca tasarım gerektirir. Tarih/kapasite sınırları dışında
sessiz truncation veya geçmişi değiştirme yapılmaz.

Ticari SLA hedefleri, tatil yetkilisi, roster güncelleme/expiry işletim prosedürü,
priority ağırlıkları ve auto-close kararları production öncesi açık kararlardır.
Bu faz auto-close veya ticari SLA garantisi vermez. Gerçek mail/IMAP/SMTP, ekler,
inbound processing F5-C; rule engine/incident/CSAT/raporlar F5-D; gelişmiş UI ve
şifreli subject araması F5-E kapsamındadır. Hiçbiri başlatılmaz. DOMAIN-002 PARTIAL,
mapping/import CRUD DEFERRED, PROV-005 ve bütün canlı GATE'ler OPEN kalır.

F5-B bağımsız teknik kabul istenir. Genel DONE, merge, staging, production veya
sonraki faza geçiş yetkisi yoktur.
