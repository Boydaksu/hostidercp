# F0.1 hardening ve review teslimi — 22 Ekim 2026

## Durum ve sınır
İncelenen F0 commit'i `8683bea9189052000622038bc4e160504994e262`; çalışmanın başlangıcı yalnız sonraki teslim belgelerini içeren `3a08b82` idi. F0.1 kaynak düzeltmeleri uygulandı; **F1 başlatılmadı, hiçbir canlı sağlayıcı etkinleştirilmedi, tüm canlı GATE'ler OPEN**. Bu rapor teknik incelemeye sunumdur; kullanıcı nihai kabulü veya production onayı değildir.

## Doğrulanmış işler
| Kimlik / kaynak | Düzeltme | Kanıt |
|---|---|---|
| F01-AUTH — FR-ID, R4-01..07, SEC-001/002/004 | staff/admin otomatik müşteri erişimi kaldırıldı; permission/customer/service/department birleşik grant; effective impersonation ve administrative ayrı | 5 yeni yetki regresyonu + eski yetki testleri |
| F01-JSON/QUEUE — OPS-005 | CanonicalJson nested map sırasını normalize eder; scalar türü/list sırası/object ayrımı korunur, float/büyük tam sayı kaybı reddedilir; eski job JSON'u yeniden yazmadan karşılaştırılır | 1 unit + 2 MariaDB regresyonu; eski idempotency/concurrency testleri |
| F01-SESSION — R4-07 | snapshot okuması kilidi kapatır; fresh-state mutation/expectedVersion; rotation/logout tombstone; context bağı; eski/unknown SID 401; geç gelen response cookie'yi ezmez; SessionGuard revocation sözleşmesi | 7 multi-process regresyon + 1 gerçek HTTP cookie testi; eski session testi |
| F01-AUDIT — FR-ID/FR-OPS | typed event sözlüğü login/MFA/permission/API key/impersonation için genişletildi; serbest metin yok, opaque actor/subject/effective+UUID trace/cause; transaction zorunlu | 2 yeni MariaDB audit testi + eski audit testi |
| F01-PROVIDER — OPS-001 | production 5 mock adapter'ı dispatch öncesinde reddeder; readonly mock provenance/schema ve ResultAcceptance canlı state kabulünü engeller | 1 yeni testte 5 adapter + eski contract/staging testleri |
| F01-RETENTION — FR-OPS | expired bucket temizliği; opt-in SUCCEEDED payload redaksiyonu; kalıcı dedupe fingerprint/attempt/trace; FAILED/UNKNOWN korunur; queue:attention | 4 yeni MariaDB regresyonu + CLI smoke |
| F01-ANALYSIS | PHPStan 2.3.1 level 5, PHP 8.3 hedefi; src/bootstrap/bin/public; locked dev-only dependency; CI 8.3/8.4 | gerçek PHPStan/Composer çıktısı; lint ve mimari kontroller |

Personelin başka müşteriye erişmesi yalnız o işlem için güvenilir Identity servisinden explicit customer grant gelince istenen davranıştır. Hiçbir rol bayrağı müşteri kapsamını kaldırmaz. Endpoint'in hedef hizmet/sahiplik/departmanını DB'den çözmesi gerekir; request body'den grant/principal oluşturulmaz. F1 bu servisleri uygulayacaktır.

## Gerçek yerel test sonuçları
PHP **8.4.24**, MariaDB **10.11.18**, gerçek InnoDB/PDO ve bağımsız PHP süreçleri; yalnız localhost test DB/session dosyaları. Aşağıdaki ham kanıtlar `docs/evidence/f0-1/` altındadır.

| Çalıştırma | Gerçek sonuç | Dosya |
|---|---|---|
| tests/run.php | 19 PASS / 0 FAIL | unit-contract.txt |
| tests/hardening-unit.php | 7 PASS / 0 FAIL | hardening-unit.txt |
| tests/session.php | 1 PASS / 0 FAIL | session.txt |
| tests/session-concurrency.php | 7 PASS / 0 FAIL | session-concurrency.txt |
| tests/integration.php | 26 PASS / 0 FAIL | mariadb-integration.txt |
| tests/http.php | 3 PASS / 0 FAIL | http.txt |
| tests/session-http.php | 1 PASS / 0 FAIL | session-http.txt |
| tools/check.php | 66 PHP dosyası / 0 ihlal | lint-architecture.txt |
| tools/audit.php | 70 kaynak/config/test dosyası / 0 örüntü bulgusu | dependencies-secrets.txt |
| PHPStan | level 5 / No errors, baseline/ignore yok | phpstan.txt |
| Composer validate --strict + audit --locked | valid / bilinen advisory yok | composer.txt |
| CLI migrate/health/attention/retention | başarılı, sadece test kayıtları | console-smoke.txt |

**64 test başarılı: F0'ın 41 senaryosu korunarak 23 regresyon eklendi.** 37 statik test etiketi ve 5 üyeli provider matrisi korunmuştur (provider döngüsü etiketi 5 teste genişlediği için toplam baseline 41). Eski güvensiz beklenti yalnız production mock yasağı ve yeni session API'si gereği sıkılaştırıldı; senaryo silinmedi. Kanıt: baseline-preservation.txt.

Testlerin sessizce başarılı sayılmadığı ara sonuçlar: PHPStan ilk denemede sandbox dosya erişimi ve ardından 128 MB analiz sınırına takıldı; izinli geliştirme çalıştırması ve 512 MB ile tamamlandı. İlk type analizindeki redundant string kontrolü kaldırıldı, transaction-abort guard'ı ayrı güvenli helper'a taşındı. Cookie kontrolünde PHP'nin disabled-cookie parametre sıralaması testi başarısız kıldı; düzeltildi, session/process/HTTP testleri tekrar geçti. Bunlar son PASS sayımına başarısız çalıştırma olarak dahil edilmedi; çözülen bulgular olarak kaydedildi.

