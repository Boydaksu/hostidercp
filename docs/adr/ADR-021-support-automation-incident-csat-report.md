# ADR-021 — bounded delegation, causation ve public incident sınırı

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
