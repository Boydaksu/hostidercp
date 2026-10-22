# ADR-005 — F2 kesin tutar ve finansal sınırlar

Durum: kullanıcının F2 kickoff ve devam talimatına dayalı teknik uygulama kararı; 2026-10-22. D2/D3/D5 ticari kuralları değişmez.

Para API/JSON sınırında canonical integer string ve ISO currency olarak taşınır. MariaDB BIGINT signed aralığına sığmayan nihai tutar reddedilir; PHP veya JavaScript float'a dönüşmez. Ara decimal hesaplar bağımlılıksız string aritmetiğiyle yapılır; BCMath/GMP/Node runtime zorunluluğu eklenmez. Hassasiyet ve yuvarlama çağrıda açıkça seçilir; tek taraflı vergi/FX ticari varsayımı yoktur. Başlangıç currency listesi TRY/USD/EUR (2), JPY (0), KWD (3); desteklenen liste açık ve sınırlıdır, diğer kodlar doğrulanmadan kabul edilmez.

Ledger aynı currency'de dengeli immutable journal/line kayıtlarıdır. Wallet bakiyesi hareketlerden türetilir; mutable balance sütunu yoktur. Wallet account row lock aynı hesaptaki tüm yazmaları sıralar; daha sonra refund/payment lock sırası uygulama servislerinde sabitlenir. Hareketler, tahsilatlar ve telafi kayıtları silinmez. DB trigger'ları financial immutable tabloları UPDATE/DELETE'den korur; uygulama hesabından DDL/TRIGGER yetkileri deployment'ta kaldırılmalıdır. Migration ve test cleanup privileged test hesabının sorumluluğudur.

Yeni modüller Identity'nin güncel user/session kilidini ve izinlerini uygulama sınırından kullanır. Financial preview ayrı salt-okunur plan/validation üretir; gerçek komut SAVEPOINT içinde denenmez. Finance servisleri trusted in-process sınırıdır; controller kimliği doğrulanmamış girdiyi bu servislerin yerine geçiremez. HTTP/API açılması ilgili güvenlik/confirmation testlerinden sonra yapılır.

Gerçek callback ve FX provider kapalıdır. Mock doğrulanmış test DTO'su gerçek PayTR imza/tutar sözleşmesi sayılmaz; gerçek merchant alanı veya imza algoritması uydurulmaz. F3 handler'ı yoktur; ödenmiş siparişin hazırlık olayı hiçbir gerçek sağlayıcı işi başlatamaz.
