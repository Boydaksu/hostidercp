# ADR-018 — F5-B zaman, snapshot ve atama sınırı

25 Ekim 2026. D4, R2-02/03, R3-09, SUP-005/006 teknik normalleştirmesi.
Durum: yalnız F5-B test/mock teknik kararı; bağımsız teknik kabul bekler.

Calendar business time gerçek UTC elapsed microseconds'tır. Local shift aralıkları
zone transition segmentlerinde UTC'ye map edilir; spring missing wall time yok,
fold'un iki gerçek UTC aralığı birer kez sayılır. Overlap union'u double-count önler.
Exception local günün tamamını, previous overnight carry dahil, override eder.
Hafta/tatil/hedef/ağırlık/risk/izin değerleri ticari varsayım olarak seed edilmez.

Policy ve roster immutable revisions; current heads ayrı projection'dır. Ticket
cycle policy'ye bağlanır. Policy yayınlamak eski cycle'ı değiştirmez. Move/reopen
explicit source event ile eski cycle'ı sonlandırıp yenisini açar; manual enroll
yalnız hiç policy bağlanmamış ticket'a uygulanır. Message type server-side authority
olarak kalır; held ack/internal_note first/next response değildir. Her customer reply
ayrı next-clock; tek gerçek reply tüm önceki pending first/next clock'ları tamamlar.

Roster manuel native-admin kaynağıdır; harici HR yoktur. Missing/expired/leave/offline/
priority dışı kayıt eligible değildir. Shift takvimi ticket SLA calendar'ından ayrıdır.
Target eligibility native Repository principal/grant resolver'ını salt karar için
kullanır; target adına oturum veya mutation yetkisi yaratmaz. MFA enrolled olmalıdır.
Operatör gerçek native session/fresh MFA/scoped assign ile çalışır. Config yalnız
administrative identity.staff.manage ve explicit server-bound confirmation ister.

Candidate user row fence'leri sorted/NOWAIT olarak department ve ticket'tan önce
alınır. Native revoke commit'i ile eligibility linearize olur; contention fail-closed
409 veya sonraki cron'dur. F5-A message transaction'ı candidate kilitlemez; yalnız
kendi ticket/cycle/clock projection'ını günceller. Department lock RR/weighted seçimi
seri yapar; cursor ve assignment/history/audit/outbox aynı transaction'dadır.

Manuel seçim auto override'dır; auto'ya explicit dönüş vardır. Valid assignee her
cron'da değiştirilmez. Native erişim/uygunluk kaybedilirse atama bırakılır, otomatik
mod varsa yalnız yeni eligible hedef seçilir. Workload department-local, yalnız
non-resolved/non-closed ticket'ların policy weight snapshot toplamıdır; tie UUID.
Raw workload/leave bilgisi ticket özetine çıkarılmaz. Uygun kimse yoksa unassigned.

SLA history/alerts ve assignment work events append-only; alert(clock,level) unique.
Held receipt teslim başarısı, müşteri bildirimi veya mail değildir. F5-A support.changed
exact event receipt, internal_note gizliliği ve unsupported q sözleşmesi değiştirilmez.
Cycle başına100 pending NEXT sınırı vardır; kapasite aşımı tüm müşteri reply
transaction'ını409 ile geri alır. Takvim hesap ufku3660 yerel gün, tarih2000–2099;
staff fence en çok64 native staff. İlk fence sorgusu locking read'dir; native
revoke öncesine ait REPEATABLE READ snapshot oluşturulmaz. NOWAIT contention
fail-closed'dur. Cron20s/64MiB sınırları yeni ticket işlemine başlama sınırlarıdır;
başlamış tek DB transaction için gerçek zamanlı hard deadline iddiası değildir.
Test dışı, SMTP/IMAP, F5-C/D/E/F6 ve canlı GATE kapanışı yoktur.
