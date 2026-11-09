# ADR-024 — F6-A sentetik snapshot ve mapping kanıtı

## 27 Ekim 2026 — F6-A sınırlı bağımsız kabulü ve F6-B yetkisi

**F6-A LIMITED MOCK TECHNICAL BASELINE — ACCEPTED.** Proje sahibinin bağımsız
kararı yalnız sentetik contract, identity, snapshot, provenance, mapping evidence
ve validation kapsamını kabul eder. Message kind/visibility P1 **CLOSED**.
Kabul edilen uygulama `063e846999e4bda513f3563de0c4f13ac8858b97`,
tree `fb238efc3aa70cbbd022652d192d9e89c089f211`.
[FULL40475881946](https://github.com/Boydaksu/hostidercp/actions/runs/40475881946):
PHP8.3/8.4 her sürüm54 suite/621 uygulama testi/0 hata; ayrı6 CI kontrolü,
Chromium11/11, state3/3, Services HTTP10/10, Commerce HTTP30/30 ve
session concurrency20/20 PASS.001–054 korunur.

Gerçek WHMCS sürüm/şema keşfi, G-O03, gerçek SUP-010 ve PROV-004 kabul edilmedi.
Diğer vendor/operasyon/live kapıları, DOMAIN-002 PARTIAL/MANUAL_REVIEW,
production mapping/import CRUD DEFERRED, PROV-005 ve F5-C istisnaları korunur.
**F6-B SYNTHETIC ISOLATED IMPORT & DRY-RUN DEVELOPMENT — GO**; yalnız ayrı geçici
sentetik test şeması ve dry-run/load geliştirmesi yetkilidir. F6-C, gerçek veri,
provider/ödeme/mail, staging, merge/deploy/cutover izni değildir. PR3 OPEN/DRAFT;
main protection değişmez. Aşağıdaki bekleme ve yetki ifadeleri tarihsel kayıttır.

Bu kabul iş paketi yalnız bu altı belgeyi değiştirir; uygulama/test/CI/migration
kaynakları değişmez. Kontrol belge diff'i ve korunan kaynak eşliği; migration yok,
geri dönüş yalnız kabul belge commit'inin revert'üdür. Sonraki F6-B planı ayrı yazılır.

27 Ekim 2026. Durum: IMPLEMENTED / bağımsız teknik kabul bekler.
D2/D4/D5/D6, FR-OPS/R1-08, FIN-003/006/007/008, SUP-003/004/010,
PROV-004, SEC-001–004/OPS-001 teknik normalleştirmesi; ticari karar değişmez.

Gerçek WHMCS sürüm/şeması/hash bilinmediğinden normalized Hostider sentetik v1
tanımlanır; gerçek tablo/kolon/API alanı iddiası yoktur. Strict source envelope
ve enum entity, immutable instance/type/ID kimliği ile snapshot/schema/transform,
extract/watermark/timezone/encoding/provenance bağlanır. Core CanonicalJson float
ve bozuk UTF-8'i reddeder; Finance Currency/Money tekrar kullanılır.

Content SHA payload ve bütün source ilişkilerini; envelope SHA metadata dahil
tam kanıtı kapsar. Snapshot manifest identity-key sırasıyla deterministiktir,
girdi sırasından bağımsızdır. SHA bütünlük kanıtıdır, kaynak authenticity veya
imza değildir. SYNTHETIC etiketi anonimleştirme/gerçek veri izin kontrolü değildir.

F6-A tam sentetik snapshot doğrular; eksik parent ret, belirsiz değer enum ile
MANUAL_REVIEW. Hiçbir UNKNOWN bilgi PAID/ACTIVE/public'e çevrilmez. Internal note
ancak explicit staff+PRIVATE kabul edilir. Staff rol/MFA/session/token mirası yok;
legacy hash kabul edilmez, reset ve verification zorunludur. Kaynak staff author
ilişkisi tarihsel kimliktir; native yetki kazanımı değildir.

Provider/account kaynak instance içinde adlandırılır; physical provider account
eşliği F6-B ve gerçek G-O03/provider discovery'de ayrıca doğrulanmalıdır. Duplicate
resource aynı instance/provider/account/external tuple'da conflict'tir. Imported
CREATE daima ret. Her sonuç effects_allowed=false; provider application seam'i yok.

MappingEvidence immutable bellek içi öneridir, CRUD veya replay executor değildir.
Aynı identity+content+context+target NOOP_REPLAY; farklı içerik/target CONFLICT;
snapshot/schema/provenance/transform bağlam farkı da CONFLICT ve manual review reason üretir. Replay
eligibility yalnız aynı kanıtı yeniden tanıma anlamındadır; load yetkisi vermez.
Manifestte raw body/yazar kimliği diagnostic'e alınmaz; normal demo yalnız sentetiktir.

Attachment path/symlink/MIME bilgisi reported metadata olarak doğrulanır, okunmaz.
PDF-only subset native F5-C sınırını genişletmez. Gerçek hash/MIME/AV/ACL doğrulaması
gelecekte ayrıca gerekir. Client projection veya attachment authorization değişmez.
Finans aggregate'leri yalnız aynı Currency Money ile; bilinmeyen FX tahmin edilmez.
Refund explicit review ister; importer/ledger/refund execution yoktur.

DB migration yok;001–054 ve native modüller korunur. Geri dönüş yalnız F6-A revert;
v1 contract değişirse version artırılır, provenance geçmişi overwrite edilmez.
F6-B persistent unique claim/transaction/outbox/checkpoint/current authorization
bu salt sözleşme katmanının sağladığı bir garanti değildir; ayrıca yetkilendirilir.
Plan/test/güvenlik matrisi: [F6_A_PLAN](../F6_A_PLAN.md). Bütün canlı GATE'ler OPEN.
