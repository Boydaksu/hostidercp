# F2-D sipariş ve ödeme talebi — çalışma raporu

FR-CHECKOUT, R3-05/07, R7-02; FIN-001/003 temel senaryoları. Migration 010: ayrı orders/items/events ve immutable billing_requests + değişebilir request state tablosu. Quote hazırlık aşamasıdır; sipariş müşteri quote'u kabul edince AWAITING_PAYMENT olur. Kısmi ödeme PARTIALLY_PAID, tam ödeme ürün politikasına göre READY_FOR_PROVISIONING/REQUIRES_APPROVAL; ödenmemiş iptal CANCELLED ve VOIDED. Fonlanmış siparişi iptal etmek açık iade politikası olmadan reddedilir. FAILED mali kayıtları silerek üretilmez; transaction hatası önceki tutarlı durumu korur.

Billing toplamı/kur/vergi/indirim snapshot'ını ve şifreli müşteri snapshot'ını korur. Cüzdan ve tahsilat Finance application service üzerinden, order→request→wallet/payment kilit sırasıyla aynı transaction'a katılır. Current-read allocation toplamı eski repeatable-read snapshot'ından hesaplanmaz. Fazla ödeme allocation olarak kabul edilmez; tahsilat unallocated kalır. İdempotency sınırı mevcut Core servisidir. Tek order quote unique, tek order ready event unique; hiçbir F3 provisioning handler'ı eklenmedi.

6 MariaDB testi: sabit proforma ve müşteri snapshot'ı, karma/kısmi ödeme+tek event+idempotency, transaction kesintisi, overpayment/cancel, müşteri ve impersonation izolasyonu, iki bağımsız PHP funding sürecinde tek readiness olayı. Önceki kod/migration/testler korunur. Gerçek HTTP yetki ve confirmation doğrulaması G'de tamamlanacak.

Sınırlar: order PAID ile aktif hizmet karıştırılmaz; bu fazda gerçek hizmet yok. Proforma resmî vergi faturası değildir. Muhasebe ledger revenue alanı iç satış kaydıdır; resmî gelir/muhasebe beyanı F/G raporlarında açık etiketlenecek. Gerçek PayTR veya dış kur yok, canlı GATE'ler OPEN. Otomatik refund ve mali müşavir kararı uydurulmaz.

Rollback: trafik/cron durdur, dosya+DB+APP_KEY snapshot birlikte restore veya forward-fix. Önceki 001–009 migration değişmez. 010 down/DROP içermez; finansal belge veya ödeme geçmişi silinmez.
