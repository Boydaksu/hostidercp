# 04 — Mantıksal veri modeli ve tutarlılık

## Tasarım sözleşmesi
- MariaDB InnoDB; bütün dış referanslar explicit FK veya kontrollü polimorfik `entity_type/entity_id` registries üzerinden.
- İç ilişkiler BIGINT UNSIGNED; dış dünyaya tahmin edilemeyen public ID (UUIDv7/ULID veya güvenli random ID) kullanılır.
- Tüm domain entity'lerde `created_at`, `updated_at`; optimistik kilit gerektirenlerde `version`; soft-delete yalnız hukuki/iş gereksinimine uygun alanlarda. Finans fişleri **silinmez**.
- UTC `DATETIME(6)`, IANA timezone ayrı; para için minor units BIGINT ve currency exponent; fiyat/saatlik oranlar `DECIMAL(24,10)` gibi kesin decimal ve tek noktadan final rounding. Kur `DECIMAL(24,12)`; php float kesin finans mantığında yok.
- Tutarlar: hesapla (precision yüksek), vergi/indirim dizisini kurallı yuvarla, para birimine indir, verilen satış fiyatını immutable snapshot yap.
- Idempotency ve provider external id üzerinde unique kısıt; müşteri aidiyet kontrolü uygulama katmanında, kritik ilişki FK ile.

## Önerilen tablolar (çekirdek)
| Modül | Tablolar ve önemli alanlar |
|---|---|
| Identity | `users(id,email,password_hash,verified_at)`, `customers(id,name,billing_currency)`, `customer_users(user_id,customer_id,role_id)`, `staff_roles`,`permissions`,`role_permissions`,`user_sessions`,`two_factor_factors`,`api_credentials`,`impersonation_sessions` |
| Catalog | `product_categories`, `products`, `product_variants`, `option_groups`, `option_values`, `product_addons`, `product_prices(product_id,variant_id,billing_cycle,currency,amount_minor,pricing_method,version)`, `product_provider_mappings`, `price_publications`, `pricing_rules`, `exchange_rates`, `tax_rules`, `promotion_rules`, `promotion_redemptions` |
| Checkout | `quotes(id,customer_id,expires_at,currency,total_minor,fx_snapshot,pricing_snapshot)`, `orders`, `order_items`, `order_adjustments`, `payment_requests`, `payment_request_lines` |
| Finance | `payment_attempts`, `payments`, `payment_allocations`, `wallet_allocations`, `wallet_accounts`, `wallet_entries`, `wallet_fx_conversions`, `refunds`, `ledger_journals`, `ledger_lines`, `cash_categories`, `cash_entries`, `recurring_cash_rules`, `official_invoice_documents` |
| Service | `services`, `service_periods`, `service_resources(provider_account_id,external_id)`, `service_configs`, `service_prices`, `renewal_policies`, `lifecycle_operations`, `provider_operations`, `service_transfers`, `service_transfer_approvals`, `service_ownership_history`, `service_events` |
| Domain | `domain_tlds`, `domain_tld_provider_rules`, `domains`, `domain_contacts`, `domain_locks`, `domain_operations`, `domain_transfer_events` |
| Hosting | `hosting_servers`, `hosting_packages`, `hosting_server_health`, `hosting_capacity_snapshots`, `hosting_accounts` |
| Cloud | `cloud_products`, `cloud_quote_snapshots`, `cloud_capabilities`, `cloud_usage_costs`, `cloud_billing_reconciliations`, `cloud_vm_resources` |
| Support | `support_departments`, `support_department_members`, `support_business_calendars`, `support_calendar_exceptions`, `support_sla_policies`, `support_sla_clocks`, `support_tickets`, `support_messages`, `support_attachments`, `support_ticket_links`, `support_assignments`, `support_mailboxes`, `support_inbound_messages`, `support_email_review`, `support_outbound_messages`, `support_incidents`, `support_incident_tickets`, `support_automation_rules`, `support_rule_executions`, `support_ratings`, `support_drafts` |
| Infrastructure | `jobs`, `job_attempts`, `outbox_events`, `inbox_events`, `dead_letters`, `notifications`, `notification_templates`, `audit_logs`, `security_events`, `backups`, `restore_tests`, `schema_migrations`, `deployment_history`, `migration_mappings` |

## Temel ilişkiler
- `Customer 1—N CustomerUsers`, `Customer 1—N Orders`, `Customer 1—N PaymentRequests`, `Customer 1—1 WalletAccount (currency=current customer currency)`.
- `Product 1—N Variants 1—N Prices`; fiyat yayın sürümleri immutable.
- `Order 1—N OrderItems`; bir order item bir veya daha çok `Service` ortaya çıkarabilir; servis geçmişi eski siparişle kalıcı bağlı.
- `PaymentAttempt 0..1—1 Payment`; `Payment N—M PaymentRequest` `payment_allocations` ile; **cüzdan borçlandırmaları** ayrı `wallet_allocations` ile payment request'e bağlanır. Ödeme kaynakları kupon değil, bakiye/PayTR/havale olarak ayrıdır.
- `PaymentRequest N—M ServicePeriod` mantıksal `billing_targets` aracılığıyla; yenilemeler yeni `ServicePeriod` üretir.
- `Service N—1 current Customer` ve `Service 1—N OwnershipHistory`; eski müşteriye ait faturalar sahiplik aktarımında hareket etmez.
- `Service 1—N ProviderOperation`; `Service 1—N ServiceResources` ancak aynı anda bir aktif birincil kaynak kuralı uygulanır.
- `Ticket N—1 Customer`; `Ticket N—M Service,Invoice,Order,Domain` görme izniyle; `Ticket 1—N Messages`.
- `SupportIncident N—M Tickets` ve birincil platform olayı, ticket'ların SLA'sından bağımsızdır.

