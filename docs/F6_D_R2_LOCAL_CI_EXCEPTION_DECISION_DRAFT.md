# F6-D-R2 — Limited Local Docker CI Verification Method Exception

STATUS: PROPOSED / OWNER APPROVAL REQUIRED

Tarih: 28 Ekim 2026, Europe/Istanbul.
Hazırlayan: Codex; ayrı bağımsız teknik inceleme ve proje sahibi kararına sunulan taslak.
Mevcut sonuç: **B — PENDING / INSUFFICIENT EVIDENCE**.
F6-D-R2 bağımsız kabulü **PENDING** kalır. Bu belge istisnayı yürürlüğe koymaz veya resmî kabul oluşturmaz.

## 1. Kapsam, dosya, migration, doğrulama ve geri dönüş planı

Gereksinimler: OPS-002/003/004, SEC-004 ve D6; yalnız sentetik restored-target SELECT-only reconciliation alt kapsamı.
Tek yeni dosya bu karar taslağıdır. Önceki iki kabul hazırlık belgesi, mevcut plan/ADR/karar kayıtları, uygulama, testler, workflow ve migration dosyaları değiştirilmez.
Migration yoktur. Yeni test, regression, restore, container çalıştırma veya image pull yoktur.
Doğrulama: GitHub GET, mevcut immutable Git blob hash hesapları, kayıtlı günlük ve assertion okuma; yalnız mevcut Docker imajı için salt-okunur image inspect.
Geri dönüş kapsamı yalnız bu yeni dosyadır; otomatik silme, restore, reset veya revert yapılmaz.
Commit/push veya GitHub Actions başlatma yetkisi yoktur.

## 2. Kimlikler ve yayın/kanıt ayrımı

| Kimlik | Doğrulanan değer |
| --- | --- |
| Repository / branch | Boydaksu/hostidercp / codex/f5-support-center |
| Güncel GitHub yayın HEAD | ccee804f57d41a88d713a4afef12cda074b929cf |
| Yayın HEAD tree | c0f7f52d4d684c1dcda9b22de9a019c5054dd464 |
| Yayın commit parent / tarihsel evidence commit | cc94f662454cb3f030addd2db0785a09900c0ed2 |
| Immutable application SHA | 53162495902b3b168c2fb8554594e99c23a34d62 |
| Application tree | e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47 |
| Production migration 001–054 subtree | c0729c92dd143c017fdec32421bf67fe6b51e05b |
| Docker evidence subtree | 9110522a521348d9a1c44a0df3975c35c8cfac2a |
| Support-fix evidence subtree | 9ef15aae2798bd988678d861424e4f4db21a5247 |
| PR #3 | OPEN / DRAFT |

GitHub ref, yayın/application commit ve yayın tree kayıtları salt-okunur doğrulandı.
cc94f662 tarihsel test kanıtı referansıdır; ccee804f iki hazırlık belgesinin sonraki yayınıdır.
Yerel izole çalışma ağacı önceki API yayını nedeniyle cc94f662 detached tabanında ve önceki iki belge untracked durumundadır; bu durum gizlenmez veya checkout/reset ile değiştirilmez. İki yerel belgenin tam baytları yayımlanan GitHub blob kimlikleriyle eşleştirilerek korunur.

GitHub'dan ccee804f ref'inde okunan belgeler:
- F6_D_R2_LOCAL_DOCKER_CI_EXCEPTION_PROPOSAL.md, blob 20a60b614e5aca19d3a560f5be85a6431fd9b034; PROPOSED / NOT APPROVED.
- F6_D_R2_INDEPENDENT_ACCEPTANCE_REVIEW.md, blob fac36255fbebfd8d26d6c2c20a8795498c63b3b1; BLOCKED / INSUFFICIENT EVIDENCE.

Bu taslak önceki kararları yeniden yazmaz; yeni imaj metadata bulgusunu ve gerekçeli kanıt sınıflandırmasını ek bir değerlendirme olarak sunar.

## 3. Gerekçe ve mevcut politika ile ilişki

