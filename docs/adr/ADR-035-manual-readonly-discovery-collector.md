# ADR-035 — manual read-only discovery collector

PROPOSED / independent review pending. 29 Ekim 2026.
Mevcut P2/P3 sözleşmeleri değişmez. MariaDB10.11 metadata alt kümesi; MySQL/vendor parity iddiası yok.
Yeni named query session yalnız sabit information_schema SELECT'leri çalıştırır. SHOW/SET/transaction,
SQL body/default/comment veya row sorgusu yoktur. CLI ayrı ack ve0600 config/key fd ister.
Bağlantı yalnız127.0.0.1, manual operator host; remote/SSH/config discovery yoktur.

Runtime görünür grant'ler: yalnız exact escaped schema SELECT, USAGE; global/table/column ekstra
yetki, grant option, applicable/enabled role ve görünür routine reddedilir. Bu operator account
provisioning veya proxy/auth-plugin kapsamının bağımsız audit'i değildir; least-privilege hesabı
ayrı operator doğrulaması zorunludur. Bilinmeyen metadata/eksik privilege görünürlüğü fail-closed.
Query row limits65 (max64),512 query,30s post-read budget,3s connect timeout; server query-kill
timeout garantisi yoktur. P3 byte/depth/node limitleri aynen korunur.

İki sıralı metadata okuması ve ön/son grant kontrolü değişimi reddeder; cross-query atomic
snapshot garantisi üretmez. Unsupported view/prefix/descending/ignored/non-BTREE index BLOCKED;
bilinmeyen type/charset/collation UNKNOWN/partial. Application version/password/files/custom
fields information_schema'dan uydurulmaz. Type precision/defaults toplanmaz, readiness iddiası yoktur.

Private POSIX0700 root/ancestor no-symlink kontrolü, random0700 child, exclusive x+b0600 file,
fstat/lstat eşliği; raw file yazılmadan unlink edilir. Açık tanıtıcı P3'e verilir ve finally close/
rmdir yapılır. Aynı UID saldırganı/host compromise ve path-race'e karşı OS-level openat garantisi
yoktur; güvenli root custody caller'a aittir. Key yalnız fd/call memory; mapping/export yayımlanmaz.
G-O03 OPEN; operator/source UNVERIFIED, effects/cutover false, real gate BLOCKED.
