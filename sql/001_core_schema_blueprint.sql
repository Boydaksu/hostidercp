-- HOSTIDER AUTOMATION V1 — DATA MODEL BLUEPRINT ONLY
-- !!! BU SQL ÜRETİM MIGRATION'I DEĞİLDİR. Kısmi / örnek şema.
-- Geliştirici tüm tabloları, constraints, migration sırasını ve audit/refund defterini tamamlamalı.
-- MariaDB 10.11+ hedef, InnoDB, utf8mb4, UTC DATETIME(6).
-- İlk örnek tablolar migration runner tarafından sırayla oluşturulmalı; bu dosyayı üretimde doğrudan çalıştırmayın.

CREATE TABLE customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(26) CHARACTER SET ascii NOT NULL,
    display_name VARCHAR(191) NOT NULL,
    billing_currency CHAR(3) CHARACTER SET ascii NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_customer_public (public_id),
    KEY idx_customers_currency (billing_currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(26) CHARACTER SET ascii NOT NULL,
    email VARCHAR(254) NOT NULL,
    email_normalized VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_user_public (public_id),
    UNIQUE KEY uq_user_email (email_normalized)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_users (
    customer_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    access_role VARCHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (customer_id, user_id),
    CONSTRAINT fk_cu_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
    CONSTRAINT fk_cu_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(26) CHARACTER SET ascii NOT NULL UNIQUE,
    category_id BIGINT UNSIGNED NULL,
    slug VARCHAR(128) NOT NULL,
    product_type VARCHAR(32) NOT NULL,
    base_currency CHAR(3) CHARACTER SET ascii NOT NULL,
    pricing_strategy VARCHAR(48) NOT NULL,
    activation_mode VARCHAR(16) NOT NULL DEFAULT 'AUTO',
    status VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
    current_price_publication BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_products_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_prices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    price_publication_id BIGINT UNSIGNED NOT NULL,
    billing_cycle_code VARCHAR(32) NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    amount_minor BIGINT NOT NULL,
    source_amount_decimal DECIMAL(24,10) NULL,
    source_currency CHAR(3) CHARACTER SET ascii NULL,
    pricing_strategy VARCHAR(48) NOT NULL,
    tax_inclusive TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    KEY idx_price_product_cycle (product_id,billing_cycle_code,price_publication_id),
    CONSTRAINT fk_price_product FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(26) CHARACTER SET ascii NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    total_minor BIGINT NOT NULL,
    tax_minor BIGINT NOT NULL DEFAULT 0,
    pricing_snapshot LONGTEXT NOT NULL,
    fx_snapshot LONGTEXT NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    KEY idx_quote_customer_expiry (customer_id, expires_at),
    CONSTRAINT fk_quote_customer FOREIGN KEY(customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(26) CHARACTER SET ascii NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    quote_id BIGINT UNSIGNED NULL,
    status VARCHAR(32) NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    subtotal_minor BIGINT NOT NULL,
    tax_minor BIGINT NOT NULL,
    total_minor BIGINT NOT NULL,
    paid_minor BIGINT NOT NULL DEFAULT 0,
    fx_snapshot LONGTEXT NOT NULL,
    pricing_snapshot LONGTEXT NOT NULL,
    due_at DATETIME(6) NULL,
    issued_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    KEY idx_pr_customer_status_due (customer_id,status,due_at),
    CONSTRAINT fk_pr_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
    CONSTRAINT fk_pr_quote FOREIGN KEY(quote_id) REFERENCES quotes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_request_id BIGINT UNSIGNED NOT NULL,
    gateway VARCHAR(32) NOT NULL,
    merchant_oid VARCHAR(64) CHARACTER SET ascii NOT NULL,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    state VARCHAR(32) NOT NULL DEFAULT 'CREATED',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_attempt_gateway_oid (gateway,merchant_oid),
    CONSTRAINT fk_attempt_request FOREIGN KEY(payment_request_id) REFERENCES payment_requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_attempt_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    gateway VARCHAR(32) NOT NULL,
    provider_transaction_id VARCHAR(191) NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    amount_minor BIGINT NOT NULL,
    status VARCHAR(24) NOT NULL,
    settled_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_payment_attempt (payment_attempt_id),
    UNIQUE KEY uq_provider_txn (gateway,provider_transaction_id),
    CONSTRAINT fk_payment_attempt FOREIGN KEY(payment_attempt_id) REFERENCES payment_attempts(id),
    CONSTRAINT fk_payment_customer FOREIGN KEY(customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_request_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    amount_minor BIGINT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_allocation_pair (payment_request_id,payment_id),
    CONSTRAINT fk_alloc_req FOREIGN KEY(payment_request_id) REFERENCES payment_requests(id),
    CONSTRAINT fk_alloc_payment FOREIGN KEY(payment_id) REFERENCES payments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) CHARACTER SET ascii NOT NULL,
    balance_minor BIGINT NOT NULL DEFAULT 0,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_wallet_customer (customer_id),
    CONSTRAINT fk_wallet_customer FOREIGN KEY(customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_account_id BIGINT UNSIGNED NOT NULL,
    operation_key VARCHAR(128) CHARACTER SET ascii NOT NULL,
    entry_type VARCHAR(32) NOT NULL,
    amount_minor BIGINT NOT NULL,
    fx_evidence LONGTEXT NULL,
    reference_type VARCHAR(32) NOT NULL,
    reference_id VARCHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_wallet_operation (wallet_account_id,operation_key),
    CONSTRAINT fk_wallet_entry FOREIGN KEY(wallet_account_id) REFERENCES wallet_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_request_id BIGINT UNSIGNED NOT NULL,
    wallet_entry_id BIGINT UNSIGNED NOT NULL,
    amount_minor BIGINT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_wallet_allocation (payment_request_id,wallet_entry_id),
    CONSTRAINT fk_wallet_alloc_req FOREIGN KEY(payment_request_id) REFERENCES payment_requests(id),
    CONSTRAINT fk_wallet_alloc_entry FOREIGN KEY(wallet_entry_id) REFERENCES wallet_entries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(26) CHARACTER SET ascii NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL,
    billing_cycle_code VARCHAR(32) NOT NULL,
    current_period_start DATETIME(6) NULL,
    current_period_end DATETIME(6) NULL,
    next_due_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    KEY idx_services_due (status,next_due_at),
    KEY idx_services_customer (customer_id,status),
    CONSTRAINT fk_service_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
    CONSTRAINT fk_service_product FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,
    provider_account_id BIGINT UNSIGNED NOT NULL,
    external_resource_id VARCHAR(191) NOT NULL,
    resource_type VARCHAR(32) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_provider_resource (provider_account_id,external_resource_id),
    KEY idx_resource_service (service_id,active),
    CONSTRAINT fk_resource_service FOREIGN KEY(service_id) REFERENCES services(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_operations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_uuid CHAR(36) CHARACTER SET ascii NOT NULL UNIQUE,
    service_id BIGINT UNSIGNED NOT NULL,
    provider_account_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(48) NOT NULL,
    status VARCHAR(32) NOT NULL,
    external_request_id VARCHAR(191) NULL,
    safe_error_code VARCHAR(64) NULL,
    evidence LONGTEXT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    KEY idx_providerop_status (service_id, status),
    CONSTRAINT fk_providerop_service FOREIGN KEY(service_id) REFERENCES services(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outbox_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_uuid CHAR(36) CHARACTER SET ascii NOT NULL UNIQUE,
    event_type VARCHAR(128) NOT NULL,
    aggregate_type VARCHAR(64) NOT NULL,
    aggregate_id VARCHAR(64) NOT NULL,
    payload LONGTEXT NOT NULL,
    publish_status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
    created_at DATETIME(6) NOT NULL,
    published_at DATETIME(6) NULL,
    KEY idx_outbox_status_time (publish_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(128) NOT NULL,
    queue VARCHAR(64) NOT NULL,
    payload LONGTEXT NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    run_after DATETIME(6) NOT NULL,
    leased_until DATETIME(6) NULL,
    lease_token CHAR(36) CHARACTER SET ascii NULL,
    dedupe_key VARCHAR(191) NULL,
    last_error_safe TEXT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_job_dedupe (dedupe_key),
    KEY idx_job_claim (queue,status,run_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    public_id CHAR(26) CHARACTER SET ascii NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    calendar_id BIGINT UNSIGNED NULL,
    assignment_mode VARCHAR(24) NOT NULL DEFAULT 'MANUAL',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(40) CHARACTER SET ascii NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    requester_user_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    status_category VARCHAR(32) NOT NULL,
    requested_priority VARCHAR(16) NOT NULL,
    effective_priority VARCHAR(16) NOT NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    subject VARCHAR(255) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    KEY idx_ticket_customer (customer_id,updated_at),
    KEY idx_ticket_queue (department_id,status_category,assigned_user_id,updated_at),
    CONSTRAINT fk_ticket_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
    CONSTRAINT fk_ticket_department FOREIGN KEY(department_id) REFERENCES support_departments(id),
    CONSTRAINT fk_ticket_requester FOREIGN KEY(requester_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    author_user_id BIGINT UNSIGNED NULL,
    message_type VARCHAR(24) NOT NULL,
    customer_visible TINYINT(1) NOT NULL,
    body LONGTEXT NOT NULL,
    inbound_message_hash VARCHAR(128) NULL,
    created_at DATETIME(6) NOT NULL,
    KEY idx_support_msg_ticket (ticket_id,created_at),
    UNIQUE KEY uq_ticket_inbound (ticket_id,inbound_message_hash),
    CONSTRAINT fk_message_ticket FOREIGN KEY(ticket_id) REFERENCES support_tickets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    effective_customer_id BIGINT UNSIGNED NULL,
    action VARCHAR(128) NOT NULL,
    subject_type VARCHAR(64) NOT NULL,
    subject_id VARCHAR(64) NOT NULL,
    trace_id CHAR(36) CHARACTER SET ascii NULL,
    meta_redacted LONGTEXT NULL,
    created_at DATETIME(6) NOT NULL,
    KEY idx_audit_subject (subject_type,subject_id,created_at),
    KEY idx_audit_actor (actor_user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eksikler bilinçli: gerçek implementasyon ayrıca orders, order_items, providers,
-- wallets refund ve ledger journals/lines, billing lines, support mail, SLA,
-- TLD, transfer, backup, notification, auth/2FA ve migration mapping tablolarını eklemelidir.
-- Tüm status/visibility, para ve borç invariants için DB constraints/transaction testleri şarttır.
