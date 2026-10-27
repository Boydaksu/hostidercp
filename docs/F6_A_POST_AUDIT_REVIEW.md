# F6-A P1 — message kind / visibility denetim düzeltmesi

27 Ekim 2026. Başlangıç HEAD89adde017fe14a750eb22c43abf4b71e7bc15a38;
denetlenen uygulama8340be6c5a12e551587ab92e72da930a18a7894d.
Durum: hedefli düzeltme ve bağımsız yeniden kabul bekler; ACCEPTED değildir.

## Kapsam / dosya / migration / test / geri dönüş planı

SUP-003/004/010, SEC-004, FR-OPS/D4/D6: yalnız sentetik mesaj türü/görünürlük ve
doğrulanmış source staff-yazar eşliği. `src/Migration/SnapshotValidator.php`,
yeni `tests/migration-visibility.php`, suite envanteri/FULL listesi, bu rapor ve
`docs/evidence/f6-a-post-audit/` değişir. Eski613 test/fixture aynen korunur.
Native F5-E projection/attachment authorization, Identity/Finance değişmez.

Migration yok;001–054 immutable. Önce yeni test eski kaynakta çalıştırılacak,
FAIL karşı-örneği kaydedilecek; sonra dar düzeltme ve eski31+yeni regresyonlar,
PHPStan/lint/pattern/CI helper ve son adayda gerçek FAST PHP8.3/8.4 çalışacak.
Yeni suite envanter ve FULL listesine birlikte eklenecek; FULL dispatch yok.
Rollback yalnız bu hedefli uygulama commit'inin revert'ü; DB down/veri silme yok.
Forward-fix bu sözleşmeyi fail-closed tutmalı, private kaydı public'e çevirmemeli.

## Karşı-örnek ve karar

Eski validator INTERNAL_NOTE için PRIVATE isterken PUBLIC_REPLY+KNOWN PRIVATE
kombinasyonunu eligible kabul edebiliyordu. PUBLIC_REPLY yalnız KNOWN PUBLIC
ile geçerli olacak; diğer görünürlükler MESSAGE_VISIBILITY_CONFLICT ile review.
INTERNAL_NOTE yalnız source author.kind=STAFF, declared author_kind=STAFF ve
KNOWN PRIVATE ile geçerli; mevcut PRIVATE_NOTE_EXPOSURE ret kodu korunacak.
Historical staff kimliği native admin/MFA/izin kazandırmaz.

Attachment doğrulaması aynı tam snapshot kararına bağlıdır; conflict bulunan
mesajın eki snapshot'ı eligible yapamaz. Geçerli private arşiv manifest'i de
client-visible import izni değildir; per-file public eligibility üretilmez.
Kaynak tür/görünürlük verisi yeniden yazılmayacak. UNKNOWN/MISSING/CONFLICTING/
UNVERIFIABLE durumları ayrı kalır; otomatik PUBLIC yoktur.

## Hedefli önce/sonra kanıtı

Aynı yeni8 regresyon eski kaynakta **8 test /4 FAIL /exit1**, düzeltilmiş kaynakta
**8 test /0 FAIL /exit0** verdi. [Eski FAIL çıktısı](evidence/f6-a-post-audit/before.txt),
[düzeltilmiş PASS çıktısı](evidence/f6-a-post-audit/after.txt).
Eski31 F6-A contract testi ve6 ayrı CI helper testi de hedefli olarak geçti.

| Karşı-örnek | Önce | Sonra |
|---|---|---|
| PUBLIC_REPLY + KNOWN PRIVATE |eligible=true|eligible=false; MESSAGE_VISIBILITY_CONFLICT|
| PUBLIC_REPLY + KNOWN PUBLIC |geçerli|geçerli; payload değişmez|
| INTERNAL_NOTE + KNOWN PRIVATE + source STAFF |geçerli|geçerli private arşiv; effects_allowed=false|
| INTERNAL_NOTE + KNOWN PUBLIC |PRIVATE_NOTE_EXPOSURE|aynı açık ret|
| INTERNAL_NOTE + dört belirsizlik durumu |ret|ret; PUBLIC'e dönüşüm yok|
| PUBLIC_REPLY + dört belirsizlik durumu |generic review|generic reason + MESSAGE_VISIBILITY_CONFLICT; payload değişmez|
| INTERNAL_NOTE claimed STAFF / source CUSTOMER |AUTHOR_REVIEW|PRIVATE_NOTE_EXPOSURE; source staff eşliği zorunlu|
| Tutarsız PUBLIC_REPLY'nin eki, normal/ters entity sırası |snapshot eligible=true|snapshot eligible=false; client-visible işareti yok|

Eski613 testin korunması ve son exact aday FAST sonucu ayrıca aşağıya kaydedilecek.
Bu hedefli kanıt tek başına FULL veya bağımsız teknik kabul değildir.

## Açık kabul sınırları

F6-A LIMITED MOCK teknik kabulü beklemede. G-O03, gerçek SUP-010/PROV-004,
DOMAIN-002 PARTIAL/MANUAL_REVIEW, mapping/import CRUD DEFERRED, PROV-005,
F5-C dört canlı istisnası, auto-close/merge/split/tarihsel SLA ve tüm canlı GATE'ler
açıktır. Gerçek WHMCS/provider/payment/SMTP/IMAP/dosya aktarımı yoktur.
PR3 OPEN/DRAFT; F6-B/staging/merge/production yoktur. Kanıtlar tamamlanınca DUR.
