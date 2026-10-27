# F6-A — sentetik discovery ve sözleşme planı

27 Ekim 2026. Yetki: SYNTHETIC DISCOVERY & CONTRACT DEVELOPMENT GO;
bağımsız kabul beklenir. Başlangıç272eb2c3e6a8f6e8cc0b5c4f66a8272f0333c909;
CI kabul kaydı ayrı91efb9360a99aab7f87e211e4fa46c4dfe4e4d9a commit'indedir
(ilk yerel313bc56 ile aynı tree). Native PHP8.3/8.4, yeni framework yok.

## Kapsam ve gereksinim izi

FR-OPS/R1-08/D6: sürüm bağımsız sentetik source envelope, immutable identity,
snapshot/provenance, mapping önerisi ve negatif doğrulama. SEC-001–004/OPS-001:
credential/rol/MFA mirası yok; dış I/O ve DB bağlantısı olmayan saf sınıflar.
FIN-003/006/007/008: mevcut Finance Currency/Money, para bazlı doğrulama,
kısmi allocation/refund/wallet ve ayrı official/proforma türü.
PROV-004: imported resource CREATE sözleşmede daima yasak; canlı kabul değil.
SUP-003/004/010: mesaj sırası/orijinal tarih/yazar/private not/ek sahipliği;
gerçek arşiv kabulü değildir. G-O03 OPEN.

## Mimari kararlar ve dosya kapsamı

`src/Migration/`: readonly kimlik/envelope, explicit bilgi durumu, strict
allowlist doğrulayıcı, bütün snapshot ilişki doğrulaması, immutable mapping
kanıtı ve salt öneri. `tests/migration-contract.php`, sentetik fixture üreticisi
ve JSON manifest; CI suite envanteri ile FULL listesine birlikte kayıt.
`docs/F6_A_DELIVERY_REPORT.md`, kanıt dizini ve ADR-024 sözleşme kararını kaydeder.
Identity/Finance/Services/Domains/Cloud/Support/Core kaynakları değiştirilmez.
Core CanonicalJson ve Finance Money/Currency yeniden kullanılır. Yeni Identity,
queue/outbox/audit veya ikinci finans motoru yoktur; HTTP/cron wiring yoktur.

## Veri sözleşmesi

Hostider sentetik contract v1 gerçek WHMCS tablo/kolon şeması değildir.
Identity = instance + entity type + immutable ID; tuple canonical SHA-256 ile
anahtarlanır. Her envelope SYNTHETIC, contract/transform sürümü, schema SHA,
snapshot ID, UTC extract timestamp/watermark, kaynak timezone/encoding,
provenance, uncertainty ve payload SHA taşır. İçerik hash'i bütün payload ve
ilişkileri kapsar; tam envelope fingerprint metadata'yı da bağlar.
Unknown/conflicting/missing/unverifiable ayrı enum; yalnız KNOWN değer taşır.
Snapshot içinde metadata tutarlılığı zorunlu; farklı instance birbirine bağlanmaz.
İlişkiler entity payload'ındaki tipli source ID referanslarıdır; tam snapshot
verilmeden eksik parent sessiz tamamlanmaz. Snapshot validation importer değildir.

Mapping yalnız bellek içi immutable kanıt: source identity/snapshot/schema,
content/envelope hash, transform, target identity ve evidence digest. Aynı
kanıt NOOP_REPLAY; değişmiş content/metadata/target CONFLICT; hiç mapping yoksa
PROPOSED. Hiçbiri kalıcı yazma veya replay yürütme izni değildir. Yeni snapshot
eski mapping'i overwrite etmez; CONFLICT ve explicit manual review reason üretir.

## Güvenlik modeli ve matris

| Alan | Fail-closed davranış | F6-B bağımlılığı |
|---|---|---|
| Kaynak | strict alan/tür/UTF-8/hash/sürüm; secret alanları allowlist dışı | G-O03 doğrulanmış read-only adapter |
| Identity | yalnız sentetik .invalid e-posta; staff hedef admin olamaz; legacy hash taşınmaz, reset+verify zorunlu | native Identity application service ve yetki incelemesi |
| Provider | imported origin, customer/service/provider/account/resource bağı; duplicate conflict, UNKNOWN/in-flight review, CREATE ret | bağımsız gerçek resource doğrulaması |
| Finans | Money/Currency; float/overflow/currency mismatch ret; bilinmeyen FX review | native immutable ledger, ayrı yetki ve mutabakat |
| Support | internal_note PRIVATE; belirsiz visibility/yazar review; hiçbir client projection yok | native ACL/author_id redaksiyonu/can_attach korunması |
| Ek | checksum/MIME/size/owner; unsafe path/symlink/missing/corrupt review veya ret | gerçek dosya okuma/MIME/AV ayrıca; şimdi dosya kopyası yok |
| Provenance | immutable canonical fingerprint ve target conflict; raw payload hata mesajına çıkmaz | kalıcı claim/transaction/outbox ve güncel yetki tekrar kontrolü |

SYNTHETIC etiketi gerçek veriyi anonimleştirmez; bu API yalnız sentetik geliştirme
girdileri içindir. F6-A hiçbir dosya path'ini açmaz; reported MIME/symlink kanıtı
gerçek dosyanın güvenli olduğunun ispatı değildir. Yeni KVKK/retention varsayımı yok.

## Migration, geriye uyumluluk ve geri dönüş

Yeni DB migration yok;001–054 immutable. Eski582 test dosya/blob/ad envanteri ve
migration SHA-256/tree karşılaştırması kanıta yazılacak. Eski assertion/skip değişmez.
Rollback F6-A commit revert; DB down/restore gerekmez. Forward-fix contract sürümünü
açık artırır, eski fingerprint/mapping kanıtını yeniden yazmaz. CI belge commit'i ayrı.

## Test ve kabul matrisi

Yeni adları F6-A öneki taşır: aynı içerik/replay, değişmiş hash conflict, instance
izolasyonu, eksik kimlik/sürüm/snapshot/parent, duplicate resource/ownership,
unknown payment/provider, currency/FX, internal note/author, checksum/path/symlink,
staff/hash ve sıfır dış etki. Ek malformed type/UTF-8/unknown field, sıra/tarih,
allocation/refund sınırı, mapping evidence/target/stale metadata testleri.
Geliştirme hedefli test+lint/PHPStan/CI self-test; son kod SHA push'u gerçek FAST
broad PHP8.3/8.4 eski582+yeni testleri çalıştırmalı. Chromium/10-30-20 yalnız
sonraki manuel FULL'e bırakılır. Workflow dispatch bu yetkinin dışındadır.

## Kabul engelleri ve durma noktası

F6-A teknik teslim independent review bekler; ACCEPTED/FULL DONE/production-ready
ilan edilmez. FULL bu aday için henüz çalışmadı. G-O03 gerçek sürüm/modül/schema,
kolasyon/encoding/timezone, hash formatı, snapshot/delta ve attachment kökleri OPEN.
SUP-010, PROV-004 gerçek import, DOMAIN-002 PARTIAL/MANUAL_REVIEW, mapping CRUD
DEFERRED, PROV-005 OPEN. F5-C dört canlı istisnası, auto-close/merge/split ve
kurum geneli tarihsel SLA raporu ile tüm vendor/operasyon/live GATE'ler korunur.
F6-B/staging/gerçek import/provider/merge/deploy/cutover ayrı talimat gerektirir.
PR3 OPEN/DRAFT, main protection değişmez. Teknik rapor ve exact SHA tesliminden sonra DUR.
