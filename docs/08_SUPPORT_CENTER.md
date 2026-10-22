# 08 — Destek Merkezi (öncelikli V1 bileşeni)

## Amaç ve kapsam
2–5 Hostider personeli için gelişmiş ticket, kurallı SLA, iki yönlü mail, departman, otomatik atama, incident, CSAT ve gerçek zamanlıya yakın operatör ekranı. Basit e-posta listesi değildir; müşterinin ilgili hizmet, fatura ve otomasyon geçmişine yetkili erişim sağlar.

## Departman / yetki
- Departman şablonu Teknik Destek, Satış, Muhasebe; admin sınırsız özel departman/alan oluşturabilir.
- Departmana göre mailbox, çalışma takvimi, öncelik, SLA politikası, assignment strategy, cevap şablonu, auto-close/reopen.
- Yetkiler ayrı: `support.ticket.view`, `.reply`, `.manage`, `.assign`, `.internal_note`, `.merge`, `.sla.override`, `.incident.manage`, `.mail_review`, `.report.view`.
- Rol + departman üyeliği + müşteri kaydı düzeyi. Personelin görme izni yoksa arama sonuçlarında metadata bile gösterilmez.

## Ticket çekirdeği
- Dış ticket no opaque `HD-...`; otomatik oluşturma; konu, müşteri, requester, hizmet bağlantıları, etkili öncelik, başlangıç kanalı, durum, versiyon.
- Durum sabit kategorileri: NEW, WAITING_STAFF, WAITING_CUSTOMER, ON_HOLD, RESOLVED, CLOSED; özel durumlar bu kategorilerden birine bağlı.
- Müşteri öncelik ister, rule engine & staff override edebilir; kaynak ve değişim nedeni kayda geçer.
- Mesaj tipleri customer_reply, staff_reply, internal_note; internal_note `customer_visible=false` ile sunucu tarafında sabit. Sistem olayları başka tabloda.
- Mesaj ekleri webroot dışında; upload MIME/magic ve boyut/tür, antivirus varsa tarama; yetkili indirme token'ı, expiry.
- Gönderilmiş cevap sessiz edit edilemez; yeni düzeltme veya düzenlemenin auditli revision'ı.
- Ticket merge: eski public ID alias/redirect, mesaj yazarları+zamanı+ek ilişkileri korunur; SLA hesapları kaybolmaz.
- Split: personel hangi mesajların yeni ticket'a taşındığını seçer, köken/bağlantı kalıcı; yanlış müşteri verisi taşınamaz.

## SLA motoru
- Takvim: IANA timezone, weekday shifts, official/local holidays, istisna vardiya, yarım gün, geceye taşan saat.
- Metrikler: ilk **gerçek personel yanıtı**, her müşteri yanıtını takip eden personel yanıtı, çözüm süresi. Otomatik acknowledge ve internal note başarı sayılmaz.
- Pause rules: on_hold veya waiting_customer çözüm saatini ancak departman politikasına göre durdurabilir; müşteri yeni yanıt verdiğinde next response clock yeniden işler.
- Uyarılar örneğin %80 ve %100; politika değerleri UI'dan değişir, geçmiş SLA hesapları değişmez; immutable policy_version snapshot.
- Takvim algoritması: UTC olayı lokal çalışma intervallerine map et, tatil istisnalarını uygula, aradaki aktif dakika birikimini hesapla, deadline'a kadar interval ilerlet. DST/timezone test et.

## Atama
- Manuel, round-robin ve düşük ağırlıklı açık iş yükü departman bazında ayrı.
- Uygunluk: aktif kullanıcı, departman üyeliği, vardiyada olma, izin durumu, ticket priority yetkisi; eşzamanlı atamada transaction + row lock.
- İş yükü sayımı düz ticket sayısı değil, configurable weights (normal=1, high=2, critical=4 örnek).
- Uygun çalışan yoksa departmental unassigned queue + on-call notification; ticket kaybolmaz.
- Birden fazla personel aynı ticket görüntülüyorsa presence; biri cevap yazarken diğerinde uyarı; optimistic version ve message idempotency.