GitHub FAST kayıtları billing/spending-limit nedeniyle uygulama testleri başlamadan başarısızdır.
Proje sahibi ücretli GitHub Actions veya başka ücretli CI kullanmayacaktır. Bu karar ödeme/rerun önerisiyle aşılmaz.
GitHub FAST durumu NOT_RUN_EXTERNAL_BILLING olarak kalır. Önceki belge yayınının [skip ci] mesajı da test başarısı değildir.

F6_D_R2_PLAN.md:71–75, son exact application üzerinde PHP8.3/8.4 geniş FAST ister.
F6_D_R2_DELIVERY_REPORT.md:29 ve 149 exact-source FAST/CI kanıtını bekler.
ADR-023 FAST, docs-only ve FULL yöntemlerini ayırır. CI_OPTIMIZATION_REPORT.md:106 nihai FULL gerekliliğinin kaldırılmamasını, :113 ilk merge öncesi ayrıca doğru kaynakta FULL kararını korur.
docs/01_DECISIONS.md, en güncel açık kullanıcı kararının aynı konudaki önceki kararla ilişkisini belirler; AGENTS.md tasarım/politika çatışmasında ADR sürecini ister.
Bu kayıtlar incelendi; daha önce onaylanmış genel veya R2 özel Docker ikamesi gösterilmedi.

Dolayısıyla teklif, mevcut şartlarla henüz uyumlu bir yerine-getirme değildir. Yalnız açık ve kayda alınmış bir yöntem istisnası onaylanırsa, belirtilen R2 alt kapsamındaki FAST kanıtının yerine değerlendirilmesi önerilir.
FAST başarılı olmuş gibi kaydedilmez. FULL gerekliliği kaldırılmaz; genel test/mock, release, merge veya production kabulüne uygulanmaz.

Gelecekte ayrıca yetkilendirilirse, proje sahibi kararını tarih/kimlik/exact kaynak/kanıt ve sınırlarıyla kaydeden yeni bir ADR hazırlanmalı; R2 planının FAST maddesi ile delivery raporuna bu dar istisna için açık çapraz referans eklenmelidir.
ADR-023 veya 01_DECISIONS sessizce yeniden yazılmamalıdır. Bu görev bu değişiklikleri yapmaz.

## 4. Önerilen karar metni — henüz onaylanmadı

Proje sahibine şu sınırlı karar önerilir:

Yalnız application 53162495902b3b168c2fb8554594e99c23a34d62, tree e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47 ve evidence commit cc94f662454cb3f030addd2db0785a09900c0ed2 için, aşağıdaki ayrı yerel Docker çalıştırmalarının F6-D-R2 sentetik SELECT-only reconciliation bağımsız değerlendirmesinde alternatif doğrulama yöntemi olarak kullanılmasına izin verilmesi değerlendirilsin.

Bu karar verilirse bile GitHub FAST PASS oluşturmaz, güvenlik invariant'larını gevşetmez, incelemeciyi olumlu kabul kararı vermeye zorlamaz ve herhangi bir gerçek operasyonu yetkilendirmez.
Proje sahibi, aşağıda DOCUMENTED LIMITATION olarak gösterilen koşulları tek tek açıkça ele almalıdır.
Eksik tanı kayıtları gerçekmiş veya tamamlanmış gibi işaretlenemez.

Proje sahibi yöntem istisnası onayı ile bağımsız teknik kabul kararı iki ayrı koşuldur.
Her ikisi kendi kapsamı ve yetkisiyle kaydedilmeden fiilî kabul verilmez.
Bu taslakta hiçbir onay alanı doldurulmamıştır.

## 5. Yerel test ve kalite kanıtının tam sınırı

| PHP sürümü | İlk başarılı paketler | Devamda başarılı diğer paketler | Ayrı tam support | Birleştirilmiş kapsam |
| --- | --- | --- | --- | --- |
| 8.3.36 | 50 paket / 398 test | 9 paket / 227 test | 1 paket / 157 test | 60 paket / 782 test |
| 8.4.27 | 50 paket / 398 test | 9 paket / 227 test | 1 paket / 157 test | 60 paket / 782 test |

