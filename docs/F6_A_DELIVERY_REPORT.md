# F6-A — sentetik sözleşme teknik teslim kaydı

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

Durum: F6-A sentetik teknik teslimi; **bağımsız teknik kabul bekler**.
Son adayda gerçek FAST PHP8.3/8.4 SUCCESS; FULL ACCEPTANCE yapılmadı.
Bu kayıt ACCEPTED, FULL DONE veya production-ready beyanı değildir.

Kapsam/requirement/security/migration/rollback matrisi [F6_A_PLAN](F6_A_PLAN.md),
teknik karar [ADR-024](adr/ADR-024-synthetic-migration-contract.md).
Saf `src/Migration/` kaynakları versioned envelope/identity/fact/snapshot/mapping
kanıtını doğrular. Mevcut Core CanonicalJson ve Finance Money/Currency kullanılır.
Gerçek WHMCS extractor, SQL dump/importer, persistent mapping CRUD, DB yazısı,
attachment kopyası veya provider/ödeme/e-posta çağrısı yoktur.

Demo: `php tools/migration-synthetic.php`; sabit SYNTHETIC fixture üretir,
snapshot manifest ve mapping önerilerini stdout'a verir. Kaynak path/URL/DB
parametresi almaz. [Fixture/manifest](../tests/fixtures/migration-synthetic-v1.json)
tamamen sentetiktir; SHA değerleri gerçek dosya/ödeme kanıtı değildir.

Yeni paket `tests/migration-contract.php`, envanter ve FULL listesine birlikte
kaydedildi; FAST yeni modül için mevcut broad kuralını kullanır. Yeni testler
F6-A01–31 adlarıyla ilk20 zorunlu negatif senaryoyu ve ek contract bütünlüğünü kapsar.
Test20 sıfır dış etki bağımlılığını ve dört imported CREATE ret yolunu denetler;
bu canlı PROV-004 doğrulaması değildir. Native F5-E author_id/can_attach ve
attachment authorization kodları korunur.

## Pozitif/negatif test ve güvenlik matrisi

| F6-A test | Doğrulanan sözleşme / negatif sonuç |
|---|---|
|01| Pozitif tam fixture, sıra bağımsız manifest ve aynı evidence NOOP_REPLAY |
|02| Aynı identity/farklı içerik SOURCE_CONTENT_CHANGED; immutable eski evidence |
|03| Instance izolasyonu; başka instance mapping'i source identity conflict |
|04–06| Eksik source ID, sürüm/entity/ek alan, karışık snapshot/schema/watermark/timezone ret |
|07| Eksik parent veya başka instance parent'i MISSING_RELATION |
|08–09| Dört sağlayıcıda duplicate external resource; customer/service/provider/account conflict |
|10–11| Kanıtsız PAID ret; unknown payment/provider ve in-flight manual review |
|12–13| Currency mismatch ret; bilinmeyen tarihsel FX ve cross-currency açık review, dönüşüm yok |
|14–15| PRIVATE note PUBLIC/UNKNOWN olamaz; yanlış author/membership AUTHOR_REVIEW |
|16–17| Eksik checksum ret; traversal/absolute/encoded/backslash/symlink path review; dosya açılmaz |
|18–19| Staff→admin/MFA/role/session/token/credential mirası ret; legacy hash ret; reset/verify zorunlu |
|20| Dört imported CREATE izni ret; saf modülde DB/dosya/network/provider/payment/mail bağımlılığı yok |
|21–23| Float/overflow/bozuk UTF-8/ek alan/duplicate ID/stale SHA ret; dört belirsizlik ayrı kalır |
|24| Target/evidence tamper ret/conflict; yeni snapshot replay açmaz |
|25| Allocation/refund original payment limitini aşamaz; refund mutabakatı açık review |
|26–28| Message sıra/orijinal tarih/ek owner; missing/corrupt/MIME/boyut; timezone/email conflict |
|29| Pozitif tam kanıtlı PAID imported resource'u ACTIVE veya CREATE'e dönüştürmez |
|30–31| Yayımlanan fixture/manifest generator eşliği; malformed knowledge/provenance/encoding ret |

Hata/manifest tanıları raw body, source email, author veya path içermez; yalnız
allowlisted reason code ve hash/count verir. Demo JSON'u yalnız SYNTHETIC payload
gösterir; müşteri API projection değildir. Private note ek manifest'i gelecekte
native public attachment handler'ına verilemez; ayrı private archive ACL tasarımı
ve G-O03/SUP-010 kabulü gerekir. Native F5-C handler bu aşamada genişletilmez.

## Uygulama kimliği ve doğrulama kanıtı

