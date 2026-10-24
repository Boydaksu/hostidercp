# F3 son denetim düzeltme planı — 24 Ekim 2026

Taban: `4c6c98e911a40ba56940a9f07cc5ca8d5684e3f8`; dal: `codex/f3-service-engine`.

- Kapsam: FR-SERVICE/FR-OPS, PROV-001/002/003/004/006, SVC-001/002/003, SEC-004, OPS-005. Runner starvation ve gerçek CLI bounded batch doğrulaması; F4 ve canlı etkiler kapsam dışı.
- Dosyalar: Runner, console, bağımsız süreç/MariaDB regresyonları, CI, benchmark ve işletim/kabul raporu.
- Migration: 038 additive operasyon değerlendirme zamanı ve indeks; 001–037 değişmez. PENDING geçici blokajda korunur; UNKNOWN tekrar gönderilmez.
- Test: önce en az 100 bloke operasyon arkasındaki sağlıklı iş için başarısız gerçek DB kanıtı; sonra tekrar cron, kapasite geri gelişi, overlap, iki süreç, tek kaynak, deadline/batch devamı. Önceki 232 test, lint, PHPStan, audit ve PHP 8.3/8.4 CI korunur. Doğrudan worker ile gerçek CLI ölçümü ayrı raporlanır.
- Geri dönüş: worker durdurulur; tutarlı kod/DB/APP_KEY snapshot restore veya forward-fix. Destructive down yok; eski kod yeni sütunu kullanmaz fakat starvation yeniden oluşur. Üretim restore kabulü iddia edilmez.

Mevcut iş kararı değişmez; bu teknik fairness düzeltmesi 037 scheduler yaklaşımını operasyon kuyruğuna uygular. GATE'ler açık ve main ayarları değişmeden kalır. Sonuç F3_POST_DELIVERY_REVIEW.md içinde kanıtlanır.
