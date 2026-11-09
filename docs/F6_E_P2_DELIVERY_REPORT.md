# F6-E-P2 — offline WHMCS source discovery teslimi

29 Ekim 2026. DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING.
OPS-004 / G-O03 hazırlığı; F6-A/B/C metadata sözleşmeleriyle sınırlıdır.

## Bileşen ve sözleşme

Yeni `Migration/Discovery/StrictJson` ve `Validator`; mevcut Contract, Core CanonicalJson,
Fact/Knowledge ve Entity kullanılır. SourceIdentity incelendi: row/entity kimliğidir;
metadata sahte bir customer/entity kaydına çevrilmez. Mevcut sözleşmeler değiştirilmedi.
Kullanım: `Validator::validate($json, $expectedSource, $expectedSnapshot, $allowedCustomFieldIds)`;
caller tarafından açılmış regular file handle için `validateStream` vardır. Path/URL açılmaz.

`hostider.offline-discovery.v1` kökü: contract, classification, encoding, source, snapshot,
provenance, version, schema, temporal, password, custom_fields, files, uncertainties, content_sha256.
Classification SYNTHETIC veya SANITIZED_METADATA; provenance buna karşılık
SYNTHETIC_FIXTURE veya OPERATOR_SUPPLIED_UNVERIFIED. Beklenen source/snapshot caller'dan
ayrı gelir; her kategori/provenance aynı kimlik çiftini taşır. Snapshot schema_sha256 ve
root content_sha256 canonical hash ile bağlanır. Object key sırası normalize edilir;
listeler verilen sırayla hashlenir. Hash yeniden hesaplanabilir; imza/attestation değildir.

Tablo/kolon/index/custom field/dosya kimlikleri opak64-hex digestlerdir; source-/snapshot-
prefix kimlikleri Contract::id sınırındadır. Gerçek tablo/kolon adı, satır içeriği, müşteri
verisi, dosya yolu, credential, password hash veya serbest açıklama kabul edilmez. Enum ve
Fact alanları sınırlıdır. Gerçek WHMCS sürüm davranışı, tablo yapısı veya parola formatı
uydurulmadı. Fixture tümüyle sentetiktir. Known numeric version yalnız biçimi doğru bir
iddiadır, compatibility UNVERIFIED kalır. Algoritma sınıflaması gerçek parola uyumluluğu değildir.

Tablo ve kolon charset/collation eşliği, type/nullable, PRIMARY/UNIQUE/INDEX referansları,
aynı envanterde duplicate ID, custom field allowlist, temporal policy, provenance zamanı,
attachment/private storage/byte-size ve completeness kontrol edilir. Unicode UTF-8;
BOM, geçersiz surrogate/encoding, fazla alan, yanlış object/list veya tür ret. Native JSON
parser ardından tüm nesting seviyelerinde decoded-key duplicate taraması uygulanır;
escaped-key eşdeğerliği de reddedilir. Byte131072, depth16, node8192, string128 limitleri;
schema8/table64/column64/index64/index-column16/custom64/file128 sınırları vardır.

Dönen rapor raw metadata'yı taşımaz; yalnız sabit kodlar, kategori sonuçları ve digest içerir.
Digestlerin PII/secret veriden türetilmediğini validator ispatlayamaz; güvenilir operatör
sanitization ve dış kaynak doğrulaması açık GATE'tir. Handle açma/no-follow politikası caller'a
aittir; bu API keyfi pathname veya network stream kabul etmez, handle ownership almaz.

## Yerel doğrulama

| Suite | PHP8.3.36 PASS/FAIL | PHP8.4.27 PASS/FAIL |
|---|---:|---:|
| Yeni discovery | 33/0 | 33/0 |
| F6-A contract | 31/0 | 31/0 |
| F6-A visibility | 8/0 | 8/0 |
| F6-B import | 43/0 | 43/0 |
| F6-C reconciliation | 35/0 | 35/0 |
| Hardening unit | 7/0 | 7/0 |
| **Toplam** | **157/0** | **157/0** |

Yeni33 case'in31'i negatif assertion da içerir; geçerli sentetik metadata, deterministik
fingerprint, regular handle ve sanitized/unverified claim pozitifleri vardır. İstenen23
negatif/pozitif durum; nested/escaped duplicate, bozuk Unicode/JSON, bound/depth/node,
source/snapshot/provenance karışması, içerik tahrifatı, typed evidence, secret/PII alanı,
G-O03 CLOSED/effect escalation, dosya eksikliği ve kolon charset çelişkisi sınanır.
Mevcut A/B/C gerçek MariaDB sentetik fixture'ları tekrar kullanıldı; canlı WHMCS yoktur.

