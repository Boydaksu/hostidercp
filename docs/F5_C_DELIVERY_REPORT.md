# F5-C — güvenli mail ve attachment test/mock teslimi

26 Ekim2026. **Test/mock doğrulama sürüyor; bağımsız teknik denetim beklenir.**
Taban794816113dc56eea3c4223c73c1d68f37aff0b08; kabul edilmiş F5-B5869483,
51 suite472 test. F4/F5-A/F5-B limited mock kabulleri değişmez. SUP-001–004 bu
raporda yalnız tanımlanmış test/mock subset için değerlendirilir; V1 DONE yoktur.

Plan [F5_C_PLAN](F5_C_PLAN.md), karar [ADR-020](adr/ADR-020-support-mail-test-boundaries.md).
Uygulama gerçek GitHub SHA/tree/diff ve çift sürüm CI kanıtı tamamlanınca kaydedilir.
Henüz doğrulanmamış sonuç PASS sayılmaz. İlk37 bağımsız yeni regresyon yerelde PASS;
son file-list/move/origin/CLI testleri dahil40 yeni test ve472 eski test tam tura girer.

## Giriş kanalı ve kimlik

InboundAdapter sözleşmesi, bounded TestAdapter, ağ erişiminden önce reddeden
DeniedAdapter ve gerçek MariaDB pipeline vardır. IMAP/cPanel bağlantısı yoktur.
Native client session'dan üretilen exact-raw sender proof yalnız test doğrulama
kanıtıdır; gerçek SPF/DKIM/IMAP doğrulaması değildir. Verified email, current native
session/user version/membership/support ACL, explicit department ve From birlikte
doğrulanır. Staff email/customer impersonation girişine izin verilmez.

Reply için ayrıca signed ticket/customer/user/department/expiry token ve server'ın
ürettigi Message-ID reference gerekir. Tek subject/ticket number, token, Message-ID
veya forged From yetki vermez. Karışık/yabancı refs fail-closed review'dur. Yeni
ticket yalnız doğrulanmış kimlik ve reply hint'i yokken canonical create yoluna
girer; requested priority NORMAL teknik normalleştirmesidir, yeni SLA ticari süresi
değildir. Native Support private methods/auth/state/event/SLA transaction'ı yeniden
yazılmaz; application seam ile kullanılır. Immutable mail sources TEST_ADAPTER
origin'ini native support event/message'e bağlar. Legacy TEST_PANEL/PANEL canonical
executor alanları korunur; client projection ayrıca mail_origin gösterir.

Mailbox/department scoped HMAC(Message-ID) claim row + raw digest variant tekilliği.
Same bytes replay aynı sonuç; farklı bytes conflict review ve en çok4 variant.
Eksik ID raw digest ile bounded review, automatic attach yok. Conflict/malformed/
missing-ID review onayı ile zorlanamaz; düzeltilmiş mail/new ID gerekir. Unknown
sender hiçbir private ticket'a sessizce bağlanmaz. Loop/bounce/autoresponder BLOCKED.

## Güvenlik matrisi

| Veri / işlem | Bağ ve kontrol |
|---|---|
| Sender proof | HMAC, exact raw SHA256, native actor/customer/session/version/department/expiry |
| Reply | Ek scoped token + tüm References aynı ticket/customer/recipient; current native ACL |
| Review | support.mail_review, active staff/MFA/fresh step-up, member + explicit scoped grant |
| Unknown customer | ayrıca explicit customer wildcard grant; list yalnız ID/reason/status/dept |
| Review karar | prepare/execute, server HMAC/state/actor/session/version confirmation, CSRF/idempotency |
| Raw MIME |128KiB sınırlı, AEAD encrypted, raw erişim scoped+auditli, base64 JSON; HTML render yok |
| Mail outbound | exact event UUID/source event/ticket/customer/public message + published outbox/job canonical hash |
| Transport retry | stable delivery ID, immutable attempt/DB mock acceptance, unique inbox receipt transaction |
| Attachment | immutable owner/message/hash metadata, encrypted UUID file, current native ACL + short header token |
| Quarantine | default QUARANTINED; yalnız exact inert fixture TEST_CLEAN, gerçek AV sonucu değil |
| Delete/hold | explicit configured retention, pre-delete deny/audit commit, hold row lock, metadata korunur |

Private internal_note mail stage'e girmez; customer template veya file listesi
oluşturmaz. Private note attachment bu subset'te desteklenmez. Receipt consumer
private/forged/unpublished/wrong hash bağlarını reddeder. Native support.held.v1
ve support.work.held.v1 consumer'ları değiştirilmez.

## Outbound durum ve crash sözleşmesi

Yalnız enabled departmanda gerçek public STAFF_REPLY için ayrı support.mail.ready
outbox oluşur. Customer inbound mail'e automatic acknowledgement üretilmez; böylece
loop ve sahte SLA yanıtı yoktur. Public event eski support.changed protokolünü korur.
PENDING→HELD native ready receipt; yalnız explicit confirmed test dispatch ile
HELD/RETRYABLE_FAILURE→PENDING→ACCEPTED_BY_TRANSPORT(mock), RETRYABLE_FAILURE veya
PERMANENT_FAILURE. Karantina yeniden HELD'e döndürür. Beş explicit mock attempt sınırı;
permanent veya accepted terminaldir. DELIVERED/read sonucu, gerçek SMTP kabulü veya
gerçek müşteri bildirimi iddia edilmez. API transport=MOCK_DB_SINK,
delivery_confirmed=false döndürür.

