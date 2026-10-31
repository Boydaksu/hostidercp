# ADR-030 — F6-D-R2 Limited Local Docker CI Verification Method Exception

STATUS: ACCEPTED — OWNER-APPROVED VERIFICATION METHOD ONLY

F6-D-R2 INDEPENDENT ACCEPTANCE: PENDING
F6-D-R2 INDEPENDENT TECHNICAL ACCEPTANCE: PENDING

Karar tarihi: 28 Ekim 2026 (Europe/Istanbul).
Karar yetkisi: proje sahibinin bu göreve eklediği açık yöntem onayı.
LOCAL DOCKER CI METHOD EXCEPTION: OWNER APPROVED — EXACT SHA LIMITED.
Bu kayıt doğrulama yöntemini resmîleştirir; F6-D-R2 uygulamasına bağımsız teknik kabul vermez.

## Kapsam / dosyalar / migration / doğrulama / geri dönüş

OPS-002/003/004, SEC-004 ve D6 yalnız sentetik restored-target SELECT-only recovery reconciliation alt kapsamındadır.
Bu karar kaydı yalnız bu yeni ADR, [R2 planı](../F6_D_R2_PLAN.md) ve [R2 delivery raporu](../F6_D_R2_DELIVERY_REPORT.md) üzerinde belgelenir.
Migration veya uygulama/test/workflow değişikliği yoktur. Doğrulama yalnız belge diff'i, kaynak kimliği, kanıt hash'leri ve kapsam tutarlılığı incelemesidir; yeni test veya Docker çalıştırması yapılmaz.
Geri dönüş yalnız bu üç belgenin bu görevdeki ekleriyle sınırlıdır; otomatik silme/restore/revert veya source/migration geri alma işlemi değildir. Commit/push bu görevde yetkili değildir.

## Proje sahibinin açık kararı — aynen

> HostiderCP F6-D-R2 için, yalnızca application SHA 53162495902b3b168c2fb8554594e99c23a34d62 ve evidence commit cc94f662454cb3f030addd2db0785a09900c0ed2 kapsamında, belgelenmiş sınırlamalar ve riskler açık kalmak kaydıyla, parçalı LOCAL DOCKER CI kanıtlarının exact-source FAST yerine sınırlı bağımsız kabul değerlendirmesinde kullanılmasını ONAYLIYORUM. Bu onay F6-D-R2 teknik kabulü, FULL, F6-E, merge, staging veya production izni değildir.

Bu metin yöntem için verilmiş açık onaydır; yeniden onay bekleyen taslak değildir.
Bağımsız teknik incelemecinin F6-D-R2 kabul kararı ayrı koşuldur ve henüz verilmemiştir.

## Exact kaynak ve kanıt bağı

| Kimlik | Değer |
| --- | --- |
| Repository / branch | Boydaksu/hostidercp / codex/f5-support-center |
| Bu kayıt hazırlığının GitHub başlangıç HEAD'i | 546dda93f1ba49387cd9702c3693c7b2dcac3d63 |
| Başlangıç HEAD tree | d1766472cd28ad49f41883d4625d19409aa91656 |
| Immutable application commit | 53162495902b3b168c2fb8554594e99c23a34d62 |
| Application tree | e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47 |
| Production migrations 001–054 subtree | c0729c92dd143c017fdec32421bf67fe6b51e05b |
| Immutable Docker evidence commit | cc94f662454cb3f030addd2db0785a09900c0ed2 |
| Docker evidence subtree | 9110522a521348d9a1c44a0df3975c35c8cfac2a |
| Support-fix evidence subtree | 9ef15aae2798bd988678d861424e4f4db21a5247 |

546dda93 belge yayını HEAD'idir; cc94f662 tarihsel test kanıtı commit'idir. Bu kimlikler birbirinin yerine geçirilmez.
Yöntem izni yalnız belirtilen application SHA/tree ve değişmeyen evidence setine bağlıdır; yeni application commit'lerine, diğer fazlara/projelere veya sınırsız geleceğe yayılmaz.

## Gerekçe ve onaylanan alternatif yöntem

