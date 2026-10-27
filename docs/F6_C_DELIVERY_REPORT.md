# F6-C — sentetik reconciliation ve güvenlik teknik teslimi

Durum: yeni modül ve hedefli testler tamamlandı; exact aday FAST ve bağımsız kabul bekler.
F6-B LIMITED SYNTHETIC ISOLATED IMPORT & DRY-RUN TECHNICAL BASELINE ACCEPTED;
B-01 CLOSED, kabul belge commit'i541cf9cb6d2efafd4bbc9a7effe03cdd4b70807f.
Yalnız README/F6_PLAN/F6_B_DELIVERY_REPORT/F6_B_B01_REVIEW değişti; uygulama,
test/fixture/CI/migration d4ead54 ile aynı kaldı. Ayrı kanıt dadef0b korunur.
Başlangıçtaki B-01 belgesi yerel tek-karakter yazım değişikliği commit dışında korunur.

## Uygulama ve algoritma

[Plan](F6_C_PLAN.md), [ADR-026](adr/ADR-026-synthetic-reconciliation-security.md).
Yeni Inspector yalnız accepted Fixture/Plan ile typed source alır; Core Database ve
CanonicalJson, F6-A SnapshotValidator/MappingEvidence/SourceIdentity, F6-B Sandbox
sign/readiness, Finance Money/Currency kullanır. Finance yalnız metadata toplama
fonksiyonudur; yeni Ledger/Identity/Queue/Audit veya native adapter değildir.

Inspector'ın private connection'ı ayrı random f6c_ hesabıdır: actual CURRENT_USER,
DATABASE/role/SHOW GRANTS, exact literal SELECT scope ve authority marker denetlenir.
Accepted Sandbox aynı kalır; authority bağlantısı yalnız accepted read/check/sign
metotları için kullanılır. Asıl data query'leri fiziksel SELECT-only hesapta,
REPEATABLE READ + READ ONLY native transaction'dadır. Ön/son fence ve accepted
schema mutex'i kullanılır; bu bir yeni worker/queue sistemi değildir.

Dokuz tablonun exact kolonları ve gerekli fiziksel PK/unique index'leri ayrıca
doğrulanır. PK üzerinden LIMIT513 probe ardından en fazla512 satır; source256,
satır64KiB/toplam2MiB sınırı vardır. Bound aşımı INCONCLUSIVE, schema/grant/lease
reddi BLOCKED'dır. 18 bounded data query +18 bounded metadata query; entity başına
SQL yoktur. Kontrol/fence sorguları bu data query sayımından ayrıdır. Gerçek büyük
WHMCS ölçeği/performance/streaming kabulü verilmez; limit aşımı truncation PASS değildir.

Kaynak ve target entity identity/type/owner/content/payload, parent bağları ve claim
hesapları bağımsız karşılaştırılır. Mapping canonical evidence exact öneriyle,
provenance run/snapshot/hash'lerle; source root plan/run ile denetlenir. Bounded
history'nin her HMAC halkası ve run checkpoint imzası tekrar hesaplanır. Fazladan
target/evidence/run veya ownership conflict onarılmaz, REVIEW_REQUIRED olur.
Kısmi/FAILED load INCONCLUSIVE; tamamlanmış, tam doğrulanmış sentetik sıfır fark PASS.
APPLIED bayrağı tek başına yeterli değildir. Bu durumlar gerçek göç kabulü değildir.

## Finans / provider / support matrisi