Mock kabul/attempt/status/receipt aynı DB transaction'ındadır. Commit öncesi crash
tam rollback; commit sonrası queue finish öncesi replay unique receipt ile no-op.
Native unsafe Queue lease UNKNOWN sözleşmesi korunur; gerçek SMTP timeout/retry
güvenliği bu mock'tan çıkarılamaz. Storage hash/authentication hatasında kabul veya
başarı receipt'i yazılmaz. E-posta body ciphertext'ten yalnız transaction içinde
okunur; mock sink yalnız HMAC ve recipient UUID tutar, body/email/token loglamaz.

## Attachment ve retention

Conservative PDF-only allowlist: filename/path/magic/finfo/size ve bilinen aktif
PDF token kontrolleri. ZIP/HTML/SVG/aktif içerik ve nested arşivler kabul edilmez;
arşiv hiç extract edilmediğinden zip bomb açılmaz. Bu tam PDF sanitizer değildir:
fixture dışındaki bütün geçerli PDF'ler QUARANTINED kalır ve download/transport'a
çıkmaz. TEST_CLEAN yalnız source içindeki exact inert synthetic byte fixture'ıdır;
gerçek antivirus altyapısı yoktur. Native authenticated download kısa süreli
X-Download-Token header'ı kullanır; token URL/access log'una konmaz. Protected PDF
response no-store/nosniff/Content-Disposition/CSP kullanır. Başka customer404;
membership/session/role iptali veya stale department token erişimi keser.

Encrypted storage webroot dışında, DB-namespace DSN hash dizinindedir. DB backup,
APP_KEY ve private storage birlikte korunmalıdır; DB DSN değişiminde namespace
eşlemesi de taşınmalıdır. DB rollback/crash encrypted orphan bırakabilir; DB
metadata/ACL olmadan indirilmez.25-adımlık cleanup24 saatten eski referanssız UUID
dosyalarını temizler. Hold silmeyi engeller. Purge önce DB erişim reddi ve immutable
audit'i commit eder, sonra physical bytes silinir; başarısız silme tekrar güvenlidir.
Raw/file retention1–365 gün explicit admin config; seed/default retention yoktur.
Config yeni içerik expiry'sini belirler; metadata/hash/provenance/history silinmez.

## Kaynak bütçesi ve açık sınırlar

Raw128KiB; header8KiB/40 satır/998 byte; UTF8 text16k byte; max10 refs; depth2 mixed
MIME;4 PDF/file16KiB, message total64KiB; department16MiB retained attachments;
namespace4096 encrypted files; department1000 retained raw ve Message-ID başına4
variant. Native HTTP JSON64KiB nedeniyle base64 raw daha düşük efektif limite
sahiptir; CLI/test adapter raw128KiB sınırına kadar çalışır. Batch25, cleanup25,
explicit mock attempt5, proof300s/reply3600s/download60s. Limitler açık fail-closed;
sessiz truncate veya sahte clean/delivery sonucu yoktur.

Strict MIME subset bare ASCII mailbox, plain UTF8 subject/body,7bit/8bit ve
multipart/mixed PDF base64 destekler. Display-name/folded headers/RFC2047 subjects,
HTML/alternative/nested MIME ve diğer dosya türleri review/reject olur. Tam mail
interoperability, gerçek sender evidence, antivirus, live transport ve operator
UI ayrı doğrulama/GATE gerektirir. Gerçek SLA/shift ticari kabulü değişmez.

## SUP-001–004 kabul matrisi / demo

1 unknown sender→review/no private link;2 identical MID dedupe;3 different raw
conflict;4 forged/expired/foreign/revoked reply token;5 forged ticket subject;
6 native verified own reply/SLA NEXT;7 note no mail/template/files;8 loop/bounce;
9 MIME/raw/body/header limits;10 traversal/ZIP/active/spoof;11 foreign download;
12 native membership/session/review revoke;13 inbound1/4/8;14 outbound1/4/8;
15 exact unpublished/forged queue fingerprint receipt;16 DB/storage rollback;
17 mock accepted never delivered;18 live adapter/config gate deny.

Ek regresyonlar: native HTTP proof/ingest/CSRF; state-bound review replay/stale;
quarantine prevents acceptance; manifest storage missing no receipt; retain/hold;
file listing + current department move token; mail provenance/settings; native
stdin CLI; empty/051→052/replay/old checksum.39+? yerine kesin test sayısı ve kanıt
tam execution sonrasında kaydedilecektir; hedef yeni40 testtir.

Sentetik demo: tests/support-c.php native users/MFA/grants ve explicit config yaratır.
Customer proof API → TestAdapter/CLI stdin ingest → canonical message/SLA; staff
reply → native outbox:dispatch/queue:work held → prepare/execute mock dispatch →
ACCEPTED_BY_TRANSPORT. Attachment upload/token/header download ve unknown review
aynı native HTTP ile test edilir. Gerçek kullanıcı verisi veya mailbox yoktur.

## Migration, rollback ve durma

052 additive;001–051 ve native Identity/Queue/Outbox/Worker, Crypto/Audit ve F5-A
exact receipt/F5-B work code korunur. Eski test adları silinmez; migration expected
list/count yalnız052 ile genişletilir. No skip/assertion weakening.
Tutarlı code/DB/key/storage restore veya reviewed forward-fix; destructive down yoktur.
EXTERNAL_MAIL_ALLOWED=false. DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED,
PROV-005 ve tüm canlı GATE'ler OPEN. PR3 açık/taslak; F5-D/E/F6, gerçek SMTP/IMAP,
mail/notification/provider dispatch, merge, staging veya production yoktur.
F5-C test/mock tesliminden sonra bağımsız teknik denetim istenir ve durulur.
