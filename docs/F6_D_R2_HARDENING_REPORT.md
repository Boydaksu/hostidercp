# F6-D-R2 — bütünsel kök/PASS hardening

28 Ekim2026. Bağımsız kabul PENDING. Bu iş yeni faz veya R2 yeniden yazımı değildir.
Başlangıç HEAD d62a828fc3584144d11703386a2d7752eb0b08cb; R2 app
f0db545b5b5fd0e569b07e33efb85e245be16b40, tree6482e0d546e9bcafbeeb7872052eda99a97d8d26.

## Ön plan — scope / dosyalar / migration / test / rollback

RecoveryContext compare status'üne bütünsel kök eşliğini dahil et. Sadece gerçek
karşı-örnek varsa minimum source değişikliği. Inspector mapping/fence koşulları,
R1/ColdRecovery/Contract/CanonicalJson ve diğer accepted modüller değişmez.
Yeni tests/migration-recovery-integrity.php ve FAST/FULL registry kaydı; mevcut test
body'leri immutable. Production001–054 değişmez, yeni migration yoktur.
Geri dönüş yalnız hardening app commit kontrollü revert; exact owned fixture teardown.
Orijinal signed evidence/ciphertext/MAC/HMAC/marker/ownership değiştirilmez.

Mevcut CanonicalJson object key'lerini sıralar, list order'ını korur. F6-D Snapshot ve
R1 logical_sha256 dokuz tablonun PK-order list köküdür. R2'nin candidate/observed root'u
aynı Contract::hash($tables) kapsamıdır. Multiset row equality bunun yerine geçmez.
Root normalization/sorting tanımı değiştirilmez; permutation kök farkı REVIEW_REQUIRED
olmalıdır. Saf comparison DB authority değildir; actual SELECT yolu ayrıca sınanır.

Önce yeni permutation karşı-örneği eski source'ta; sonra minimum fix ve aynı test.
Sonra gerçek target mutate/invariant testi, mevcut24 R2 ve25 R1 suite; lint/PHPStan/
pattern/locked audit/helper. Eski780 body ve registry59 suite korunur.
Billing giderildi ve bu yeni SHA için açık FAST izni gelmeden push/dispatch yoktur;
önceki tüketilmiş retry izni yeniden kullanılmaz. Koşullar sağlanmazsa local commit
ve NOT_RUN_EXTERNAL_BILLING kanıtı ile dur. FULL/Chromium/10/30/20 yok.

## A — Exact kaynak

Minimum app commit **53162495902b3b168c2fb8554594e99c23a34d62**, tree
**e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47**. Yalnız RecoveryContext.php kaynak
değişikliği (bir root reason kontrolü), yeni2-test dosyası ve iki suite registry kaydı.
RecoveryInspector/TargetPolicy/ColdRecovery/Contract/CanonicalJson/Report dahil diğer
accepted source dosyaları değişmedi. Production001–054 subtree
**c0729c92dd143c017fdec32421bf67fe6b51e05b** değişmez. Application yeni açık kullanıcı izniyle yayımlandı; source SHA/tree aynıdır.

## B — Gerçek karşı-örnek ve minimum düzeltme

Orijinal imzalı paket çözülüp test kopyasındaki f6b_history listesi ters çevrildi.
Bütün tablolar için source==target==matched, unmatched==extra==0; finance equal;
row multiset aynıdır. Fakat ordered logical root farklıdır. Eski compare sonucu
MATCHED_REFERENCE ve immutable_evidence DIFFERENCES birlikte döndü. Yeni assertion
eski kaynakta1 test/1 FAIL verdi; [negative-before](evidence/f6-d-r2-hardening/negative-before.txt).
Bu saf reference comparison karşı-örneğidir; DB authority değildir ve actual DB'de
yanlış PASS yeniden üretildiği iddia edilmez. Inspector actual okuma PK-order kullanır.

