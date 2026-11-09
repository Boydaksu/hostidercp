# ADR-033 — bounded offline discovery metadata v1

Status: PROPOSED — P2 independent review pending. 29 Ekim 2026.

Mevcut CanonicalJson duplicate JSON key'lerini denetlemez; onu değiştirerek diğer
güvenlik sözleşmelerini etkilemek yerine P2'ye özel StrictJson scanner eklenir.
Native parser grammar/Unicode/depth kontrolünü yapar; scanner tüm object seviyelerinde
decoded anahtar eşliğini kontrol eder. Object/list ayrımı stdClass ile korunur.

hostider.offline-discovery.v1 yalnız normalize metadata kabul eder. Kaynak/snapshot
kimlikleri Contract::id sınırında source-/snapshot- +64 hex; diğer inventory kimlikleri64 hex.
Tablo/kolon/custom-field/dosya isimleri, paths, row data, password hashes, serbest metin yoktur.
Bu digestler anonimleştirme garantisi değildir; operatör müşteri/veri değerlerini
hashleyip bu kanallara koymamalıdır. Validator gizli veri taşıyan digestin kaynağını bilemez.
Sentetik fixture hiçbir gerçek WHMCS tablo/sürüm/parola davranışını belgeleyemez.

Classification SYNTHETIC veya SANITIZED_METADATA; provenance sırasıyla
SYNTHETIC_FIXTURE / OPERATOR_SUPPLIED_UNVERIFIED. Bölümler source/snapshot bağını tekrarlar.
Snapshot schema_sha256 ve root content_sha256 CanonicalJson/Contract::hash ile bağlanır.
Beklenen source/snapshot ve izinli custom-field ID listesi caller tarafından ayrı verilir;
bunların kendisi kimlik doğrulama veya güvenilir operatör yetkisi değildir.
Entity enum yalnız custom field metadata kategorisidir; SourceIdentity yalnız row entity
kimliğini temsil ettiğinden metadata'yı sahte customer identity'ye dönüştürmeyiz.

version fact, schema inventory, temporal fact/policy, password algorithm class,
custom_fields ve files zorunlu kategorilerdir. Eksiklik bilinçli UNKNOWN/MISSING/boş
envanter ile gösterilir; kategori tamamen yoksa contract BLOCKED olur.
Known numeric version yalnız biçimi doğrulanmış iddiadır; davranış/uyumluluk UNVERIFIED.
Genel SQL charset/type enum'ları WHMCS schema iddiası değildir. Desteklenmeyen değerler ret;
çeviri/karakter kaybı/parola reset veya native mapping kararı üretilmez.
F6-A identity/values, B rows/private files, C finance/visibility/final delta kanıtı yoktur.

Sonuç KNOWN_METADATA/UNKNOWN/UNVERIFIED/INCOMPATIBLE/BLOCKED ile sınırlıdır.
Geçerli contract offline doğrulanabilir; çelişki/bozuk format BLOCKED olur.
G-O03 her zaman OPEN; kaynak doğruluğu, operatör yetkisi ve attestation UNVERIFIED.
Digest yeniden hesaplanabilir; imza/attestation veya anti-replay freshness kanıtı değildir.
Object field sırası canonical; listeler verilen sırayla hashlenir. Dosya içeriği okunmaz.
Byte131072/depth16/node8192/string128; list başına sabit üst sınırlar.
Caller-opened regular handle API'si path çözmez; handle açma/no-follow politikası caller'a aittir.
Dönen rapor raw metadata içermez; yalnız kategori/kod/fingerprint ve sabit izin sınırları.
