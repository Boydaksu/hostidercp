# CI optimizasyonu — çalışma ve kanıt kaydı

## Yetki, başlangıç ve uygulama planı

Başlangıç: `fb8a217619196b650d82a89a16b3d2680736cf2d`.
Kabul edilmiş uygulama: `e0d460d0aea05f496135d169ecbb64ce94659cc9`.
Bu iş CI yapılandırması/yardımcıları ve F6 plan belgeleriyle sınırlıdır.
Uygulama algoritmaları, eski testler ve migration001–054 değiştirilmeyecek.

Kapsam: otomatik FAST ile açık tetiklenen, exact SHA doğrulamalı FULL ACCEPTANCE
ayrımı; güvenli etki yönlendirme; docs-only hafif kontrol; gerçek Actions kanıtları.
OPS-001/005, SEC-001–004 ve mevcut tüm regresyonların korunması esastır.

Dosyalar: `.github/workflows/f0.yml` (FULL), `fast.yml` (FAST), `tools/ci/`
(suite envanteri, etki seçimi, kaynak/belge kontrolleri ve bunların testleri), bu rapor,
F6_PLAN, README ve ADR-023. Üretim bağımlılığı veya Node worker eklenmez.

Migration: yok. Test: yönlendirme/negatif SHA kontrolleri yerel; gerçek FAST PHP8.3/8.4,
gerçek docs-only çalışma ve son CI kaynak SHA'sında FULL PHP8.3/8.4.
FULL mevcut52 suite/582 PHP, Chromium11, state3, bağımsız1/4/8 worker,
10/30/20 tekrar, migration empty/upgrade/replay, lint, PHPStan ve iki audit'i korur.
Yeni CI yardımcı kontrolleri mevcut582 uygulama testine karıştırılmaz.

Geri dönüş: yalnız CI/belge commit'lerini geri al; eski otomatik push+PR full
davranışı geri gelir. DB geri dönüşü veya destructive down yoktur. Önceki kabul
kayıtları korunur. main protection değiştirilmez. PR3 OPEN/DRAFT kalır.

## Durum

Uygulama ve gerçek Actions doğrulaması sürüyor. Bu kayıt DONE veya FULL kabul
beyanı değildir. F6 yalnız plan; geliştirme/cutover/staging/merge yetkisi yoktur.

## Önceki ve yeni çalışma sözleşmesi

| Olay | Önce | Şimdi |
|---|---|---|
| Normal branch kod push'u / aynı PR synchronize | İki eşdeğer tam matris | Tek push FAST; PR commit'i bu check'i taşır |
| README/docs/ADR/rapor metni | Yine582+Chromium+tekrarlar | FAST scope, belge/source pattern ve6 CI koruma testi; DB/PHP matrisi açıkça skipped |
| Yalnız Support leaf | Her modülün tüm testleri + pahalı tekrarlar |13 native PHP suite; Identity/session/HTTP/queue/migration güvenliği dahil |
| Core/Identity/Finance/Queue, migration, lockfile, workflow, başka/bilinmeyen yol | Tam otomatik paket | FAST broad52 suite, JS/state ve audit; son kabulde ayrıca FULL |
| Nihai test/mock kabul adayı | Otomatik koşuyla karışabiliyordu | Sadece workflow_dispatch, zorunlu exact40 SHA, ayrı FULL adı |

FAST `concurrency` branch bazında stale koşuları iptal edebilir. FULL grubu run ID
bazındadır ve cancel-in-progress:false; yeni push veya docs commit'i eski FULL'ü
iptal etmez. Hiçbir akış release/deploy/merge yapmaz. contents:read ve
persist-credentials:false kullanılır; canlı credential veya provider erişimi yoktur.

## Koruma matrisi

| Kontrol | FAST | FULL ACCEPTANCE |
|---|---|---|
| PHP8.3/8.4 | Kod profillerinde iki sürüm | Her zaman iki sürüm |
|582 eski PHP testi /52 suite | broad52; support13; docs0 (açık scope) | Aynı52 script, aynı582 test; gelecekte suite envanteri ve FULL listesi birlikte büyür |
| Native HTTP/MariaDB ve security | Seçilen kapsamın gerçek endpoint/DB testleri | Tüm modüller |
| Migration empty/upgrade/replay/checksum/failed DDL | Kod profillerinde support-migrations dahil | Korunan mevcut support-migrations + temel integration |
|1/4/8 bağımsız PHP worker | Kapsama giren mevcut yarışlar | Tüm mevcut Support/Cloud/maliyet/Identity yarışları |
| PHP lint/mimari, PHPStan, Composer locked audit | İki kod matrisi | İki matris |
| Pattern taraması | Her olay, docs dahil | Her matris, docs dahil |
| JS syntax/state3 | Her kod matrisi | Her matris |
| Chromium11 | Son adaya bırakılır | Mevcut gerçek Chromium paketi aynen |
| npm lock audit | Her kod matrisi, install gerekmeden | Mevcut npm ci + npm audit; Chromium install |
| Services10 / Commerce30 / Session20 | Son adaya bırakılır | Aynen korunur |
| CI yardımcı regresyonları | Scope işinde6 |6 ek kontrol;582 uygulama testinden ayrı |
| Exact source kanıtı | Push commit'i; her suite öncesi/sonrası tracked source kontrolü | SHA formatı, erişilebilir checkout, commit/tree ve temiz tracked source; test başı/sonu JSON artifact |