Mevcut root tanımı korundu: verified.logical_sha256, candidate root ve observed target
root dokuz tablonun ordered-list Contract::hash($tables) köküdür. backup_id ise tüm
backup data/schema/files/report envelope hash'idir; bu farklı scope körlemesine
eşitlenmez. Source roots hash'i de ayrı anlam taşır. Signed root/HMAC yeniden yazılmadı.

Root hesaplaması status kararından önceye alındı; fark varsa **EVIDENCE_ROOT_MISMATCH**
reason eklenir. Böylece compare REFERENCE_DIFFERENCES (privacy/ownership farkında
BLOCKED), Inspector REVIEW_REQUIRED/BLOCKED döner. Yeni normalization/sorting yoktur.
Inspector'da ek refactor gerekmedi: MATCHED_REFERENCE artık tüm eski koşullarla beraber
root eşliğini de gerektirir. Aynı permutation testi düzeltmeden sonra PASS.

Nihai invariant: **PASS ⇒ immutable_evidence MATCHED ⇒ observed_target_evidence_sha256
== verified.logical_sha256**. PASS ayrıca bütün count'ların eşliğini, zero unmatched/
extra, finance/ownership eşliğini ve mevcut schema/migration/authorization fence'lerini
gerektirir. Yeni actual DB testi tam fixture PASS koşullarını tek tek doğrular, aynı
kayıt sayısıyla payload_hash değiştirir; sonuç REVIEW_REQUIRED, root ve fingerprint
farklıdır. Bu gerçek SELECT target proof'udur, JSON rehash ile ikame edilmez.

## C — Yerel kanıt ve koruma

PHP8.4.24: **yeni2/0**, **mevcut R2 24/0**, **ilgili R1 son25/0**.
R2'nin mevcut mapping/provenance/checkpoint/history, missing/extra/finance/ownership/
privacy/schema/index/expiry/wrong-target/stale-marker regresyonları değiştirilmeden
çalıştı; gereksiz kopya testler eklenmedi. İlk R1 turu25/1, bir fixture setup
F6B_TEST_SETUP_FAILED_2002 bağlantı hatası verdi. Bu tur PASS sayılmadı; ayrı owned DB
çalışmasında değişmeyen R1 suite25/0 geçti. [Başarısız tur](evidence/f6-d-r2-hardening/local-r1-failed.txt)
ve [son tur](evidence/f6-d-r2-hardening/local-r1-25.txt) ayrı kayıttır.

PHPStan0; lint453/0; pattern475/0; Composer locked audit advisory yok; local CI
scope/source helper6/0. Eski32 F6-D tekrar edilmedi: o modül/R1 davranışı değişmedi,
ilgili R2/R1 sınırları çalıştırıldı. Bu old756 runtime CI PASS iddiası değildir.
Eski **780 testin134 dosyası blob-equal**; önceki756 ve R2'nin24 body'leri silinmedi,
değiştirilmedi, skip edilmedi. Eski59 suite korunur; yeni registry **60 suite/782 test**
beklentisidir, CI sonucu değildir. [Koruma](evidence/f6-d-r2-hardening/preservation.json),
[local](evidence/f6-d-r2-hardening/local.json), [yeni testler](evidence/f6-d-r2-hardening/local-targeted.txt),
[R2 24](evidence/f6-d-r2-hardening/local-r2-24.txt).

## D — Açık izin sonrası tek exact-source FAST

