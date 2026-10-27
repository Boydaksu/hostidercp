# F6-A — sentetik sözleşme teknik teslim kaydı

Durum: geliştirme doğrulaması sürüyor; bağımsız teknik kabul bekler.
Bu kayıt ACCEPTED, FULL DONE veya production-ready beyanı değildir.

Kapsam/requirement/security/migration/rollback matrisi [F6_A_PLAN](F6_A_PLAN.md),
teknik karar [ADR-024](adr/ADR-024-synthetic-migration-contract.md).
Saf `src/Migration/` kaynakları versioned envelope/identity/fact/snapshot/mapping
kanıtını doğrular. Mevcut Core CanonicalJson ve Finance Money/Currency kullanılır.
Gerçek WHMCS extractor, SQL dump/importer, persistent mapping CRUD, DB yazısı,
attachment kopyası veya provider/ödeme/e-posta çağrısı yoktur.

Demo: `php tools/migration-synthetic.php`; sabit SYNTHETIC fixture üretir,
snapshot manifest ve mapping önerilerini stdout'a verir. Kaynak path/URL/DB
parametresi almaz. [Fixture/manifest](../tests/fixtures/migration-synthetic-v1.json)
tamamen sentetiktir; SHA değerleri gerçek dosya/ödeme kanıtı değildir.

Yeni paket `tests/migration-contract.php`, envanter ve FULL listesine birlikte
kaydedildi; FAST yeni modül için mevcut broad kuralını kullanır. Yeni testler
F6-A01–31 adlarıyla ilk20 zorunlu negatif senaryoyu ve ek contract bütünlüğünü kapsar.
Test20 sıfır dış etki bağımlılığını ve dört imported CREATE ret yolunu denetler;
bu canlı PROV-004 doğrulaması değildir. Native F5-E author_id/can_attach ve
attachment authorization kodları korunur.

Yeni migration yok;001–054 korunur. Rollback F6-A kod commit revert; DB down yok.
Forward-fix explicit contract version ile, eski provenance overwrite edilmeden.
Son exact uygulama SHA/tree, koruma manifesti, yerel ve gerçek FAST run/job
kanıtları doğrulama tamamlanınca bu rapora eklenecek. Henüz bu aday için FULL
ACCEPTANCE çalıştırılmadı ve dispatch yapılmayacak; eski CI kabulü yeni adaya taşınmaz.

## F6-A → F6-B bağımlılık sözleşmesi ve açık keşif

| Çıktı | F6-B'nin ayrıca sağlaması gereken |
|---|---|
| Immutable instance/entity/source ID ve snapshot manifest | G-O03 gerçek sürüm/schema/kolasyon/encoding/timezone/hash/dosya keşfi; tutarlı extract ve watermark |
| Content/envelope SHA ve MappingEvidence önerisi | Kalıcı unique claim, target uniqueness, transaction/checkpoint/outbox; güncel native ACL, MFA/step-up, explicit mapping CRUD yetkisi |
| Strict entity/parent/customer/provider-account doğrulaması | Gerçek kaynak adapter allowlist'i, physical provider account eşliği ve resource inspection; ikinci CREATE sıfır |
| Finance Money/currency ve unknown FX review | Immutable native ledger/provenance, currency bazlı bakiye/allocation/refund mutabakatı; sıfır tahmini tarihsel kur |
| Mesaj sırası/zaman/private note/yazar ve attachment manifest | Gerçek arşiv ve dosya checksum/MIME/AV/retention doğrulaması, native müşteri projection/attachment ACL korunması |

G-O03 açık liste: WHMCS sürümü ve kurulu modüller/custom fields; gerçek tablo/kolon
haritası ve değişmez ID'ler; source hash formatı; kaynak UTC/DST/kolasyon/encoding;
tutarlı snapshot/delta/watermark yöntemi; gerçek dosya kökleri/symlink/owner/MIME;
staff-user/customer membership ve historical ownership; para/kur/refund/proforma
vs official PDF eşliği. F6-A bunları doğrulanmış saymaz ve credential istemez.

SUP-010 gerçek arşiv kabulü, PROV-004 gerçek imported resource, DOMAIN-002
PARTIAL/MANUAL_REVIEW, mapping/import CRUD DEFERRED, PROV-005 OPEN kalır.
F5-C'nin dört canlı istisnası (routing/sender authentication; kesin retention
cutoff/scheduler; gerçek AV/IMAP/SMTP/genel MIME; mock HELD/ACCEPTED teslim değildir),
auto-close, merge/split, kurum geneli tarihsel SLA raporu ve tüm vendor/operasyon/live
GATE'ler açıktır. F6-B, gerçek import/provider, staging/merge/deploy/cutover ayrıca
proje sahibi talimatı ister. PR3 OPEN/DRAFT, main protection değişmez.
