# F6-E-P3 — offline source inventory adapter teslimi

29 Ekim 2026. DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING.
OPS-004/G-O03 hazırlığı; gerçek kaynak toplama/WHMCS erişimi yapılmadı.

## Çalışan bileşen ve kullanım sınırı

Yeni `Migration/Discovery/InventoryAdapter`, operatörün önceden hazırladığı genel ilişkisel
şema metadata export'unu mevcut P2 Validator sözleşmesine dönüştürür. SQL dump, row data,
API response, WHMCS vendor schema varsayımı veya canlı DB bağlantısı yoktur.
Yeni endpoint/daemon/framework/dependency/migration yok; P2 ve önceki kaynaklar değişmez.

İleride ayrıca yetkilendirilmiş operatör güvenli yerel handle açtıktan sonra:

```php
$result = (new InventoryAdapter())->convert(
    $verifiedLocalHandle, $expectedSourceId, $expectedSnapshotId,
    $independentlyPinnedExportSha256, $privateOperatorKey
);
```

Bu görevde gerçek export toplanmadı veya gerçek metadata kullanılmadı. Caller; dosya
izin/ACL/no-follow/symlink/local-origin ve immutable custody kontrollerini, ayrı byte-digest
pinini, source/snapshot kimliğini ve rastgele32-byte HMAC key custody/rotasyonunu sağlamalıdır.
Handle devri yok; position0 regular plainfile/STDIO gerekir; before/after stat, byte limit,
eof ve expected byte SHA-256 kontrol edilir. Path/URL/SSH/cPanel/WHMCS API açılmaz.

`hostider.relational-inventory.v1` kapalı ara formatıdır. Root: contract/classification,
encoding/system/source_id/snapshot_id/extracted_at/watermark/schema_state/schemas.
Schema/table/column/index kaydı aynı source_id/snapshot_id taşır. Schema name/tables_state/tables;
table name/charset/collation/columns_state/columns/indexes_state/indexes;
column name/type/nullable/charset/collation; index name/kind/ordered columns referansları.
KNOWN/UNKNOWN/MISSING collection state; non-KNOWN state nonempty items ile çelişemez.
Her seviyede required/extra alanlar kapalıdır; mevcut StrictJson tüm nested ve escaped-key
JSON duplicate/Unicode/depth/byte/string sınırlarını uygular. SQL/row/credential alanları ret.

Identifier'lar ASCII unquoted64-byte alt kümesindedir. Vendor davranışı varsayılmaz;
aynı scope'ta exact/case-only duplicate reddedilir. Destek dışı gerçek identifier için
bu v1 fail-closed olur; sessiz trim/casefold/isim dönüştürmesi yapılmaz.
`system=WHMCS` yalnız caller'ın unverified kaynak etiketi olarak P2'ye aktarılır;
adapter generic metadata'dan vendor kimliği keşfetmez. Farklı etiket P2 tarafından engellenir.

## HMAC ve P2 sınırı

HMAC-SHA256 domaini canonical JSON [hostider.inventory-id.v1, source, snapshot, kind, path].
Schema/table/column/index ve export-provenance ayrıdır; parent/source/snapshot/key değişimi
kimliği ayırır. Index referansı aynı table içindeki exact column adına çözülür, column sırası
korunur. Inventory listeleri opaque ID ile sıralanır. Aynı byte/key/context tekrarında sonuç
aynıdır; reordered export aynı entity ID'lerini korur fakat byte-provenance değişir.

Ham adlar, private map ve operatör anahtarı yalnız çağrı belleğindedir; rapor/log/artifact'e
ve object property'ye yazılmaz. Key parametreleri SensitiveParameter işaretlidir.
Provenance artifact_sha256 keyed export-reference'dır; ham export'un yayımlanan byte hash'i değildir.
HMAC anonimleştirme, operatör yetkisi, kaynak doğruluğu veya attestation garantisi değildir.

Metadata type/charset/collation/index semantiğini **yalnız değişmeyen P2 Validator** denetler.
Adapter export grammar, context ve isimleri dönüştürür; P2 BLOCKED ise manifest dışarı verilmez.
Pozitif çıktı P2 ile tekrar doğrulanabilir. Eksik table/column/index inventory_coverage ile
UNKNOWN ve P2 schema UNKNOWN/UNVERIFIED olarak raporlanır.

Schema-only export version/temporal/password/custom fields/files toplamaz: category_coverage
bunları UNKNOWN tutar. P2'nin zorunlu custom_fields boş items placeholder'ı yapısal olarak
KNOWN_METADATA görünebilir; bu **gerçek sıfır custom field** kanıtı değildir. Bu manifest
P3 coverage/raporundan koparılıp completeness iddiasına dönüştürülmemelidir. Toplanmayan
kategoriler için vendor bilgisi veya sentetik gerçek inventory uydurulmadı. Gerçek sürüm,
timezone, password sınıflaması, custom fields, file bytes ve index/export completeness,
identifier case policy ile operator authority ayrı doğrulama gerektirir.