Bu, **ayrı çalıştırmaların birleştirilmiş kapsamıdır; tek uninterrupted broad PASS yoktur**.
Önceki denetimde başarılı paket birleşimi registry60 ile ve sürüm başına782 tekil PASS adıyla eşleşti.
Bu görevde support günlüklerinde157 bireysel PASS,157/0 özet, application_exit0 ve exact source-proof tekrar okundu.
İlk broad ve continuation süreçlerinin support başarısızlıkları silinmez veya PASS sayılmaz.
İlk support59 PASS/1 FAIL/97 çalışmayan; continuation60 PASS/1 FAIL/96 çalışmayan;97 closure tanısı31 PASS/66 FAIL tarihsel ayrı kayıtlardır.
Son tam support koşusu önceki tanı closure sürücüsünü kullanmaz; doğrudan değişmeyen tests/support-http.php girişidir.

Her iki manifest yeniden immutable Git blob baytlarıyla kontrol edildi: docker27/27, support-fix13/13; toplam40/40 SHA-256 eşleşir.
Kayıtlar iç tutarlılığı destekler; bağımsız/kriptografik bir yürütme attestasyonu değildir.

Önceki exact-SHA Docker kalite kanıtları: docker/sanitized-83.txt ve sanitized-84.txt:1–69; quality.sh ve run-version.ps1.
PHPStan0; lint453/0; pattern475/0; Composer locked audit advisory yok; npm audit0; CI helper6/0; JS state3/0 ve dört JS syntax adımı exit0.
Bunlar geçmişte çalıştırılmış aynı kaynak kontrolleridir; bu görevde tekrarlanmadı, güncel advisory veritabanı taraması yapılmadı.
Son support runner'ın quality_exit=0 alanı atanmış placeholder'dır; quality_rerun=false. Bu alandan kalite PASS üretilmez.
Son support koşusunda kalite kontrolleri NOT RUN / NOT REVERIFIED olarak kalır.

## 6. MariaDB imaj/sürüm bulgusu — yeni salt-okunur kanıt

28 Ekim 2026 tarihli bu incelemede yalnız mevcut imaja docker image inspect uygulandı.
Ham Config/Env/Labels çıktısı dışarı verilmedi; yalnız izinli güvenli alanlar seçildi.

| Güvenli alan | Gerçek çıktı |
| --- | --- |
| Id | sha256:de61fed4a40d3842f3ee09944ba52792156cfd9adf489b2cc670fc6ded28df8d |
| Os / Architecture | linux / amd64 |
| Config.Env içindeki MARIADB_VERSION | 1:10.11.18+maria~ubu2204 |
| org.opencontainers.image.title | MariaDB Database |
| org.opencontainers.image.version | 10.11.18 |
| Created | 2026-08-04T01:42:35.761895748Z |

Id, tarihsel runner dosyalarındaki dbImage ve before/after result JSON'larındaki db_image ile tam eşleşir.
Dolayısıyla **saklanan imaj kimliği → MariaDB10.11.18 metadata eşleştirmesi VERIFIED**.
Etiket ismine veya güncel mariadb tag'ine dayanılmadı; doğrudan tam image ID sorgulandı.
Image pull, yeni container veya SQL çalıştırılmadı.

Bu metadata, geçmiş kayıtlarda belirtilen imaja bağlanabilir; tarihsel container'ın gerçekten o imajdan çalıştığının bağımsız runtime attestasyonu değildir.
Geçmiş çalışan sunucudan SELECT VERSION() çıktısı bu incelemede elde edilmedi: **NOT INDEPENDENTLY VERIFIED**.
Metadata sürüm eşleştirmesiyle runtime gözlemi arasındaki bu ayrım korunur.
Önceki rapordaki image inspect unavailable ifadesi o önceki incelemenin erişim durumudur; yeni erişimle bulunan metadata önceki belgeler değiştirilmeden burada kaydedilir.

## 7. Tarihsel kapasite kanıtı ve araştırmanın sınırı

İncelenen kaynaklar: capacity.json; runner-768m.ps1.txt; runner-4g.ps1.txt; before-768m-php84.txt/json; after-4g-php83.txt/json; after-4g-php84.txt/json; application.sh; result.json; cleanup.json; önceki docker özet/günlükleri.
İki runner arasındaki tek değişen satır28, /var/lib/mysql tmpfs768m→4g yapılandırmasıdır.
Her iki tarafta aynı PHP ve DB image ID'leri/source SHA bulunur. Değişiklik yalnız test runner'ındadır; production kapasite değişikliği yoktur.

