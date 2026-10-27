# F6-A P1 — message kind / visibility denetim düzeltmesi

27 Ekim 2026. Başlangıç HEAD89adde017fe14a750eb22c43abf4b71e7bc15a38;
denetlenen uygulama8340be6c5a12e551587ab92e72da930a18a7894d.
Durum: hedefli P1 teknik düzeltme teslimi; **bağımsız yeniden kabul bekler**.
FAST PHP8.3/8.4:621/621 PASS. F6-A ACCEPTED veya FULL DONE değildir.

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
Somut kaynak geri dönüşü063e846 commit'ini revert etmektir; önceki P1 davranışı
geri geleceğinden bu güvenlik kabulü sağlamaz. Veri veya migration geri dönüşü gerekmez.

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
Tekrar üretim: izole8340be6 checkout'una yalnız yeni `tests/migration-visibility.php`
dosyasını ekleyip `php tests/migration-visibility.php` çalıştırılır (beklenen exit1,
4 FAIL).063e846 checkout'unda aynı komut exit0/8 PASS verir. Harness ve sentetik
fixture iki kaynakta byte eşidir; gerçek veri/bağlantı veya eski test değişikliği gerekmez.

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

Eski613 testin korunması ve son exact aday FAST kanıtı aşağıdadır.
Bu hedefli teslim FULL veya bağımsız teknik kabul değildir.

## Kaynak ve koruma kaydı

Uygulama adayı `063e846999e4bda513f3563de0c4f13ac8858b97`;
tree `fb238efc3aa70cbbd022652d192d9e89c089f211`.
[FAST40470409239](https://github.com/Boydaksu/hostidercp/actions/runs/40470409239)
push ile tamamlandı: **SUCCESS**, broad profil. İki sürümde54 suite/621 test/0 hata;
eski613 PASS adının tamamı eşleşti, yeni8 regresyon gerçekten çalıştı.
FULL için exact aday aynı SHA'dır; FULL dispatch yapılmadı.

Denetlenen8340be6 ve başlangıç89adde0 validator blob'u aynıdır:
`366386308db04f976d7171e443169232cd624d5a`. Yeni8-test dosyasının blob'u
`167575a3ac13d3c3df35efb342e786ff317fa589`; önce/sonra aynı regresyon kullanıldı.
Yeni suite mevcut53 suite'in sonuna54. olarak hem envantere hem FULL listesine
eklendi. Eski613 testi barındıran109 test/fixture dosyasında değişiklik yoktur.
[Koruma manifesti](evidence/f6-a-post-audit/preservation.json):54 migration'ın
SHA-256 listesi ve11 native dizin tree eşliği korunmuştur; native F5-E müşteri
API'leri/attachment authorization ve Identity/Finance/Payments/Core değişmedi.
Migration tree `c0729c92dd143c017fdec32421bf67fe6b51e05b` aynıdır.

Yerel PHP8.4.24: yeni8/8, eski contract31/31, ayrı CI helper6/6 PASS;
PHPStan0 hata,403 PHP lint/mimari0 ihlal,425 pattern0 bulgu. Tam yerel DB/HTTP
paketi veya Chromium/10-30-20 tekrarları bu hedefli düzeltmede çalıştırılmadı.
Eski613+8 testin exact kaynak kanıtı iki gerçek FAST job'udur.

## Gerçek FAST ve teslim kanıtı

| Kontrol | PHP8.3.36 | PHP8.4.27 |
|---|---|---|
| Gerçek job | [122075976024](https://github.com/Boydaksu/hostidercp/actions/runs/40470409239/job/122075976024) | [122075976111](https://github.com/Boydaksu/hostidercp/actions/runs/40470409239/job/122075976111) |
| Application suites / tests / failures |54/621/0|54/621/0|
| Eski613 adından eksik / eski53 suite sayım farkı |0/0|0/0|
| Yeni migration-visibility suite |8/8 PASS|8/8 PASS|
| PHPStan / Composer locked audit |0 hata / temiz|0 hata / temiz|
| PHP lint/mimari / pattern |403/0 ihlal;425/0 bulgu|403/0 ihlal;425/0 bulgu|
| JS syntax / state / npm lock audit |PASS;3/3;0 vulnerability|PASS;3/3;0 vulnerability|
| Suite öncesi/sonu exact source guard |PASS|PASS|

Scope/security122075911020 ve FAST result122081029245 SUCCESS;6 CI helper testi
application621 sayısından ayrıdır. Yeni testin `FAST_SUITE tests/migration-visibility.php`
altında8/8 çalıştığı job günlüklerinde doğrulandı. [CI özeti](evidence/f6-a-post-audit/ci.json),
[8.3 test/koruma matrisi](evidence/f6-a-post-audit/fast-83.json),
[8.4 test/koruma matrisi](evidence/f6-a-post-audit/fast-84.json),
[yerel hedefli sonuç](evidence/f6-a-post-audit/local.json) ve
[yeni test/source-line indeksi](evidence/f6-a-post-audit/test-index.json).
Ham günlükler depoya alınmadı; yalnız allowlisted ad/sayım/kalite sonuçları ve linkler saklandı.

**FULL ACCEPTANCE exact uygulama adayı: `063e846999e4bda513f3563de0c4f13ac8858b97`.**
Tree: `fb238efc3aa70cbbd022652d192d9e89c089f211`.
Bu adayda FULL/Chromium/10-30-20 tekrarları çalıştırılmadı; FULL listesi korunur ve
yeni suite eklenmiştir. Belge/kanıt kapanışı uygulama/test/CI/migration kaynaklarını
değiştirmez. FULL, bağımsız kabul veya release izni olarak raporlanmaz.

## Açık kabul sınırları

F6-A LIMITED MOCK teknik kabulü beklemede. G-O03, gerçek SUP-010/PROV-004,
DOMAIN-002 PARTIAL/MANUAL_REVIEW, mapping/import CRUD DEFERRED, PROV-005,
F5-C dört canlı istisnası, auto-close/merge/split/tarihsel SLA ve tüm canlı GATE'ler
açıktır. Gerçek WHMCS/provider/payment/SMTP/IMAP/dosya aktarımı yoktur.
PR3 OPEN/DRAFT; F6-B/staging/merge/production yoktur. Kanıtlar tamamlanınca DUR.
