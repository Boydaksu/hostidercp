# F2 → F3 güvenli devir sözleşmesi

Bu bir F2 kapanış sözleşmesidir; F3 provider handler, claim servisi veya hizmet kurulumu uygulanmadı. Kullanıcı F3 kabulü olmadan dispatch yoktur.

## Kaynağın yeniden keşfi

Queue `SUCCEEDED` yalnız yerel fact tüketimidir; hazır hizmetin yapıldığı anlamına gelmez. F3 kaynak gerçeği olarak **orders + billing_request_states + immutable order_items snapshot** kullanacak. `commerce_event_receipts` iz/uyarıdır, tek discovery kaynağı değildir. Outbox/inbox tüketilmiş veya queue payload'ı retention ile kaldırılmış olsa da canonical sipariş mevcuttur. Periyodik anti-join taraması, hizmet/operation claim'i olmayan uygun siparişleri bulacaktır; geçmiş queue kör replay yapılmaz.

021 migration bütün eski siparişleri `LEGACY_UNKNOWN` olarak karantinaya alır. Sonraki Commerce siparişinde origin ortamı Config'den server tarafında atanır; UPDATE trigger'ı kökenin değiştirilmesini reddeder. Request alanından köken alınmaz. Test/staging/legacy siparişler production aday listesine girmez; geriye dönük otomatik production backfill yoktur. Gerçek eski sipariş varsa ayrı kullanıcı onaylı kanıtlı import/transfer süreci gerekir. DB restore/import kaynağının güvenilirliğini hiçbir alan tek başına kanıtlayamaz; production DB yazma yetkisi ve migration rolü ayrıca korunur.

Örnek aday sorgu (gelecek F3 claim tabloları henüz yoktur): `orders WHERE origin_environment='production' AND status='READY_FOR_PROVISIONING'`. `ix_handoff_candidate` bu taramayı destekler. Her aday transaction içinde order→request→items kilitlenerek tekrar değerlendirilir; customer/bill/order bağları, PAID durumu, refund NONE ve bütün activation_policy AUTO şarttır. Manuel siparişler REQUIRES_APPROVAL kalır. `HandoffPolicy::evaluate()` salt okunur fail-closed sözleşmeyi test eder; dispatch her sonuçta false'tur. Eski snapshot içinde belirsiz politika otomatik kabul edilmez.

## F3 uygulamasının zorunlu atomik claim sınırı

F3'te yeni migration ile `(order_item_id, operation_kind, generation)` UNIQUE kalıcı operation ve item/service bağlantısı kurulacak. Aynı transaction'da order/request tekrar doğrulanacak, immutable kaynak snapshot hash'i + customer + provider hedefi + capability/gate sürümü + actor/trace kaydedilecek ve transactional outbox üretilecek. İki worker unique claim'i aşamayacak; ikinci worker mevcut operation'u döndürecek. Çok ürünlü siparişte her item bağımsız operation'dur; order başına tek hizmet varsayılmayacak. Imported service ile eşleşen item için create yasaklanacak.

Provider POST transaction dışında yapılacak. Aynı operation yeniden istenirse request fingerprint uyumu zorunlu olacak; farklı payload aynı anahtarı kullanamayacak. Lease fencing stale worker'ın sonradan başarı yazmasını engelleyecek. Dış sağlayıcıda idempotency garantisi doğrulanmadıkça yeni create kör tekrarlanmayacak.

UNKNOWN/timeout durumunda operation UNKNOWN kalacak; ödeme veya sipariş geri açılmayacak. Yeni append-only reconciliation case/evidence gerekecek. Sağlayıcı inspect/status ve external reference ile sonuç kanıtlanana kadar ikinci create, failover ve otomatik retry yoktur. Doğrulanmış sonuç yeni operation event'iyle kaydedilir; geçmiş attempt silinmez. F2 finans mutabakat servisi sağlayıcı UNKNOWN'unu sonuçlandırmak için kullanılamaz.

## F3 giriş ve yayın koşulları

F3 geliştirmesi ancak kullanıcı kabulüyle başlayabilir. Mock claim/iki worker/lease/UNKNOWN/manual/import/refund ve queue yokken discovery testleri F3 kabulünde zorunludur; bu belgede çalışan claim varmış gibi sunulmaz. Gerçek cPanel G-C01 ve operasyon gate'leri kapanmadan canlı create yoktur. Test/mock senaryosunun başarılı olması bu GATE'leri kapatmaz. F2 post-delivery testleri yalnız eligibility/provenance ve mevcut queue lease güvenliğini kanıtlar.
