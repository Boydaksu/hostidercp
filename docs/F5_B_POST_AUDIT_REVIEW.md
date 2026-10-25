# F5-B — iki P1 için hedefli denetim düzeltmesi

26 Ekim 2026. Durum: iki P1 hedefli test/mock düzeltmesi teslim edildi;
**bağımsız yeniden kabul beklenir.** Taban uygulama
4d58b81823deb2b44ce52ef89bae77dce468ef3b; belge HEAD
b225f3e52961ef1e2bdf39931c961dd174305670. Eski461 test ve001–050 korunur.

## Uygulama öncesi kapsam / dosya / migration / test / geri dönüş planı

Yalnız P1-1 ve P1-2. Engine/Clocks staff-reopen canonical FIRST completion;
Policy/Commands publish-time deadline feasibility; Engine calendar-only savepoint
guard; Service açık degraded sonuç; Work summary explicit SLA status. Ayrı immutable
SLA_UNAVAILABLE event ve mevcut held consumer kullanılır. Native Identity, private
note ve support.changed exact receipt değiştirilmez.

051 additive: mutable work projection'a UNCONFIGURED/ACTIVE/UNAVAILABLE SLA durumu.
001–050 byte/hash korunur. Policy/cycle/clock/history snapshot'ları değiştirilmez.
Takvim hesap hatasında yalnız SLA alt işinin savepoint'i geri alınır; asıl ticket,
message, support event ve idempotent sonuç aynı outer transaction içinde korunur.
Sonuç açık SLA_UNAVAILABLE uyarısı taşır; mail veya sahte deadline yoktur. Bu durum
cron tekrarlarında duplicate event üretmez. Yeni başarısız cycle hiç oluşturulmaz;
unconfigured ticket geçerli policy sonrası mevcut explicit enroll ile alınabilir.
Var olan cycle hesap ufkunu aşarsa geçmiş korunur ve SLA otomatik yeniden yazılmaz.

Staff reply ile reopen yeni FIRST'i aynı kaynak olayda bir kez tamamlar. Customer
reply ile reopen FIRST'i açık bırakır; yeni cycle FIRST'i bu isteği temsil eder,
aynı olay için ayrıca NEXT açılmaz. Önceki cycle sonuçları sabit kalır.

Regresyonlar: resolved/closed staff reopen, customer reopen, stale/replay/parallel
reply, old snapshot eşliği; empty weekly ve exception horizon publish reddi;
ret sonrası HTTP create; valid atomic clocks; legacy/future invalid calendar'da
durable warning, replay ve failure injection rollback. Eski kaynak FAIL / yeni PASS,
51 suite461+ test, PHP8.3/8.4 gerçek CI, statik analiz ve1/4/8 süreçler korunur.

Rollback: tutarlı code/DB/key restore veya reviewed forward-fix.051 history silmez;
destructive down yoktur. Uygulama/gerçek CI SHA'ları, kaynak diff ve kanıtlar sonuç
doğrulanınca eklenecek. F4/F5-A limited mock kabulü ve bütün canlı GATE'ler OPEN.
F5-C/D/E/F6, gerçek mail, merge, staging veya production yoktur.

## Neden ve karşı-örnekler

P1-1: closed cycle sonrası Engine.start erken return ediyordu. Staff kaynak olayı
cycle'a bağlanıyor fakat gerçek yanıt FIRST'e uygulanmıyordu. Artık doğrulanmış
public staff_reply yeni FIRST'i doğrudan COMPLETED olarak, consumed_us=0 ve aynı
UTC completed_at ile oluşturur; history'de aynı kaynak için yalnız bir COMPLETED
vardır. Ardından aynı olayı advance ederek ikinci completion üretilmez. Customer
reopen FIRST RUNNING kalır; note tamamlamaz. Güncel policy yeni cycle'a bağlanır,
önceki cycle/history/target/policy byte olarak kalır.

P1-2: yapısal Calendar doğrulaması weekly boşluğunu ve pozitif hedefin takvim
ufkunda hesaplanabilirliğini denetlemiyordu. Policy artık recurring weekly çalışma
aralığı ister; prepare/execute DB UTC anında en büyük explicit hedefi hesaplar.
SLA_POLICY_UNSCHEDULABLE422 politika/config transaction'ını reddeder; eski policy
head'i ve müşteri talep kanalı korunur. Roster için boş vardiya hâlâ geçerli
off-shift verisidir; Calendar aritmetiği veya ticari hedefler değiştirilmez.