GitHub Actions FAST, billing/spending-limit nedeniyle uygulama testleri başlamadan engellenmiştir.
Proje sahibi ücretli GitHub Actions veya yeni ücretli CI kullanmayacaktır.
Exact-source FAST sonucu varmış gibi gösterilmeden, aynı immutable kaynak ve kayıtlı güvenlik/kalite kontrollerini kullanan parçalı yerel Docker kanıtlarının yalnız bu dar bağımsız değerlendirmede FAST yerine kullanılmasına izin verilmiştir.

| Runtime | İlk başarılı grup | Devamdaki başarılı diğer grup | Sonraki tam support HTTP | Ayrı çalıştırmaların toplamı |
| --- | --- | --- | --- | --- |
| PHP 8.3.36 | 50 paket / 398 test | 9 paket / 227 test | 1 paket / 157 test | 60 paket / 782 test |
| PHP 8.4.27 | 50 paket / 398 test | 9 paket / 227 test | 1 paket / 157 test | 60 paket / 782 test |

**Tek uninterrupted broad PASS yoktur.** İlk broad/continuation başarısızlıkları ve tanı koşuları tarihçeden silinmez; başarısız veya çalışmayan testler başarılı toplamına eklenmez.
Başarılı paket gruplarının birleşimi registry 60 ve sürüm başına 782 tekil PASS adıyla önceki incelemede eşleştirilmiştir.
Son support koşuları doğrudan değişmeyen tests/support-http.php üzerinden 157 bireysel PASS/0 failure ve application_exit0 kaydeder.
Bu yerel sonuçlar GitHub Actions PASS veya FULL ACCEPTANCE olarak adlandırılamaz.
GitHub FAST **NOT_RUN_EXTERNAL_BILLING / NOT RUN** kalır; bu ADR CI check durumunu değiştirmez veya yeni workflow izni vermez.

## Kalite ve bütünlük kanıtları

Immutable evidence commit altındaki docs/evidence/f6-d-r2-hardening/docker/manifest.json için 27/27, docker-support-fix/manifest.json için 13/13; toplam **40/40 SHA-256** canonical Git blob baytlarıyla eşleşmiştir.
Kaynak proof kayıtları application SHA/tree'yi bağlar. Bu hash eşliği içerik tutarlılığıdır; bağımsız execution attestasyonu değildir.
Önceki exact-SHA kalite kayıtları docker/sanitized-83.txt ve sanitized-84.txt:1–69, quality.sh ve run-version.ps1 içindedir:
PHPStan 0; lint 453/0; pattern 475/0; Composer locked audit advisory yok; npm audit 0; CI helper 6/0; JS state 3/0 ve dört JS syntax adımı exit0.
Bu adımlar son support turunda yeniden çalıştırılmamıştır; son runner'ın quality_exit:0 alanı placeholder, quality_rerun:false'tur. Placeholder kalite kanıtı olarak hariçtir.
Bu kayıt güncel vulnerability veritabanı taraması, bağımsız secret audit'i veya FULL sonucu değildir.

Kanıt ve ayrıntılı önceki incelemeler:

- [Docker doğrulama raporu](../F6_D_R2_DOCKER_VALIDATION.md)
- [Support ortam düzeltmesi](../F6_D_R2_SUPPORT_DOCKER_FIX.md)
- [Bağımsız kabul hazırlık raporu](../F6_D_R2_INDEPENDENT_ACCEPTANCE_REVIEW.md)
- [Yöntem teklifinin tarihsel taslağı](../F6_D_R2_LOCAL_DOCKER_CI_EXCEPTION_PROPOSAL.md)
- [Karar taslağı ve metadata gözlemi](../F6_D_R2_LOCAL_CI_EXCEPTION_DECISION_DRAFT.md)

Önceki üç hazırlık belgesinin PROPOSED/BLOCKED ifadeleri o kayıtların tarihsel durumudur; bu görevde değiştirilmez.
Bu ADR sonraki açık owner yöntem kararını kaydeder. Önceki B sonucunu geriye dönük değiştirmez ve kendiliğinden A/teknik kabul üretmez.

## Güvenlik değişmezleri