İlk yerel kanıt kapanışından sonra kullanıcı **Billing giderildi; tek yeni FAST izinli**
diye açık teyit verdi. Yalnız application `53162495902b3b168c2fb8554594e99c23a34d62`
izinli dala push edildi; GitHub Git commit API tree'si
`e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47` ile eşleşti. Kaynak değişmedi.
[FAST40639410631](https://github.com/Boydaksu/hostidercp/actions/runs/40639410631),
attempt1 **FAILURE — job başlamadı**.
[Scope job122653789237](https://github.com/Boydaksu/hostidercp/actions/runs/40639410631/job/122653789237)
annotation: recent account payments have failed or spending limit needs increasing.
Result job122653804161 FAILURE, PHP matrix placeholder122653805195 SKIPPED.

**EXTERNAL_BILLING_BLOCKED — APP TESTS NOT RUN / NOT_RUN_EXTERNAL_BILLING**.
Billing çözümü platformda doğrulanmadı; kullanıcı teyidine rağmen aynı mesaj geldi.
PHP8.3 NOT RUN; PHP8.4 NOT RUN. Yeni aday için açık izin vardı ve tek koşu başlatıldı;
retry/ikinci uygulama koşusu başlatılmadı. CI source/scope helper/PHPStan/lint/audit
çalışmadı. Beklenen60 suite/782 test runtime sonucu değildir; eski FAST yeni adayın
başarısı sayılmadı. Önceki40637188575 attempts1/2 ayrı historical billing kayıtlarıdır.

[CI](evidence/f6-d-r2-hardening/ci.json),
[source proof](evidence/f6-d-r2-hardening/source-proof.json). Application exact SHA/tree
sabit; sonrasında source/test/CI değişmedi. Önceki local docs-only2f30ba8 kaydı tarihçedir;
bu yeni gerçek girişimin kanıtı ayrıca docs-only commit ile kapanır. Financial account/
spending-limit ayarları değiştirilmedi. FULL/Chromium/10/30/20 yok.

## E — Durum / rollback / sınırlar

R2 TECHNICAL HARDENING COMPLETED (LOCAL); R2 genel PARTIAL ve EXACT FAST NOT RUN;
INDEPENDENT ACCEPTANCE PENDING. Root/status sözleşmesi yerelde doğrulandı; R2 için
kendi kendine ACCEPTED kararı verilmedi. Native runtime recovery BLOCKED,
BLOCKED_IDENTITY_BOUND PARTIAL READ_ONLY, BLOCKED_ISSUER_RECOVERY PARTIAL,
BLOCKED_ORIGINAL_ARCHIVE_BYTES OPEN. executable_object_inventory NOT_VERIFIED_SELECT_ONLY
sınırı aynıdır. Gerçek offsite/key custody/PITR/RPO/RTO NOT VERIFIED; G-O01–04 ve tüm
WHMCS/provider/payment/mail/transfer kapıları OPEN, real SUP-010/PROV-004 NOT PASSED.
F6-A/B/C LIMITED SYNTHETIC ACCEPTED; F6-D LIMITED ESCROW ACCEPTED/GENERAL PARTIAL;
R1 LIMITED COLD VERIFICATION ACCEPTED; FULL NOT DISPATCHED; F6-E NOT AUTHORIZED.
PR3 OPEN/DRAFT; main protected=false/rulesets=[] salt-okunur gözlendi, değiştirilmedi.
Production UNCHANGED. Kullanıcı docs/F6_B_B01_REVIEW.md düzenlemesi commit dışında korunur.

Rollback app commit yalnız53162495902b3b168c2fb8554594e99c23a34d62; signed artifacts ve
immutable facts korunur. Daha geniş canonical normalization ancak ayrı versioned
contract olur. Production restore/DROP/DELETE yok; cleanup yalnız exact owned fixtures.
Yetkili tek koşu billing nedeniyle başlamadı; başka deneme yok. Yerel ve platform engeli kanıt teslimi ve DUR.

## F — Docker Desktop alternatif yerel doğrulama

28 Ekim 2026 kullanıcı talimatıyla exact aday PHP 8.3.36/8.4.27 üzerinde Docker'da çalıştırıldı. Her sürümde kalite kontrolleri ve 59 paket/625 test geçti; mevcut destek paketi başarısız kaldı. R2 24, R1 25 ve yeni2 test her sürümde PASS. Genel 782-test PASS yoktur; GitHub FAST hâlâ NOT_RUN_EXTERNAL_BILLING, bağımsız kabul PENDING. [Ayrı rapor ve başarısızlık kanıtları](F6_D_R2_DOCKER_VALIDATION.md). Kaynak değişmedi; yeni GitHub koşusu/FULL/sonraki faz yoktur.
