# F6-D-R2 — sınırlı bağımsız teknik kabul kaydı

28 Ekim 2026. Proje sahibinin bu görevde ilettiği bağımsız teknik incelemeci kararı aynen:

**F6-D-R2 LIMITED SYNTHETIC READ-ONLY RECOVERY RECONCILIATION — ACCEPTED WITH EXPLICIT EXCEPTIONS**

Bu karar Codex'in kendi denetiminden çıkardığı yeni bir kabul değildir; kullanıcı tarafından iletilen ayrı incelemeci kararının kaydıdır.
Yalnız application `53162495902b3b168c2fb8554594e99c23a34d62`,
tree `e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47`,
evidence `cc94f662454cb3f030addd2db0785a09900c0ed2` için geçerlidir.
Production migrations001–054 subtree `c0729c92dd143c017fdec32421bf67fe6b51e05b` korunur.
Başlangıç GitHub HEAD `546dda93f1ba49387cd9702c3693c7b2dcac3d63` belge yayınıdır.

[ADR-030](adr/ADR-030-f6-d-r2-local-docker-ci-method-exception.md) owner yöntem onayını ayrı kaydeder.
PHP8.3.36 ve8.4.27 her sürümde ayrı50/398 +9/227 +support157 =60 paket/782 test;
tek uninterrupted broad PASS yoktur.40/40 immutable manifest hash'i ve önceki exact-SHA kalite
kayıtları kabul kapsamının kanıtıdır; son support quality_exit:0 placeholder, quality_rerun:false.
İlk support59 PASS/1 FAIL/97 çalışmayan, devam60 PASS/1 FAIL/96 çalışmayan,
closure tanısı31 PASS/66 FAIL ve önceki R1 setup25/1 tarihçesi korunur; PASS toplamına eklenmez.
GitHub FAST NOT_RUN_EXTERNAL_BILLING; yerel sonuç GitHub Actions PASS/FULL değildir.

PASS ⇒ immutable_evidence MATCHED ⇒ observed_target_evidence_sha256 == verified.logical_sha256
ve farklı ordered logical root'un PASS olmaması kabul sınırıdır. SELECT-only, owner/privacy,
finance ve pinned signature/source/target policy fence'leri gevşetilmez.

Açık istisnalar: MariaDB10.11.18 tam image metadata eşleştirmesi yerel inspect gözlemidir;
tarihsel SELECT VERSION()/bağımsız execution attestasyonu yoktur. Eski768MiB hatasının kesin
ENOSPC nedeni ve ham tarihsel kapasite örnekleri kanıtlanmadı; dar inceleme için diagnostic gap.
Native runtime recovery BLOCKED; original archive bytes OPEN; issuer recovery PARTIAL/OPEN;
offsite/key custody/PITR/RPO/RTO NOT VERIFIED; executable inventory NOT_VERIFIED_SELECT_ONLY.
F6-A/B/C ve R1 önceki sınırlı kabulleri, genel F6-D PARTIAL ve tüm gerçek GATE'ler korunur.

Yeni yetki yalnız F6-E-P0 offline/sentetik preflight kodu ve test geliştirmesidir.
F6-E gerçek cutover, FULL, merge, staging, production, gerçek WHMCS/provider/payment/mail yetkisi yoktur.
Yeni preflight kaynak adayı bu R2 yöntem istisnasını veya teknik kabulünü otomatik devralmaz.
Kapanış belgeleri ve yeni kod ayrı mantıksal gruplardır; otomatik push/merge yoktur.
Rollback yalnız görev belge ekleri ve yeni preflight kaynakları için kontrollü incelemedir;
accepted R2 kaynak/kanıt veya kullanıcı dosyası geri alınmaz.
