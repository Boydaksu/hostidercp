# ADR-023 — FAST kapsamı ve exact SHA FULL kabulü

Durum: CI optimizasyon talimatı kapsamında uygulanıyor; gerçek Actions kanıtı ve
bağımsız inceleme bekleniyor. Üretim tasarımını veya önceki sınırlı kabulleri değiştirmez.

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
