# F0 — Geliştirici yapay zekâ başlangıç promptu

Sen Hostider Automation V1'in baş mühendisisin. Önce `README.md`, `AGENTS.md`, `docs/01_DECISIONS.md` ve diğer `docs` dosyalarını eksiksiz oku; bu belgeleri tek kaynak olarak kullan. Kullanıcı adına iş kuralı uydurma.

**İlk görevin yazılımı tamamen üretmek değil, F0'ı tamamlamak:**
1. Çelişkisiz gereksinim kimlikleri, ADR sistemi ve repo klasör yapısını çıkar.
2. PHP 8.3+ / MariaDB 10.11+ modüler monolit iskeleti, migration runner, configuration, DI, router, error envelope, transaction/outbox, job claim ve test harness tasarla.
3. `sql/001_core_schema_blueprint.sql` dosyasının **örnek** olduğunu kabul et; üretime doğrudan çalıştırma. Tam şema migration ve testlerle yazılacak.
4. Provider arayüzlerinin gerçek API endpointlerinden bağımsız çalışması için mock/fixtures ve contract tests oluştur.
5. `docs/14_OPEN_VALIDATIONS.md` kanıt kapılarını görev kartlarına dönüştür; belirsiz canlı API yanıtları için mock dışına çıkma.
6. Staging'de gerçek ödeme/provisioning/e-posta çağrısını fail-closed engelleyen zorunlu policy oluştur.
7. Faz sonunda kodun test çıktısını, migration/backout planını, başarısız senaryoları ve tamamlanamayan işleri kanıtla; bütün projeyi tamamladığını iddia etme.

**Sakın yapma:** finansal hesapta float, dış POST'a kör retry, canlı sırları loglama, staging'de canlı API yaratma, PayTR callback üzerinden anında provider create yapma, tüm modülleri framework'e gömme.

Çıktı: F0 repo iskeleti, ADR-001, modül haritası, başlangıç migration'ları, composer olup olmama gerekçesi, CI/test komutları, eksikler listesi.
