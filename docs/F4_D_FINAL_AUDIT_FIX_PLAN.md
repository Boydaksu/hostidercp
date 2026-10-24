# F4-D son hedefli düzeltme planı

Taban 4bde80fd34adfd390fc9ff2f0110e957dd2ad1b2; 313 test. FR-VOYAR,
PROV-002/004/006, SEC-004, OPS-005. Yalnız son P0 ve P1 devam güvencesi.

Kapsam: financial_context yalnız audit olarak kalır. Cloud fiziksel UNKNOWN,
financial containment sırasında hizmetin bağımsız durumunu ezmez. apply güncel canonical
kaynağı ve kilitli hizmet durumunu yeniden okur; geçersiz finans SUSPENDED, bağımsız
suspension korunur, miras UNKNOWN otomatik ACTIVE yapılamaz. Task/resource kanıtı
finans değişse de işlenir; doğrulanmış terminate ve durable fence korunur.

Dosyalar: Cloud Engine, Services Cloud gateway status kilidi; Domain EventSink yalnız
receipt/completion ayrımı; yeni cloud-final-audit MariaDB testleri ve CI suite satırı.
Migration: yeni tablo gerekmiyor; 001–045 byte/hash eşliği korunur.

Test: önce eski kaynakta karşı-örneği göster; 313 mevcut + yeni ayrı MariaDB regresyonları.
Gerçek Identity native session; refund/cancel/bağımsız suspension, eksik task,
valid source, duplicate reconciliation, tek POST/tek effect ve finans hash eşliği.
Queue teslimi sonrası Cloud PENDING/WAITING_TASK ve Domain PENDING için ayrı PHP cron.
PHP 8.3/8.4 tam CI, PHPStan, Composer audit, HTTP/session ve 1/4/8 worker.

Geri dönüş: cron durdur; UNKNOWN replay yok; tutarlı kod/DB/APP_KEY restore veya forward-fix.
Tarihsel snapshot'tan ACTIVE geri yükleyen eski sürüme otomatik dönüş yok.
F4-E/F/F5, merge, production ve gerçek provider yok; DOMAIN-002 PARTIAL/MANUAL_REVIEW,
G-V01–04 ve diğer GATE'ler OPEN kalır. Sonuç docs/F4_D_FINAL_AUDIT_FIX.md içinde raporlanır.
