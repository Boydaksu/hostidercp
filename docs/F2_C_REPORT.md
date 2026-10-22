# F2-C FX ve fiyatlandırma — çalışma raporu

FR-CATALOG/CHECKOUT; R3-02/03/06, R5-11, R7-01..03; FIN-005 fiyatlandırma temeli. Migration 009 immutable FX rate ve quote tablolarını ekler. FX rate, source amount, sürümlü price, vergi ve indirim snapshot içinde string precision ile saklanır. 15 dakika sonunda eski quote ile ödeme talebi çıkarılamaz; yeniden hesaplanan fiyat açık onay ister.

PriceResolver SOURCE_FX / FIXED_PER_CURRENCY / FIXED_THEN_FX önceliğini uygular. TaxCalculator açık vergi politikası ister; dahil/hariç ve per-line HALF_UP kuralını snapshot'a yazar. Dahil vergi bölümü doğrudan currency scale'ine yuvarlanır; iki kez ara yuvarlama yapılmaz. PromotionEngine deterministic kod sırası, scope/period ve kombinasyon kuralı uygular. Catalog Promotions application service redemption işlemini aynı transaction'da kendi tablolarına yazar; promotion row lock + güncel quota sayımlarıyla paralel kullanım limiti korunur. Quote plan yalnız okur, gerçek komutu savepoint'te çalıştırmaz.

9 MariaDB testi: USDTRY/spread, bağımsız TRY önceliği, tam 900 saniye expiry ve immutable quote, dahil/hariç vergi/kuruş, missing/stale/untrusted/future FX, option seçimi, salt-okunur plan, iki ayrı süreçte promo quota ve kombinasyon, renewal protection/notice policy. Finansal exact arithmetic sınırı 10 promosyonun ara decimal hassasiyetini koruyacak şekilde genişletildi; nihai Money hâlâ signed BIGINT aralığını aşamaz. Kanıtlar evidence/f2/f2-c-*.

Sınırlar: üretim veya staging sentetik dövizle checkout yapamaz; G-X01 OPEN. Gerçek feed yok. Yenileme servisi yeni dönem için yeniden fiyatlar ve açık protection/notice girdisi ister; hizmet scheduler'ı F3'tür. Vergi ülke/şirket/muafiyetin hukuki seçimi onaylanmadığında vergi oranı kendiliğinden çıkarılmaz; açık policy zorunludur. Kesinleşmiş ödeme talebi koruması D'de test edilecek.

Geri dönüş: önceki kod+DB+anahtar snapshot birlikte veya forward-fix; 009 destructive down içermez. Eski kur/fiyat/quote verisi güncellenmez veya silinmez. GATE durumları değişmedi; gerçek finansal/sağlayıcı etki yok.
