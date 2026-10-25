# F5-B — iki P1 için hedefli denetim düzeltmesi

26 Ekim 2026. Durum: tam doğrulama sürüyor; bağımsız yeniden kabul beklenir. Taban uygulama
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

## Yerel karşılaştırma (henüz tam CI yerine geçmez)

b225f3e git archive içinde aynı dokuz P1 işlev testi:7 FAIL /2 PASS. Eski kaynakta
resolved/closed/parallel staff reopen FIRST açık; boş ve exception-horizon policy
HTTP200 ile hazırlanıyor; gelecekte takvim hatasında create HTTP422 oluyor;
active-cycle horizon hatası talep akışını kesiyor. Kanıt before.txt. Yeni on
regresyon (bunlara050→051 dahil):10 PASS; failed-target-move sınırı ayrıca1 PASS.
İlk yeni denemede tek hata yeni testin PDO integer0 yerine string0
beklemesiydi; yalnız sayısal assertion düzeltildi. Eski461 test değiştirilmedi;
migration listeleri/count yalnız051 eklenerek genişletildi. Nihai tam sonuç ve
gerçek GitHub SHA/CI aşağıda doğrulamadan sonra kaydedilecek.
