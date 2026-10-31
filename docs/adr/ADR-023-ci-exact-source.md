# ADR-023 — FAST kapsamı ve exact SHA FULL kabulü

## Güncel bağımsız karar — 27 Ekim 2026

**CI OPTIMIZATION — LIMITED TECHNICAL BASELINE: ACCEPTED.**
[FULL40459020759](https://github.com/Boydaksu/hostidercp/actions/runs/40459020759),
PHP8.3 job122038976890 ve PHP8.4 job122038977146 SUCCESS:
582/582 uygulama + ayrı6/6 CI, Chromium11/11, state3/3, HTTP10/30 ve session20
PASS; PHPStan/Composer/npm audit/lint/pattern temiz. Önceki582 test korunur.
Uygulama `7e2092898287e566fc6a487c6540d6f70cec7481`,
tree `b4451d7e85cf42ce48b51cc577096a4de6537d0f`,
workflow `272eb2c3e6a8f6e8cc0b5c4f66a8272f0333c909`;
SHA ayrılığı beklenen ve doğrulanmıştır. FAST/docs-only/manual FULL ayrı kapsamlarını korur.
FULL production release, deployment veya merge izni vermez. PR3 OPEN/DRAFT,
main protection ve canlı GATE'ler değişmez. F6-A yalnız sentetik sözleşme geliştirmesi
için ayrıca GO aldı; F6-B/import/staging/cutover yoktur. Bu üç belgeli kabul commit'i
uygulama/CI/test/migration değiştirmez; geri dönüş yalnız belge revert'üdür.
Aşağıdaki bekleme ve plan-only ifadeleri tarihsel karar kaydıdır.

Durum: FAST PHP8.3/8.4 ve docs-only gerçek Actions ile doğrulandı; manual FULL
kanıtı ve bağımsız inceleme bekleniyor. CI optimizasyonu DONE değildir.
Üretim tasarımını veya önceki sınırlı kabulleri değiştirmez.

Eski f0.yml her push ve PR'da aynı tam matrisi çalıştırıyordu. Yeni FAST yalnız push
olayını dinler; PR commit'i aynı push check'lerini taşır. Path trigger filtresi yoktur.
FAST result her zaman scope sonucunu ve gerekiyorsa iki PHP matrisini denetler;
ileride required check yapılırsa docs-only sonsuz pending üretmez. Branch protection
bu görevde değişmez; dış fork push check'leri ve PR merge-result entegrasyonu ayrıca
politika gerektirir. FAST kaynak commit doğrulamasıdır; merge sonucu kabulü değildir.

Etki seçimi açık izin listesidir: README/docs metinleri hafif güvenlik/encoding;
Support leaf değişiklikleri native Identity/HTTP/queue/migration dahil Support paketi;
diğer her şey ve belirsiz diff geniş52 suite. Rename eski/yeni yollarıyla değerlendirilir.
Shared Core/Identity/Finance/Queue, migrations, lockfile ve workflow daraltılmaz.
Yeni modül varsayılan broad; yeni suite envantere ve FULL workflow'a birlikte eklenir.

Mevcut f0.yml yolu FULL ACCEPTANCE olarak yalnız workflow_dispatch kullanır.
Zorunlu lowercase40 SHA, ref checkout, HEAD/commit/tree ve tracked çalışma ağacı
kontrolleri test başlangıcında/sonunda doğrulanır. Workflow yürütme SHA'sı ve uygulama
SHA'sı ayrı kaydedilir. FULL otomatik iptal edilmez; FAST stale branch koşuları iptal
edilebilir. FULL eski582/11/3, yarış/tekrar/migration/audit kontrollerini korur.
Gelecekte bağımsız onay workflow tanımını ve exact application tree'yi birlikte inceler;
değiştirilebilir repo kodu dışarıdan kriptografik güven kökü oluşturmaz.

İzin contents:read; checkout kalıcı credential tutmaz. Gerçek mail/provider/payment,
release/deploy veya repo ayarı işlemi yoktur. F6 yalnız planlanır; runtime yetkisi yoktur.
Kanıtlar ve rollback: [CI raporu](../CI_OPTIMIZATION_REPORT.md).