## Hedefli test ve kalite

| Suite | PHP8.3.36 PASS/FAIL | PHP8.4.27 PASS/FAIL |
|---|---:|---:|
| Yeni inventory adapter | 27/0 | 27/0 |
| P2 discovery | 33/0 | 33/0 |
| F6-A contract | 31/0 | 31/0 |
| F6-A visibility | 8/0 | 8/0 |
| F6-B import | 43/0 | 43/0 |
| F6-C reconciliation | 35/0 | 35/0 |
| Hardening unit | 7/0 | 7/0 |
| **Toplam** | **184/0** | **184/0** |

Yeni27 case: sentetik end-to-end/P2 uyumu; determinism; kaynak/snapshot/parent/key ayrımı;
independent canonical HMAC hesabı; missing table/column/index; duplicate/case ambiguity;
charset/collation; modified export pin; bozuk/oversized/duplicate/Unicode JSON; dosya türü,
position/replay/ownership; raw name/key/private map/credential/PII sızıntısı; context karışımı;
G-O03/cutover escalation; index sıra/ref ve P2 type rejection. Bütün fixture adları tamamen
uydurma ve kodda SYNTHETIC olarak etiketlidir; gerçek müşteri veya metadata yoktur.

Son kaynakta iki runtime PHPStan level5:0 hata;465 PHP lint:0 ihlal;487 source/config/test
pattern:0 bulgu; belge encoding/credential check exit0. İlk PHP8.3 koşusunda184 test geçti,
ancak PHPStan wrapper_type için gereksiz null-coalescing buldu; **overall FAILED** kaydı
korundu. Bu koşuda belge denetimi çalışmadı. Kural gevşetilmeden ifade düzeltildi; son kaynak
iki runtime'da bütün kontrollerle tekrar geçti. Her koşunun ayrı source manifesti vardır.

Koşular readonly source bind, ephemeral workspace/DB, internal Docker ağında ve host portu
olmadan çalıştı. Yalnız görev label'ı doğrulanan kaynaklar temizlendi. Pinned runtime/image
kimlikleri ve sonuçlar [summary](evidence/f6-e-p3/summary.json) ile JSON/TXT kayıtlarında;
review runner redaksiyonu [proof](evidence/f6-e-p3/runner-source-proof.json) içinde açıklanır.
FULL, Chromium, Actions ve güncel Composer/npm audit çalıştırılmadı. R2 yöntem istisnası P3'e aktarılmadı.

## Bütünlük, yayın ve rollback

Base HEAD `d8dd28ae7a6cd9b13b4dc7ff35742ba7c0faa955`, tree `9f967e2e7eaec0b418b6712bf77cf93b70f3b06c`.
Production migration tree `c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmedi.
[487 dosyalı canonical kaynak manifesti](evidence/f6-e-p3/candidate-source-manifest.json) SHA-256:
`bcfd1a44ab789916f18c421976fd86e61cd3c75c81d68330750c2c410d83d81d`.
[Yeni üç kaynak/test manifesti](evidence/f6-e-p3/new-source-manifest.json),
[source diff](evidence/f6-e-p3/source.diff), [koruma](evidence/f6-e-p3/preservation.json),
[kanıt manifesti](evidence/f6-e-p3/manifest.json) ve changed-files teslimi bağlar.
Test edilen baytlar before/after/host SHA-256 ile sabittir; operatör key'i manifestlere yazılmaz.

385 önceki kaynak/test,54 migration ve510 tarihsel evidence aynı Git blob'larıdır.
İki suite kaydı dışında bütün1163 eski dosya aynıdır. Kullanıcının ana Windows inceleme
belgesi hash ile korunur; eski worktree/untracked veriler temizlenmedi.
Registry64 suite /895 test yalnız beklenen toplamdır, FULL PASS değildir.
Workflow trigger/permissions değişmez. Yalnız FULL listesine yeni suite eklenir.
Test kayıtlarında source_commit null, testlerin commit öncesi yapıldığını gösterir;
final yayın SHA/tree/platform gözlemi görev sonucunda bildirilir.
Exact parent + tek `feat(F6-E-P3): add offline source inventory adapter [skip ci]` commit,
yalnız fast-forward; [skip ci] CI başarısı değildir.

Migration rollback gerekmez. Rollback ayrı yetkiyle yalnız yeni P3 dosyaları ve iki suite
kaydını geri almalıdır; P2/P1/P0/R2 ve kullanıcı dosyaları korunur. Otomatik revert yoktur.

G-O03 OPEN; source_truth/operator_authority UNVERIFIED; cutover_allowed=false,
effects_allowed=false, real_gate_status=BLOCKED her sonuçta korunur.
OFFLINE_INVENTORY_ADAPTED gerçek discovery/migration readiness veya cutover izni değildir.

F6-E-P3: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING
G-O03: OPEN
REAL WHMCS SOURCE ACCESS: NOT AUTHORIZED
FULL / LIVE CUTOVER / MERGE / STAGING / PRODUCTION: NOT AUTHORIZED
