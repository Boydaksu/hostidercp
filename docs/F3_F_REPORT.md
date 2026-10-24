# F3-F — hizmet paneli ve işlem onayı

SEC-004 / OPS-001 / OPS-005: gerçek Identity oturumu, müşteri ve hizmet kapsamı, personel MFA ve güncel parola doğrulamasıyla HTTP hizmet yönetimi eklendi. Müşteri görünümü sağlayıcı kaynaklarını ve eski malikin finans kayıtlarını döndürmez. Impersonation yalnız sınırlı okumadır.

Hazırlık çağrısı teklif, sipariş, ledger veya provider işi oluşturmaz. Beş dakikalık şifreli plan; aktör, oturum, Identity sürümü, payload ve güncel hizmet/finans durumu ile bağlıdır. Açık onay sonrası iş, audit, outbox ve onay tüketimi tek transaction içindedir. Idempotency önbelleği mevcut izin ve step-up denetimini atlayamaz. Provider inspect isteği kalıcı işe dönüşür; dış çağrı transaction dışında, delil yazımı sırasında yetki tekrar doğrulanır.

Müşteri ve yönetici paneli; liste, dönem/fiyat, yenileme ve ödeme bağlantısı, cüzdan izni, iptal, paket değişimi, devir ve personel uzlaştırma/onay akışlarını sunar. Gerçek sağlayıcı transportu açılmadı.

Yeni additive migration 035–037: onay/inspect işleri, immutable bağlar, sorgu indeksleri ve cron tarama adaleti. Eski migration değiştirilmedi. Destructive down yok; kod, DB ve APP_KEY birlikte doğrulanmış yedekten geri yüklenir veya forward-fix uygulanır.

Yerel PHP 8.4 / gerçek MariaDB: tam paket 229 test, sıfır başarısızlık (kanıt `evidence/f3/local-f3-f-regressions.txt`). PHPStan, mimari ve güvenlik taraması geçti. Yeni HTTP paketi 10 testtir. CI ve performans sonuçları nihai F3_REPORT içinde ayrıca raporlanacaktır; burada henüz başarılı ilan edilmez.

Tekrarlı yerel veri üzerinde grace testi ilk çalışmada başarısız oldu: kron ilk 100 süresi geçmiş, politika bekleyen kayda tekrar dönüyor ve sonraki kaydı aç bırakıyordu. 037 ile son kontrol zamanı eklendi; her tur 100 kayıt / 20 saniye sınırında adil dönüş sağlandı. Test tüm batch turlarından sonra aynı tek suspend/tek notice beklentisini korur. Kuyruğa alınan unsuspend için ödeme hem dispatch öncesi hem sonuç yazımında tekrar kontrol edilir.

Demo: `/client/services` veya `/admin/services`; gerçek test oturumu aç, hesap seç, hizmeti aç, işlemi incele, etkisini kontrol et ve açık kutuyu işaretleyerek onayla. Worker çalışana kadar provider sonucu aktif sayılmaz. UNKNOWN için inspect işi ve güncel personel delili gerekir.

G-C01/G-O01/G-T01 açık; canlı WHM, üretim JIT/cron/restore ve ticari politika doğrulaması yapılmış sayılmaz. F4 başlamadı.
