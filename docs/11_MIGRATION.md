# 11 — WHMCS'den veri taşıma ve geri dönüş

## Hedef
<50 müşteri olsa da **tüm** geçmiş fatura/proforma/ödeme, ticket/mesaj/ek, domain, hosting/cloud hizmeti, müşteriler, kullanıcılar, bakiye ve kritik log ilişkileri korunur. Var olan cPanel/Voyar kaynakları **yeniden oluşturulmaz**.

## Veri kaynakları
1. Salt-okunur WHMCS DB erişimi veya şifreli SQL dump.
2. Resmi fatura PDF'leri, destek ekleri, template/credential harici referansları ve storage path map.
3. WHMCS API yalnız fark doğrulaması ve gerekli alanlar için; canlı cron/otomasyon sisteminden bağımsız.
4. Kullanılan WHMCS sürümünün gerçek şeması migration keşif fazında incelenir. Burada WHMCS tablo ismi veya parola hash uyumunu ezbere hardcode etme.

## Envanter ve eşlemeler
| Kaynak alanı | Hedef kayıt | Mutabakat ölçütü |
|---|---|---|
| WHMCS müşteri/personel | users, customers, customer_users | sayı + e-posta + ilişkiler |
| Müşteri bakiyesi / para birimi | wallet ledger, customers | para birimine göre toplam ve yükümlülük |
| Sipariş/hizmet | orders, services, service_resources | aktif/suspended/terminated sayısı; provider external id |
| Domain | domains, registrar refs | FQDN, provider, TLD, expiry, lock/transfer status |
| Fatura/proforma | payment_requests, lines, official_docs | belge no/tutar/para birimi/tarih ve dosya checksum |
| Ödemeler | payment_attempts/payments/allocations | tahsilat toplamı, provider ID, tarih, refund |
| Ticket | support_tickets/messages/attachments | sayım, açılma/kapanma, yazar, görünürlük, orijinal zaman ve ekler |
| Parolalar | password_hash + migration_verifier | yalnız destekli formatta rehash-on-login, değilse reset |

## Pipeline (idempotent)
- `extract`: immutable raw dump/checksum, kaynak sürüm, zaman ve row counts.
- `transform`: PII/encoding normalize, para+kur snapshot (eski kur bilinmiyorsa UYDURMA, `legacy_unknown`), status map ve provider ID.
- `load`: `migration_mappings(source_system, source_table, source_id, target_table, target_id)` UNIQUE, tekrar çalıştırılabilir upsert; finansal fişleri tarihi gerçek değerle; müşteri kapalı bilgilerini koru.
- `verify`: tüm kaynak/hedef sayım, para birimi bazlı finans mutabakatı, destek ek checksum, provider read-only inspect ve aktivasyon yasağı.
- `cutover`: kısa maintenance, eski WHMCS cron + provisioning + notifications durdur; ödenmekte olan işlemleri reconcile; son incremental import; tek aktif otomasyon.
- `archive`: WHMCS salt-okunur, ağ erişimi kısıtlı, eski webhook route uyumluluğu gözden geçirme; alternatif koruma arşiv export.

## Riskli detaylar
- **Parola:** WHMCS hash algoritması sürüme göre değişebilir; geçerli hash doğrulayıcı yazılmadan eski hash'i yeni sistemde kabul etme. Hash migration yalnız kullanıcı başarıyla login olduğunda ve auditli.
- **Support ekleri:** Path traversal ve gizli dosya alanları; eski dosya adlarını web dışına kopyala, hash, gerçek MIME verify, müşteri ilişkisine bağla.
- **Proforma vs resmî PDF:** tarihi numaraları/ekleri kaybolmaz; yeni sistemde başka muhasebe belgesi gibi yeniden yaratma.
- **Provider kaynakları:** WHM account / domain external registrar order / Voyar VM public id; read-only discover ile doğrulama; hiçbiri otomatik create/terminate edilmez.
- **Kısmi ödemeler:** migration payment allocations eksi kalmasın, historik belirsiz tahsilatı 'paid' diye varsayma; inceleme kaydı aç.
- **Aktif transfer/yenileme:** cutover sırasında provider'da in-flight iş varsa fence, kaynak tarafın bitmesini bekle/reconcile; çift etkin motor asla yok.

## Rollback planı
- Geri alma penceresi önceden belirlenir; yeni sistem üretim işlemi yaptıktan sonra yalnız 'eski DB backup'a dönmek' veri kaybı yaratır.
- Cutover sonrası yeni ödeme veya provisioning varsa WHMCS'ye geri dönüş ancak delta reverse-import ve sağlayıcı state reconciliation ile mümkündür.
- Özellikle PayTR callback/webhook yönlendirmesi, domain registrar ve Voyar renew task sahipliğini rollback listesine koy.
- Yayın kararı sadece finansal kayıt, kaynak eşleşmesi, ticket arşivi ve email callback smoke testi tutarlıysa verilir.

## Mutabakat raporu şablonu
`entity / source_count / target_count / unmatched_count / amount_by_currency / checksum_mismatch / owner_mismatch / approved_by / timestamp`.
- 0 açıklanamayan para farkı; 0 eksik aktif servis mapping; 0 eksik resmi PDF/ticket eki; 0 yetki/açık webhook çakışması yayın kapısı.
