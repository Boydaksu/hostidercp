# ADR-008 — F3 unit claim, mock provenance ve yenileme sınırı

Onaylı F3 kickoff kapsamında teknik normalleştirme; ticari karar değiştirmez.

F2 handoff eligibility production yönünü tanımlar. F3 test motoru ayrı ve fail-closed mock yoludur: yalnız APP_ENV=test ve order.origin_environment=test kabul eder. Staging/LEGACY_UNKNOWN siparişler backfill edilmez. Production motoru/WHM ağ transport'u açılmaz.

Bir order item quantity>1 olabilir. Her quantity unit'i `(order_item_id, unit_no)` unique service üretir. CREATE için item-level generation unit numarasıdır; sonraki lifecycle kind'larında generation item+kind düzeyinde monoton tahsis edilir. `(order_item_id, operation_kind, generation)` unique kuralı korunur. Aynı hizmette tek in-flight pointer/fence vardır. UNKNOWN create'in yeni generation ile kör tekrarı yoktur; bulunamayan kaynak manuel review'da kalır.

F2 immutable billing request order_id unique'dir. Yenileme, mevcut QuoteService/OrderService/BillingService üzerinden yeni renewal order/request oluşturur; Services period/order bağlantısını kendi tablolarında tutar. Renewal order canonical create discovery'den dışlanır. Finans tablolarına doğrudan yazılmaz. Yeni period ancak ödeme tam + bağımsız mock provider uzatma/inspect kanıtıyla current olur; para kaydı provider hatasında geri yazılmaz.

HostingProvider mevcut altı metodu korunur. Gerçek WHM için dönem uzatma endpoint'i varsayılmaz: gerekirse ayrı optional HostingRenewal capability yalnız mock tarafından sağlanır; canlı destek G-C01 doğrulaması ister. Aynı şekilde package proration ve transfer borç kapsamı açık test politikasıyla doğrulanır, GATE olmadan canlı davranışa yükselmez.

Politikalar global→kategori→ürün→hizmet nullable mirasıyla çözülür. Eksik kritik süre/politika fail-closed'dur; test fixture sayıları SLA veya kullanıcının ticari kararı değildir. Bildirimler durable HELD kalır; gerçek SMTP teslimi iddia edilmez.