PASS ⇒ immutable_evidence MATCHED ⇒ observed_target_evidence_sha256 == verified.logical_sha256.

Bu invariant gevşetilemez. Aynı row multiset fakat farklı ordered logical root, PASS üretemez.
RecoveryContext::compare (src/Migration/Operations/RecoveryContext.php:38–44) kök farkını durum kararından önce EVIDENCE_ROOT_MISMATCH yapar.
RecoveryInspector::inspect yalnız MATCHED_REFERENCE'i actual SELECT-only DB PASS'a dönüştürür; farklı kök REVIEW_REQUIRED/BLOCKED olur.
tests/migration-recovery-integrity.php:7–13 multiset/count/finance eşliği korunurken ordered-root farkının reddini; :14–22 gerçek DB'de aynı kayıt sayısıyla tahrifat sonrası root/fingerprint farkını ve PASS reddini assertion'larla sınar.
Kayıtlı her PHP sürümündeki integrity 2/0, mevcut R2 target 24/0 ve R1 recovery 25/0 negatif kanıtları değerlendirme setindedir; yeni test çalıştırılmaz.
Salt array comparison DB authority değildir. SELECT-only grant/role/schema/marker/migration fence, base-table/view reddi, bounded READ ONLY REPEATABLE READ, DML/DDL/CALL reddi, ownership/privacy ve finance kontrolleri aynen korunur.
Kaynak/günlük incelemesi canlı deployment grant'lerini veya tüm executable-object inventory'sini ispatlamaz.
Güvenlik ihlali veya yanlış PASS, yöntem onayı ile kabul edilebilecek bir risk değildir; böyle bir bulgu değerlendirmeyi bloke eder.

## Açık sınırlamalar ve risklerin kaydı

1. Tek uninterrupted broad PASS yoktur; paketler arası kesintisiz ortak çalışma güvencesi iddia edilmez.
2. GitHub FAST NOT_RUN_EXTERNAL_BILLING kalır; yerel sonuç platform PASS değildir.
3. Son support turunda kalite tekrarı yapılmadı; quality_exit:0 placeholder'dır.
4. Tam image ID sha256:de61fed4a40d3842f3ee09944ba52792156cfd9adf489b2cc670fc6ded28df8d, yerel mevcut Docker image inspect gözleminde linux/amd64, MARIADB_VERSION=1:10.11.18+maria~ubu2204 ve org.opencontainers.image.version=10.11.18 ile eşleşmiştir.
5. Bu MariaDB metadata eşleştirmesi bağımsız tarihsel runtime attestasyonu değildir. Tarihsel SELECT VERSION() kanıtı **NOT INDEPENDENTLY VERIFIED**; uydurulmaz.
6. Yerel logların bağımsız kriptografik execution attestasyonu yoktur; ilk taraf kaynak/log/hash ilişkisi bu sınırla değerlendirilir.
7. Runner yapılandırmasındaki 768 MiB→4 GiB tek satır farkı ve sonraki tam support başarıları kayıtlıdır; eski HY000/1005 tek başına kesin ENOSPC değildir.
8. capacity.json'daki tarihsel kullanım sayılarının alttaki ham DB-mount df/inspect örnekleri bulunmamıştır. Bu eksiklik **yalnız bu dar inceleme için NON-BLOCKING HISTORICAL DIAGNOSTIC GAP** olarak ele alınır; ölçümler varmış veya kesin kök neden kanıtlanmış gibi sunulmaz. Yeni ölçüm eski ölçüm yerine konulamaz.
9. Bu sınırlamalarla yönteme izin verilmesi, eksik kanıtın tamamlanması veya tüm DB/production kapasitesinin onayı değildir.
10. Bağımsız teknik incelemeci mevcut kanıtı ve güvenlik koşullarını ayrıca değerlendirecektir; gerekli güvenlik kanıtı yetersizse PENDING/BLOCKED kararı verme yetkisi korunur.

## Önceki kararlarla ilişki