Docs-only profilinde bağımlılık dosyası değişemez: tools/analysis veya browser lockfile
değişikliği broad'a gider. Bu yüzden pahalı dependency/type/runtime kontrollerinin
atlanması açık bir kapsam kararıdır; güvenlik pattern taramaları çalışır. Tarihsel
docs/evidence içindeki bazı `.json` dosyaları eski patch biçimindedir; içerikleri
değiştirilmez, UTF-8/credential pattern kontrolünde metin olarak taranır. Bu tarama
tam secret/vulnerability incelemesi değildir. CI suite JSON'u ayrıca parse edilir.

## Yanlış kapsam ve branch protection sınırları

Yalnız açık Support leaf sınırı daralabilir. Rename no-renames ile eski/yeni yolların
ikisini de kapsar. Yeni modül, eksik/zero before SHA, okunamayan diff, boş diff veya
tanınmayan yol broad'a düşer. Source-proof arızası test runner'ında fail-closed'dur.
Yeni paylaşımlı bağlantılar geliştirilirse Support izin listesi yeniden incelenmeli;
nihai kabulün FULL gerekliliği kaldırılmamalıdır.

FAST workflow trigger'ında path filtresi yoktur. `FAST result` always çalışır ve scope
başarısızsa, kod matrisi başarısız/iptalse veya beklenmeyen skip varsa başarısız olur.
Bu sabit check ileride branch protection adayıdır; docs-only sonsuz pending üretmez.
`main` protection halen kapalıdır ve değiştirilmedi. Fork PR'ları, base-only değişiklik
ve merge-result testleri bu push stratejisinin otomatik güvencesi değildir. İlk merge
öncesi protection politikası ve doğru son kaynakta FULL bağımsız kararı ayrıca gerekir.

Workflow tanımı SHA'sı uygulama SHA'sından ayrı kaydedilir. Commit erişilemiyorsa
checkout başarısız; format yanlışsa checkout öncesi ret; farklı HEAD/tree/dirty tracked
source test başlangıcında ret. Gelecekte final inceleme workflow tanımını da denetler:
deponun kendi değiştirilebilir doğrulama kodu dışsal bir güven kökü sayılmaz.

## Yerel doğrulama

PHP8.4.24: CI yardımcı6/6; temiz exact SHA/tree positive ve tracked source mutation
negative; malformed/unreachable/wrong SHA negative. PHPStan0 hata;390 PHP lint/mimari
dosyasında0 ihlal.411 tarihsel belge metni encoding/pattern kontrolünden geçti.
Bu sonuçlar GitHub PHP8.3/8.4 veya FULL başarısı yerine sunulmaz.

## GitHub kaynak kimliği

CI kaynak commit'i [`7e2092898287e566fc6a487c6540d6f70cec7481`](https://github.com/Boydaksu/hostidercp/commit/7e2092898287e566fc6a487c6540d6f70cec7481);
tree `b4451d7e85cf42ce48b51cc577096a4de6537d0f`.
[Başlangıca diff](https://github.com/Boydaksu/hostidercp/compare/fb8a217619196b650d82a89a16b3d2680736cf2d...7e2092898287e566fc6a487c6540d6f70cec7481).
[İlk FAST](https://github.com/Boydaksu/hostidercp/actions/runs/40377794488) SUCCESS;
FULL kanıtı henüz yoktur. Yerel commit ile GitHub commit'i
farklı author metadata kullanır; yayımlanan tree byte eşliği doğrulanmıştır.

## Gerçek FAST sonucu

Push run40377794488, profile broad; aynı SHA için ikinci pull_request koşusu yok.
[PHP8.3 job121765622065](https://github.com/Boydaksu/hostidercp/actions/runs/40377794488/job/121765622065)
ve [PHP8.4 job121765622035](https://github.com/Boydaksu/hostidercp/actions/runs/40377794488/job/121765622035):
her sürüm **52 suite /582 test /0 hata**; eski582 adın tamamı ve state3/3 korundu.
PHPStan, Composer locked audit, npm lock audit,390 PHP lint/mimari dosyası ve411
source/config/test pattern taraması0 hata. Scope job121765492578 ayrıca6 CI kontrolünü
ve414 belge metni taramasını başarıyla tamamladı. FAST result job121773020775 SUCCESS.
[Ayıklanmış kanıt](evidence/ci-optimization/fast.json); ham runner logları credential
tanı alanları içerebildiğinden depoya kopyalanmadı.

[Kaynak koruması](evidence/ci-optimization/preservation.json):389 kaynak/test/migration
dosyası ve54 migration başlangıçla aynı; altı protected directory tree hash'i eşit.
Yeni F6 runtime yoktur. Chromium ve10/30/20 tekrarları bu FAST'ta çalıştırılmadı;
bu sonuç FULL ACCEPTANCE yerine sunulmaz.

## FULL için açık doğrulama

Hedef uygulama SHA'sı `7e2092898287e566fc6a487c6540d6f70cec7481`.
FULL workflow kaynağı hazır; gerçek manual run sonucu henüz alınmadı. GitHub
connector workflow dispatch aracı sunmuyor; yerel GitHub CLI oturumu yoktur.
Windows tarayıcı kontrolü URL'yi doğrulayamadığı için aracı durdurdu; bu güvenlik
kontrolü atlanmadı. Proje sahibinden GitHub Actions arayüzünde açık manual trigger
istendi. Trigger sonrası run/job, source-proof SHA/tree,582/11/3 ve10/30/20 kanıtı
alınmadan **CI optimizasyonu DONE değildir**. Bu eksik kullanıcı onayı değil,
mevcut erişim araçlarının manual başlatma sınırıdır.

Bu kanıt commit'i yalnız docs içerir. Gerçek docs-only Actions sonucu takip eden
kayıtta eklenecek. F6 planı tamamlanmıştır; F6-A kodu başlatılmamıştır ve ayrı yetki
bekler. PR3 OPEN/DRAFT, main protection değişmemiştir.
