# G-O03 collector — plan ve teslim

29 Ekim 2026. OPS-004/G-O03 hazırlığı; independent review pending.
Base f0b1db72c5fae63294e8d01b79136d9d089ffb87, tree6aabe66764f1714edb388aebe7daba59b96e0553.
Kapsam: yeni MetadataSession, Collector, PrivateExport, DiscoveryCli ve bin/discovery-whmcs.php;
sentetik fixture/suite, iki kayıt, kısa kullanım rehberi ve ADR-035.
Mevcut R2/P0/P1/P2/P3 kaynakları ve migration korunur.
Test: yeni suite önce; P2/P3/F6-A/B/C/hardening, ücretsiz Docker PHP8.3/8.4;
PHPStan/lint/pattern/docs ve kaynak before/after SHA-256.
Rollback: yalnız yeni collector ekleri ve iki suite kaydı, migration yok; ayrıca yetki olmadan revert yok.
Başarı sonrası tek [skip ci] commit ve exact-parent fast-forward, sonra bağımsız inceleme için dur.
Gerçek WHMCS erişimi/metadata toplama yapılmaz; G-O03 OPEN, source/operator UNVERIFIED, effects/cutover false.


## Çalışan araç ve kullanım

G-O03 DISCOVERY TOOL: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING.
[Operatör rehberi](F6_E_GO03_OPERATOR_GUIDE.md) komut/config-FD/key-FD ve gerçek çalıştırma öncesi
onay/hesap/no-follow/izin kontrol listesini içerir. Komut **ÖRNEK / ÇALIŞTIRILMAMIŞTIR**.
Bu görevde gerçek WHMCS bağlantısı/credential arama/metadata toplama yapılmadı; yalnız izole
sentetik Docker MariaDB çalıştı. Araç yararlı gerçek collector kodudur; sahte kaynak fallback yoktur.

MetadataSession fresh loopback PDO kullanır: named, fixed, parameter-bound information_schema SELECT,
row/query/budget limitleri; app table, DML/DDL/CALL/SHOW/SET veya routine body sorgusu yoktur.
Privilege fence exact dedicated schema SELECT ve USAGE dışını, grant option/role/routine yetkisini
reddeder. Görünmeyen proxy/auth-plugin yetkileri ve account provisioning ayrı operator audit'idir;
collection sonucu bağımsız least-privilege attestasyonu değildir. Yanlış/unsupported metadata BLOCKED.

Collector schema/table/column/type/nullability/index/ordered composite columns/charset/collation okur.
İki capture ve ön/son görünür yetki eşliği değişimi engeller; MATCHED_TWO_READS global atomic snapshot
kanıtı değildir. P2/P3 limitleri korunur:64 table,64 column/index-entry per table,131072-byte export;
65inci kayıt veya büyük export sessiz truncate edilmez. Prefix/descending/ignored/non-BTREE index ve
view BLOCKED. Bilinmeyen charset/type/collation PARTIAL. Column precision/defaults/generation ve
application-row watermark doğrulanmaz. P3 timestamp/watermark yalnız collector host extraction saatidir.

PrivateExport POSIX0700 private root/ancestor kontrolü, random0700 child ve exclusive0600 handle
kullanır. Dosya raw byte yazılmadan unlink edilir; açık descriptor P3'e verilir, finally close/rmdir
olur. Güvenli root custody ve aynı UID/host compromise sınırı caller'a aittir; PHP openat/no-follow
OS garantisi iddia edilmez. HMAC key/mapping/raw export rapor/log/repository'ye yazılmaz.
P2/P3 değişmeden kullanıldı; yeni JSON/schema/migration engine eklenmedi. CLI stdout yalnız sanitize
counts/fingerprint/coverage/missing-list üretir; raw manifest döndürmez.

WHMCS version, custom fields, password algorithm, attachment locations/file inventory UNKNOWN.
Bunlar metadata'dan çıkarılmaz; guide yalnız definition/version/vendor-sourced sanitized kanıt,
private opaque inventory ve ayrıca owner izni gerektirir. Gerçek müşteri örneği/config/hash istenmez.

## Test ve kalite kanıtı

| Suite | PHP8.3.36 PASS/FAIL | PHP8.4.27 PASS/FAIL |
|---|---:|---:|
| Yeni collector/CLI |22/0|22/0|
| P3 inventory adapter |27/0|27/0|
| P2 discovery |33/0|33/0|
| F6-A contract |31/0|31/0|
| F6-A visibility |8/0|8/0|
| F6-B import |43/0|43/0|
| F6-C reconciliation |35/0|35/0|
| Hardening |7/0|7/0|
| **Toplam** |**206/0**|**206/0**|

