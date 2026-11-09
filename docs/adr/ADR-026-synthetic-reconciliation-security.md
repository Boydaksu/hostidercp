# ADR-026 — ayrı salt-okunur sentetik mutabakat

27 Ekim 2026. F6-C IMPLEMENTATION; bağımsız teknik kabul bekler.
Normatif kapsam F6_C_PLAN, D2/D4/D5/D6 ve FR-OPS/SEC-004/FIN/SUP/PROV sentetik sınırıdır.

APPLIED manifest source-target eşliği veya gerçek göç kanıtı değildir. F6-C accepted
Fixture/Plan/MappingEvidence ve Core Database/Money sözleşmeleriyle teknik facts'i
bağımsız karşılaştırır. Yeni Identity/Ledger/Queue/Audit motoru ve native adapter yoktur.

Accepted Sandbox'ın loader grant şartlarını gevşetmek yerine ayrıca yalnız SELECT
yetkili test reader hesabı kullanılır. Accepted Sandbox authority HMAC/lease/readiness
içindir; reader için credential/host/schema input'u strict ephemeral/loopback olur.
READ ONLY transaction ve accepted schema mutex'i consistent bounded report üretir;
post-read fence fail-closed'dur. Veriler rapor için değiştirilmez veya onarılmaz.

Manifest yalnız aggregate/reason/hash verir; private body/author/path/mapping payload
çıkarmaz. Yeni native HTTP yüzeyi yoktur. Finance currency bazında Money ile toplanır;
olmayan ledger/byte/vendor kanıtı NOT_AVAILABLE/NOT_VERIFIED olarak kalır.
Inspector salt okunur olduğu için production migration veya yeni tablo gerekmez.
Bound aşımı raporu kesip PASS üretmez; INCONCLUSIVE döner. Operasyonel attestation
deterministik content hash'inden ayrıdır. Rollback code revert + owned fixture cleanup.

Rapor fingerprint'i yalnız aggregate fark özetine dayanamaz: bounded actual target
tablolarının canonical evidence kökü de içerilir. Bu, aynı özetli farklı bozuk
veriyi ayırır; ham body/author/path/credential rapora çıkmaz. Fiziksel kolon ve
gerekli PK/unique kontrolleri bounded okumanın temel varsayımlarını da doğrular.