İlk PHP8.3 koşusu30/2, ikinci31/1: test girdisi hataları (geçerli sıfır boyutu olumsuz sayma,
bozuk escaped JSON, float girdisini validator'dan önce CanonicalJson ile hashleme) saklandı.
Son revizyonda test girdileri düzeltildi ve kolon charset doğrulaması/testi eklendi.
Her başarısız koşunun kendi kaynak manifesti vardır; eski sonuçlar yeni kaynağa mal edilmez.
Son iki başarı aynı484 dosyanın aynı byte manifestinde, before/after ve host eşliğiyle çalıştı.

PHPStan level5:0 hata;462 PHP lint:0 ihlal;484 source/config/test pattern:0 bulgu;
belge encoding/credential kontrolü iki koşuda exit0. Son teslim ekleri ayrıca denetlenir.
[summary](evidence/f6-e-p2/summary.json) ve PHP JSON/TXT kayıtları runtime/image IDs,
sonuçları ve task-label doğrulamalı cleanup'ı içerir. Disposable internal Docker ağı,
host portu yok, source readonly bind, ephemeral workspace/DB; yalnız görev kaynakları temizlendi.
[Runner proof](evidence/f6-e-p2/runner-source-proof.json) review kopyasındaki sabit sentetik
parola redaksiyonunu açıklar. FULL, Chromium, Actions ve güncel Composer/npm audit çalıştırılmadı.
R2 yöntem istisnası P2'ye aktarılmadı.

## Bütünlük, yayın ve rollback

Base HEAD `06de312796f19485aabb830f6e2cf531238f9975`, tree `50a27f2ca35d6ac97976c44b2a72c0fc0adae87d`.
Production migration tree `c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmedi.
[484 dosyalı canonical kaynak manifesti](evidence/f6-e-p2/candidate-source-manifest.json) SHA-256:
`0b0b4d99c33ff565e4576ba0c2989775f9389933d5a7afbd1fbdfbbfe2e5a999`.
Yeni dört kaynak/test dosyasının ayrı [manifesti](evidence/f6-e-p2/new-source-manifest.json) de vardır.
[Kaynak diff](evidence/f6-e-p2/source.diff), [koruma](evidence/f6-e-p2/preservation.json),
[kanıt manifesti](evidence/f6-e-p2/manifest.json) ve changed-files kaydı teslimi bağlar.

381 mevcut kaynak/test,54 migration ve487 tarihsel evidence blob-equal; iki test kayıt
dosyası dışında bütün1133 mevcut dosya aynıdır. Yeni migration/dependency/endpoint/worker yok.
P0/P1/R2 ve güvenlik mekanizmaları değişmedi. Ana Windows kullanıcı inceleme dosyası hash ile korundu;
eski worktree/untracked veriler temizlenmedi. Registry63 suite /868 test yalnız beklenen
sayım, FULL PASS değildir. Workflow trigger/permissions değişmedi; yalnız FULL listesi kaydı eklendi.

Testler commit öncesi çalıştı; kayıtlarda source_commit null olması bu zaman sınırını gösterir.
Başarı sonrası exact parent kontrolü ve tek `feat(F6-E-P2): add offline WHMCS discovery validator [skip ci]`
commit ile güvenli fast-forward yayınlanır; nihai SHA/tree/workflow gözlemi görev sonucunda bildirilir.
[skip ci] CI PASS değildir. Source/test baytları test sonrasında değiştirilmez.

Migration rollback gerekmez. Rollback yalnız yeni P2 dosyaları ve iki suite kaydına ilişkin
ayrı yetkili işlem olmalıdır; accepted kaynak/user dosyası korunur. Otomatik revert yapılmaz.

## Açık gerçek doğrulamalar ve durma sınırı

KNOWN_METADATA yalnız beyan edilmiş normalize metadata'dır; UNKNOWN/UNVERIFIED/INCOMPATIBLE/BLOCKED
sonuçları açıkça korunur. Missing kategori contract BLOCKED; bilinçli boş/UNKNOWN envanter
metadata eksikliği olarak raporlanır. Version/schema iddiaları gerçek inventory doğruluğu değildir.
F6-A gerçek identity/values, F6-B row/private-file byte eşliği ve F6-C finance/visibility/delta
UNVERIFIED; mapping/readiness kararı verilmez. Gerçek kaynak/operatör/kriptografik attestation
kanıtı yoktur. G-O03 OPEN kalır. Tüm raporlarda cutover_allowed=false, effects_allowed=false,
real_gate_status=BLOCKED; başarı yalnız OFFLINE_DISCOVERY_CONTRACT_VALIDATED.

F6-E-P2: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING
G-O03: OPEN — REAL WHMCS SOURCE NOT VERIFIED
FULL / LIVE CUTOVER / MERGE / STAGING / PRODUCTION: NOT AUTHORIZED
