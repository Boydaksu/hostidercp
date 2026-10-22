# 13 — Kabul testleri / yayın kapıları

## Tüm testler için ortak kontrol
- Unit, DB transaction/integration, API, contract mock, security, E2E, restore rehearsal.
- Her test aynı commit ve migration sürümünde otomatik çalışır; canlı testlerde maliyetli işlemler açık yetkilendirmeyle yapılır.
- Her Gherkin senaryosu belirli entity state/ledger balance/provider call count doğrular.

## P0 — Ödeme ve finans
1. **FIN-001 Duplicate PayTR:** Aynı geçerli callback 3 kez geldiğinde 1 payment, 1 allocation, 1 provisioning job, 3 idempotent yanıt.
2. **FIN-002 Bad signature:** HMAC yanlışsa ödeme kaydedilmez, hizmet kurulmaz, denetim olayı var.
3. **FIN-003 Partial:** 1000 TRY request, 400 wallet + 500 gateway -> partial, 0 provisioning; son 100 -> paid ve tam 1 job.
4. **FIN-004 Concurrent wallet:** İki paralel 800 TRY debit, mevcut 1000 TRY -> yalnız birisi başarılı; bakiye >= 0.
5. **FIN-005 FX quote:** 3 USD kaynak, TRY müşteri, 15 dk quote; süre dolunca yeniden fiyat; 14.dk payment request oluşturulursa daha sonra kur değişmez.
6. **FIN-006 Ledger balance:** her ödeme/refund/cüzdan transferi için debit/credit dengeli, double-cash yok.
7. **FIN-007 Refund:** orijinal gateway tahsilatı kısmen iade edildiğinde limit ve para birimi korunur, tekrar refund ikinci kez yapılmaz.
8. **FIN-008 Invoice PDF:** yüklenen resmi PDF sahibi dışındaki kullanıcı tarafından indirilemez; hash ve evrak metaverisi korunur.
9. **FIN-009 Currency change:** admin FX dönüşümü sonrası yalnız tek wallet currency; geçmiş paid belgeler aynı tutar/parada.
10. **FIN-010 Price publication:** maliyet artışı fiyat önerisi oluşturur ancak onay yokken müşteri fiyatı değişmez.

## P0 — Provisioning/Domain/Cloud
11. **PROV-001 Timeout create:** Voyar/cPanel oluşturma POST'u timeout -> UNKNOWN; otomatik ikinci create yok.
12. **PROV-002 Unknown reconciliation:** create sonucu bulunursa dış resource id bağlanır, ikinci kaynak yok; bulunmazsa manuel review.
13. **PROV-003 Manual approval:** ödeme tamam ama ürün manuel -> PROVIDER'e çağrı yapılmaz, yetkili onay sonrası yapılır.
14. **PROV-004 Imported account:** WHMCS'ten aktarılan cPanel/Voyar resource üstünde normal hizmet yönetimi var; yeni create yok.
15. **PROV-005 Cloud hourly:** aktif ondemand VM 2 saat sonra sonlandırılır; sağlayıcı tarafı gerçek ücretli saat ve sonlanma kanıtı kayıtlı; gözlemlenmeden pass yok.
16. **PROV-006 Failed renewal:** ödeme kaydedilmiş ancak extend başarısız -> aktif yenilendi gibi gösterme; ödeme korunur ve reconciliation.
17. **DOMAIN-001 Registrar ambiguous:** birincil timeout'ta alternatif registrar kaydı **gönderilmez**.
18. **DOMAIN-002 Definitive failure:** birincil kesin ve ücret/kaynak etkisiz başarısız; uyumlu alternatife policy ile tek defa denenir.
19. **DOMAIN-003 TLD capabilities:** unsupported EPP/DNS/transfer UI ve API'da doğru hata, müşteri yanıltılmaz.
20. **SVC-001 Transfer:** borcu olan service transfer edilemez; borç kapat, iki ayrı müşteri onayı, sahiplik geçmişi ve bildirim.
21. **SVC-002 Concurrent renewal+terminate:** aynı service üzerinde in-flight conflict kilidi; çift sağlayıcı action yok.
22. **SVC-003 Downgrade:** fiziksel disk küçültmesi desteklenmeyince 'anında yapıldı' denmez; güvenli politika uygulanır.

## P0 — Destek
23. **SUP-001 Unknown sender:** kayıtsız e-posta review queue, müşterinin private ticket'ına bağlanmaz.
24. **SUP-002 Duplicate Message-ID:** aynı mesaj iki kez poll -> 1 support message.
25. **SUP-003 Internal note:** client API, notification template, ekler ve ticket export'ta görünmez.
26. **SUP-004 ACL:** departman izni olmayan çalışan ticket konusunu, mesajını, ekini göremez.
27. **SUP-005 SLA:** cuma 17:30 + 2 mesai saati, hafta içi 9–18 takvim -> pazartesi 10:30, tatil yok; tatil eklenince doğru kayar.
28. **SUP-006 Round-robin concurrency:** paralel ticket ataması atomik ve yalnız uygun personeller.
29. **SUP-007 Conflict:** iki personel eski version ile ticket değiştirince birine 409, iki cevabın hiçbiri sessiz kaybolmaz.
30. **SUP-008 Automation loop:** kurallar birbirini tetiklese max depth ve dedupe çalışır.
31. **SUP-009 Incident privacy:** ana olaya bağlı müşteri A'nın private cevabı müşteri B'ye gösterilmez.
32. **SUP-010 WHMCS archive:** eski ticket mesaj sırası/ek checksum/dahili not yetkileri korunur.

## P0 — Güvenlik ve operasyon
33. **SEC-001 Admin TOTP:** MFA yoksa admin panel/privileged API açılmaz; token session abuse yok.
34. **SEC-002 Impersonation:** actor id auditli, kısa TTL, credential/2FA/transfer onayı engelli.
35. **SEC-003 API token:** expire/revoke/scope kontrolü ve basit enumerate attack engeli.
36. **SEC-004 Customer scope:** request path ID değiştirerek diğer müşterinin servisine erişilemez.
37. **OPS-001 Staging:** canlı PayTR / registrar / WHM / Voyar create ve gerçek e-posta dispatch test ortamından teknik olarak engellenir.
38. **OPS-002 Backup:** tam gece yedeği + şifrelenmiş dış kopya; restore testinde DB+ekler+official PDF doğru.
39. **OPS-003 Failed migration:** güncelleme ön koşulu başarısız -> sürüm açılmıyor; backup ve geri dönüş adımları uygulanabilir.
40. **OPS-004 Cutover:** kaynak/hedef müşteri/fatura/ticket/ek sayı ve tutarlar reconcile, WHMCS cron/provider mutation kapalı, yeni sistem tek aktör.
41. **OPS-005 Cron overlap:** iki worker aynı job'u çift provider operation'a dönüştürmez.

## Yayın onay formu
- [ ] Tüm P0 testler PASS (test raporu / commit / tarih)
- [ ] Güvenlik denetimi, kimlik bilgisi sızıntı kontrolü, yetki testleri
- [ ] Provider yetenek matrisi ve canlı ticari GATE kapandı
- [ ] Paralı/irreversible üretim işlemlerine manuel kontrollü smoke testi yapıldı
- [ ] Backups dış hedefte, son izole restore başarılı
- [ ] WHMCS veri mutabakatı: açıklanmamış finans farkı 0, aktif service mapping kaybı 0, support attachment checksum farkı 0
- [ ] Rollback/forward-fix sorumlusu, bakım penceresi, ödeme callback rotası belirlendi
- [ ] Kullanıcıdan üretim cutover için açık onay alındı
