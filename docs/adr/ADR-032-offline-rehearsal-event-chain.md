# ADR-032 — Offline synthetic rehearsal event chain

29 Ekim 2026. STATUS: IMPLEMENTATION DECISION — SYNTHETIC P1 ONLY; REVIEW PENDING.
[Plan](../F6_E_P1_PLAN.md).

P0 değiştirilmeden final Preflight nesnesi her adımda çağrılır. Yerel olay dizisi bir
operasyon/Queue/recovery motoru değildir; yalnız in-memory, sıralı rehearsal doğrulamasıdır.
Açık event JSON'larını sırf tekrar hash'leyerek güvenilir saymak yerine instance-local
random MAC anahtarı ile issue edilmiş zarflar doğrulanır. Rehearsal ID, sıra, önceki hash,
lease ve P0 pin'i bağlanır. Aynı kimlikle açılan yeni nesnenin anahtarı farklıdır; eski token ret.
İz hash'leri MAC'ten bağımsızdır; aynı kimlik/lease/reader kanıtı ve sıra aynı public izi üretir.
MAC/key report'a çıkmaz. Public hash izi dışarıdan incelenebilir; bağımsız execution imzası,
kalıcı kayıt veya production authenticity iddiası değildir.

Geçersiz adım terminal BLOCKED latch üretir; başarılı eski rapor session veya DB'nin güncel
durumunun yerine kullanılamaz. finish yeniden P0 okur; sonradan DB değişmesi başarıyı kaldırır.
Snapshot pin'i değişirse otomatik repin/restore/delta yazımı yoktur.
Named Simulation yalnız sentetik aktör/callback/provider/delta gözlemi; real gate enrollment değildir.
REQUEST_SOURCE_STOP hiçbir gerçek çağrı yapmaz. Global snapshot atomikliği NOT_VERIFIED.
P0/R2 kontrolleri, ADR-031 ve gerçek gate'lerin kapalı yetki sınırı korunur.

Başarı SYNTHETIC_REHEARSAL_COMPLETE bile olsa cutover_allowed=false/effects_allowed=false,
real_gate_status=BLOCKED; bütün gerçek gate'ler OPEN/BLOCKED kalır.
Yeni SHA doğrulaması ayrı; ADR-030 yöntem istisnası devralınmaz.
Yeni migration veya mevcut güvenlik sınıflarında değişiklik gerekmiyor.
Rollback yalnız yeni rehearsal/test/registration ve bu görev belgeleri; kullanıcı/accepted kanıtlar korunur.