Gelecekteki exceptions / tarih /3660 gün ufku tam sonsuz geçerlilik garantisine
izin vermez. Yalnız WORK_CALENDAR_HORIZON, INVALID_CALENDAR_DAY ve INVALID_WORK_TIME
SLA guard tarafından ele alınır. SQL/constraint, native yetki, stale version,
idempotency, kapasite veya event time conflict yutulmaz; outer rollback olur.
Takvim hatasında SLA savepoint rollback'i geçmişi/partial alerts/clocks/outbox'u
geri alır. Asıl ticket/message/support event/idempotency sonucu ve tek immutable
SLA_UNAVAILABLE diagnostic aynı outer transaction'da commit edilir. HTTP200 gerçek
ticket kabulüdür; response sla_status=UNAVAILABLE ve warning=SLA_CALENDAR_UNAVAILABLE
taşır, SLA başarısı anlamına gelmez. Yetkili detail açık UNAVAILABLE/clocks=[] verir.
Cron tekrarları yeniden diagnostic üretmez; unavailable mevcut cycle hesapları
donar, assignment ayrı güvenlik kontrolleriyle devam eder. Değerlendirilmiş eski
snapshot'lar değiştirilmez. Tek operasyonel cycle referansı work projection'dır;
failed arşiv cycle'ı history ve diagnostic ile korunur, geriye dönük başarı yazılmaz.
Normal move mevcut sözleşmeyi korur; failed move cross-department assignee bırakmaz,
assignment removal audit'i ve doğru attempted policy kimliği aynı transaction'dadır.

## Yerel karşılaştırma

b225f3e git archive içinde aynı dokuz P1 işlev testi:7 FAIL /2 PASS. Eski kaynakta
resolved/closed/parallel staff reopen FIRST açık; boş ve exception-horizon policy
HTTP200 ile hazırlanıyor; gelecekte takvim hatasında create HTTP422 oluyor;
active-cycle horizon hatası talep akışını kesiyor. Kanıt before.txt. Yeni on
regresyon (bunlara050→051 dahil):10 PASS; failed-target-move sınırı ayrıca1 PASS.
İlk yeni denemede tek hata yeni testin PDO integer0 yerine string0
beklemesiydi; yalnız sayısal assertion düzeltildi. Eski461 test adı/amacı korundu;
migration listeleri/count yalnız051 eklenerek genişletildi.

## Gerçek kaynak ve CI

