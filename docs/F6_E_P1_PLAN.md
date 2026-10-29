# F6-E-P1 — offline/sentetik cutover rehearsal

29 Ekim 2026. DEVELOPMENT AUTHORIZED; INDEPENDENT CODE REVIEW PENDING.
OPS-004, OPS-001/002/003, SEC-004, FIN-001/003 ve SUP-003/004/010 yalnız sentetik alt kapsam.
Başlangıç HEAD25d916ad0c3ef1002b6ba7f25df0029136dbf145,
treeba5cc43046168cc3b5e9ff8384cd43c3ff703e9d;
migrationc0729c92dd143c017fdec32421bf67fe6b51e05b.
Kullanıcının ilettiği olumlu P0 incelemesi yalnız sınırlı sentetiktir; FAST/FULL/genel CI kabulü değildir.

## Kapsam / dosya / migration

Mevcut final Preflight/Simulation ve onların Migration/Reconciliation/Recovery reader'ları yeniden kullanılır.
P0/R2 kaynakları, testleri ve eski kanıtları değişmez. Tek yeni Cutover/Rehearsal.php,
yeni migration-cutover-rehearsal.php; suite registry ve FULL listesine aynı kayıt.
Bu plan, gerekiyorsa ADR-032 ve tek delivery raporu; ayrı yeni kabul rapor zinciri yok.
Yeni provider/ödeme/Identity/Queue/recovery motoru, endpoint, worker veya migration yoktur.

## Protokol ve güven sınırı

Sıra: PREPARE → PIN_SNAPSHOT → REQUEST_SOURCE_STOP → CHECK_IN_FLIGHT →
FINAL_DELTA → CHECK_SINGLE_ACTOR → FINALIZE.
Nesne ömrüne özel gizli MAC anahtarı; rehearsal kimliği, ordinal, önceki olay hash'i,
P0 report pin'i ve kısa lease'e bağlı sentetik olay zarfları üretilir.
JSON fingerprint'i tek başına kanıt değildir; MAC ve sıralama doğrulanmadan iz ilerlemez.
Her issue/accept ve finish mevcut Preflight'ı yeniden çağırır; eski Report girişi kabul edilmez.
İlk pin sonraki P0 fingerprint'leriyle aynı kalmalıdır; değişen snapshot/root/checkpoint ret.
Eksik/duplicate/out-of-order/başka nesne veya rehearsal token'ı, stale lease ya da belirsizlik
session'ı BLOCKED kilitler. İn-memory iz report'ta güvenli public payload/hash ile doğrulanabilir;
MAC/key veya raw müşteri kanıtı report'a çıkmaz. Hash zinciri bağımsız execution attestasyonu değildir.
Nesne/lease devamlılığı yalnız trusted test process içindedir; durable replay veya canlı fence değildir.

Başarı yalnız SYNTHETIC_REHEARSAL_COMPLETE. cutover_allowed=false, effects_allowed=false,
real_gate_status=BLOCKED; P0'nin30 gerçek gate'i OPEN/BLOCKED kalır.
Source stop yalnız sentetik istek etiketi; canlı freeze/actor devir/callback routing/komut yok.
Global cross-DB snapshot atomikliği NOT_VERIFIED kalır.

## Test / doğrulama / rollback

Önce yeni suite PHP8.3/8.4 mevcut ücretsiz pinned Docker imajlarında; ardından mevcut P0,
C reconciliation, R2 integrity/target, R1 recovery, finance-unit ve hardening-unit seçimi.
Gerçek sentetik DB tahrifatı, sıralama/eksik/duplicate/replay, actor/callback/provider/delta,
root/pin/lease/identity/tamper/redaksiyon/yetki sınırları assertion'larla sınanır.
PHPStan/lint/pattern/belge; source SHA-256 manifest'i, diff ve önceki blob korunumu.
Internal ağ, host portu yok, geçici4GiB DB; yalnız görev label'ı doğrulanan kaynaklar temizlenir.
Başarısız koşular korunur; broad/FULL/Chromium/maraton veya Actions yok.
Rollback yalnız yeni P1 kaynak/test/registration/doc ekleri; gerçek rollback veya accepted P0/R2 silme yok.
Yeni commit/push oluşturulmaz. R2 yöntemi P1'e aktarılmaz; yeni aday ayrı inceleme bekler.
