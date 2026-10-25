# F4 ara denetim sonrası uygulama planı

24 Ekim 2026; kullanıcının devam talimatı. Öncelik mevcut A/B kaynağını F3 tabanında uzak dala yayımlamak ve gerçek PHP 8.3/8.4 CI kanıtını almaktır. GitHub bağlantısı içerikleri aynı Git blob/tree SHA'larıyla taşır; sunucu commit metadata'sı nedeniyle yerel/uzak commit SHA eşlemesi evidence/f4/github-publication.json içinde saklanır. Main hâlâ protected=false; sahibi PR review, zorunlu verify (8.3)/(8.4), force-push/delete ve bypass korumalarını açmalıdır. Merge yapılmaz.

Sonraki sıra: (1) özgün SDK POST/auth retry sentetik transport regresyonu ve tek dispatch fence; (2) Domain operation, kalıcı iş/outbox, lease/fencing, evidence ve kontrollü failover; (3) Domain Commerce renewal/transfer ownership ve HTTP confirmation; (4) Cloud kalıcı dünya/task/VM akışları ve exact cost; (5) paneller, güvenlik, 1/4/8 worker performans, final CI. F4_PLAN gereksinimleri ve çıkış kriterleri aynen korunur.

SDK güvenlik değişikliği migration istemez; `Providers/VoyarMutationFence`, zip:// üzerinden salt okunur özgün SDK ve FakeTransport testleri, workflow zip extension/suite ve SDK inceleme raporu değişir. Auth exception, network timeout ve body loss ayrı sentetik senaryolardır. Fence her gerçek mutation transport send'inde uygulanır; ilk denemeden önce tüketilir ve hiçbir exception bunu sıfırlamaz. Kalıcı operation/lease guard'ının yerine geçmez. Test-only ve G-V02/V03 canlı dispatch kapalıdır.

Domain/Cloud ortak operation mekanizması Services application service alanında additive migration 041+ ile kurulacaktır. F3 engine kopyalanmaz; yeni provider-specific akışlar mevcut Commerce/Finance/Services sınırlarını kullanır. Her alt faz kendi gerçek migration/rollback ve concurrency/HTTP/performance kanıtını kaydeder. Eski 001–040 yeniden yazılmaz. Testler gevşetilmez; başarısızlıklar saklanır. Geri dönüş tutarlı kod+DB+APP_KEY snapshot/forward-fix; destructive down yoktur.

G-V01..04, G-R01, G-D01 ve önceki açık bütün GATE'ler OPEN; production provider/PayTR/WHM/SMTP veya F5 yoktur. Eksik uygulama tam F4 kabulü olarak sunulmaz.
