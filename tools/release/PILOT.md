# cPanel pilotu için operatör kontrolü

Ayrı yetkiyle, sentetik verili özel pilot hesabında uygulayın. Kurulum komutları [kurulum kılavuzunda](INSTALL.md) bulunur. Önce dış kanaldan alınan manifest pin'i, exact runtime SHA ve teslim makbuzundaki arşiv SHA'sını kontrol edin.

- Domain kökünü yalnız sürümün public dizinine bağlayın. Başka vhost üzerinden config, storage, session veya kaynak erişimini de sentetik HTTP istekleriyle kontrol edin.
- Web ve tam CLI binary ile PHP 8.3 veya 8.4 sürümünü ve yapılandırmasını karşılaştırın. 64-bit, PDO MySQL, sodium, mbstring, JSON, session ve Argon2id desteğini iki tarafta doğrulayın. Apache veya LiteSpeed rewrite, HTTPS yönlendirmesi ve güvenli cookie davranışını kontrol edin.
- MariaDB 10.11+ ve boş pilot şema kullanın. Ayrı migration hesabıyla şemayı hazırlayın. Runtime hesabında DDL ve trigger yönetiminin reddedildiğini doğrulayın. Private storage ve session sahipliği, kilit ve kota koşullarını web ve CLI için doğrulayın.
- Cron kapalıyken sağlayıcı, ödeme ve mail etkilerini kapalı tutun. Ayrı pilot DB'deki sentetik kayıtlarla Queue ve Outbox komutlarını operatör gözetiminde bir kez prova edin. Üretim kayıtlarını kullanmayın. Bir dakikalık cron yeteneğini ve süreç çakışmasını engelleyecek kilit mekanizmasını teyit edin. Overlap sırasında ikinci sürecin iş almadığını pilotta sınayın.
- Pilot öncesi kod, DB, storage ve anahtarın geri yüklenebilir yedeğini alın. Geri dönüşte HTTP ve cron'u durdurun. Uyumlu kod, DB ve storage setini geri yükleyin ve aynı anahtarı kullanın. Migration history üzerinde kör tekrar veya silme yapmayın.

Eksik, başarısız veya kanıtlanamayan herhangi bir kontrol kurulumu **BLOCKED** bırakır. Sonuç şablonuna yalnız durum ve sentetik kanıt kimliği girin. Hesap adı, host, dizin, credential, token, PII ve ham çıktı eklemeyin. Pilot sonucu ayrı incelemeye tabidir.