CI kabulü yalnız üç belge: `91efb9360a99aab7f87e211e4fa46c4dfe4e4d9a`,
tree `72c7002ce8bc3bd957d14f760b148d7aa900e0b0`.
Son F6-A uygulama/fixture/test/CI adayı `8340be6c5a12e551587ab92e72da930a18a7894d`,
tree `63c9f86c6b618002d140ec49512d1e703f00f2cb`.
[Son gerçek FAST40465060398](https://github.com/Boydaksu/hostidercp/actions/runs/40465060398)
push olayıyla tamamlandı: **SUCCESS**; profile broad,53 suite/613 test/0 hata.
İki PHP sürümünde eski582 testin tamamı ad bazında eşleşti; yeni31 F6-A testi
`FAST_SUITE tests/migration-contract.php` altında gerçekten çalıştı.
İlk aday3c941bd/tree c1379b85bd128e825cee4d3ea6c735de8aee7c09 ve
[FAST40464340021](https://github.com/Boydaksu/hostidercp/actions/runs/40464340021)
son kabul adayı değildir: snapshot/provenance fingerprint farkının da açık CONFLICT
üretmesi için8340be6 hedefli düzeltmesi yayımlandı. Önceki run sonucu yeni adaya
aktarılmaz: eski PHP8.3/8.4 job'ları branch concurrency ile CANCELLED, eski FAST
result FAILURE oldu. Bu koşu PASS/kabul kanıtı değildir. Yeni FULL dispatch yapılmadı.

| Son aday kontrolü | PHP8.3.36 | PHP8.4.27 |
|---|---|---|
| Gerçek job | [122058872099](https://github.com/Boydaksu/hostidercp/actions/runs/40465060398/job/122058872099) | [122058872039](https://github.com/Boydaksu/hostidercp/actions/runs/40465060398/job/122058872039) |
| Native application suites/tests |53/613/0 hata|53/613/0 hata|
| Eski582 adından eksik |0|0|
| Yeni F6-A |31/31 PASS|31/31 PASS|
| PHPStan / Composer locked audit |0 hata / temiz|0 hata / temiz|
| PHP lint/mimari / pattern |402/0 ihlal;424/0 bulgu|402/0 ihlal;424/0 bulgu|
| JavaScript syntax/state / npm lock audit |PASS;3/3;0 vulnerability|PASS;3/3;0 vulnerability|
| Her suite öncesi/sonu exact source guard |PASS|PASS|

Scope/security job122058798175, ayrı6/6 CI helper testi ve
FAST result122065266369 SUCCESS. [CI özeti](evidence/f6-a/ci.json),
[8.3 log özeti](evidence/f6-a/fast-83.json), [8.4 log özeti](evidence/f6-a/fast-84.json),
[eski582 ad](evidence/f6-a/baseline-tests.json), [eski52 suite](evidence/f6-a/baseline-suites.json)
ve [yeni31 test/source-line](evidence/f6-a/test-index.json) bağımsız inceleme indeksidir.
Ham runner günlükleri olası credential tanı alanları nedeniyle depoya alınmadı;
yalnız allowlisted test adı/sayım/kalite sonuçları ve job linkleri saklandı.
Eski52 suite'in test sayısı aynı; yalnız son31-test suite eklendi.001–054 korunur.

**FULL için exact uygulama SHA: `8340be6c5a12e551587ab92e72da930a18a7894d`.**
Bu adayda Chromium11 ve Services10/Commerce30/Session20 tekrarları çalıştırılmadı;
mevcut FULL workflow listesinde korunurlar. FULL henüz yoktur; FAST onun yerine
sunulmaz. Belge/kanıt kapanışı son uygulama kaynaklarını değiştirmez.

[Koruma manifesti](evidence/f6-a/preservation.json):391 mevcut uygulama/test/
contract/migration dosyasının Git blob eşliği, changed_count0;54 migration SHA-256,
başlangıç ve aday migration tree `c0729c92dd143c017fdec32421bf67fe6b51e05b` eşit.
Eski test/assertion/skip değiştirilmedi.52 eski suite envanteri sıralı korunur,
yalnız53. `migration-contract.php` eklendi;582 kabul edilmiş test kaynağı aynıdır.
[Hedefli31/31 yerel kanıt](evidence/f6-a/targeted.txt). PHP8.4.24 PHPStan0 hata,
402 PHP lint/mimari0 ihlal,424 pattern0 bulgu;6 CI self-test ve state3/3 geçti.
Yerel ilk PHPStan kısıtlı dosya erişiminde yapılandırmayı okuyamadı; normal
erişimle aynı yapılandırmada başarı elde edildi. İlk DB bağlantısı süreç başlatma/
erişim sınırında başarısızdı; yalnız yeni izole yerel DB ile seri koşu başlatıldı.
Bu hazırlık hataları test PASS sayısına katılmadı; ürün kaynak değişikliği gerektirmedi.

Ek Windows yerel seri turu50 suite/398 test/0 tamamlanmış paket hatasıyla Support
çalışmasına ulaştı. İki exact aday CI job'u613/613 tamamlandıktan sonra hâlâ süren
yerel ek koşu temizlik sırasında durduruldu; **tam yerel PASS değildir**.
[Kısmi yerel kanıt](evidence/f6-a/local.json) STOPPED statüsünü korur. Bu tur
geliştirme sırasında başladığı için exact-tree full koşu olarak da sunulmaz.
Son kaynağın bağımsız hedefli31/31 testi geçti; eksiksiz582+31 kaynak kanıtı
yukarıdaki PHP8.3/8.4 CI job'larıdır. Tamamlanmayan yerel testler PASS sayılmadı.

Sözleşme v1'in açık teknik sınırları: en çok10000 entity/snapshot,1–128 ASCII
identity karakteri,8192 byte sentetik metin, strict tam saniye ISO-Z timestamp,
mevcut native Currency allowlist'i (TRY/USD/EUR/JPY/KWD) ve PDF-only dosya manifest'i.
Uyumsuz veri kesilip/dönüştürülüp tamamlanmaz; ret/review gerekir. Gerçek naive/
fractional timestamp ve source timezone dönüşümü bu sentetik v1 ile doğrulanmış
sayılmaz; G-O03 ve versioned adapter tasarımı gerekir. SHA authenticity/imza değildir.

Yeni migration yok;001–054 korunur. Rollback F6-A kod commit revert; DB down yok.
Forward-fix explicit contract version ile, eski provenance overwrite edilmeden.
Somut kod geri dönüşü: önce8340be6 fingerprint düzeltmesi, sonra3c941bd F6-A
uygulama commit'i tersine alınır;91efb93 CI kabul kaydı korunur. Belge/kanıt
kapanış commit'i ayrıca geri alınabilir. Hiçbir finans veya müşteri kaydı silinmez.
Exact uygulama SHA/tree, koruma manifesti ve gerçek FAST run/job kanıtları yukarıdadır.
Bu aday için FULL ACCEPTANCE çalıştırılmadı ve dispatch yapılmayacak;
eski CI kabulü yeni adaya taşınmaz. F6-A ACCEPTED veya production-ready ilan edilmez.

## F6-A → F6-B bağımlılık sözleşmesi ve açık keşif

| Çıktı | F6-B'nin ayrıca sağlaması gereken |
|---|---|
| Immutable instance/entity/source ID ve snapshot manifest | G-O03 gerçek sürüm/schema/kolasyon/encoding/timezone/hash/dosya keşfi; tutarlı extract ve watermark |
| Content/envelope SHA ve MappingEvidence önerisi | Kalıcı unique claim, target uniqueness, transaction/checkpoint/outbox; güncel native ACL, MFA/step-up, explicit mapping CRUD yetkisi |
| Strict entity/parent/customer/provider-account doğrulaması | Gerçek kaynak adapter allowlist'i, physical provider account eşliği ve resource inspection; ikinci CREATE sıfır |
| Finance Money/currency ve unknown FX review | Immutable native ledger/provenance, currency bazlı bakiye/allocation/refund mutabakatı; sıfır tahmini tarihsel kur |
| Mesaj sırası/zaman/private note/yazar ve attachment manifest | Gerçek arşiv ve dosya checksum/MIME/AV/retention doğrulaması, native müşteri projection/attachment ACL korunması |

G-O03 açık liste: WHMCS sürümü ve kurulu modüller/custom fields; gerçek tablo/kolon
haritası ve değişmez ID'ler; source hash formatı; kaynak UTC/DST/kolasyon/encoding;
tutarlı snapshot/delta/watermark yöntemi; gerçek dosya kökleri/symlink/owner/MIME;
staff-user/customer membership ve historical ownership; para/kur/refund/proforma
vs official PDF eşliği. F6-A bunları doğrulanmış saymaz ve credential istemez.

SUP-010 gerçek arşiv kabulü, PROV-004 gerçek imported resource, DOMAIN-002
PARTIAL/MANUAL_REVIEW, mapping/import CRUD DEFERRED, PROV-005 OPEN kalır.
F5-C'nin dört canlı istisnası (routing/sender authentication; kesin retention
cutoff/scheduler; gerçek AV/IMAP/SMTP/genel MIME; mock HELD/ACCEPTED teslim değildir),
auto-close, merge/split, kurum geneli tarihsel SLA raporu ve tüm vendor/operasyon/live
GATE'ler açıktır. F6-B, gerçek import/provider, staging/merge/deploy/cutover ayrıca
proje sahibi talimatı ister. PR3 OPEN/DRAFT, main protection değişmez.