## Finansal tutarlılık kuralları
- Ledger dengeli çift taraflı defter gibi muhasebeleştirilebilir (debit=credit, aynı currency/valuation). V1 UI tek kasa; ayrı finansal kayıt sistemi saklı tutulur.
- Her `payment_attempt` benzersiz `merchant_oid`; başarılı PayTR callback en fazla bir `payment`, her business allocation unique `(payment_id, payment_request_id,allocation_reference)`.
- `wallet_entries` yalnız append: topup pending → confirmed credit, reservation/charge debit, cancellation compensation credit; eksi bakiye izinsiz oluşamaz.
- Refund max = ilgili orijinal payment'ın daha önce iade edilmemiş tahsilat parçası; cüzdan harcamasının geri alımı yeni wallet credit fişi.
- Her ödeme talebi bir para biriminde ve değişmez `total_minor`/`tax_minor`/`fx_snapshot`; geçmiş döviz kurları yeniden fiyatlandırma tetiklemez.
- Product price update yeni `price_publication` üretir; yenileme anında aktif policy değerlendirilip yeni dönem snapshot'ı oluşturulur.
- Promosyon kullanımı quota ve müşteri limitleri DB transaction içinde atomik rezervasyon/commit ile tutulur.
- Provider cost `cloud_usage_costs` ayrı tablodur; müşteri tahsilatının kendisi değildir. Sağlayıcı faturası mutabakatıyla ilişkilenir.

## Durum/işlem kısıtları
- `service.status`: `PENDING_PAYMENT`, `PENDING_APPROVAL`, `QUEUED`, `PROVISIONING`, `ACTIVE`, `SUSPENDED`, `CANCEL_SCHEDULED`, `TERMINATING`, `TERMINATED`, `FAILED`, `UNKNOWN`.
- `provider_operations.status`: `PENDING`, `RUNNING`, `SUCCESS`, `DEFINITIVE_FAILURE`, `UNKNOWN`, `MANUAL_REVIEW`.
- `payment_requests.status`: `DRAFT`, `ISSUED`, `PARTIALLY_PAID`, `PAID`, `VOIDED`, `REFUNDED_PARTIAL` (iade durumu ayrıca tutulur).
- `service_transfers.status`: `REQUESTED`, `OWNER_APPROVED`, `RECIPIENT_APPROVED`, `READY`, `COMPLETED`, `EXPIRED`, `REJECTED`, `CANCELLED`, `BLOCKED_DEBT`.
- `support_tickets`: görünen özel status ID; sistem kategorileri sabit `NEW,WAITING_STAFF,WAITING_CUSTOMER,ON_HOLD,RESOLVED,CLOSED`.
- Durumlar serbest string set edilmez; transition service atomik olay üretir.

## DB indeksleri ve referanslar
- `users(email_normalized)` UNIQUE; `customer_users(customer_id,user_id)` UNIQUE.
- `services(customer_id,status,next_due_at)`, `service_resources(provider_account_id,external_id)` UNIQUE.
- `provider_operations(operation_uuid)` UNIQUE; `(service_id,kind,status)` seçici indeks; in-flight concurrency ayrıca row lock.
- `payment_attempts(provider,merchant_oid)` UNIQUE; `payments(provider,provider_transaction_id)` UNIQUE NULL değilse.
- `quotes(customer_id,expires_at)`, `payment_requests(customer_id,status,due_at)`.
- `support_tickets(customer_id,updated_at)`, `(department_id,status_id,assigned_user_id,updated_at)`, `support_messages(ticket_id,created_at)`, `support_inbound_messages(mailbox_id,message_hash)` UNIQUE.
- `jobs(queue,status,run_after)`, `outbox_events(publish_status,created_at)`, `jobs(dedupe_key)` unique only when meaningful.

## Migration kuralları
- Her migration idempotent? Migration framework tek uygulamalı; migrations apply-once unique checksum, transaction destekleyen DDL ve MariaDB sınırlamalarını bil.
- **Expand → backfill → verify → switch → contract**; destructive alter başka sürüme kalır.
- Üretimde otomatik downgrade garantisi verilmez; veritabanı snapshot'ıyla rollback planı kurulmalıdır.
- `sql/001_core_schema_blueprint.sql` yalnız örnek kritik tablolar; geliştirici tüm şema/constraint/testleri üretmekle yükümlüdür.
