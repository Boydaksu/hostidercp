# F4 bütünleşik mock çıkış kabul paketi planı

25 Ekim 2026. Taban HEAD 8156208ad90afcb8b563864e3fe065d8bea05f35;
uygulama 6e25a8b98de6e0c9da5e84df6b78e46cd47854f2; kabul edilen 49/394/0.
Kullanıcı C/D/E/F teknik mock kabullerini verdi; bütünleşik F4 çıkışı verilmedi.

Kapsam: mevcut A–F application service, finans/Identity/HTTP/queue/cron sözleşmelerinin
kanıtlarını tek F4_REPORT altında toplamak. FR-DOMAIN/VOYAR, FIN-010, DOMAIN-001/002/003,
PROV-001/002/004/005/006, SEC-001–004, OPS-001/005, R1-05/06/07, R5-04/07, R7-06/07.
Native kod, provider contract veya iş kuralı yeniden yazılmaz. Mevcut 394 testteki gerçek
modüller arası senaryolar tek tek kaynak ve ham CI PASS satırıyla eşlenir; tek bir dev
senaryo veya bütün P0'ların tamamlandığı iddia edilmez. Eksik kalan kapsam açık yazılır.

Dosyalar: F4_REPORT güncellemesi; evidence/f4-mock-exit checksum, test/invariant indeksi,
CI/runtime ve migration replay kanıtı; README güncel kabul sınırı. Uygulama/test/migration
değişikliği beklenmiyor. Yeni test ancak mevcut akış kanıtında somut boşluk bulunursa eklenir.

Doğrulama: mevcut HEAD PHP8.3/8.4 ham CI ve iş durumları; gerçek MariaDB üzerinde boş
şema 001–048 apply/replay ve 047→048 upgrade/replay; 48 dosya byte/SHA256 eşliği.
HTTP/session ve 1/4/8 worker/cron ölçümleri aynı checkout CI günlüklerinden alınır. Rapor
commit'inin otomatik tam CI'ı da kontrol edilir. Kod değişmediği için gereksiz yerel tam
suite tekrarı yerine kabul edilen tam CI + odaklı schema replay kullanılır.

Geri dönüş: paket yalnız belgedir; git ile belge sürümüne dönülebilir. Runtime geri dönüşü
ayrı tutarlı kod/DB/APP_KEY snapshot restore veya forward-fix gerektirir; finans/evidence
silme, migration downgrade veya UNKNOWN POST replay yapılmaz.

İstisnalar: DOMAIN-002 PARTIAL/MANUAL_REVIEW; mapping/import CRUD DEFERRED / NOT IMPLEMENTED /
NOT ACCEPTED (önceki açık erteleme onayı uygulama kabulü değildir); PROV-005 ve tüm canlı
GATE'ler OPEN; gerçek credential retrieval/live mapping/customer operation NOT IMPLEMENTED /
NOT VERIFIED. GO/NO-GO bu sınırlarla açık gerekçelendirilir; F4 DONE/production-ready yoktur.
Paket sonunda durulur; F5/F6, merge, staging/production veya canlı provider erişimi yoktur.