## GitHub ve CI
Anlamlı gruplar ayrı gönderildi:
- `bee3624`: explicit access grants ve canonical queue dedupe.
- `415364a`: session lock/tombstone, audit vocabulary, production mock/result sınırı.
- `cf9c015`: migration 002, bounded retention, PHPStan/CI; [CI 39768091626](https://github.com/Boydaksu/hostidercp/actions/runs/39768091626) PHP 8.3/8.4 SUCCESS.
- `4ec37db`: HTTP cookie overwrite koruması ve testi; [CI 39768737397](https://github.com/Boydaksu/hostidercp/actions/runs/39768737397) **PHP 8.3 ve 8.4 SUCCESS**. PHPStan ve 64 testin tamamı her iki job'da geçti.

İlk F0 son belge revizyonu `3a08b82` için [CI 39765457853](https://github.com/Boydaksu/hostidercp/actions/runs/39765457853) SUCCESS idi. F0.1 nihai kaynak doğrulaması yukarıdaki 4ec37db run'ıdır; sonraki commit yalnız teslim belgeleri ve test kanıtlarını kaydeder. Eski F0 raporu tarihsel olarak korunur.

## Migration ve geri dönüş
`001_f0_infrastructure.php` değiştirilmedi. `002_f0_1_retention.php` yalnız rate_limits.expires_at ve index; jobs.payload_fingerprint/payload_purged_at ve retention index ekler. Nullable alanlar legacy kayıtları korur; iş/audit/idempotency silinmez. 001→002 upgrade, checksum/history, tekrar çalıştırma, advisory lock, gerçek yarım DDL ve readiness testleri korunur; CI temiz MariaDB üzerine iki migration'ı uygular.

Rollout: doğrulanmış yedek → cron/traffic durdur → migrate → readiness/test → aç. Eski native F0 session formatı yeniden giriş gerektirir. APPLYING/şema farkında uygulama ve worker açılmaz. Geri dönüş önceki kod+DB snapshot birlikte veya kontrollü forward-fix; otomatik down/DROP yok. Payload redaksiyonu işletmeci açık yaş seçmedikçe çalışmaz ve uygulama kodundan geri alınamaz; önce yedek/retention değerlendirmesi gerekir. Ayrıntı: F0_1_OPERATIONS.md.

## Sonraki fazlara ait işler
- F1: gerçek Identity repository/rol yönetimi, TOTP/recovery, login/logout API, revocation registry/logout-all, SessionGuard implementasyonu, business transaction öncesi authoritative identity-version kontrolü, API key lifecycle ve denetimli impersonation servisi/UI.
- F3/F4: provider operation in-flight lock, gerçek external reconciliation, ResultAcceptance kullanan application service'ler; gerçek API adapter'ları yalnız GATE kapandıktan sonra.
- Operasyon fazı: attention ekranı/pagination, unknown/dead-letter alarmı, arşiv/retention yasal kararı, backup/restore/PITR ve deployment-host doğrulaması.

## Açık güvenlik ve operasyon riskleri
- G-V01..04, G-P01..02, G-C01, G-R01, G-D01, G-X01..02, G-O01..04, G-T01 halen açık. Canlı API, ekonomik işlem, production staging-host load testi veya gerçek müşteri verisi kullanılmadı.
- Grant/principal ve audit subject referanslarının güvenilir kaynaktan üretimi F1'in sorumluluğudur. F0.1 kapsam/sözleşme testi gerçek entity repository yetkilendirme denetiminin yerine geçmez.
- SessionGuard tek başına uzun bir business transaction'ın revocation yarışını kapatmaz; F1 commit sınırında registry/version+gerekli DB lock uygulamalıdır. Native session handler'ın process kilidi hedef hostingde doğrulanmalıdır. F0.1'de authenticated endpoint bulunmaz.
- Session mutation callback'inde uzun iş/network yapılması yeniden kilit süresi riski yaratır; sözleşme kısa dönüşümle sınırlıdır. Periodic session rotation F1 tarafından kontrollü sınırda tetiklenmelidir; AJAX okumalarında yapılmaz.
- Job dedupe/attempt/audit/UNKNOWN tombstone kayıtları büyümeye devam eder; disk izleme/arşiv kararı gerekir. Teknik temizleme yasal veri saklama kuralı oluşturmaz. FAILED/UNKNOWN otomatik silinmez veya yeniden çalıştırılmaz.
- Audit uygulama seviyesinde append-only; DB kullanıcı izinleri, OS/webserver log redaksiyonu ve bağımsız tamper-evidence deployment işidir.
- PHPStan level 5 tam mixed/collection-shape doğrulaması veya kapsamlı güvenlik denetimi değildir. Secret pattern taraması tüm sızıntıları bulmayı garanti etmez. Dev-only PHPStan ve CI actions tedarik zinciri ayrıca güncel tutulmalıdır; runtime üçüncü taraf PHP bağımlılığı hâlâ 0.

## F1 hazırlığı
F0.1 çekirdek sözleşmeleri F1 planlaması için hazırdır; nihai F0 kabulü kullanıcı incelemesine sunulur. İlk F1 tasarımı explicit grant üretimi, revocation transaction sınırı ve testli login/rotation/audit akışları üzerinden başlamalıdır. **Bu görevde F1 geliştirilmedi.**
