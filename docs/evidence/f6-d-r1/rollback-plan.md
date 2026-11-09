# R1 rollback

Yalnız R1 application commit3773351c01164dc3e58a7c4b869ef980a5b7cf79 kontrollü revert.
Original artifacts/issuer MAC/history/checkpoint/provenance ve migrations001–054 değişmez.
Capsule sidecar'ları orijinal artifact yerine geçmez; eski verify/restore yolu korunur.
Owned fixture teardown yalnız exact f6b_/f6d_ schema/user ve kayıtlı f6d- dizinlerdir.
Production DB restore/DROP/destructive migration/real backup deletion yoktur.
Forward-fix capsule version ister; native recovery ayrı authority/durable attestation tasarımı gerektirir.
Gerçek cutover rollback delta/reconciliation ve owner kararı olmadan uygulanmaz.
