# ADR-034 — yerel genel ilişkisel şema export adapter'ı

Status: PROPOSED / independent review pending. 29 Ekim 2026.

hostider.relational-inventory.v1 kapalı bir export ara formatıdır; SQL dump veya gerçek
WHMCS inventory standardı değildir. ASCII unquoted identifier alt kümesi64-byte ile sınırlı;
vendor case folding varsayılmaz, aynı scope'ta case-only duplicate dahi reddedilir.
Her schema/table/column/index kaydı source_id/snapshot_id taşır. Parent nesting HMAC
scope'udur; index referansı aynı table içindeki exact column adıyla çözümlenir.
Şema/table/column/index metadata scalar semantics P2 Validator'a devredilir.
Önceki P2 validator veya güvenlik sözleşmesi değiştirilmez.

HMAC-SHA256 message canonical JSON:
[hostider.inventory-id.v1, source, snapshot, kind, path-components].
Schema/table/column/index ve export-provenance domainleri ayrıdır. Kind/parent/source/snapshot
değişimi farklı kimlik üretir; key caller'dan SensitiveParameter olarak gelir, object property,
rapor, log, source manifest veya özel mapping tablosuna yazılmaz. Mapping sadece çağrı belleğindedir.
Rastgele32-byte anahtar custody/entropy/rotasyonu caller'a aittir; HMAC anonimleştirme,
source doğruluğu, operator authentication veya independent attestation değildir.
Provenance artifact_sha256 burada keyed export-reference'dır, orijinal export byte hash'i değildir.
Beklenen ham byte hash caller'dan ayrı pin olarak gelir; dosya içi digest güven kaynağı değildir.

Regular file handle: plainfile/STDIO, position0, bounded read, stat before/after ve bağımsız
expected byte digest. Path/URL açılmaz. Caller symlink/no-follow, izin/ACL, local origin ve
değişmez export custody'sini sağlamalı; handle API bunları atteste edemez ve ownership almaz.
StrictJson limitleri/decode/Unicode/duplicate denetimleri aynen kullanılır.
Ham isimler, key ve export hiçbir artifact'e yazılmaz. Yalnız sentetik fixture kodu yayımlanır.

KNOWN/UNKNOWN/MISSING collection state ve inventories bağlanır; missing state nonempty
items ile çelişemez. Tables/columns/indexes eksikleri ayrı inventory_coverage üretir,
SCHEMA_INCOMPLETE ile P2'nin sonucu daraltılır. P2 EMPTY custom_fields items için structurally
KNOWN_METADATA diyebilir; P3 bunu collection sonucu saymaz: category_coverage.custom_fields
UNKNOWN, version/temporal/password/files da toplanmamıştır. Manifest bu coverage ve rapordan
koparılarak completeness iddiasına dönüştürülmemelidir. Gerçek custom-field/schema/files
doğrulaması ayrı yetki/operatör kanıtı gerektirir; sentetik kayıt uydurulmaz.

Opak inventory listeleri ID'ye göre sıralanır; composite index column sırası korunur.
Aynı input bytes/key/context deterministik aynı çıktıdır. Aynı metadata yeniden sıralanmış
export'ta aynı entity ID'lerini korur, fakat byte-provenance pin/fingerprint değişir.
Her sonuç G-O03 OPEN ve source/operator UNVERIFIED; effects/cutover false, real gate BLOCKED.
