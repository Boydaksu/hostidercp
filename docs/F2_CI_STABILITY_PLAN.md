# F2 son CI kararlılığı kapanış planı

Kaynak HEAD d78e708; başarısız run 39982782126. Finans/Identity iş kodu ve 001–022 migration değişmez. F3 ve canlı işlemler kapalı.

1. Tam GitHub loglarını incele, ilk HTTP transport kaybını bağımlı assertion'lardan ayır. Eski çalışmada saklanmamış sunucu stderr/exit nedenini kanıt varmış gibi sunma.
2. Test sunucusuna benzersiz port/çalışma kimliği, doğrulanmış readiness, süreç/transport tanısı ve güvenli kapanış ekle. Gerçek hata test başarısızlığı olmalı; retry ile mali komut tekrar gönderilmemeli.
3. Mevcut on commerce HTTP senaryosunu koru; bağımlı senaryolarda ilk hata sonrası zincirleme yanlış tanıları önle. Harness arızalarını deterministik test et.
4. Windows session cleanup yarışını ölç, hata ve kaynak yaşam döngüsünü kaydet; Linux CI tekrarlarıyla karşılaştır. Yalnız gerekli test altyapısı düzeltmesi.
5. PHP 8.3/8.4 fail-fast matrix iptalini kaldır; 175 regresyon + yeni harness testleri, HTTP/session tekrarları, failure artifacts, PHPStan/audit/lint. Tek yeşil koşuyu yeterli sayma; güncel HEAD'de tekrarlı kanıt üret.
6. main korumasını salt okunur doğrula; 173/171 farkını dosya manifestiyle açıkla. Son rapor F2_CI_STABILITY_REPORT.md, gerçek commit/run sonuçları ve kalan riskler.

Geri dönüş yalnız test/workflow/dokümantasyon commit'lerini geri almak; migration yok. Test verisi yalnız izole DB; başarısız işlemler saklanacak, assertion veya eski testler kaldırılmayacak. Repository ayarı değiştirilmeyecek.
