# ADR-028 — bağımsız offline synthetic issuer attestation

F6-D-R1,27 Ekim2026; kapsam [R1 planı](../F6_D_R1_PLAN.md).

Eski Vault HMAC'i source capture authority'yi encryption key'den ayırır, fakat
operational lease sona erince cold verification yapamaz. Lease'i uzatmak veya
HMAC'i kaldırmak yerine, geçerli Vault ve ayrı Ed25519 issuer'ın ortak yetkisiyle
exact authenticated ciphertext'e detached versioned recovery attestation eklenir.
Orijinal ciphertext/plaintext/issuer MAC/backup ID immutable kalır. Signer private
key/ciphertext/nonce/signature Git raporuna çıkmaz. Public key capsule'dan kabul
edilmez; ayrı operator policy pin/revocation/expected identity'leri belirler.

Fresh offline recovery authorization kaynak lease'inden bağımsızdır. Verification
read-only ve tekrar edilebilirdir; native runtime authority ve single-use activation
değildir. Bu ayrım için Sandbox/Inspector güvenlik koşulları değiştirilmez.
Native BLOCKED_IDENTITY_BOUND OPEN, original archive bytes OPEN; issuer recovery
yalnız önceden enrol edilmiş sentetik paketlerin cold verification'ında ilerler.
Gerçek uzun vadeli custody, offsite, rotation operasyonu, güvenilir clock/policy ve
native application cold restore ayrı GATE'tir. Production migration yoktur.
Cached doğrulama proof'u enrollment authority değildir: signer current Vault issuer
HMAC/lease'ini girişte ve fence'i imza sonrası tekrar denetler. Private signing key
tek başına uygulama seam'inden doğrulanmamış artifact attestation üretemez.
