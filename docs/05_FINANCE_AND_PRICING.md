# 05 — Finans, döviz, ödeme ve fiyatlandırma normları

## Fiyatın kaynakları
Her ürün/döngü için `pricing_strategy`:
1. `SOURCE_FX`: örn. 3 USD kaynak fiyatı; TRY hesap için onaylı FX feed + ürün farkıyla hesaplanan TRY.
2. `FIXED_PER_CURRENCY`: hesap para biriminde elle tanımlı sabit fiyat; yoksa tanımlı fallback politikası.
3. `CUSTOM_FORMULA`: açıkça admin tarafından kabul edilmiş CPU/RAM/disk tablosu veya maliyet+marj kurallı satış önerisi; otomatik publish yok.

Fiyat kaynağına `source_currency`, `source_amount_decimal`, `billing_cycle`, `fx_rate_id`, `spread_rule`, `tax_rule`, `product_price_version` bağlanır. Ara hesap `decimal`; nihai ödeme `minor unit` olarak saklanır.

### Örnek fiyat akışı (rakamlar temsili)
- Ürün temel 3.00 USD; müşterinin hesap para birimi TRY.
- Kaynak `USDTRY = 43.000000` ve ürün fiyatlandırma farkı `%2`; varsayılan **satış kuru** 43.86 TRY/USD.
- Vergi hariç fiyat 131.58 TRY, seçilmiş vergi kuralına göre son toplam hesaplanır.
- `quote` oluşturulur: `expires_at = created_at+15 min`, fiyat/kur/vergi snapshot.
- `payment_request` oluşturulduğu an tutar kesinleşir. Bir sonraki yenilemede aktif fiyat politikası yeniden değerlendirilir.
- Hesabı USD müşteri USD tahsil edilir; PayTR'nin bu hesap için seçilmiş gateway para birimini desteklemesi canlı test kapısıdır.

## 15 dakikalık teklif ve belgeler
- `Quote`: geçici seçim/fiyat ve son geçerlilik; quote süresi geçtiyse checkout yeniden fiyatlanır ve müşteri onayı alınır.
- `PaymentRequest`: oluşturulmuş ve muhasebe referansına bağlanmış immutable tutar/para birimi/kurlar/indirim/vergi; yeni kura göre canlı değişmez.
- `Draft` ödeme talebi düzenlenebilir ama her değişimde revision. `Issued` için iptal+yeniden çıkarma veya kayıtlı düzeltme gerekir.
- Sepet fiyatını müşteri tarafındaki gizli inputtan değil, sunucu quote snapshot ve ürün kurallarından hesapla.

## Çoklu döviz ve tek cüzdan
- Her müşterinin tek `billing_currency` vardır. `wallet_account.currency = customer.billing_currency`.
- Yabancı para ile top-up **yalnız dövizle dönüşüm ve gateway uygunluğu doğrulandıktan sonra** cüzdan hesabına aktarılır; orijinal ödeme tutarı / FX rate / spread / net credited immutable kaydedilir.
- Kur dönüşümünde mevzuat, sağlayıcı komisyonu veya bakiye iadesi yükümlülüğü sonuçlarını hukuk/muhasebe teyidi olmadan varsayma; uygulama teknik kayıt tutar.
- Para birimi değiştirme: açık ödeme taleplerini ve rezervasyonları denetle, yönetici step-up auth ve onay, cüzdan tek transaction ile convert, geçmiş faturalar sabit.
- Cüzdan borç değildir? Müşteri yatırdığı fakat tüketmediği bakiye Hostider için yükümlülüktür; kasa gelirine ikinci kez yazılamaz.

## Tahsilat defteri
- PayTR deneme oluşturma -> benzersiz `merchant_oid`; callback hash sabit zamanlı doğrulama, tutar+currency+payment_request referansı mutabakatı, tekrar bildirimlerde no-op.
- `paid_amount < due_amount` ise `PARTIALLY_PAID` ve servis kurulumu başlamaz; `paid_amount == due_amount` halinde ödeme tamamlanmış işlem ve outbox provisioning oluşur.
- Fazla ödeme: sessiz fatura kapatma yok; `UNALLOCATED` veya iade/inquiry işlemi. Taksit tutarı farkları gateway bildirimine göre muhasebeleştirilir.
- Havale onayı kim, ne zaman, referans ve dekont; iki yönetici işleme aynı anda onay verse bir tahsilat kaydedilir.
- Karma ödeme: wallet debit ve PayTR payment ayrı kaynak; `payment_allocations` ile aynı talebi kapatırlar. Ödeme öncesi cüzdan payı reserve edilebilir, başarısızlıkta reserve çözülür.
- Resmi belge: manuel e-fatura/e-arşiv PDF, belge no/tarih/vergi kimlik alanları, yükleyen actor, checksum, belge tipi, erişim kuralı. Bir PDF ne invoice ne ödeme request tutarını geriye dönük düzenler.

## Kasa ve tekrarlayan giderler
- Tek kasa dashboard; işlem kategorileri gelir, gider, düzeltme, transfer türünde. PayTR bloke/bekleyen ödeme ve gerçekleşmiş banka girişleri iki ayrı **gelir** üretmez.
- `cash_entries` aynı `ledger_journal_id` üzerinde tahsilat/gider ile eşleştirilir; manuel hareketler açıklama ve source_reference ister.
- Tekrarlayan giderler kural ve gerçekleşen ödeme kayıtlarına ayrılır; oluşturulan 'ödenecek' kaydı, fiilen ödenmiş gider değil.
- Kârlılık: müşteri hasılatı (vergisiz) - gerçek sağlayıcı maliyeti - komisyon/ek gider; nakit akışı ile kâr farklı rapor.

## İade
- Uygunluk ürün politikası (`none`, `prorated`, `staff_approval`) + hemen/dönem sonu durdurma + veri/hizmet sonuçlarının doğrulanması.
- Orijinal ödeme para birimi ve ödeme referansı ile maksimum kalan iade limiti. PayTR partial refund desteklenebilir (güncel API/sözleşme test edilmeli).
- Karma ödemenin iadede hangi kaynağa nasıl döneceği açık kural: önce ilgili tahsilat kaynaklarına oransal/algoritmik dağılım; kullanıcıya dekont; cüzdan payı wallet credit olarak geri yazılır.
- Cüzdana iade farklı para birimindeyse tarihsel orijinal tahsilatı değiştirme; ayrı kurdan dönüşüm fişi oluştur.
- Bir iadenin iki kez gerçekleşmesi DB unique request/reconciliation ile engellenir.

## Vergi politikası ve uyumluluk
- Ürün/kategori düzeyinde vergi dahil/hariç; vergi oranı ve muafiyet müşteri ülke/şirket bilgisi ve tarihe bağlı sürümlenir.
- Bu doküman vergi hukuku yorumu değildir. E-belge ne zaman düzenlenir, USD/TRY tevsik, iade ve cüzdan uygulaması mevzuat kapsamında mali müşavir onayından geçmelidir.
- Hesap para birimi ile banka ödeme gateway para birimi farklı tutulabilir ama otomatik kabul edilmez; FX ve şirket muhasebesi mutabakatı gerekir.