Yeni22 case gerçek izole MariaDB least-privilege hesabı ve ayrı CLI süreçlerini kullanır.
Kolon/index/composite sıra, HMAC gizlilik/source/snapshot/key ayrımı, eksik/mutated metadata,
charset UNKNOWN, yetersiz/global/write/grant-option/role/routine yetkileri, arbitrary SQL denial,
actual source row eşliği, table overflow, prefix/view fail-closed, private directory symlink/type/
permissions, export mismatch/oversize/malformed cleanup, insecure config FD, missing ack/credential
failure, P2/P3 uyumu ve sabit G-O03/no-effects assert edilir. Duplicate/partial/change negative
observations, gerçek capture'ın kontrollü bellek tahrifatıyla production conversion/consistency
metotlarını sınar; bunlar gerçek tarihsel source attestation değildir. Test setup DDL/DML yalnız
owned sentetik schema/user oluşturmak içindir, collector kodunun SQL allowlist'inde bulunmaz.

Her runtime PHPStan level5:0 hata;472 PHP lint:0 ihlal;494 source/config/test pattern:0 bulgu;
belge encoding/credential check exit0. Bu görevde başarısız koşu yoktur; önceki historical failures
aynen korunur. [Summary](evidence/f6-e-go03/summary.json), iki runtime JSON/TXT ve
[runner proof](evidence/f6-e-go03/runner-source-proof.json) test ortamı/image IDs ve task-owned
cleanup'ı bağlar. Runner review kopyasında yalnız sabit disposable test DB parola literal'i redakte
edilmiştir; gerçek credential/key/ham metadata yoktur. Temp DB ve kaynak workspace tmpfs/internal
network'te, readonly source bind ve host portu olmadan çalıştı. Görev kaynakları cleanup edildi.
FULL, Chromium, Actions ve güncel Composer/npm audit çalıştırılmadı; R2 yöntem istisnası aktarılmadı.

## Kaynak bütünlüğü ve yayın

[494 dosyalı kaynak manifesti](evidence/f6-e-go03/candidate-source-manifest.json) SHA-256:
`5fce854aee56cd7a2e27cf3c327d68f93e29fded2da7e77a914c6d76834001ef`.
[Yeni7 kaynak/test manifesti](evidence/f6-e-go03/new-source-manifest.json),
[source diff](evidence/f6-e-go03/source.diff), [koruma](evidence/f6-e-go03/preservation.json),
[artifact manifesti](evidence/f6-e-go03/manifest.json) ve changed-files listesi teslimi bağlar.
Test edilen kaynak before/after/host hash eşliğiyle sabittir. Source_commit null testlerin yayın
öncesi yapıldığını belirtir; final commit SHA/tree görev sonucunda ayrıca doğrulanıp bildirilir.

388 önceki src/tests,54 migration ve530 historical evidence blob-equal; iki suite kaydı dışında
bütün1189 eski dosya aynıdır. Production migration subtree
`c0729c92dd143c017fdec32421bf67fe6b51e05b` değişmez. Kullanıcı F6_B_B01_REVIEW düzenlemesi
hash ile korunur. Reset/clean/rebase/merge veya eski worktree temizliği yoktur.
Registry65 suite/917 test yalnız beklenen sayım, FULL PASS değildir. Workflow trigger/permissions
aynı; yalnız FULL suite listesine yeni kayıt. Yeni dependency/framework/daemon/provider yoktur.

Başarı sonrası tek `feat(F6-GO03): add read-only WHMCS discovery collector [skip ci]` commit,
exact parent f0b1db72c5fae63294e8d01b79136d9d089ffb87 ve fast-forward. [skip ci] CI PASS değildir.
Rollback yalnız ayrı yetkiyle bu collector ekleri ve iki suite kaydı; migration rollback gerekmez.
Gerçek erişim için proje sahibi onayı, least-privilege hesabı, trusted host/root/FD/key custody ve
kaynak doğruluğu/row/file/mapping/finance mutabakatı hâlâ gereklidir. Araç bunları kendi kendine onaylamaz.

G-O03 DISCOVERY TOOL: DEVELOPMENT VALIDATED / INDEPENDENT CODE REVIEW PENDING
REAL WHMCS SOURCE DISCOVERY: NOT EXECUTED
G-O03: OPEN
FULL / LIVE CUTOVER / MERGE / STAGING / PRODUCTION: NOT AUTHORIZED
