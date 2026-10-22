# ADR-001 — F0 sınırı ve bağımlılıksız çekirdek

Tarih: 2026-10-22. Durum: güncel kullanıcı kickoff talimatına dayalı uygulama kararı.

`docs/12_ROADMAP.md` bootstrap/router/PDO'yu F1'de listeler; güncel kickoff bunları açıkça F0'a taşır. Kaynak hiyerarşisine göre güncel talimat uygulanır. F1 kullanıcı, TOTP enrollment/recovery, oturum yönetimi UI, API credentials ve impersonation akışlarını içerir; bu faz bunların tamamlandığını iddia etmez.

PHP 8.3+, PDO MariaDB, cron ile kısa çalışan worker. Production Composer gerektirmez; yerel PSR-4 autoloader ve native sodium kullanılır. Ağ istemcisi ve canlı sağlayıcı adapter'ı yoktur. Composer runtime bağımlılığı eklenmez. Statik analiz başlangıçta token/mimari tarama; tam tip analizi ayrı kalite açığı olarak izlenir.

İş ve outbox aynı transaction; dış yan etki transaction dışında. Güvensiz iş hata/lease kaybında UNKNOWN; otomatik tekrar yalnız açıkça yerel/idempotent işlerde. Lease token stale worker'ın yerel commit'ini engeller; harici serviste fencing desteği varsayılmaz.

Migration DDL transaction'a sarılmaz; lock + kalıcı APPLYING/APPLIED kaydı ve checksum kullanılır. APPLYING kaldığında otomatik devam yok; operatör restore/forward-fix yapar.

Loglar serbest metin/payload kabul etmez; allowlist event kodu, trace ve exception sınıfı. Audit metadata da allowlist'tir. Sağlayıcı SDK'sı runtime'a eklenmez: auth hatasında POST'un yeniden gönderilebilmesi, SSL kapatma seçeneği ve eksik PII redaksiyonu canlı kullanım öncesi kapatılmalıdır (G-V02/G-V03).

Referanslar: R3-07, R3-10, R4-01..07, R6-01, R6-03, R7-07; FR-OPS/ID/NOTIFY; OPS-001/003/005, PROV-001.
