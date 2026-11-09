# ADR-036 — scoped durable SYSTEM_AUTO_CLOSE policy

PROPOSED / independent code review pending. 30 Ekim 2026.
R2-06 yalnız test/development kapsamı; live auto-close NOT AUTHORIZED.

Normal support_events actor/session FK'leri staff-only kalır. System close mevcut Support.State,
Service update seam, Support.Work.Store immutable event+audit+HELD Outbox ve yeni055 receipt kullanır.
Sahte staff hesabı/session yaratılmaz; actor SYSTEM_AUTO_CLOSE, sponsor/policy/resolution ayrı provenance.
SLA cycle RESOLVED'ta sonlanmıştır; auto-close geçmiş clock/history'yi tekrar hesaplamaz.
Aktif SLA clock kalmışsa fail-closed skip. Native customer reply/reopen State allow_reopen aynen uygulanır.
AUTO_CLOSED Work event'i Automation.trigger allowlist'inde yeni action tetiklemez; rule loop/mail yoktur.

Policy interactive değişikliği mevcut native administrative fresh authorization+department-wide grant,
membership, MFA, CSRF ve payload/state-bound300s confirmation ister. Worker authn session yaratmaz;
mevcut Eligibility capability-selection örüntüsüyle Repository principal/grants çözümünü kullanır,
issuer active/current identity_version/MFA-enabled/administrative capability ve wildcard department
scope tekrar doğrulanır. Bu yalnız önceden confirmed policy'nin sabit RESOLVED→CLOSED hakkını sınırlar;
generic admin yetkisi veya yeni login mekanizması değildir. Aktör daima SYSTEM_AUTO_CLOSE.
Policy logout sonrasında da ayardır; session revoke prepare/execute yolunu iptal eder, issuer capability
değişikliği policy işlemesini durdurur. Disable/republish yeni immutable revizyondur.

Native Queue enqueue retry_safe=true, max_attempts3 varsayılan; eski policy/ticket/resolution pin'leri
stale olduğunda terminal no-op/SUCCEEDED, storage hatası native bounded retry/dead-letter yolundadır.
Sweep max25/20s, tek geçiş; dry-run ticket/job/audit/business mutation yapmaz. Cron kurulmaz.
Queue lease failure sonrası replay receipt/state re-read ile güvenlidir; dış effect yoktur.
Batch seçiminde daha önce seed edilmiş exact dedupe key atlanır; failed receipt/job kendiliğinden
yeni authority kazanmaz, tekrar için operator policy review/republish gerekir.

055 yalnız yeni3 tablo/immutable trigger ekler. Native support event FK/trigger, completed SLA history,
Identity/Queue/Mail sözleşmesi gevşetilmez. Migration failure APPLYING native readiness'i bloklar;
destructive down değil, backup restore veya reviewed forward-fix gerekir.
