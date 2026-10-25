# ADR-010 — F4-C kanıt ve yenileme sınırı

24 Ekim 2026; ikinci bağımsız denetim düzeltmesi. D2/D3 değişmez.

Domain yenilemesi Services.Renewals.request tarafından oluşturulan immutable order/period/price
bağına dayanır. Serbest DOMAIN siparişi yetmez. Domain motoru bu bağı Services application
servisinden enqueue, claim, finish ve reconciliation anında doğrular. Yeni dönem önceki VERIFIED
dönemin bitişinden başlar; kümülatif registrar sayacı satın alınan artış değildir.

F4 mock registrar güncel ticari teklif/bakiye kanıtı sağlamıyor. Bu yüzden kesin no-effect sonucu
alternatif create açmaz, MANUAL_REVIEW olur. Önceki sentetik otomatik failover kabul iddiası geri
çekilir. Canlı API alanı veya fiyat politikası uydurulmaz; G-R01/G-D01 açık kalır.

Inspect açık uygulama transaction'ında çalışmaz. Snapshot ile sonuç arasında generation, active
attempt, operation state ve domain version fence uygulanır. Süreli inspection kaydı aynı fence'i
resolve anında tekrar denetler. Domainin yokluğu veya call kaydının yokluğu no-effect değildir.