Bu ADR, docs/01_DECISIONS.md'deki sonraki açık owner kararı ilkesi ve ADR süreci uyarınca, R2 planının exact-source FAST şartına yalnız yukarıdaki exact kapsam için açık yöntem istisnasını kaydeder.
Genel FAST kuralı silinmez. ADR-023'ün FAST/docs-only/FULL ayrımı ve nihai FULL/merge koşulları kaldırılmaz.
ADR-028'in pre-enrolled cold verification ve ADR-029'un ayrı SELECT-only restored-target authority sınırları değişmez.
docs/01_DECISIONS.md, mevcut ADR-023/028/029, README, evidence ve önceki kabul kayıtları değiştirilmez.

## Kabul edilmeyen genişlemeler

- F6-A/B/C: Limited synthetic acceptance; F6-D: limited encrypted escrow acceptance / GENERAL PARTIAL; R1: limited cold verification acceptance değişmez.
- F6-D-R2 independent technical acceptance **PENDING**; bu ADR'nin ACCEPTED durumu yalnız doğrulama yöntemine aittir.
- Native runtime recovery **BLOCKED**; original archive bytes **OPEN**; issuer recovery **PARTIAL / OPEN**.
- Offsite/key custody ve PITR/RPO/RTO **NOT VERIFIED**.
- Executable-object inventory **NOT_VERIFIED_SELECT_ONLY**; bütün schema/runtime güvenliği kabulü değildir.
- Bütün gerçek provider/payment/mail/WHMCS/cutover kapıları açık kalır.
- **FULL / F6-E / MERGE / STAGING / PRODUCTION: NOT AUTHORIZED**. Deployment veya gerçek recovery/ödeme/sağlayıcı işlemi yetkisi yoktur.

## Geçersizlik, yeniden değerlendirme ve geri dönüş

Yeni application commit'i veya güvenlik düzeltmesi, application/tree/migration drift'i, evidence byte/hash uyuşmazlığı, farklı runner ya da maddi ortam/trust değişikliği bu exact kapsamda istisna kullanımını geçersiz kılar; yeni kaynak otomatik kapsanmaz.
Yeni yanlış PASS/invariant ihlali, image kimlik uyumsuzluğu veya başka kritik kanıt çelişkisi kabul değerlendirmesini durdurur; kaynak düzeltmesi veya yeni test ayrı yetki gerektirir.
Docs-only sonraki yayınlar yeni application kabulü doğurmaz; immutable evidence referansları otomatik değişmez.
Owner yöntem kararının geri alınması/değişmesi açık yeni karar kaydı ve yeniden değerlendirme gerektirir; mevcut genel FAST şartı sessizce kaldırılmaz.
Belgesel rollback yalnız ADR-030 ve bu görevin plan/delivery ekleriyle sınırlı, ayrıca yetkilendirilecek incelemeye tabidir. Mevcut dosya/kullanıcı değişikliği, signed evidence, source veya migration otomatik geri alınmaz.

## Durma noktası

Owner yöntem onayı verilmiştir; **F6-D-R2 bağımsız teknik kabul kararı verilmemiştir**.
Sonraki en küçük adım bu üç belgenin diff'inin bağımsız incelenmesi ve ayrıca teknik kabul değerlendirmesidir.
Bu yerel belge hazırlığı commit/push, CI dispatch/retry/rerun veya sonraki faz yetkisi içermez.

## 28 Ekim 2026 — sonraki ayrı bağımsız teknik karar kaydı

Proje sahibinin yeni görevde ilettiği bağımsız incelemeci kararı,
[R2 kabul kaydında](../F6_D_R2_ACCEPTANCE_RECORD.md) ayrı belgelenmiştir:
F6-D-R2 LIMITED SYNTHETIC READ-ONLY RECOVERY RECONCILIATION — ACCEPTED WITH EXPLICIT EXCEPTIONS.
Yalnız bu ADR'deki eski immutable application/evidence içindir. Yukarıdaki PENDING ifadeleri
bu yöntem kararı hazırlanırken geçerli tarihsel durumu gösterir; yöntem onayı tek başına
teknik kabul oluşturmuş değildir. Yeni F6-E-P0 uygulama adayı kapsam dışındadır.
Bütün açık recovery/runtime/production istisnaları ve başarısız test geçmişi korunur.
