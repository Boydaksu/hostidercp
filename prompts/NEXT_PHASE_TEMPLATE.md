# Sonraki fazlar için prompt kalıbı

Bu repoda yalnız `F{N}` fazını geliştir. Önce `AGENTS.md`, `docs/01_DECISIONS.md`, ilgili docs ve önceki fazın test raporunu oku. Mimari veya kapsamı değiştirmek gerekirse ADR ve yazılı gerekçe üret; sessizce değiştirme.

1. Fazın gereksinim kimliklerini ve bağımlılıklarını listele.
2. Migration planı, API taslağı, rol/izin matrisi ve dış sağlayıcı contract tests yaz.
3. Önce failing test; sonra kod; sonra unit/integration/security/retry/idempotency testleri.
4. Bütün kodu paylaşımlı hosting ve 1 dakikalık cron koşullarına göre sınırla.
5. Dış sağlayıcı side-effect işleminde UNKNOWN/DEFINITIVE_FAILURE ayır.
6. Teslimatta: değişiklikler, test komutları/sonuçlar, geçiş/rollback, bilinmeyenler ve hazır olmayanlar açıkça belirt.
7. Fazın release gate listesine uymadan 'tamam' deme.
