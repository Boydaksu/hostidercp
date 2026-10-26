# F5-E bağımsız denetim sonrası — client message metadata redaksiyonu

Durum: hedefli P1 teknik düzeltme teslimi; bağımsız yeniden kabul bekleniyor.
Denetlenen uygulama:f226220847390ea3f5c69eef90ad968117d7edc7.
Başlangıç teslim HEAD:579788e66f448b5560e05c37b7a3ba9487923731.
Düzeltme uygulama SHA:`e0d460d0aea05f496135d169ecbb64ce94659cc9`;
tree:`412bb36ab783ede0c7076646b244f92b894fe562`.
[Gerçek kaynak diff'i](https://github.com/Boydaksu/hostidercp/compare/579788e66f448b5560e05c37b7a3ba9487923731...e0d460d0aea05f496135d169ecbb64ce94659cc9).

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
Gerçek SHA/tree/diff, eski FAIL/yeni PASS ve iki API karşılaştırması aşağıdadır.
Hedefli düzeltme tesliminden sonra bağımsız yeniden kabul istenir ve durulur.

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
001–054 değişmedi, yeni migration yok. İki PHP tam CI işi de SUCCESS tamamlandı.

Kaynak karşılaştırma noktaları: `src/Support/UI/Views.php:24` current capability ve
client unset; `src/Support/Service.php:173` current reply-grant hesabı,
`:175` legacy client unset; `public/assets/support.js:26` strict boolean kullanımı.
Değişmemiş otorite `src/Support/Mail/Attachments.php:26` strict input allowlist ve
`:28` native ticket/version/actual-author denetimidir. Yeni HTTP testleri
`tests/support-e-post-audit.php`; yeni DOM testleri `tests/support-e-browser.cjs`
içinde eski dokuz testin yanında ayrı PASS adlarıyla kaydedilir.

## Gerçek tam CI ve korunan taban

[GitHub CI SUCCESS](https://github.com/Boydaksu/hostidercp/actions/runs/40367106726),
[sanitized CI kanıtı](evidence/f5-e-post-audit/ci.json).

| PHP/job | Suite / PHP test / hata | Eski579 kayıp | Chromium | State | Tekrar |
|---|---|---|---|---|---|
| [8.3.36](https://github.com/Boydaksu/hostidercp/actions/runs/40367106726/job/121727667707) |52 /582 /0|0|11 /0; eski9 korundu|3 /0|Services10 / Commerce30 / Session20:0 hata|
| [8.4.27](https://github.com/Boydaksu/hostidercp/actions/runs/40367106726/job/121727667054) |52 /582 /0|0|11 /0; eski9 korundu|3 /0|Services10 / Commerce30 / Session20:0 hata|

Her iki sürümde eski579 PHP PASS adı eksiksiz, yeni3 PHP regresyonu PASS;
eski9 Chromium ve3 state adı eksiksiz, yeni2 Chromium kontrolü PASS.
Native1/4/8 bağımsız PHP worker/send yarışları ve10/30/20 HTTP/session tekrarları
yeniden çalıştı. PHPStan0; locked Composer ve npm audit temiz;384 PHP lint0 ihlal;
403 pattern scan0 bulgu. Assertion silme/gevşetme, test skip/ignore yoktur.

CI checkout `736fb0b021713ca4ba91dc3931824a635e6bd742` GitHub tree'si
`412bb36ab783ede0c7076646b244f92b894fe562` ile uygulama tree'sine eşittir.
Bu bir test checkout'udur; gerçek merge değildir. Ham job-log API linkleri
`ci.json` içindedir. Credential içerebilen tam CI günlükleri repository'ye yazılmadı;
PASS adları, sayımlar, adım sonuçları ve kaynak eşliği ayrıca kaydedildi.

Yerel PHP8.4.24/MariaDB10.11.18 üzerinde eski3 FAIL→yeni3 PASS, E27/0,
Chromium11/0 ve state3/0 gözlendi. Yalnız görevde oluşturulan test kök şeması
temizlendi ve görevde başlatılan yerel DB kapatıldı; önceki şemalar silinmedi.
Final dokümantasyon commit'i uygulama/test/migration kodunu değiştirmez.

Bu sonuç bağımsız kabul kararı değildir. F5-E LIMITED MOCK teknik yeniden kabulü
proje sahibinin bağımsız denetimini bekler. PR3 OPEN/DRAFT; canlı GATE'ler OPEN.
Yeni geliştirme, F6, gerçek mail/provider, merge, staging ve production başlatılmadı.
