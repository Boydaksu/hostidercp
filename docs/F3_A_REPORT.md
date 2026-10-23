# F3-A hizmet modeli — test/mock teslim notu

FR-SERVICE, PROV-003/004, SEC-004 temeli. 023 service/config/price/period/ownership/event, 024 immutable resource/period evidence ekler; eski 001–022 değişmez. State enum geçişleri ayrı, paid canonical item/quote snapshot doğrulanır, quantity unit başına unique hizmet ve tam minor toplam dağılımı vardır. Kaynak konfigürasyon AEAD + HMAC; price/ownership/event/resource geçmişi append-only. Verified period tarih/binding guard'ı yeni trigger'dır.

4 gerçek MariaDB testinde state retleri, üç unit/replay/snapshot/fiyat toplamı, unpaid/cancel/refund/forged/legacy kaynak retleri, manual approval, scope ve rollback doğrulandı. PHPStan level 5 başarılı. Kanıt `evidence/f3/a-model.txt`; CI suite'e eklendi. İlk testte PHP array key sırası nedeniyle assertion düzeltildi: snapshot içeriği canonical JSON olarak karşılaştırılır, veri bütünlüğü beklentisi gevşetilmedi.

Henüz provider çağrısı veya claim worker yok; faz A veri modelidir. G-C01/G-O01/G-T01 ve production gate kapalı, canlı işlem yok. Geri dönüş kod+DB+APP_KEY tutarlı snapshot veya forward-fix, destructive down yok. F3-B claim/operation motoruna bu testler sonrası geçilir; F3 tamamlandı denmez.
