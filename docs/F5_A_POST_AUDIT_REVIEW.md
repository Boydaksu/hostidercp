# F5-A — Bağımsız denetim sonrası hedefli düzeltme

25 Ekim 2026. Dal codex/f5-support-center. Taban HEAD
5389825e93af183f79753e8f00ed6025939909dc; denetlenen uygulama a38e8da.
**Yalnız test/mock; bağımsız yeniden kabul bekler. F5-A DONE değildir.**

## İki P1 ve karşı-örnek

P1-1: önceki EventSink ticket_id/message_id eşliğindeki bütün outbox satırlarını
dolaşıyordu. Aynı ticket'ın RESOLVED ve CLOSED STATE olayları ayrı UUID taşımasına
rağmen ikisinin business payload'ı `{ticket_id,message_id:null}` idi. İlk işi
tüketmek yayımlanmamış ikinci event için de support.held.v1 receipt yaratıyordu.

P1-2: ACL filtresi pagination öncesindeydi fakat q filtresi LIMIT 50 sonrasında
uygulanıyordu. İlk 50 yetkili konu eşleşmezken 51. eşleşen konu yanlış boş sonuç
veriyordu. Konular encrypted olduğu için yeni plaintext indeks veya bütçesiz
decrypt taraması eklenmedi; kullanıcı talimatındaki unsupported seçeneği uygulandı.

## Kaynak diff'i ve sözleşme

| Dosya | Hedefli değişiklik |
|---|---|
| src/Support/Service.php | Public payload'a kendi immutable event_uuid'si; q anahtarı varsa açık 422; kısmi subject filtresi kaldırıldı |
| src/Support/EventSink.php | Tek event lookup; event/ticket/customer/message/subject/type, published outbox ve native outbox:<uuid> job payload/fingerprint bağlaması |
| tests/support-post-audit.php | 5 yeni gerçek MariaDB/native Queue ve HTTP regresyonu |
| tests/support-http.php | Aynı 30 test korunur; q unsupported assertion'ları ve native dispatch ile yeni payload sözleşmesi |
| tests/support-http.php test girişi | Yeni 5 test mevcut suite'e dahil edilir; CI workflow byte olarak değişmez |
| F5_A_POST_AUDIT_PLAN, ADR-017, bu rapor ve evidence/f5-a-post-audit | Plan, karar, eski/yeni kanıt ve teslim |

Native Core Outbox/Queue, Identity, finance/domain/cloud/provider kaynakları ve
001–049 migration dosyaları değişmez. Yeni migration yoktur. Mevcut immutable
message/event CHECK ve trigger'ları, permission/MFA/freshness ve 1/4/8 süreç testleri korunur.

Public payload tam olarak `{event_uuid,ticket_id,message_id}`. Producer aynı DB
transaction'ında support_events.id, outbox_events.event_uuid ve payload UUID'yi eşler.
STATE/MOVE business payload'ı aynı olsa da tam payload UUID nedeniyle farklıdır.
Consumer o UUID'ye ait **yayımlanmış** outbox'ı ve native `outbox:<uuid>` job'unu
zorunlu tutar; canonical outbox/job payload ve job fingerprint birlikte eşleşir.
Message'lı event kind/subject/customer/ticket/visible/type bağı da doğrulanır.
STATE/MOVE yalnız null message ve ticket subject kabul eder. Private note reddedilir.

Receipt native Outbox.consume'un unique (consumer,event_uuid) transaction'ındadır.
İlk insert callback'inde bütün bağlar locking read ile yeniden doğrulanır; uyuşmazsa
receipt rollback olur. Duplicate replay yeni receipt yaratmaz; farklı UUID'lere
yayılma veya toplu consume yoktur. Bu yalnız HELD receipt'tir; mail/notification yoktur.

## Arama ve pagination

