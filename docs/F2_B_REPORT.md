# F2-B katalog — çalışma raporu

FR-CATALOG; R3-01/06, R5-01..04/11. Migration 008: kategori, ürün, varyant, seçenek grubu/değeri, bağlı/bağımsız addon, bağımsız döngü/fiyat, vergi, immutable fiyat yayını/maliyet önerisi, sağlayıcı eşleme ve promosyon/redemption veri modeli. Önceki migration'lar değişmez.

CatalogService gerçek transaction içinde ticari kayıt oluşturur, optimistic version ile satış durumunu değiştirir, audit ve outbox yazar. Kategori vergisi ürün override'ı yokken miras alınır; fiyatlar döngü ve currency bazında ayrı sürümlenir. Maliyet önerisi fiyatı yayınlamaz; ayrı onaylı price-publish gerekir. SOURCE_FX, FIXED_PER_CURRENCY, FIXED_THEN_FX stratejileri açıkça seçilir. CUSTOM_FORMULA için kabul edilmiş formül olmadan hesap uydurulmaz; yapılandırılmış CPU/RAM/disk seçenek fiyatları ayrı item olarak modellenir.

6 gerçek MariaDB testi: entity ilişkileri/döngüler, bağımsız fiyatlar ve eski sürümün korunması, maliyetin satışa etkisizliği, provider eşlemesinin kaynak oluşturmaması, vergi mirası/status/version, promosyon politikası ve rollback/impersonation red. Kanıt dosyaları evidence/f2/f2-b-* içinde kaydedilir. Promosyon kullanımının atomik tüketimi C/D'de test edilecek; B'de yalnız policy/quota altyapısıdır. UI ve gerçek müşteri checkout G'de; bu rapor F2 tamamlandı anlamına gelmez.

Geri dönüş: 008 additive DDL için otomatik down yok; doğrulanmış DB+kod snapshot restore veya forward-fix. Fiyat geçmişi silinmez. Gerçek provisioning/kur/PayTR çağrısı yok; bütün canlı GATE'ler OPEN.
