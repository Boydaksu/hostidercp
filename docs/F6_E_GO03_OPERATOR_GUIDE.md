# G-O03 — manuel discovery CLI kullanım rehberi

Bu rehberdeki komut **ÖRNEK / ÇALIŞTIRILMAMIŞTIR**. Bu geliştirme görevi gerçek WHMCS erişimi,
metadata toplama veya gerçek credential kullanımı yetkilendirmez. Proje sahibi ayrı onay vermelidir.

## Desteklenen çalışma sınırı

PHP8.3+ CLI, PDO MySQL, POSIX euid; MariaDB10.11 metadata alt kümesi. Aracı operatör kaynak
sunucuda manuel çalıştırır; bağlantı yalnız127.0.0.1 TCP, ayrı port/account ile yapılır.
SSH/remote fetch/config arama, cron veya background worker yoktur. MySQL/vendor parity kanıtlanmadı.
Sabit information_schema SELECT dışında statement, SET/SHOW/SQL body/default/comment veya user row okunmaz.
64 table, table başına64 column ve64 index-entry, P3 export131072-byte sınırı;65inci kayıt BLOCKED,
sessiz truncation veya otomatik kaynak bölme yoktur. Unsupported view/prefix/descending/ignored/non-BTREE
index BLOCKED. Bilinmeyen charset/collation/type PARTIAL/UNKNOWN. İki okuma eşliği atomiklik değildir. P3 extracted_at/watermark collector host saatidir; gerçek application-row watermark değildir.
30s kontrolü query sonrası/öncesi budget'tır, server-side query-kill garantisi değildir; connection timeout3s.

## Gerçek çalıştırma öncesinde operatör sağlamalı

- Ayrı proje sahibi erişim onayı, doğru server/schema ve source/snapshot kimliği.
- Yeni dedicated read-only hesap: yalnız exact schema SELECT; global/başka schema/table/column
  grant, grant option, role, routine EXECUTE, proxy/auth-plugin ek yetki yok. Kolektör görünür
  grant/role/routine metadata'sını reddeder; hesap provisioning ve gizli/proxy authority audit'i değildir.
- Operatöre ait0700 güvenli yerel çalışma dizini; canonical absolute path, symlink/unsafe ancestor yok.
  Aynı UID saldırganı veya host compromise karşısında PHP path kontrolleri openat/no-follow garantisi değildir.
- Sahipliği doğrulanmış0600 regular config dosyası ve ayrı0600 regular32-byte binary rastgele HMAC key.
  Input FD'leri caller güvenli no-follow ile açmalıdır; symlink/izin/ACL/root custody caller sorumluluğudur.
- Private config alanları: port(integer), account, password, schema, source_id(source-+64hex),
  snapshot_id(snapshot-+64hex). Parola yalnız dedicated collector hesabına aittir; WHMCS config secret'ı değildir.
  Config/key argv/env/log/repository'ye konmaz. Gerçek credential veya müşteri örneği paylaşılmaz.

```sh
# ÖRNEK / ÇALIŞTIRILMAMIŞ — yalnız ayrı owner onayı ve güvenli FD açılışından sonra
php bin/discovery-whmcs.php \
  --ack-owner-approved-read-only-discovery \
  --config-fd=3 --key-fd=4 --private-dir="$PRIVATE_WORKDIR" \
  3<"$PRIVATE_CONFIG" 4<"$PRIVATE_KEY"
```

Shell redirection no-follow sağlamaz; örnek yalnız private input paths'in symlink/ancestor güvenliği
önceden doğrulanmışsa kullanılabilir. Daha güçlü FD açılışı gereken platformda operator güvenli
launcher kullanmalıdır. CLI yanlış input/yetki/erişimde exit1/sabit BLOCKED JSON üretir; sahte kaynak fallback yoktur.
Collector'a özel key custody/rotasyon ve private mapping operator sınırıdır; key/hash-of-key raporlanmaz.

Raw export0700 random alt dizinde exclusive0600 handle olarak oluşturulur, yazılmadan unlink edilir;
P3 tanıtıcıdan okur, finally close/rmdir yapılır. CLI stdout yalnız sanitize JSON özetidir. Çıktı dahi
paylaşılmadan operatör gizlilik incelemesinden geçmelidir. Keyed source/snapshot/metadata fingerprint,
envanter counts/coverage ve MATCHED_TWO_READS kaynak doğruluğu/attestation değildir.

## Bu araçtan çıkarılmayacak kanıtlar

WHMCS application version: yetkili operatör vendor-managed version bilgisini, secret/config içeriği
olmadan ayrıca beyan etmeli; metadata'dan sürüm tahmini yapılmaz.
Custom fields: yalnız definition envanteri ayrı sanitize sözleşmeyle; field values veya müşteri kayıtları yok.
Password algorithm: yetkili vendor dokümanı/operatör sınıflaması; parola veya gerçek hash örneği yok.
Attachment locations/file inventory: ayrı izinli private inventory ve opak kimlik/checksum/count;
path'ler ve dosya içerikleri GitHub'a konmaz. Source rows, mali uzlaşma, görünürlük ve byte eşliği bu araçta yoktur.
Type precision/default/generated behavior ve tam vendor mapping ayrı inceleme ister.

G-O03 OPEN, source_truth/operator_authority UNVERIFIED, cutover_allowed=false,
effects_allowed=false, real_gate_status=BLOCKED; başarılı metadata capture gerçek operasyon izni değildir.

Metadata alanları ve yardımcı dosya semantiği için birincil başvurular:
[MariaDB STATISTICS](https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-statistics-table),
[MariaDB USER_PRIVILEGES](https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-user_privileges-table),
[MariaDB APPLICABLE_ROLES](https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-applicable_roles-table),
[PHP fopen exclusive mode](https://www.php.net/manual/en/function.fopen.php).
