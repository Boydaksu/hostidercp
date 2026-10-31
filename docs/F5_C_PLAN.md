# F5-C — güvenli mail ve attachment test/mock planı

26 Ekim2026. Taban794816113dc56eea3c4223c73c1d68f37aff0b08; kabul edilmiş F5-B
5869483 /51 suite472 test. Yalnız F5-C; PR3 açık/taslak. SUP-001/002/003/004,
SEC-001/004, OPS-001/005. F5-D/E/F6, canlı SMTP/IMAP/provider/notification yoktur.

## Kapsam, dosyalar, migration, test, geri dönüş

src/Support/Mail altında sınırlı Mime, signed Tokens, Inbound adapter/pipeline,
Review, Outbound/sink, encrypted AttachmentStore ve native HTTP Controller.
Support.Service canonical inbound application seam ve public-event hook;
bootstrap/bin bağlantısı. Native Identity/Queue/Outbox/Worker ve exact F5-A sink
yeniden yazılmaz. Immutable canonical support kayıtları ve SLA hook'u kullanılır.
052+ additive mail ingress/variants/provenance/tokens/outbound/attempts/attachment/
retention tabloları ve support.mail_review permission.001–051 byte/hash korunur.
Mail departmanı explicit enabled+retention yapılandırması olmadan etkin değildir;
hiçbir mail hesabı, SMTP credential veya ticari retention değeri seed edilmez.
Mevcut051'e kadar migration test beklentileri yalnız052 ile genişletilir.

Gerçek MariaDB/native HTTP,472 eski ad + yeni testler,1/4/8 PHP süreç;
PHP8.3/8.4 full CI, PHPStan/locked audit/lint/pattern ve10/30/20 tekrarları.
Rollback tutarlı code/DB/key/private-storage restore veya forward-fix; down/history
silme yok. Storage önce encrypted atomic dosya yazımı, sonra DB commit; rollback
ve crash orphan'ları indirme yetkisi oluşturmaz. Bounded orphan cleanup ve içerik
retention yalnız expired/hold olmayan kayıtlar için auditli çalışır.

## Giriş, sender ve dedupe

Networkless TestAdapter ve live-denied adapter sözleşmesi. Raw MIME128KiB, header8KiB,
en çok40 header/line998, body16k UTF8 plain text, depth2, max4 ek ve toplam64KiB ek.
Strict CRLF MIME subset; single From/To/Subject/Message-ID, exact ASCII mailbox ve
bounded RFC angle Message-ID/References. Unsupported encoding/type/nesting reddi;
HTML/SVG/archives/executables açılmaz veya render edilmez. Arşiv hiç extract edilmez.

MIME sender alanı authentication değildir. Test kimlik kanıtı native müşteri
session'ından üretilir: verified email + selected customer + identity/session version,
department + raw SHA256 + expiry birlikte imzalanır. Bu gerçek DKIM/SPF/IMAP sender
doğrulaması değildir; canlı trusted evidence sağlayıcı GATE'ine bağlıdır. Worker
güncel native client session/membership/support scope ve exact From adresini tekrar
doğrular. Customer-session proof yoksa review; staff adına customer ingest yoktur.

Reply ayrıca signed scoped ticket/customer/user/department token ve server-issued
outbound Message-ID reference ister. Subject numarası, Message-ID veya token tek
başına yetki değildir. References farklı ticket/customer'a işaret ederse review.
Yeni ticket yalnız verified customer identity, explicit department ve hiçbir reply
hint'i yokken oluşturulur. Inbound canonical Service.create/reply yolu aynı native
auth ve SLA transaction'ını kullanır; mail origin ayrı immutable provenance kaydıdır.
TEST_PANEL canonical executor sözleşmesi korunur; gerçek posta provenance uydurulmaz.

Mailbox-scoped HMAC(Message-ID) tekil row lock; same raw digest replay tek sonuç,
different digest ayrı conflict variant/review; eksik ID bounded raw digest ile
review (raw başına dedupe), otomatik ticket yok. Loop/bounce/autoresponder terminal
BLOCKED: Auto-Submitted, Return-Path empty, multipart/report ve precedence controls.

## Review yetki matrisi