| Alan | Teknik doğrulama | Kabul sınırı |
|---|---|---|
| Order/payment/confirmed/allocation/remaining | Her currency ayrı Money minor-string; baseline10000/6000/6000/4000/6000 TRY | PAID veya native ledger hareketi üretilmez |
| Wallet | Source2000 TRY liability ve owner/currency/hash eşliği | Yeni cüzdan bakiyesi yazılmaz |
| Refund | Original limit ve REFUND_RECONCILIATION_REQUIRED accepted validator'da korunur | Kaynak refund review-only; açıklanamayan extra refund finance NOT_AVAILABLE |
| Historical FX | UNKNOWN explicit review; currency toplama ayrı | Güncel/tahmini kur yok |
| PDF/proforma | Tür/numara/tutar/date/owner/checksum metadata ve ayrı totals | Yeni numara/PDF ve byte checksum kabulü yok |
| cPanel/Voyar/ResellerClub/DNA | Provider/account/service/owner/external claim mismatch, dört ayrı resource sayımı | CREATE/RENEW/TERMINATE/TRANSFER/UPGRADE/DOWNGRADE yolu yok; gerçek yönetim NOT_VERIFIED |
| Support | Source sequence/time/known timezone/kind/visibility/author/dependency, metadata hash ve private attachment ilişkileri | Gerçek SUP-010 ve dosya bytes/MIME/AV/retention NOT_VERIFIED |

Source ve target finance toplamları ayrı verilir, farklar yalnız aynı currency
içinde hesaplanır. Kaynağı bulunmayan ledger debit/credit NOT_AVAILABLE'dır. Tam
sentetik scope'ta hiç fact olmayan kategoriler ayrıca belirtilir; belirsiz veriye
sıfır atanmaz. Açıklanamayan financial target scope eşit toplam iddiasına dahil
edilmez, finance bütünü NOT_AVAILABLE olur. Otomatik repair/merge/rebind yoktur.

Manifest classification/contract/plan/transform/source roots/target snapshot,
entity source-target-matched-unmatched-extra counts, differences/reasons, finance,
provider ve support metadata özetini içerir. Reviewer SHA-256/time attestation
fingerprint'in dışındadır. Herhangi bir body, author/customer ID, e-posta, path,
credential veya mapping detail döndürülmez. Public/search/export/mail/notification
endpoint'i eklenmedi. Native Identity/MFA/session/revoke/department/impersonation
seam'leri değişmez; yeni endpoint testleri NOT APPLICABLE'dır. Test-only projection
native API değildir; cross-owner ret mevcut accepted seam üzerinden de doğrulanır.

## Hedefli testler, demo ve gelişim kayıtları

Yeni34 F6-C testi gerçek ephemeral MariaDB schema/account'larında34/34 PASS.
İlk31-test turu31/31 PASS idi. Fiziksel unique-index negatif testinin ilk34-test
turunda1 setup FAIL'i oldu: FK'nin kullandığı indeks doğrudan drop edilemedi
(SQLSTATE1553). Test setup'ı FK'yi koruyan non-unique destek index'i ekleyerek
yalnız uniqueness'i düşürecek şekilde düzeltildi; assertion ve uygulama gate'i
gevşetilmedi. Son34/34 PASS. Başarısız tur kabul kanıtı değildir.

Yeni özellik olduğu için eski kaynakta var olmayan Inspector'a ait uydurma
before-PASS/FAIL sunulmaz. Karşı-örnek gerçek APPLIED yükün mapping/provenance'ı
eksiltildiğinde target sayısı26 olsa da reconciliation REVIEW_REQUIRED olur.
Her tablo state digest'i tekrar rapor/negatif/4 bağımsız PHP process sonrasında
aynıdır. Reader DML/DDL ve sibling/mysql schema erişimi fiziksel olarak reddedilir.
UNKNOWN/failure/root/claim/ownership/credential/visibility ve bound kontrolleri
gerçek yeni suite'te çalışır. Schema/index bozulması yalnız owned test schema'sındadır.

Tekrar demo, explicit test env ve kontrollü fixture admin yetkisiyle
`php tools/migration-reconciliation-demo.php` komutudur; fixture kurulumu/load ayrı,
Inspector'ın kendisi salt okunurdur. Örnek manifest gerçek yerel DB'den üretilir.
PHPStan0; lint422/0, pattern444/0. Eski664 test yerelde tekrar koşturulmadı;
son adayın tek FAST8.3/8.4 turu ve ad eşliği kanıtı kapanışta kaydedilecek.

## Migration / rollback / forward-fix

