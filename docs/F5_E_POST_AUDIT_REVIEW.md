# F5-E bağımsız denetim sonrası — client message metadata redaksiyonu

Durum: hedefli P1 düzeltmesi ve doğrulaması sürüyor; bağımsız kabul verilmedi.
Denetlenen uygulama:f226220847390ea3f5c69eef90ad968117d7edc7.
Başlangıç teslim HEAD:579788e66f448b5560e05c37b7a3ba9487923731.

## Kapsam / dosya / migration / test / geri dönüş planı

SUP-003/004 ve F5_E_PLAN client actor/session/internal metadata sözleşmesi.
Yalnız UI Views ve legacy Support.Service detail message projection'ı daraltılır;
client mesajlarından tüm author_id alanları kaldırılır. Admin/staff yetkili author
görünümü korunur. Güncel native Identity/ticket reply izni, mesaj sahipliği ve public
görünürlükten hesaplanan can_attach boolean'ı iki yüzeyde sağlanır. JS bu boolean'ı
kullanır; ham UUID karşılaştırması yapmaz. Bu alan bir UI eligibility hint'idir;
version/quota/retention/manifest ve native F5-C upload authorization yerine geçmez.

Dosyalar:src/Support/UI/Views.php, src/Support/Service.php,
public/assets/support.js; ek regresyon dosyası ve mevcut E/Chromium registry'sine
yalnız yeni test eklenmesi. Native Mail/Attachments, Access, Identity, Queue/Outbox,
SLA/Automation algoritmaları değişmez.001–054 byte korunur; migration gerekmez.

Önce yeni regresyonlar denetlenen kod üzerinde gerçek MariaDB/native HTTP ile FAIL
olarak çalıştırılır; ardından aynı assertions düzeltilmiş kaynakta PASS olmalıdır.
İki API JSON redaksiyonu, kendi public mesajı/staff public mesajı, private note,
admin projection, cross-customer denial ve current reply-grant kontrolü doğrulanır.
Upload pozitif/başkasının mesajı/ticket'ı, forged can_attach, CSRF/stale/replay/revoke
negatifleri native C handler üzerinden denenir. Gerçek Chromium kendi mesajında
upload kontrolünü gösterir, staff mesajında göstermez; JSON'da author_id olmadan
çalışır. Önceki579 PHP/9 browser/3 state adı ve assertions korunur; PHP8.3/8.4 tam
CI, PHPStan/Composer/npm audit,1/4/8 process ve10/30/20 tekrarları yeniden çalışır.

Geri dönüş: yalnız bu dar projection/UI kodu için reviewed forward fix veya tutarlı
kabul edilmiş snapshot restore; schema/data/history/audit silme yoktur. Eski kaynak
metadata açığını geri getirir; sıradan rollback bir güvenlik çözümü değildir.

## Açık sınırlar

F5-E teknik kabul bekler; DONE/FULL ACCEPTED/production-ready değildir. F4/F5-A/B/C/D
sınırlı mock kabulleri, C'nin dört canlı istisnası ve tüm önceki açık GATE'ler korunur.
PR3 OPEN/DRAFT; gerçek mail/AV/provider, F6, merge, staging veya production yoktur.
Gerçek SHA/tree/diff, eski FAIL/yeni PASS ve iki API karşılaştırması doğrulama sonrası
aşağıya eklenir; hedefli düzeltme tesliminden sonra bağımsız yeniden kabul istenir.

## Neden, karşı-örnek ve iki API yüzeyi

Her iki detail sorgusu mesaj sahipliğini kontrol edebilmek için author_id okuyordu;
decryption sonrası aynı row'un client JSON'a konması bu dahili alanı da yayıyordu.
UI ayrıca upload kontrolünü author UUID eşitliğiyle gösteriyordu. SQL public-only
filtre note body'yi saklasa da bu, public staff reply'ın kimlik metadata'sını redakte
etmiyordu. Bu alanın frontend'de gerekli olması bir client projection istisnası değildir.

| Yüzey | Eski client | Yeni client | Yetkili admin/staff |
|---|---|---|---|
| `/api/v1/client/support-ui/detail` (poll changed projection dahil) | Public own/staff mesajlarında author_id | Tüm message author_id alanları yok; can_attach boolean | author_id ve note izniyle note görünümü korunur |
| `/api/v1/client/support/detail` | Aynı ham author_id açıklığı | Aynı redaksiyon ve can_attach semantiği | Mevcut author_id/system-origin/note projection korunur |

can_attach = güncel native ticket.reply yetkisi AND mesajın public/visible olması
AND mesajın authenticated user'a ait olması. Private note için false. Bu hesap native
Identity kilidi/current ticket scope içinde yapılır; istemciden gelen boolean okunmaz.
Staff/admin için de aynı dar hint hesaplanır. Customer'ın kendi author UUID'si de
gereksiz olduğundan çıkarılır. `support.js` yalnız `m.can_attach===true` kullanır.

Native C upload dosyası değiştirilmedi: current ticket/customer/reply authorization,
actual DB author eşliği, public message, version, quota/retention/frozen manifest ve
idempotent replay/revoke kontrolleri yerindedir. Payload'a can_attach eklemek422;
başkasının mesajı/ticket'ı404; CSRF403; stale version409; revoked session401 verir.

## Yerel eski FAIL / yeni PASS

[Sanitized karşılaştırma](evidence/f5-e-post-audit/before-after.json): aynı üç yeni
regresyon, uygulama değişikliğinden önce gerçek MariaDB/native HTTP ile3 FAIL/exit1;
düzeltilmiş kaynakta3 PASS/exit0. İlk iki test ham author_id'nin iki JSON yüzeyinde
mevcut olduğunu yakaladı; üçüncü test mevcut olmayan can_attach alanını yakaladı.
Eski testte kullanılan sentetik user/session UUID'leri rapora kopyalanmadı.

Mevcut E suite eski24+new3=27/0; gerçek Chromium eski9+new2=11/0;
state3/0; PHPStan0,384 PHP lint0 ihlal,403 pattern scan0 bulgu.
Yeni browser kontrolleri iki API JSON'unu redaksiyon açısından okur ve author UUID
olmadan kendi public mesajında upload kontrolünün göründüğünü, staff public
mesajında görünmediğini doğrular. Önceki dokuz kontrolün adları/assertions korunur.
[Koruma manifesti](evidence/f5-e-post-audit/preservation.json): üç hedef runtime
dosyası dışındaki277 uygulama/bootstrap/public/migration blob'u byte-eşit;
001–054 değişmedi, yeni migration yok. Tam iki PHP CI doğrulaması ayrıca bekleniyor.