before-768m-php84.txt:74 gerçek temizlenmiş hata kaydı LOCAL_ERROR_SQLSTATE HY000 DRIVER_CODE1005'tir.
Bu kod birçok CREATE TABLE hatasında görülebilir; tek başına kesin ENOSPC kanıtı değildir.
capacity.json os_enospc_explicitly_captured=false bilgisini açıkça taşır.

capacity.json, eski kullanım703020 KiB/available83412 KiB/%90 ve sonraki2174860/2287580 KiB değerlerini kaydeder.
Bu sayılar yalnız saklanan özet/rapor iddiası olarak okunur; alttaki tarihsel ham df veya DB-mount inspect örnekleri yeniden bulunamadı.
application.sh:6 df -k /workspace kullanır; MariaDB /var/lib/mysql mount'unu doğrudan ölçmez ve runner filtresi df satırlarını korumaz.
Bu nedenle bu script satırı özetin tarihsel DB kullanım ölçümüne bağımsız ispat sağlamaz.

Repo docs/tools/storage metinleri, ilgili Git kanıtları, C:/Projects/hostidercp-ci ve C:/Projects/hostidercp-ci-verified dizinlerinde ilgili kimlik/sayısal örüntüler ve .local-ci içerikleri araştırıldı.
TEMP altında görev adıyla eşleşen klasör listesi de kontrol edildi; ek tarihsel ham örnek bulunmadı.
Bu, araştırılan konumlarla sınırlı bir bulgudur; makinedeki tüm dosyaların veya başka saklama kaynaklarının yokluğu iddia edilmez.
cleanup kayıtları tarihsel test kaynaklarının kaldırıldığını bildirir; güncel yeni ölçümler geçmiş kapasitenin yerine konulmaz.
Gizli ortam dosyaları veya genel kullanıcı geçmişi dışarı aktarılmadı.

**Ayrı sonuçlar:**
1. 4 GiB olarak yapılandırılmış runner ile aynı kaynak/support paketinin başarılı sonuçları kayıtlarda vardır.
2. Eski hatanın kesin kök nedeninin ENOSPC olduğu, mevcut ham kanıtlarla kesinleştirilemez.
Birinci sonuç ikinci sonuca dönüştürülmez.

## 8. Eksik kanıtların kabul açısından sınıflandırılması

Bu sınıflandırma yalnız bu dar sentetik kabul önerisine aittir; eksik kanıtı tamamlamaz ve mevcut kabul sonucunu otomatik değiştirmez.

