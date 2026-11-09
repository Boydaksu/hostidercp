# F6-E-P0 — offline/sentetik cutover preflight planı

28 Ekim 2026. DEVELOPMENT AUTHORIZED; INDEPENDENT CODE REVIEW PENDING.
OPS-004, OPS-001/002/003, SEC-004, FIN-001/003/006, SUP-003/004/010 ve D6 yalnız sentetik alt kapsam.

## Kapsam ve güven sınırı

[R2 kabul kaydı](F6_D_R2_ACCEPTANCE_RECORD.md) yalnız eski immutable adayı kapatır.
Yeni saf PHP preflight, mevcut F6-B Fixture/Plan ve F6-C Inspector ile R2 RecoveryContext/
RecoveryInspector üzerinde salt-okunur uygulama katmanı bileşimidir; ikinci reconciliation,
recovery, Identity veya worker motoru değildir. Mevcut accepted source dosyaları değişmez.

Preflight doğrudan final Inspector ve RecoveryInspector nesnelerini çağırır; dış JSON/Report
veya caller-rehashed PASS raporu güvenilir kanıt girişi olamaz. Signed checkpoint/target/source
ilişkileri mevcut mekanizmalarca doğrulanır; güncel source report kökü restored target ve
verified logical root ile eşleşmelidir. Source iki kez okunur; aradaki fingerprint farkı ret.
Bu ayrı DB snapshot'larının atomik global transaction veya canlı final-delta garantisi değildir.

Test/synthetic flag'leri, ayrı preflight flag'i, kısa lease ve SYNTHETIC_PREFLIGHT_ONLY purpose
zorunludur. Lease ve mevcut R1/R2 fence'leri her değerlendirmede tazelenir; cache kullanılmaz.
Bu trusted process konfigürasyonu native kullanıcı/HTTP yetkisi yerine geçmez; endpoint yoktur.
Simulation yalnız allowlist named senaryolar üretir; gözlemleri gerçek aktör/callback/provider
durumu sayılmaz. İki aktif aktör, UNKNOWN callback, in-flight operation ve incomplete delta ret.
Real GATE'ler kodda sabit OPEN/BLOCKED kalır; sıradan JSON true/CLOSED flag'i kapatamaz.

Olumlu sonuç yalnız SYNTHETIC_PREFLIGHT_COMPLETE; risk/eksik kanıtta BLOCKED.
Her durumda effects_allowed=false, cutover_allowed=false; hiçbir live capability üretilmez.
Rapor yalnız güvenli durum kodu, gate ID ve hash taşır; body/customer/credential/path taşımaz.
Salt-okunur kalıcı veri sınırı içinde session/advisory-lock işlemleri mevcut reader davranışıdır.

## Dosya / migration

Yeni src/Migration/Cutover/Preflight.php ve Simulation.php; yeni hedefli test suite.
Yeni ADR-031 bu bileşim ve gerçek gate'lerin kapatılamaması kararını açıklar.
tools/ci/suites.json ve FULL workflow listesine aynı yeni suite eklenir; workflow tetiklenmez.
Kapanış: yeni R2 acceptance record ve önceki ADR-030/R2 plan/delivery'ye tarihli çapraz referans;
F6_PLAN'a yalnız P0 geliştirme yetkisi. Eski tarihsel metinler ve üç yayımlanmış hazırlık belgesi korunur.
Production migration yok;001–054 ve fixture DDL değişmez. Bağımlılık veya daemon yok.

## Test ve kanıt

Önce yeni suite: başarı+gerçek DB snapshot/root/checkpoint/mapping/finance/currency/owner/privacy
tahrifatı; eksik gate; çift/eksik aktör; unknown callback; in-flight provider; incomplete delta;
stale lease; amaç/environment eskalasyonu; sahte JSON/Report; cross-context; redaksiyon ve no-write.
Mevcut reconciliation, recovery-integrity/target/R1 ve finance-unit/security sınırlarından
ilgili hedefli suite'ler PHP8.3/8.4 mevcut ücretsiz Docker imajlarında çalıştırılır.
Yeni dedicated internal ağ,4GiB tmpfs MariaDB; host portu yok; APP_ENV=test; sahte DB credential.
Kaynak host bind-readonly; çalışma kopyası yalnız ephemeral container/tmpfs içinde.
Application regression broad veya FULL/Chromium/maraton çalıştırılmaz. Lint/pattern ve erişilebilir
PHPStan yapılır; yeni sorun/değişiklik yoksa gereksiz tekrar edilmez. Güvenilir log filtresi raw
hata/credential dışarı çıkarmaz. Başarısız koşular korunur; eksik kontrol PASS sayılmaz.

## Geri dönüş ve yeni aday

Yeni kod/test/registry ekleri kontrollü geri alınabilir; accepted R2 kod/evidence değiştirilmez.
Geçici test kaynaklarının yalnız bu görev etiketli tam kimlikleri temizlenir.
Yeni kaynak hash manifest'i ile diff incelenir; commit oluşturulmadıkça yeni application SHA yoktur.
Gelecekte ayrı izinle üretilecek yeni SHA için bağımsız doğrulama gerekir; ADR-030 otomatik uygulanmaz.
Gerçek F6-E/cutover, WHMCS, FULL, merge, staging/production ve otomatik push yetkisizdir.
