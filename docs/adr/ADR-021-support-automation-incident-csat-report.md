# ADR-021 — bounded delegation, causation ve public incident sınırı

## Bağımsız teknik kabul durumu

**F5-D LIMITED MOCK TECHNICAL BASELINE — ACCEPTED**

Proje sahibinin bağımsız teknik kabulü yalnız F5-D test/mock kapsamı içindir.
Kabul edilen uygulama `3404ddda79bc2017f05cb474b5546e23647910d8`.
PHP 8.3/8.4: **51 suite /555 test /0 hata**; eski514 test eksiksiz, yeni41 PASS.
SUP-008 otomasyon döngüsü/AST/dedupe/güncel yetki/transaction sınırları,
SUP-009 incident public/private müşteri izolasyonu, CSAT ve sınırlı raporlama
çekirdeği PASS.001–052 ve kritik kaynaklar korunmuş;053 additive'dir.

Bu karar tam V1, canlı automation scheduler, incident operasyonu veya production
kabulü değildir. F4/F5-A/F5-B/F5-C kabulleri ve555 testlik F5-D tabanı korunur.

Açık kalanlar: F5-C'nin dört canlı istisnası (mailbox routing/sender authentication;
retention sonunda kesin erişim kesilmesi/scheduler; antivirus/gerçek IMAP/SMTP/genel
MIME; mock HELD/ACCEPTED gerçek teslim değildir), auto-close, merge/split, kurum
geneli tarihsel SLA rapor doğrulaması, F5-E arayüzü ve F6 aktarımı. Customer/service
predicate projection UNAVAILABLE sınırı değişmez. DOMAIN-002 PARTIAL, mapping/import
CRUD DEFERRED, PROV-005 ve bütün canlı GATE'ler OPEN kalır.

Yalnız kabul kaydı güncellenir; uygulama/test/migration/CI kanıtı değişmez. Yeni
geliştirme veya yeni test koşusu iddia edilmez. Doğrulama belge diff'i ve kaynak
eşliği; geri dönüş yalnız bu belge commit'inin geri alınmasıdır. PR #3 OPEN/DRAFT;
merge, staging, production ve gerçek SMTP/IMAP/provider işlemleri yoktur.
Kayıt tamamlanınca durulur; F5-E için proje sahibinin ayrı yetkisi beklenir.

Aşağıdaki teslim/denetim bekleme ifadeleri ve kanıt statüleri tarihsel kayıt olarak
korunur; güncel sınırlı bağımsız kabul kararı bu bölümdür.

## Teknik kararın tarihsel kaydı

F5-D test/mock teknik kararı. D4/R2-05/06/07/08/09, SUP-008/009.
F5_D_PLAN.md ayrıntılı normatif sözleşmedir. Yeni Identity/Queue/State/Audit yoktur.

Rule publish sınırsız system authority vermez: explicit native fresh confirmed
sponsor delegation her worker action'da current native session/grant/department
ile tekrar doğrulanır. Session/freshness kaybı SKIPPED (AUTHORIZATION_UNAVAILABLE); gizli session yenileme
yoktur. Canonical native executor sponsor, gerçek kural aktörü immutable execution
ve audit'te support-rule olarak ayrılır. Sistem staff_reply yaratamaz. Bu kısa
ömürlü test delegation canlı kalıcı scheduler yetkilendirmesinin kabulü değildir.

Immutable native source adapter, exact event/outbox/job fingerprint, source ticket
version ve immutable chain kullanılır. Max depth4 ve repeated rule UUID block;
revision değiştirerek cycle bypass yoktur. Rule başına atomic savepoint; aynı event
receipt/idempotency transaction'ı. Simulation authentication side effect'leri dahil
rollback edilerek aynı evaluator/preflight'ı çalıştırır. Unsupported predicate veri
sağlamıyorsa UNAVAILABLE; unknown AST/action422.

Priority değişimi yeni canonical support operation'dır; requested korunur, effective
ve assignment weight açık history ile değişir. Geçmiş SLA hedef/snapshot/sonuçları
değişmez; yeni cycle mevcut effective priority'yi kullanır. Auto-close/merge/split
bu fazda DEFERRED, explicit reopen korunur. Ticari değer seed edilmez.

Incident yalnız kendi public allowlist içeriklerini yayımlar; ticket'lardan metin
toplamaz. Private root cause encrypted ayrıdır. Linked customer projection diğer
ticket IDs veya sayılarını bile içermez. Update gerçek staff response değildir;
incident resolved ticket kapatmaz. CSAT explicit configurable window ve immutable
revision; report SQL ACL-first, integer sums/counts, null/UNAVAILABLE ayrı.
Atanmış zaman actual work hours değildir. F5-C dört istisnası/canlı GATE'ler açıktır.
