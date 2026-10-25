# F5-A hedefli denetim düzeltme planı

25 Ekim 2026. Taban HEAD 5389825e93af183f79753e8f00ed6025939909dc,
denetlenen uygulama a38e8da; 51 suite / 428 test. Yalnız P1-1/P1-2 test/mock.
Gereksinimler: FR-SUPPORT, SUP-003/004/007 çekirdek, OPS-001/005, R3-09.

Kapsam/dosyalar:
1. Support.Service public event payload'ına kendi immutable event_uuid'sini koyar.
   Support.EventSink exact event/outbox/ticket/message ve native outbox:<uuid> job
   payload bağını ve published_at durumunu doğrular. Native Core Queue/Outbox değişmez.
   Tek consume tek support.held.v1 receipt; callback içinde bağlar tekrar doğrulanır.
2. Şifreli subject üzerinde bütçesiz tam tarama veya plaintext indeks yoktur.
   F5-A list q anahtarı varsa (boş değer dahil) 422 SUPPORT_SEARCH_UNSUPPORTED;
   doğru şifreli arama tasarımı F5-E'ye ertelenir. q'suz bounded ACL-before-pagination
   listesi korunur. Sabit veri kümesinde ORDER BY id/OFFSET kayıpsız test edilir;
   eşzamanlı yeni kayıtlar için snapshot pagination garantisi verilmez.
3. Yeni tests/support-post-audit.php: iki kusurun eski kaynakta karşı-örnekleri;
   gerçek MariaDB/native Outbox+Queue ve gerçek HTTP/cookie ile regression.
   Eski 428 test adları/korumaları korunur. Yalnız değişen q ve event payload
   sözleşmesini kullanan eski test girdileri güncellenir; gizlilik assertion'ları güçlenir.

Migration: yeni şema yok; 001–049 byte/hash korunur. Eski iki alanlı support.changed
test/mock işleri fail-closed olur; kör backfill/replay veya geçmiş receipt silme yoktur.
Eski hatalı held receipt mail teslimi sayılmaz; mevcut test verisi gerekiyorsa izole
yeniden kurulum veya ayrı incelenmiş düzeltme gerekir.

Test: eski kaynakta yeni P1 testleri FAIL kanıtı; düzeltilmiş kaynakta PASS; tüm 428+
test, migration replay, PHPStan, locked audit, lint/pattern scan ve PHP8.3/8.4 CI.
Native Identity, MFA/step-up, note CHECK/projection, transaction ve 1/4/8 süreç korunur.

Geri dönüş: tutarlı kod/DB/key snapshot restore veya forward-fix. Yeni migration/down
yoktur; eski yanlış tüketim/search davranışına otomatik geri dönüş önerilmez.
Demo: CREATE dispatch/receipt → RESOLVED/CLOSED → ilk STATE receipt → ikinci unpublished
ve receipt yok → ikinci dispatch/receipt → duplicate tek receipt. 51 ticket q açık
hata; q'suz müşteri/staff aynı yetkili sayfalar. Mail/notification/provider dispatch yok.

Teslim: F5_A_POST_AUDIT_REVIEW ve evidence/f5-a-post-audit, gerçek SHA/çift PHP CI.
Bağımsız yeniden kabulde durulur. F4 kabulü/GATE'ler, F5-B/C/D/E/F6 ve canlı sınır değişmez.
