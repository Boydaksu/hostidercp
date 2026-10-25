# F4-F — Son bağımsız denetim düzeltmesi

25 Ekim 2026. Yalnız test/mock; bağımsız yeniden kabul bekler. Dal codex/f4-domain-cloud.
Taban uygulama 131859cbb5c02e5e6d2f63b8af928c63edc78782, rapor HEAD
c7f4b06c936978d096777c1d89befa5d6b223098; 49 suite / 383 test / 0 hata.

## Gerçek kaynak ve doğrulama

Uygulama SHA: `6e25a8b98de6e0c9da5e84df6b78e46cd47854f2`.
Yerel metadata `bf71191835310db6689901948687ea67d9eff260` ile aynı
`9f0b5a67007559cf2daac3c2cf4af94b714df497` tree; sıfır dosya farkı doğrulandı.
[Kaynak commit](https://github.com/Boydaksu/hostidercp/commit/6e25a8b98de6e0c9da5e84df6b78e46cd47854f2),
[taslak PR #2](https://github.com/Boydaksu/hostidercp/pull/2).

PR CI [40177231429](https://github.com/Boydaksu/hostidercp/actions/runs/40177231429):

- [PHP8.3.36 / job121120451178](https://github.com/Boydaksu/hostidercp/actions/runs/40177231429/job/121120451178): **49 suite / 394 test / 0 hata**, SUCCESS.
- [PHP8.4.27 / job121120451006](https://github.com/Boydaksu/hostidercp/actions/runs/40177231429/job/121120451006): **49 suite / 394 test / 0 hata**, SUCCESS.

Branch CI [40177226236](https://github.com/Boydaksu/hostidercp/actions/runs/40177226236) de
[PHP8.3 job121120435794](https://github.com/Boydaksu/hostidercp/actions/runs/40177226236/job/121120435794) ve
[PHP8.4 job121120435991](https://github.com/Boydaksu/hostidercp/actions/runs/40177226236/job/121120435991)
için ayrı ayrı 49/394/0 SUCCESS. MariaDB10.11.19. Dört ham günlükten suite/test sayımı ve
11 yeni PASS ayrı doğrulandı. PHPStan, Composer audit (0 advisory), 311 PHP lint ve 323-file
pattern scan başarılı. Her sürümde 10 hizmet HTTP / 30 Commerce HTTP / 20 session concurrency
tekrarı sıfır failure; üç JS syntax kontrolü başarılı. 1/4/8 bağımsız PHP worker testleri korunur.
[Job/suite/worker ve ham log API kanıtı](evidence/f4-f-post-audit/ci.json).

PR checkout `6eb40c30e859b962f77717acba302da59ff5074f` fetch edilip kaynakla aynı
`9f0b5a67007559cf2daac3c2cf4af94b714df497` tree ve sıfır dosya farkı doğrulandı.
Branch job checkout'u doğrudan uygulama SHA'sıdır. Merge yapılmadı; PR taslaktır.
14 dosyalık [kaynak manifesti](evidence/f4-f-post-audit/publication.json).

## P1-1 — Native doğrulamadan güvenli kanıt özeti

Yeni GET endpoint:
`/api/v1/admin/provider-panel/evidence?customer=…&id=<service UUID>&operation_id=…&evidence_id=…`.
Native Identity cookie/MFA/fresh step-up; aynı gerçek customer/service ownership üzerinde
**hem services.reconcile hem services.manage**; staff ve impersonation yasağı gerekir.
Client route aynı staff kontrolüyle 403 verir. Farklı service/operation/evidence bağı reddedilir.

Allowlist: evidence_id, operation_id, attempt_id (Domain) veya task_id (Cloud), generation,
operation_state, classification, binding boolean'ları, observed_at/expires_at (UTC), fresh,
MOCK provenance ve allowed_outcomes; Domain no-effect gerekliliği, Cloud failure_supported=false.
Account/resource için doğrulanmış eşlik boolean'ları verilir. Raw provider DTO, contact PII,
EPP/VNC/root/credential, encryption context, plaintext request veya lease token verilmez.

DomainReconciliation.checkedInspection artık summary ve resolve tarafından ortak kullanılır:
inspection/operation/active attempt/generation/domain version/stamp/5dk TTL, context-bound AEAD,
HMAC ve güncel immutable call eşliği birlikte doğrulanır. Gelecek tarih de fresh sayılmaz.
AttemptEvidence.success/noEffect mevcut canonical sözleşmesi korunur; provider inspect HTTP
transaction'ında yapılmaz. Özet mevcut bağımsız inspection'ın yeniden doğrulanmasıdır; yeni
provider sorgusu veya mutation değildir. Eski/mismatch inspection 409 ile yeni inceleme ister.

Cloud.Engine readObservation/boundObservation/verifiedObservation helper'ları summary ve native
apply tarafından paylaşılır. Task/account/resource/operation/fingerprint/generation/lease/version,
hedef durum ve request/spec/ref eşliği korunur. Aktif operation pointer ayrıca doğrulanır.
Summary `verified_success`, `pending` veya `mismatch` döndürür; son iki durumda outcome yoktur.
Provider task ACCEPTED/PENDING bir success değildir. Cloud için failure yolu eklenmedi.

## P1-2 — Domain SUCCESS / FAILURE operatör akışı

| Canonical sonuç | Panel seçeneği | Koruma |
|---|---|---|
| verified_success | Yalnız CONFIRMED_SUCCESS | Found + bağımsız resource/call/account/attempt eşliği |
| definitive_no_effect | Yalnız CONFIRMED_FAILURE | Resource bulunmaması + kesin no-effect call; resource/term/ücret etkisizliği |
| insufficient_evidence | Kesin sonuç butonu yok | Kaynak bulunamaması tek başına failure kanıtı değildir; MANUAL_REVIEW korunur |
| Cloud pending/mismatch | Kesin sonuç butonu yok | UNKNOWN korunur; yeni bağımsız inspect gerekir |

UI önce korumalı endpoint'ten özet alır; SUCCEEDED inspection job'u artık resolve yetkisi veya
provider başarısı saymaz. Yalnız allowed_outcomes için buton üretir; stale/403/error durumunda
yeniden inceleme gösterir. Kesin failure için domain ve ücret etkisizliği gerektiği açık yazılır.

Prepare planı seçilen outcome ve server-generated safe summary'yi gösterir. Summary mevcut
panel confirmation state HMAC'ına dahil edilir. Execute summary'yi yeniden üretir ve aynı
state/payload/actor/session/identity/customer/service binding'i denetler; native resolve
aynı canonical proof kontrollerini tekrar uygular. İstemciden summary alınmaz; ek/tahrif edilmiş
summary alanı 422; outcome değişimi 403; stale version/attempt/inspection 409'dur.
Farklı key ile ikinci confirmation/replay ikinci reconciliation üretmez. Aynı idempotency key
eski kabul sözleşmesi gereği güncel yetki kontrolüyle cached sonucu döndürebilir; effect tekrarlanmaz.

DOMAIN-002 ikinci registrar dispatch/failover açılmadı. Doğrulanmış no-effect FAILURE yalnız
mevcut operation'ı sonuçlandırır; ücret/bakiye/failover onayı yerine geçmez.

## Testler ve başarısızlık kaydı

Eski 383 test silinmedi/gevşetilmedi. Mevcut gerçek HTTP suite'ine ayrı
provider-panel-evidence-cases.php üzerinden **11** yeni senaryo kaydedildi. Yerel PHP8.4.24 /
MariaDB10.11.18 tam koşusu **49 suite / 394 test / 0 hata**; HTTP paketi **39/39**.
[Yerel sayım](evidence/f4-f-post-audit/local.json), [11 yeni ham PASS satırı](evidence/f4-f-post-audit/http-regressions.txt),
[sentetik HTTP kanıt özeti örnekleri](evidence/f4-f-post-audit/http-examples.json).
Eski F4-F tesliminin kanıt dosyaları değiştirilmedi. Yeni senaryolar:

1. Domain bağımsız success → allowlist summary → açık SUCCESS confirmation.
2. Kesin no-effect → yalnız FAILURE → tek attempt ve tek registrar call; failover yok.
3. Kaynak absent, para etkisi belirsiz → insufficient; iki kesin outcome da ret.
4. Başka customer/service operation ve caller-forged summary reddi.
5. Active attempt değişimi → eski inspection ret.
6. Generation immutable DB koruması; aggregate version değişimi → stale summary/confirmation.
7. Outcome tamper ve eski tarihli immutable inspection fixture'ı reddi.
8. Cloud pending task → success/failure yok; UNKNOWN korunur.
9. Customer ve iki gerekli yetkiden biri eksik scoped staff için summary 403.
10. Farklı Cloud task/current binding ve instance generation/version değişimi → mismatch/stale; effect yok.
11. İki bağımsız HTTP server/process aynı onayla yarışır → bir reconciliation/evidence kaydı;
    sonraki yeni-key replay reddi.

Native login cookie/MFA/session, gerçek MariaDB, HTTP request/response ve ayrı PHP cron/process
kullanılır. Mock provider gerçek sağlayıcı sayılmaz. Test geçmişi:

- İlk yeni koşuda generation fixture'ı immutable domain_operation trigger'ına takıldı.
  Trigger korunup test doğrudan bu ret için assertion yapacak şekilde düzeltildi; ardından
  version drift ile eski confirmation'ın reddi denetlendi. Eski inspection tarihini UPDATE
  etmek yerine context-bound şifreli, tarihli ayrı sentetik fixture INSERT edildi.
- Sonraki hedefli koşuda mevcut 28 + ilk 10 yeni HTTP testi: **38/38** başarılı.
  Ek Cloud task/generation senaryosu dahil son tam yerel HTTP koşusu **39/39** başarılıdır.
- PHPStan başarılı; 311 PHP lint/mimari dosyası 0 ihlal; 323 pattern dosyası 0 bulgu;
  Composer locked audit 0 advisory; JS syntax başarılı. Pattern scan tam secret denetimi değildir.
- Uzak kaynak CI'ında başarısız step veya rerun yoktur. Yerel ilk fixture hatası yukarıda
  ayrı kaydedildi; başarısızlık test silme, ignore veya trigger kaldırmayla gizlenmedi.

## Dosyalar / migration / geri dönüş

Native Domain/Cloud proof validator extraction; yeni ProviderPanel.Evidence; controller GET,
Commands.state ve Confirmations plan/state binding; JS outcome butonları; HTTP test kayıtları;
endpoint envanteri, düzeltme planı ve kapsam kararı. Yeni queue/dispatch/identity/finance motoru yoktur.
F4-C/D/E claim/send/lease/fence/entitlement/containment/financial policy'leri kaldırılmadı.
Mevcut inspection worker ve outbox receipt/completion ayrımı korunur.

**001–048 48/48 byte/hash eşliği** korunur; yeni migration yoktur. Manifest
[migrations.json](evidence/f4-f-post-audit/migrations.json). Geri dönüş: panel/inspection cron durdur,
UNKNOWN mutation replay etme; tutarlı kod/DB/APP_KEY restore veya forward-fix. Immutable finans,
provider kanıtı veya eski migration silinmez. Canlı migration/restore işlemi yapılmadı.

## Açık kapsam istisnası ve durma noktası

**Mapping/import CRUD uygulanmadı ve DONE değildir.** Proje sahibi bu görüşmede ayrı
yetkilendirilecek işe ertelenmesini açıkça onayladı. F4_PLAN güncellendi;
[ayrı kapsam / yetki / test planı](F4_F_MAPPING_IMPORT_SCOPE.md). Bu onay CRUD uygulamasını,
canlı mapping'i, imported VM CREATE'i veya F5/F6 başlangıcını yetkilendirmez.

DOMAIN-002 PARTIAL/MANUAL_REVIEW; G-V01–04, PROV-005 gerçek saatlik maliyet, registrar,
G-C01 ve bütün canlı GATE'ler OPEN. Gerçek Voyar/registrar/PayTR/WHM/SMTP ve production
credential kullanılmadı. Bu düzeltmede durulur; bağımsız yeniden kabul istenir. F5/F6,
target/main merge, staging veya production'a geçilmez.