| İşlem | Yetki / yeniden kontrol |
|---|---|
| client proof/reply-token | current native customer support.view/reply, verified email, ownership |
| review list/detail/raw | active staff + MFA + fresh step-up + support.mail_review + department membership + explicit customer grant |
| unknown customer review | ayrıca explicit wildcard customer grant; hiçbir private ticket metadata listelenmez |
| approve/create/link | scoped reviewer + optimistic version + Idempotency-Key + allowlist; fresh sender proof + reply token/ref + native owner ACL tekrar |
| reject | aynı review scope, immutable decision/audit; tekrar yeni karar üretmez |
| attachment list/download | current native ticket/message/customer/department ACL; private note eklerine public erişim yok |
| retention/hold | administrative identity.staff.manage + fresh step-up; scoped config/audit |

Approval unknown sender'ı keyfi müşteriyle eşleştirmez; kanıt oluşmamışsa fail-closed.
HTTP CSRF/native sessions/rate limit; review raw128KiB encrypted, summary yalnız ID/
reason/status/department, PII ve private ticket body içermez. Raw erişim de auditlidir.

## Outbound ve receipt

Public immutable support event için ayrı ID-only support.mail.ready outbox olayı;
event_uuid/support_event_uuid/ticket/customer/message bağları canonical payload,
fingerprint, published native job ve receipt ile doğrulanır. Private note hook'tan
çıkar; state/move için mail template üretilmez. Eski support.held.v1 korunur.
Unique(public event, recipient user) PENDING→HELD; explicit confirmed test dispatch
HELD/RETRYABLE_FAILURE→PENDING→ACCEPTED_BY_TRANSPORT(mock) veya
RETRYABLE_FAILURE/PERMANENT_FAILURE. DELIVERED/read sonucu yoktur. Transport stable
delivery UUID idempotency sözleşmesi; networkless sink receipt ve state tek DB tx,
crash/replay/parallel duplicate kabul üretmez. Gerçek adapter constructor/send deny.

## Attachment / retention

Webroot dışı private root; UUID path, exclusive atomic encrypted file, SHA256/
size/name/type/owner binding. Conservative PDF-only attachment subset; basename
allowlist, magic/finfo ve active PDF tokens reddi. HTML/SVG/ZIP/nested archives asla
extract/render edilmez. Scan yoksa QUARANTINED. Yalnız exact synthetic PDF fixture
TEST_CLEAN olabilir; gerçek AV sonucu değildir, diğer tüm dosyalar karantinada.
Download authenticated native ACL + kısa süreli signed actor/session/identity/
ticket/customer/department/message/file token; current ACL her istekte tekrar.
PDF uuid.pdf Content-Disposition/no-store/nosniff/CSP native protected response.
Token URL'de değil X-Download-Token header'ındadır. Native ticket taşınmasında dosya
origin department metadata'sı korunur; erişim current ticket department ACL ile,
eski token reddi ve yeni scoped token ile yürür. Upload yalnız mesaj yazarıdır ve
ticket version artırır; dispatch manifest'i freeze edilir. Ek bütçesi16MiB/department
ve4096 encrypted file/storage namespace; raw retained quota1000/department.
Attachment token quarantine bypass vermez. Test clean fixture gerçek AV değildir.

Raw/attachment retention ayrı configurable1–365 gün; explicit legal hold override
silinmez. Bounded purge bytes only, immutable metadata/hash/audit/provenance korunur;
purged içerik yeniden döndürülmez. Exact retention ticari/hukuki kabul değildir.

## Kabul matrisi

1 unknown review;2 duplicate ID;3 different content conflict;4 bad/expired/foreign
token;5 forged subject;6 verified own reply/SLA;7 note no template/outbox/attachments;
8 bounce/loop;9 MIME limits;10 traversal/zip/HTML/spoof;11 foreign download;
12 scope revoke;13 inbound1/4/8;14 outbound1/4/8;15 exact unpublished/forged job binding;
16 DB/storage rollback;17 mock accepted not delivered;18 live network deny.
Empty/051→052/replay +472 old names. Mock evidence yalnız gerçek test execution'dır;
production veya bağımsız kabul sonucu değildir. Teslimden sonra bağımsız denetim
istenir ve durulur. DOMAIN-002 PARTIAL/import DEFERRED/PROV-005/GATE OPEN korunur.
