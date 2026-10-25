# F4-F son denetim düzeltme planı

25 Ekim 2026. Taban c7f4b06c936978d096777c1d89befa5d6b223098; 49/383/0.
FR-DOMAIN/VOYAR, SEC-004, DOMAIN-001/003, PROV-001/002, OPS-005.

Kapsam: native Domain/Cloud reconciliation doğrulamasını yeniden kullanan allowlist kanıt özeti;
staff + services.reconcile ve services.manage exact customer/service scope; dated/fresh MOCK
evidence, operation/attempt/task/account/resource/generation/version binding. Raw response,
contact/EPP/root/VNC/credential/context projection'ı yoktur. Stale/mismatch sonuç review ister.
Domain verified_success / definitive_no_effect / insufficient_evidence ayrımı; yalnız ilk iki
sınıf uygun SUCCESS veya FAILURE sonucu seçebilir. Cloud pending/mismatch başarı veya failure
açmaz. Outcome ve server-generated summary mevcut confirmation state HMAC'ına bağlanır;
execute aynı özeti yeniden üretir, native resolve tekrar doğrular. Yeni provider mutation yoktur.

Dosyalar: Domains.DomainReconciliation, Cloud.Engine, ProviderPanel evidence/read/command/
confirmation, JS, gerçek HTTP regresyonları ve rapor. Yeni tablo/migration beklenmiyor;
001–048 korunur. Geri dönüş: paneli durdur, tutarlı kod/DB/APP_KEY restore veya forward-fix;
kanıt/finans silinmez, UNKNOWN replay yapılmaz.

Test: mevcut 383 + native cookie/session/MariaDB success/no-effect/insufficient/stale/
cross-tenant/attempt/outcome/projection/pending Cloud ve çift HTTP resolve; tam PHP8.3/8.4 CI,
PHPStan, Composer audit, lint, HTTP/session ve 1/4/8 worker korunur.

Proje sahibi bu görüşmede mapping/import CRUD'un ayrı yetkilendirilecek işe ertelenmesini
açıkça onayladı. Bu kapsam DONE değildir. Ayrı plan F4_F_MAPPING_IMPORT_SCOPE.md içindedir.
Canlı GATE'ler OPEN; DOMAIN-002 PARTIAL. F5/F6, merge veya production yoktur.