| Eksik kanıt / koşul | Kategori | Somut gerekçe ve ele alma şekli |
| --- | --- | --- |
| Mevcut exact-source FAST şartının yerine yöntem istisnası için açık proje sahibi kararı | MANDATORY BLOCKER | Bağlayıcı plan hâlâ FAST ister; Codex taslağı politika değiştiremez. Exact kaynak/kanıt ve sınırlamalarla açık karar kaydı gerekir. |
| Ayrı bağımsız teknik kabul kararı | MANDATORY BLOCKER | Yerel kanıtı üretmek/özetlemek veya yöntem onayı vermek bağımsız kabul değildir. F6-D-R2 PENDING kalır. |
| Son koşunun tek uninterrupted broad olmaması | DOCUMENTED LIMITATION / OWNER RISK DECISION REQUIRED | Paket içi tüm157 support assertion'ı son koşuda vardır; ancak tüm60 paketin tek süreç/ortam zincirindeki etkileşimi ispatlanmadı.782 parçalı kapsam tek full PASS diye adlandırılamaz. |
| Yerel kayıtların bağımsız runtime attestasyonu ve tarihsel SELECT VERSION() kaydı yokluğu | DOCUMENTED LIMITATION / OWNER RISK DECISION REQUIRED | Tam image ID/sürüm metadata'sı artık doğrulanmıştır, fakat geçmiş çalıştırmanın bağımsız ispatı değildir. İncelemeci retained kaynak/log/hash ilişkisinin dar kapsam için yeterliliğini değerlendirmelidir. |
| MariaDB patch sürümünün yalnız imaj metadata'sıyla bağlanması | DOCUMENTED LIMITATION / OWNER RISK DECISION REQUIRED | Önceki sürüm eşleme açığı imaj düzeyinde kapandı. Plan belirli patch'i ayrı bir güvenlik invariant'ı olarak zorunlu tutmuyor; SELECT-only/SQL privilege davranışı kayıtlı DB testleriyle sınanmış. Buna rağmen metadata runtime sürümü veya tüm DB güvenliği için garanti değildir. Yeni bir sürüm uyuşmazlığı bulunursa STOP. |
| Eski703020/2174860/2287580 KiB ham DB-mount örnekleri ve kesin ENOSPC kök nedeninin yokluğu | NON-BLOCKING HISTORICAL DIAGNOSTIC GAP | Salt-okunur PASS/root/privilege invariant'ı eski hatanın ENOSPC olmasına bağlı değildir. Son tam support157/0 ve runner değişikliği doğrudan kayıtlarda; eski kesin kök nedenini kabul şartı yapmadan sadece tanı açıklamasından çıkarma önerilir. Eski başarısızlıklar korunur; production kapasite iddiası yapılamaz. |
| Son support turunda kalite tekrarının olmaması | DOCUMENTED LIMITATION / OWNER RISK DECISION REQUIRED | Önceki aynı immutable SHA'daki gerçek kalite adımları bulunuyor; source değişmemiş. Placeholder kanıt değildir. Farklı kaynak veya güncel audit sonucu gerektiğinde bu eski kayıt yeterli sayılmaz. |
| Gerçek deployment grants / tüm executable-object inventory / server-root güvenliği | MANDATORY BLOCKER, yalnız bu yetkiler/iddialar için | Bunlar mevcut sentetik reader kabulüne eklenemez. Production/runtime yetkisi açısından bloke kalır; bu istisna ile risk kabul edilmez. |
| Native recovery, custody/offsite/PITR/RPO/RTO ve original archive bytes tam kapsamının yokluğu | MANDATORY BLOCKER, ilgili geniş kabul için | Dar reconciliation bunları ispatlamaz. Bu alanlarda başarı veya FULL/production kabulü verilemez; dar önerinin dışında açık bırakılır. |

Tarihsel kapasite açığının non-blocking tanı açığı olarak değerlendirilmesi bir teknik gerekçeli öneridir; eski B sonucu otomatik A yapılmaz.
Proje sahibi ve bağımsız incelemeci ham kapasite nedenini dar kabul için ayrıca zorunlu görürse eksik kayıt koşulu açık kalır; Codex bunu varsayımla kapatmaz.

## 9. Güvenlik invariant'ı ve negatif test sınırı

Korunan koşul:
PASS ⇒ immutable_evidence MATCHED ⇒ observed_target_evidence_sha256 == verified.logical_sha256.

RecoveryContext::compare, src/Migration/Operations/RecoveryContext.php:38–44, ordered root farkını durum kararından önce EVIDENCE_ROOT_MISMATCH yapar.
RecoveryInspector::inspect:45–49 yalnız MATCHED_REFERENCE'i actual DB PASS'a dönüştürür; observed hash aynı candidate kökten gelir; hata/belirsizlik BLOCKED'a gider.
Contract/CanonicalJson list order'ını korur; row multiset eşliği ordered logical root yerine geçmez.

tests/migration-recovery-integrity.php:7–13 history listesini ters çevirir; aynı multiset/count/finance'a rağmen root eşitsizliği, DIFFERENCES ve REFERENCE_DIFFERENCES assertion'larını zorunlu tutar.
:14–22 actual DB başlangıç PASS/MATCHED/root eşliğini ve aynı sayıyla payload_hash tahrifatından sonra REVIEW_REQUIRED/DIFFERENCES/root farkını sınar.
Saf permutation testi actual DB saldırısı yeniden üretildi diye sunulmaz.
Önceki kayıtlar her PHP'de integrity2/0, R2 target24/0 ve R1 recovery25/0 içerir; bu görev bunları yeniden çalıştırmadı.

