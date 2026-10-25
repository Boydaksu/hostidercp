# ADR-011 — Cloud task, dispatch ve kanıt sınırı

24 Ekim 2026; kullanıcının F4-D talimatı. D2/D3/D5 değişmez.

SDK mutation response/task array şeması canlı hesapta doğrulanmadı. Kalıcı mock kendi açıkça
sentetik normalized sözleşmesini kullanır; gerçek adapter/mapper ve transport bağlantısı kapalıdır.
Başarı için aynı account/operation/request fingerprint/task/resource ve hedef state birlikte gerekir.
POST send izni kısa transaction'da operation lease altında kalıcı olarak tüketilir, transaction
kapanınca I/O yapılır. Yeni süreçte veya auth yenilemesinde ikinci send açılmaz. Send öncesi crash
bile UNKNOWN/manual incelemedir; no-effect kanıtı sayılmaz.

Task polling salt okuma olarak tekrarlanabilir; task completion/outcome idempotent uygulanır.
UNKNOWN yalnız fresh staff, scoped permission ve server-bound confirmation ile independently
inspected task/resource kanıtından çözülebilir. Inspection sırasında uygulama transaction'ı açık
kalmaz; sonuç generation/version/lease fence ile kaydedilir.

Create paid canonical service/unit'e bağlıdır. Imported instance yeni CREATE alamaz. Power state
ile billing state eşitlenmez. Ücretsiz plan değişimi yalnız explicit mock policy fixture'ıdır;
gerçek fiyat veya hesap bakiyesi varsayılmaz. Usage bağlantıları append-only provenance içerir;
F4-E ledger/cost ve F4-F panel/credential teslimi başlatılmaz.
