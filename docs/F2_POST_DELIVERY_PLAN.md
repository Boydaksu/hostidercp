# F2 teslim sonrası denetim planı — 23 Ekim 2026

Kaynak: da38e40a11b0214ca340462f77ec2e1fedaadbdc; F3 ve canlı işlemler kapsam dışı. FR-PAY/BILL/CHECKOUT/ID, FIN-001..010 ve SEC-002/004 temelinde inceleme.

1. Finance/Commerce mutabakatını incele; açık liste, kanıtlı tek kullanımlık çözüm ve append-only telafi eksikse aynı application service/confirmation sınırında tamamla. Dosyalar Finance, Commerce, yeni migration, test ve panel.
2. MariaDB üzerinde mevcut State kilitlerini hacim/eşzamanlılık ile ölç; EXPLAIN, p50/p95 ve kilit bekleme kanıtı üret. Sonuca göre yalnız ilgili bağımlılıkları kilitle; stale ve yarış kontrollerini koru.
3. Queue tüketiminden bağımsız, test/prod kökenini açık tutan F2→F3 handoff sözleşmesini ve mock kabul testlerini yaz; provider handler ekleme.
4. Kaynak/güvenlik/izin/PDF/para/migration sınırlarını ve specification kapsamını karşılaştır. GitHub branch protection'ı salt okunur denetle; ayar değiştirme.
5. Eski 164 testi ve yeni regresyonları, PHPStan/audit/lint ile çalıştır; commit/push sonrası PHP 8.3/8.4 CI kanıtı kaydet. Nihai bulgu–düzeltme–kanıt–kalan risk matrisi F2_POST_DELIVERY_REVIEW.md.

Migration additive; uygulanmış 001–019 değiştirilmez. Finans kayıtları silinmez. Geri dönüş kod+DB+APP_KEY+PDF tutarlı snapshot restore veya forward-fix; üretim restore provası bu görevin kanıtı değildir. Yeni migration kullanılmadan önce backup gerekir. Sayısal performans hedefleri öneri olarak ayrılacak; kullanıcı kararı sayılmayacak.