SELECT-only grants/role/schema/marker/migration fence, base-table/view ret, bounded READ ONLY REPEATABLE READ, DML/DDL/CALL reddi ve negatif grant testleri korunur.
Kaynak incelemesi canlı yetkileri veya tüm executable objects envanterini ispatlamaz; NOT_VERIFIED_SELECT_ONLY aynen kalır.
Session ayarları ve advisory lock işlemleri amaçlı ulaşılabilir etkilerdir; persistent target write veya runtime activation yetkisi değildir.
Bu görevde invariant ihlali gösterilmedi. Yeni ciddi ihlal, kaynak/kanıt hash uyuşmazlığı veya image ID uyumsuzluğu bulunursa kabul önerisi bloke edilir; kod bu görevde düzeltilmez.

## 10. Kabul edilmeyen riskler ve kapsam dışı yetkiler

F6-A/B/C sınırlı sentetik statüleri, F6-D limited escrow/genel PARTIAL ve R1 limited cold verification kayıtları değişmez.
Native runtime recovery BLOCKED; gerçek production recovery yetkisiz.
Issuer recovery yalnız pre-enrolled read-only verification alt kapsamındadır; genel issuer recovery açık/partial kalır.
Original archive bytes OPEN; offsite/key custody, PITR ve RPO/RTO NOT VERIFIED.
Durable policy anti-rollback, tam revocation operasyonu, server-root compromise ve executable-object inventory bu kabulün güvencesi değildir.
Bütün gerçek WHMCS/provider/payment/mail/cutover GATE'leri açık kalır.
FULL ACCEPTANCE, merge, staging, deployment, production ve F6-E yetkisi verilmez.
Güvenlik invariant ihlali, finans/veri bütünlüğü kaybı, kimlik bilgisi sızıntısı veya yetkisiz yan etki bir owner risk kararıyla bu yöntem altında kabul edilemez.

## 11. Geçerlilik ve yeniden değerlendirme

Yöntem istisnası yalnız yukarıdaki immutable application/tree/migration ve evidence commit için önerilir; genel/süresiz CI muafiyeti değildir.
Yeni application commit'i, güvenlik düzeltmesi, tree/migration drift'i, kanıt baytı/hash değişikliği, farklı runner veya maddi ortam/trust değişikliği bu exact kapsamda kullanımı geçersiz kılar ve yeniden inceleme gerektirir.
Sonraki docs-only yayınlar tek başına yeni uygulama adayı yaratmaz; yine de bu karardaki exact evidence referansları yerine otomatik geçirilmez.
Tarihsel başarısızlıklar ve eksik kanıtlar saklı tutulamaz veya yeni ölçümle geriye dönük tamamlanamaz.
Yeni test gereksinimi ortaya çıkarsa ayrı gerekçe/dar kapsam/izin gerekir; bu taslak test izni içermez.

## 12. Ayrı kararlar ve en küçük sonraki adım

- Proje sahibinin yöntem istisnası kararı: **BEKLİYOR / VERİLMEDİ**.
- Bağımsız incelemecinin teknik kabul kararı: **BEKLİYOR / VERİLMEDİ**.
- Nihai F6-D-R2 kabulü: **PENDING**.
- Codex önerisi: **B — PENDING / INSUFFICIENT EVIDENCE**.

En küçük sonraki adım bu taslağın owner tarafından ayrı ve açık yöntem/risk kararına sunulması, ardından bağımsız incelemecinin kapsam ve kalan sınırlamalar üzerinden ayrı teknik kabul kararı vermesidir.
Bu taslak owner onayı istemini somutlaştırır; bu görev onay verildiğini varsaymaz ve yeni talimat olmadan karar kaydı/ADR/plan güncellemesi, commit/push veya sonraki faz başlatmaz.

## 13. Koruma ve teslim notu

Yeni dosya: docs/F6_D_R2_LOCAL_CI_EXCEPTION_DECISION_DRAFT.md.
Önceki iki yerel belge bu görev başlangıcındaki halleriyle korunur; izole worktree'nin önceki untracked kayıtları yeni görev değişikliği sayılmaz.
Ana çalışma dizinindeki docs/F6_B_B01_REVIEW.md korunur; başlangıç SHA-256:
fe09203507b7ad8d97200aa4641b52ad103072804ad66ad41f804d47209a8aa7.
Son dosya farkı/hash ve Git durum kontrolü teslimde yapılır.
Ham Docker environment veya hassas metadata dosyası oluşturulmaz; yalnız yukarıdaki güvenli sürüm alanları bu tek taslakta kaydedilir.
