# ADR-027 — immutable encrypted synthetic recovery escrow

F6-D yalnız test/CLI kapsamıdır. Native Crypto ve DB/Migrator kullanılır;
yeni scheduler/dispatcher veya production backup engine kurulmaz.
No-compression bounded JSON envelope path/archive parser saldırı yüzeyini azaltır.
Private atomic temp→authenticate→rename, immutable identity ve flock aynı işi
iki final artifact'e dönüştürmez. Lease yeniden doğrulanır; crash partial final olamaz.

Accepted F6-B schema/account/snapshot/secret binding restore sırasında yeniden
imzalanmaz. Ayrı schema'da exact byte/logical escrow clone mümkündür; native
Sandbox/F6-C activation açık BLOCKED_IDENTITY_BOUND'dır. Accepted metadata fixture
dosya SHA/size'ı gerçek byte kaynağı değildir; F6-D örnek dosyaları ayrı labelled
inventory'dir. Bunları gerçek support/PDF byte kabulü diye göstermek yasaktır.
Restore source keyler veya gerçek APP_KEY'i değiştirmez. Şifre anahtarı caller'ın
test-only native Crypto nesnesinde kalır, pakete/manifest kanıtına yazılmaz.

Şifreleme anahtarı kaynak verisi için yetkili issuer sayılmaz. Capture logical ID'si
ayrı private Vault capability HMAC'i ile encrypted envelope içinde bağlanır;
canonical JSON eşliği duplicate-key belirsizliğini reddeder. Capability/lease
kurtarma tasarımı mevcut değildir: 30 dakikalık test lease'i sonrasında bağımsız
cold recovery BLOCKED_ISSUER_RECOVERY kalır. MAC/key/nonce kanıta yazılmaz.

INSERT-only data writer definer trigger metadata'sını göremediğinden restore ayrıca
aynı owned hedefe bağlı, TRIGGER-visible setup witness ister. Bu bağlantı runtime'da
yalnız metadata SELECT/SHOW yapar; veri yazma bağlantısının yetkisi artırılmaz.
Witness eksikse veya trigger varsa INSERT öncesi ret. Gerçek hosting'de böyle bir
metadata yeteneğinin varlığı ayrıca GATE'tir; production yetkisi varsayılmaz.
