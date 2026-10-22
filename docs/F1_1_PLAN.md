# F1.1 security closure planı

Başlangıç main 5f4d50b; incelenen kaynak bd92174. Kapsam FR-ID, R4-01..07, SEC-001/002/004 ve D5 kritik yönetim onayı. F2 ve canlı etkiler kapsam dışı.

1. IdentityService MFA challenge'ını pending oturuma sınırla; login/enrollment activation step-up zamanı üretmesin. Explicit password+factor step-up dışında verified_at yenilenmesin. Fixtures gerekli explicit step-up'ı kullansın; eski 84 testin adları ve işlevsel beklentileri korunsun.
2. assignRole/customer/grant eşitliği ve actor delegasyon scope'unu authoritative DB modeliyle denetle. Negatif regresyonları ekle ve ilk anlamlı grubu gönder.
3. Yeni 004 migration ile kısa ömürlü, aktör/session/işlem/payload/hedef durumuna bağlı tek kullanımlık confirmation kaydı; server-side preview/explicit accept/execute, aynı transaction'da tüketim+audit+mutation. Panelde ayrıntılı etki ve açık onay. İkinci kişi yok. Eski migration'lara dokunma.
4. Önceki tüm suites + yeni servis/HTTP/confirmation concurrency negatifleri; PHPStan, Composer locked audit, PHP8.3/8.4 MariaDB CI. F1_1_REPORT ve gerçek kanıtlar; incelemeye sun ve dur.

Rollback: trafik/cron durdur, güncelleme öncesi doğrulanmış dosya+DB+APP_KEY snapshot birlikte restore veya forward-fix. Otomatik destructive down yok. 004 mevcut kullanıcı/rol/müşteri verisini dönüştürmez. Güvenlik düzeltmesini yalnız kod rollback ile kaldırma; eski confirmation kayıtları kullanılamaz sayılır.