Production001–054 ve test F6-B DDL aynıdır; yeni migration veya tablo yoktur.
Subtree c0729c92dd143c017fdec32421bf67fe6b51e05b. Fixture yalnız random reader
hesabını ve owned F6-B schema/account'larını kaldırır. Rollback code revert +
owned fixture teardown; native DOWN/DROP/DELETE/restore yoktur. Forward-fix eski
immutable evidence'ı değiştirmez; contract değişirse ayrı versioned tasarım gerekir.

## Açık istisnalar

| ID | Teknik kapsam | Mevcut durum | Eksik gerçek kanıt | Etkisi | Sonraki faz |
|---|---|---|---|---|---|
| G-O03 | WHMCS kaynak discovery | OPEN | Gerçek sürüm/schema/hash/dosya | Gerçek migration/reconciliation kabulü yok | Ayrı açık kaynak erişim yetkisi |
| SUP-010 / PROV-004 | Synthetic metadata/privacy/resource | Gerçek NOT PASSED | Arşiv/dosya ve provider current resource kanıtı | Sentetik PASS gerçek hizmet yönetimi değildir | Ayrı gerçek doğrulama |
| G-V01–04 | Voyar | OPEN | Ücret/auth/unknown/custom-upgrade vendor kanıtı | Canlı operasyon kapalı | Ayrı vendor GATE |
| G-P01–02 | PayTR | OPEN | Merchant currency/callback/refund mutabakatı | Gerçek ödeme/ledger yok | Ayrı finans GATE |
| G-C01 / G-R01 / G-D01 | WHM/RC/DNA | OPEN | ACL/versiyon/API/OT&E | Gerçek provider kabulü yok | Ayrı vendor GATE |
| G-X01–02 | FX/e-belge | OPEN | Ticari FX ve mali müşavir/e-belge | Tarihi kur veya resmî belge tahmini yok | Ayrı finans kabulü |
| PROV-005 / DOMAIN-002 | Hourly/failure fallback | OPEN / PARTIAL-MANUAL_REVIEW | Gerçek ücret/sonlanma ve definitive-failure proof | Otomatik canlı kabul yok | Ayrı provider doğrulama |
| G-O01–02 / G-O04 / G-T01 | Runtime/PITR/retention/transfer | OPEN | Hosting/restore/saklama/devretme provası | Operasyon release kapalı | F6-D ayrıca yetkili olmalı |
| F5-C1 | Routing/sender auth | OPEN | Gerçek mailbox/departman/auth | Gerçek mail kabulü yok | Ayrı mail GATE |
| F5-C2 | Retention cutoff/scheduler | OPEN | Gerçek kesin erişim kesilmesi | Retention PASS değil | Ayrı operasyon |
| F5-C3 | AV/IMAP/SMTP/genel MIME | OPEN | Gerçek transport/AV/MIME | Metadata byte kabulü değil | Ayrı mail/dosya GATE |
| F5-C4 | Transport delivery | OPEN | Gerçek müşteri teslimi | HELD/mock ACCEPTED gerçek teslim sayılmaz | Ayrı mail doğrulama |
| Support ileri | Auto-close/merge/split/tarihsel SLA | DEFERRED/OPEN | Kurum geneli gerçek kaynak | Yeni otomasyon/rapor kabulü yok | Ayrı kapsam |
| Mapping/import CRUD | Production binding | DEFERRED | Native permission/step-up/confirmation/API proof | Yeni production API yok | Ayrı açık görev |
| SEC-MAIN | GitHub main protection | protected=false, rulesets=[] gözlendi | Etkin koruma politikası | Bu görevde değiştirilmedi | Proje sahibi ayrı işlem |
| F6-D/E / FULL | Operasyon/cutover/full | NOT AUTHORIZED / NOT DISPATCHED | Ayrı owner yetkisi ve FULL kanıtı | Canlı ilerleme yok | Ayrı talimat |

F6-C SYNTHETIC DEVELOPMENT tamamlandıktan sonra INDEPENDENT ACCEPTANCE PENDING
kalır. PR3 OPEN/DRAFT, production UNCHANGED. Exact kaynak/CI kapanışı sonrası DUR.
