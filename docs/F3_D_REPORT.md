# F3-D UNKNOWN provider mutabakatı

PROV-001/002/006, SEC-004. 028 inspection/resolution tabloları append-only, evidence AEAD/HMAC ve kısa güvenlik geçerliliği içerir. Reconciliation Finance modülünden ayrıdır. Scoped staff/MFA/fresh step-up; inspect ve resolve actor/service/customer/version/operation/server/package/period kanıtlarına bağlıdır. Found kaynak manuel kararla mevcut hesaba bağlanır; eski UNKNOWN operation değişmez, ayrı resolution geçmişi vardır. Not-found kör retry/failover veya kapasite release yapmaz. Refund/cancel tekrar kontrol edilir.

4/4 MariaDB test: found tek kaynak/ledger sabit, not-found hold, actor/scope/stale step-up retleri, immutable inspection, refund ret, event hatasıyla resource/period/resolution/activation atomik rollback ve sonra aynı kanıtla başarılı karar. İlk koşuda fixture array union eski katalog service nesnesini korudu; array_replace ile isim çakışması giderildi. PHPStan başarılı. HTTP confirmation/idempotency F fazında bağlanacak; bu aşama dahili application service testidir.

001–027 değişmedi. Snapshot/forward-fix dışında destructive rollback yok. GATE'ler açık, gerçek transport yok, F3 final kabulü veya F4 GO değildir.
