# F6-E-P1 — offline cutover rehearsal teslimi

29 Ekim 2026. DEVELOPMENT PACKAGE COMPLETE; INDEPENDENT CODE REVIEW PENDING.
OPS-004 ve OPS-001/002/003, SEC-004, FIN-001/003, SUP-003/004/010 yalnız sentetik alt kapsam.

Yeni `Rehearsal` mevcut typed P0 Preflight/Simulation ile Migration, Reconciliation ve Recovery reader kontrollerini yeniden kullanır. Yedi sıralı olay: PREPARE → PIN_SNAPSHOT → REQUEST_SOURCE_STOP → CHECK_IN_FLIGHT → FINAL_DELTA → CHECK_SINGLE_ACTOR → FINALIZE. Her issue/accept ve final doğrulama P0'yi tekrar okur. Rehearsal kimliği, önceki olay hash'i, ordinal, kısa lease ve sabit P0 fingerprint'i nesneye özel MAC ile bağlanır. Hata session'ı terminal BLOCKED yapar. Public trace aynı kimlik/lease/kanıt/sıra için deterministiktir; MAC/key rapora çıkmaz.

Source-stop yalnız sentetik istek etiketidir; gerçek freeze/fence/ack veya aktör devri kanıtı değildir. Aktör/callback/provider/delta kontrolleri P0'nin sentetik kapsamını kullanır. Bu zincir bağımsız runtime attestasyonu, durable replay deposu veya gerçek operasyon yetkisi değildir. P0'nin global snapshot atomikliği **NOT_VERIFIED** kalır. Yeni provider/ödeme/Identity/Queue/recovery motoru veya endpoint eklenmedi.

## Hedefli doğrulama

| Suite | PHP 8.3.36 | PHP 8.4.27 |
|---|---:|---:|
| Yeni rehearsal | 26/0 | 26/0 |
| P0 preflight | 27/0 | 27/0 |
| Reconciliation | 35/0 | 35/0 |
| R2 integrity | 2/0 | 2/0 |
| R2 recovery target | 24/0 | 24/0 |
| R1 recovery | 25/0 | 25/0 |
| Finance unit | 4/0 | 4/0 |
| Hardening unit | 7/0 | 7/0 |
| **Toplam PASS/FAIL** | **150/0** | **150/0** |

Her sürümde PHPStan level 5, PHP lint, pattern ve mevcut belge kontrolü exit 0. Kaynak before/after manifest eşliği doğrulandı. İlk PHP 8.3 denemesi Docker engine başlangıcında test çalıştırmadan başarısız oldu; ayrı BLOCKED_OR_FAILED kaydı korunur. Docker Desktop hazır olduktan sonra iki başarılı koşu aynı kaynakla yapıldı. Geniş FULL, Chromium, Composer/npm dependency audit veya GitHub Actions çalıştırılmadı; bağımlılık değişmedi.

Yeni testler gerçek assertion ve mevcut sentetik MariaDB fixture'larını kullanır: sıra/duplicate/missing, replay/eski pin, iki aktif veya eksik aktör, UNKNOWN callback, in-flight provider, eksik/çelişkili delta, logical root değişmesi, P0/P1 lease expiry, yanlış rehearsal kimliği, tahrifat, redaksiyon ve operasyon yetkisi yükseltme girişimi. Olumlu uçtan uca demo yedi olayın izini, kaynak/hedefin değişmediğini ve izinlerin false kaldığını doğrular. Sonradan DB tahrifatı başarılı görünen eski sonucun yeni finish ile BLOCKED olmasını sınar.

Koşular disposable internal Docker ağında, host portu olmadan ve salt okunur kaynak bağıyla yapıldı. Yalnız görev etiketli container/ağlar temizlendi. Runtime ve pinned image kimlikleri, komut sonuçları ve temizleme kanıtı [summary.json](evidence/f6-e-p1/summary.json) ile ilgili koşu JSON/TXT dosyalarındadır. Runner inceleme kopyasındaki tek sabit sentetik parola redaksiyonu [runner-source-proof.json](evidence/f6-e-p1/runner-source-proof.json) içinde açıklanır.

## Kaynak bütünlüğü ve değişiklikler

Base HEAD `25d916ad0c3ef1002b6ba7f25df0029136dbf145`, P0 tree `ba5cc43046168cc3b5e9ff8384cd43c3ff703e9d`.
Production migration subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmedi.
Yeni aday commit edilmedi; bir P1 commit SHA'sı yoktur.
480 kaynak dosyalı [canonical manifest](evidence/f6-e-p1/candidate-source-manifest.json) SHA-256:
`e47f38315314b08d501be5c0d3b71127d4bb2110a61548b27723ff5fa48909cc`.

[Kaynak diff'i](evidence/f6-e-p1/source.diff): yeni tek sınıf/test ve yalnız suite kaydı için iki mevcut dosya (`tools/ci/suites.json`, `.github/workflows/f0.yml`). Workflow trigger/deployment değişmedi. Registry/FULL listesi 62 suite; 835 toplam test yalnız beklenen envanterdir, FULL çalıştırılmış değildir. Belgeler bu rapor, kısa plan ve ADR-032 ile sınırlıdır.

[Koruma kaydı](evidence/f6-e-p1/preservation.json): mevcut 379 src/tests, 54 migration ve 468 tarihsel evidence dosyası base blob'larıyla byte eş; 901 dosyada fark yok. Ana Windows çalışma alanındaki kullanıcı `docs/F6_B_B01_REVIEW.md` değişikliği hash ile korundu. Eski worktree temizlenmedi. Son GitHub gözlemi aynı HEAD ve PR #3 OPEN/DRAFT. Commit/push yapılmadı.

## Açık GATE'ler, kabul sınırı ve rollback

Başarı yalnız **SYNTHETIC_REHEARSAL_COMPLETE**. Her raporda `cutover_allowed=false`, `effects_allowed=false`, `real_gate_status=BLOCKED`; P0'nin 30 gerçek GATE'i (28 OPEN, 2 BLOCKED) aynen korunur. Canlı aktör fencing/devri, source freeze, webhook routing, gerçek final delta, gerçek snapshot atomikliği, native recovery ve canlı yetkilendirme kapanmamıştır. Gerçek WHMCS/provider/ödeme/mail/staging/production/merge/cutover yapılmadı.

PHP 8.3/8.4 hedefli yerel sonuçları yeni adayın geliştirme kanıtıdır; bağımsız P1 kabulü, FAST/FULL veya P0 genel CI kabulü değildir. R2'ye özel yöntem istisnası P1'e aktarılmadı.

Migration yok; DB rollback gerekmez. Geri dönüş yalnız bu paketteki yeni P1 dosyalarını ayırıp iki suite kaydını kaldırmayı gerektirir; accepted P0/R2, eski evidence ve kullanıcı dosyası korunmalıdır. Otomatik reset/clean/revert yapılmadı.

P1 INDEPENDENT CODE REVIEW: PENDING. Gerçek operasyon yetkisi verilmeden bağımsız inceleme için durulur.
