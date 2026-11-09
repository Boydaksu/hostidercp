# ADR-017 — Support event kimliği ve şifreli arama sınırı

25 Ekim 2026. Kullanıcının F5-A P1-1/P1-2 talimatı. ADR-016'yı bu iki konuda daraltır.

Public support.changed payload = {event_uuid,ticket_id,message_id}; UUID producer'ın
aynı transaction'da yazdığı support_events.id / outbox_events.event_uuid'dir.
STATE/MOVE business payload'ları aynı olabilir; UUID eşitliği olmadan receipt verilmez.
Consumer published outbox, native outbox:<uuid> queue kaydı ve canonical payload'ı,
event kind/subject/customer/ticket/message bağlarını doğrular. Private note kabul edilmez.
Native Outbox.consume unique consumer/event_uuid mekanizması korunur; başka event'leri
toplu tüketme veya geçmiş receipt düzeltmesi yapılmaz. Handler yerel HELD receipt'tir.

Şifreli subject'i ilk 50 kayıt üzerinde filtrelemek doğru arama değildir. Açık yetki
ile F5-A q tamamen unsupported seçilir: authenticated list isteğinde q anahtarı
varsa değerinden bağımsız 422 SUPPORT_SEARCH_UNSUPPORTED. Plaintext indeks, body/note
arama veya kaynak bütçesini aşan decrypt taraması açılmaz. F5-E kapsam/yetki gerektirir.
q'suz list ORDER BY id, LIMIT 50, bounded offset ve native ACL-before-pagination kalır.

Bu karar ticari politika veya canlı kabul değiştirmez; yeni migration gerektirmez.
