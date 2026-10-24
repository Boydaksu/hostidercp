# ADR-012 — Satın alınmış fixed Cloud config ve finansal zarar durdurma

24 Ekim 2026; bağımsız denetimin P0-1/P0-2/P1 talimatı. Yeni ticari fiyat kararı yoktur.
Katalog capability satın alma hakkı değildir. Immutable katalog fiyatına bağlı fixed offer,
quote line üzerinden canonical immutable order/item/price/bill/service unit'e taşınır.
Geçmiş entitlement'sız siparişe sonradan hak uydurulmaz. Ücretli custom/plan değişimi kapalıdır.

Finans kaynağı değişince normal mutation kapalıdır. Fresh gerçek admin session/scope/step-up,
resource/account kanıtı ve state-bound confirmation ile yalnız STOP/TERMINATE istisnası verilir.
Reason/session/identity version ve financial exception ayrı immutable encrypted kayıttır.
Bu izin CREATE/UPGRADE/REINSTALL'e taşınmaz; worker send öncesi session/resource fence'i tekrar
doğrular. UNKNOWN/pending task yeni POST açmaz. STOP maliyetin bittiği anlamına gelmez.

Queue teslimi bir receipt'tir; task başarısı veya fiziksel etki belirsizliği değildir. Durable
operation cron tarafından yürütülür. F4-E/F ve canlı adapter açılmaz.