Native authentication/impersonation kontrolünden sonra list'te q anahtarı varsa
(boş string dahil) HTTP **422 / SUPPORT_SEARCH_UNSUPPORTED**, data=null döner.
Bu hata müşteri, yetkili personel ve query değerleri için aynı içeriksiz sözleşmedir.
Şifreli subject araması F5-E'ye açıkça ertelendi; body/internal_note indeksi yoktur.
q'suz list native ACL filtresini LIMIT/OFFSET öncesinde uygular, her satırı yeniden
yetkilendirir, ORDER BY id / LIMIT 50 / offset 0..1000000 kullanır.
Sabit veri kümesinde sayfalar kayıpsız ve tekrarsızdır. Eşzamanlı insert/move için
snapshot/cursor garantisi bu alt fazın kabulü değildir.

## Eski kaynakta FAIL, düzeltilmiş kaynakta PASS

[Önce](evidence/f5-a-post-audit/before.txt): aynı 5 test, **4 FAIL / 1 PASS**.
İlk STATE sonrası ikinci receipt için 0 beklenirken 1; UUID alanı eksik; unpublished
payload FAILED yerine SUCCEEDED; q unsupported 422 yerine 200. q'suz pagination
kontrolü eski kaynakta da PASS idi; bu sonuç kusur diye sunulmadı.

[Sonra](evidence/f5-a-post-audit/after.txt): **5 PASS / 0 hata**.

1. CREATE receipt tamamlanır; RESOLVED/CLOSED UUID'leri ayrı, business payload'ları aynı.
   İlk dispatch/consume yalnız ilk STATE receipt'i üretir; ikinci unpublished/receipt yok.
   İkinci dispatch ayrı receipt üretir; replay tekillik korunur. Finans/mesaj/notification hash'i aynı.
2. Forged UUID, başka ticket/message, private note event/message ve extra field reddedilir.
3. Unpublished event, eski iki alanlı payload ve native job payload uyuşmazlığı receipt üretmez.
4. 50 nonmatching + ID sırasındaki 51. matching konu ile müşteri/personel q istekleri
   aynı açık unsupported hata verir; foreign customer/department/note içeriği görünmez.
5. Aynı 51 yetkili ID müşteri/personelde 0/50/100 sayfaları boyunca tam bir kez görünür;
   yabancı kayıt ve private note listede yoktur.

Eski 428 test isimleri ve güvenlik beklentileri korunur. İki q kullanan eski test artık
yeni açık hata sözleşmesini de doğrular; yetkisiz normal liste ve private projection
assertion'ları korunmuştur. Queue testi artık producer'dan UUID'li payload alıp gerçek
native dispatch üzerinden gider. Test silme/gevşetme/skip/ignore yoktur.

## Doğrulama durumu

Hedef toplam 51 suite / 433 test (Support HTTP 30→35). Yerel PHP8.4.24/MariaDB10.11.18 beş yeni test PASS;
tam regresyon ve PHP8.3/8.4 CI sürüyor. Gerçek son kaynak SHA ve CI sonuçları teslim
kanıtı tamamlandığında bu bölüme bağlanır; henüz tüm CI için PASS iddia edilmez.

## Migration, geri dönüş ve açık sınırlar

Yeni migration yok; 001–049 byte/hash korunur. Geri dönüş tutarlı code/DB/key snapshot
veya forward-fix'tir; geçmiş receipt/mesaj/audit silinmez. Eski iki alanlı support.changed
test/mock işleri fail-closed kalır; otomatik yeniden yazma/backfill yapılmaz. Eski hatalı
held receipt fiziksel teslim kanıtı değildir. Mevcut test verisi gerekiyorsa izole
yeniden kurulum veya ayrı incelenmiş düzeltme gerekir; bu paket geçmiş receipt onarımı değildir.

SUP-003/004/007 yalnız F5-A çekirdek sınırında; diğer SUP maddeleri OPEN kalır.
F4 sınırlı mock kabulü değişmez; DOMAIN-002 PARTIAL, mapping/import CRUD DEFERRED,
PROV-005 ve tüm canlı GATE'ler OPEN. F5-B/C/D/E, gerçek SMTP/IMAP/notification/provider
dispatch, F6, merge, staging ve production yoktur. Düzeltme sonunda durulur ve bağımsız
yeniden kabul istenir.