## Gelen/giden posta
- Inbound IMAP veya cPanel piping; service adapter ile iki yöntemden biri yeterli, aynı mail tekrar işlenmez.
- `Message-ID` + `In-Reply-To` + `References` + imzalı reply token + gönderen/customer/ticket access doğrulaması; yalnız konu satırı numarası güvenilmez.
- Kayıtlı doğrulanmış müşteri adresi otomatik yeni/reply. Bilinmeyen adres `review` (asla rastgele müşterinin ticket'ına bağlanmaz); loop/bounce/autoresponder engelle.
- Mail eklerinde path traversal / zip bombs / aktif içerik / HTML sanitisasyonu. Inbound raw e-postayı sınırlı süre ve şifreli alanda koru, erişim yalnız yetkili.
- Outbound outbox + SMTP; retry, dedupe, delivery status. SMTP accepted ≠ kesin teslim edildi.
- Dağıtım kanal kısıtı: müşteri paneli + e-posta. WhatsApp ve canlı chat V1 zorunlu değil.

## Kural motoru
- Olaylar: ticket_created, customer_replied, staff_replied, state_changed, assigned, SLA_risk, incident_updated.
- Koşullar: department, source, priority, tag, local business hours, customer group, service type/status, assigned user, SLA.
- Aksiyonlar: assign, set status/priority, add tag, internal note template, staff notification, incident link; **servis silme/para tahsili** support rule engine tarafından doğrudan yapılmaz.
- Kurallar JSON AST (allowlisted predicates/actions), önce simulation, priority order, trigger chain max depth, dedupe ve execution audit.
- İptal edilen/kapalı ticket'larda sakıncalı aksiyon varsayılan no-op; admin görebilir.

## Incident
- `support_incidents` merkezi arıza, etkilenen sağlayıcı/kaynaklar, durum, public updates, private root cause.
- N ticket ile ilişki; tek olay açıklaması ilgili ticket'larda görünür, müşteri arasında özel destek içeriği paylaşılmaz.
- Incident solved olmak ticket'ların tümünü otomatik kapatmaz; SLA ve gerçek staff response sayımları ayrı.

## CSAT ve rapor
- RESOLVED sonrasında 1–5 puan + opsiyonel yorum; kimlik ve bilet erişimi şart, puan güncelleme penceresi/config.
- Raporlar: ilk yanıt, sonraki yanıt, çözüm, SLA breaches, personel work hours, backlog, ticket type, CSAT; departman/rol erişimiyle.

## Operatör ekranı
- Sol: kayıtlı filtreler, departman ve arama. Orta: konuşma, reply/internal switch, ekler, timeline. Sağ: müşteri, açık faturalar (yetkiye bağlı), hizmetler, aktif sağlayıcı durumu, SLA sayaçları, personel ve incident.
- Fetch/delta polling; ticket.version optimistic lock, autosave drafts per user, keyboard shortcuts, avatar/presence approximate; kayıp veri/çift yanıt önleme.
- Müşteride yalnız kendi müşteri hesabına yetkili ticket'lar, ekler, incident public updates, mesajlar, CSAT.

## Release blocker testleri
- Bilinmeyen gönderen müşteri ticket'ına bağlanmıyor.
- Aynı inbound e-posta iki defa tek mesaja dönüşüyor.
- Dahili not e-posta gönderimini tetiklemiyor, müşteri API ile göremiyor.
- Başka müşteri ekine erişemiyor.
- İki personel eşzamanlı reply/assign update kayıpsız.
- SLA çalışma takvimi, resmi tatil, timezone/DST ve policy changes doğru.
- Kural döngüsü önleniyor, birleştir/split mesaj-sahiplik tutarlı.
