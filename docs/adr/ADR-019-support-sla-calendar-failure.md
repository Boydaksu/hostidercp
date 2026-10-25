# ADR-019 — F5-B reopen ve SLA takvim hatasının izolasyonu

Durum: yalnız iki P1 için test/mock teknik normalleştirme; bağımsız kabul bekler.

Staff reply ile reopen aynı olayda yeni FIRST'i tamamlar; customer reply yeni
FIRST'i bekletir. Yeni cycle'ın kaynak olayı yeniden işlenerek iki completion
yaratılmaz. Eski cycle/policy/history korunur.

SLA policy recurring weekly çalışma aralığı içermelidir. Prepare ve execute anında
DB UTC zamanı ve en büyük explicit hedef için gerçek calendar deadline hesaplanır;
empty veya horizon/date-domain sebebiyle hesaplanamayan policy422 ile reddedilir.
Bu kontrol sonsuza kadar geçerlilik garantisi değildir: future exceptions, mevcut
legacy policy veya tarih/hesap ufku ayrıca hata verebilir.

Calendar horizon/date-domain hatası customer kaydını iptal etmez. SLA alt işlemindeki
savepoint geri alınır; outer ticket/message/event/idempotency transaction'ı açık
UNAVAILABLE sonucu, durable projection ve tek immutable diagnostic event ile
commit edilir. Diğer hatalar (SQL, yetki, stale, capacity, time conflict) yutulmaz;
outer transaction rollback olur. Sahte deadline, ticari fallback veya sessiz
başarı yoktur. Cron degraded SLA'yı yeniden hesaplamaz; assignment etkilenmez.
Yeni policy snapshot'ı oluşturmak eski cycle'ı değiştirmez. No-cycle unavailable
ticket mevcut confirmed enroll ile alınır; eski failed cycle için retroactive
recalculation/recovery bu dar düzeltmenin kapsamı değildir.