Uygulama:
[`5869483fd0d977ede646cbef02bdf4a9f2b12bc8`](https://github.com/Boydaksu/hostidercp/commit/5869483fd0d977ede646cbef02bdf4a9f2b12bc8).
[Son teslim HEAD'inden diff](https://github.com/Boydaksu/hostidercp/compare/b225f3e52961ef1e2bdf39931c961dd174305670...5869483fd0d977ede646cbef02bdf4a9f2b12bc8),
[denetlenen uygulamadan diff](https://github.com/Boydaksu/hostidercp/compare/4d58b81823deb2b44ce52ef89bae77dce468ef3b...5869483fd0d977ede646cbef02bdf4a9f2b12bc8).

Her gerçek CI işi **51 suite /472 test /0 hata**:
[PHP8.3.36](https://github.com/Boydaksu/hostidercp/actions/runs/40234838426/job/121294067148),
[PHP8.4.27](https://github.com/Boydaksu/hostidercp/actions/runs/40234838426/job/121294067287).
Eski461 test adı iki yeni ham günlükte eksiksiz; yeni11 test PASS. PHPStan0,
lint340 dosya/0 ihlal, pattern scan352 dosya/0 bulgu, locked Composer audit temiz.
Service HTTP10, commerce HTTP30 ve session concurrency20 tekrarlarının her biri
iki sürümde0 hata. Pattern scan tam güvenlik denetimi garantisi değildir.

PR CI sentetik checkout fc858aa2aa0f7f2b0c6f8a3a65eb72f8f92c0cf6 ağacı
452ba54008edfdcc755a17bb7811e044b2db8447 ile uygulama ağacı birebir eşittir;
gerçek merge yapılmadı. [CI kanıtı](evidence/f5-b-post-audit/ci.json) yalnız güvenli
özet/test adları ve GitHub log API bağlantıları içerir. Ham CI logları diske veya
projeye kaydedilmedi. [Koruma](evidence/f5-b-post-audit/preservation.json):63 dosya,
001–050 + native Identity/Queue/Outbox/workflow byte/hash değişmedi.

## Regresyon matrisi

| Senaryo | Eski b225f3e arşivi | Düzeltilmiş kaynak |
|---|---|---|
| RESOLVED staff reopen / yeni policy / old snapshot | FAIL: FIRST RUNNING | PASS: tek COMPLETED, sıfır süre |
| Reopen izinli CLOSED, stale/replay | FAIL: FIRST RUNNING | PASS: replay aynı sonuç, stale409 |
| Customer reopen / note / gerçek reply | PASS | PASS: FIRST bekler, note kapatmaz |
| Paralel gerçek HTTP reopen | FAIL: FIRST RUNNING | PASS:200/409, tek cycle/completion/public outbox |
| Tamamen boş weekly policy ve ardından create | FAIL: prepare200 | PASS:422/no writes, müşteri create200 |
| Exception-horizon policy | FAIL: prepare200 | PASS:422, eski head kullanılabilir |
| Sonradan hesaplanamayan takvim / HTTP create | FAIL:422 | PASS:200 + explicit warning, tam kayıt/replay |
| Valid create / SQL error rollback | PASS | PASS: atomik FIRST/RESOLUTION;500/no partial key |
| Active cycle horizon / reply / move | FAIL: işlem durur | PASS: eski snapshot donar, mesaj korunur |
| 050→051 / replay /50 eski checksum | Yeni migration öncesinde uygulanamaz | PASS |
| Failed target calendar move | Ek sınır testi | PASS: audit/policy doğru, partial alert yok |

İlk dokuz karşılaştırma gerçek eski repository arşivinde, aynı P1 test koduyla
çalıştırıldı; yeni migration testi bu eski karşı-örnek sayısına dahil değildir.
Gelecek zaman senaryosu izole MariaDB event timestamp trigger fixture'ıdır; gerçek
HTTP/native Identity/transaction çalışır, gerçek30 gün beklenmiş gibi sunulmaz.
[Before](evidence/f5-b-post-audit/before.txt), [after10](evidence/f5-b-post-audit/after.txt),
[failed move1](evidence/f5-b-post-audit/move.txt).

Yerel PHP8.4.24 / MariaDB10.11.18 tam tur: **51 suite /472 test /0 hata**;
461 eski ad eksiksiz,11 yeni test PASS. [Yerel özet](evidence/f5-b-post-audit/local.json),
[tam PASS günlüğü](evidence/f5-b-post-audit/local-tests.txt),
[kontroller](evidence/f5-b-post-audit/checks.txt). İzole test DB temizlendi ve bu
görev için açılan MariaDB süreci kapatıldı. Son belge kaydı yalnız README/docs'tur;
doğrulanmış uygulama kaynak SHA'sı yukarıdaki5869483 olarak sabittir.

## Migration ve açık sınırlar

051 yalnız mutable work projection'a SLA availability ekler; mevcut policy/cycle
bağı olan projection ACTIVE olarak işaretlenir. Bu, ticket'ın açık/kapalı hayat
durumu değildir. Empty001→051,050→051, replay/no-op ve old checksum testleri PASS;
eski migration test adları tarihsel olarak korunmuş, expected listeler051 ile
genişletilmiştir. Failed-DDL/APPLYING/checksum-drift kontrolleri korunur.

Mevcut2000–2099 tarih,3660 gün hesap ufku ve diğer F5-B kapasite sınırları değişmez.
Publish-time kontrol sonsuz gelecek garantisi değildir. No-cycle UNAVAILABLE ticket
geçerli policy ile mevcut confirmed enroll üzerinden alınabilir. Mevcut failed
cycle geriye dönük yeniden hesaplanmaz; yeni recovery/override/export/UI akışı bu
dar düzeltmeye eklenmedi. History/diagnostic korunur; gerçek SLA taahhüdü, ticari
süre veya tatil değeri varsayılmadı. Geri dönüş tutarlı code/DB/key restore veya
forward-fix; veri silen down yoktur. Runtime source/schema birlikte yönetilmelidir.

F4/F5-A sınırlı mock kabulleri değişmez. DOMAIN-002 PARTIAL, mapping/import CRUD
DEFERRED, PROV-005 OPEN ve tüm canlı GATE'ler korunur. PR #3 açık/taslak kalır.
F5-C/D/E/F6, SMTP/IMAP, gerçek mail/provider, merge, staging veya production yoktur.
Bağımsız yeniden kabul istenir; genel DONE veya production-ready ilan edilmez.
