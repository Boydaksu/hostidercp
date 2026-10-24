<?php
declare(strict_types=1);
$id = 'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin';
$sql = [
"CREATE TABLE domain_operations (
    id $id PRIMARY KEY,
    domain_id $id NOT NULL,
    service_id $id NOT NULL,
    order_id $id NOT NULL,
    kind VARCHAR(24) CHARACTER SET ascii NOT NULL CHECK(kind IN ('REGISTER','RENEW')),
    generation INT UNSIGNED NOT NULL,
    request_fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
    status VARCHAR(24) CHARACTER SET ascii NOT NULL DEFAULT 'PENDING' CHECK(status IN ('PENDING','RUNNING','SUCCEEDED','FAILED','UNKNOWN','MANUAL_REVIEW')),
    lease_token $id NULL,
    lease_until DATETIME(6) NULL,
    primary_account_id $id NOT NULL,
    active_attempt_id $id NULL,
    created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
    UNIQUE KEY uq_domain_op_gen(domain_id,kind,generation),
    KEY ix_domain_op_pending(status,created_at,id),
    KEY ix_domain_op_lease(status,lease_until,id),
    FOREIGN KEY(domain_id) REFERENCES domains(id),
    FOREIGN KEY(service_id) REFERENCES svc_services(id),
    FOREIGN KEY(order_id) REFERENCES orders(id),
    FOREIGN KEY(primary_account_id) REFERENCES domain_registrar_accounts(id)
) ENGINE=InnoDB",

"CREATE TABLE domain_registrar_attempts (
    id $id PRIMARY KEY,
    operation_id $id NOT NULL,
    account_id $id NOT NULL,
    attempt_no INT UNSIGNED NOT NULL CHECK(attempt_no BETWEEN 1 AND 2),
    status VARCHAR(24) CHARACTER SET ascii NOT NULL DEFAULT 'PENDING' CHECK(status IN ('PENDING','RUNNING','SUCCEEDED','DEFINITIVE_FAILURE','UNKNOWN','UNSUPPORTED')),
    request_encrypted LONGTEXT NOT NULL,
    request_fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
    no_effect BOOLEAN NOT NULL DEFAULT FALSE,
    external_resource_id $id NULL,
    safe_code VARCHAR(64) CHARACTER SET ascii NOT NULL DEFAULT 'PENDING',
    created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
    finished_at DATETIME(6) NULL,
    UNIQUE KEY uq_domain_attempt(operation_id,attempt_no),
    KEY ix_domain_attempt_op(operation_id,id),
    FOREIGN KEY(operation_id) REFERENCES domain_operations(id),
    FOREIGN KEY(account_id) REFERENCES domain_registrar_accounts(id)
) ENGINE=InnoDB",

"CREATE TABLE domain_attempt_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_id $id NOT NULL,
    attempt_id $id NOT NULL,
    lease_token $id NOT NULL,
    kind VARCHAR(24) CHARACTER SET ascii NOT NULL CHECK(kind IN ('STARTED','FINISHED','HELD_FOR_REVIEW','RECONCILED')),
    safe_code VARCHAR(64) CHARACTER SET ascii NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
    UNIQUE KEY uq_domain_attempt_event(attempt_id,lease_token,kind),
    FOREIGN KEY(operation_id) REFERENCES domain_operations(id),
    FOREIGN KEY(attempt_id) REFERENCES domain_registrar_attempts(id)
) ENGINE=InnoDB",

"CREATE TABLE domain_evidence (
    operation_id $id PRIMARY KEY,
    attempt_id $id NOT NULL,
    evidence_encrypted LONGTEXT NOT NULL,
    fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
    FOREIGN KEY(operation_id) REFERENCES domain_operations(id),
    FOREIGN KEY(attempt_id) REFERENCES domain_registrar_attempts(id)
) ENGINE=InnoDB",

"CREATE TABLE domain_inspections (
    id $id PRIMARY KEY,
    operation_id $id NOT NULL,
    attempt_id $id NOT NULL,
    actor_id VARCHAR(64) CHARACTER SET ascii NOT NULL,
    found BOOLEAN NOT NULL,
    evidence_encrypted LONGTEXT NOT NULL,
    fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
    KEY ix_domain_inspect(operation_id,created_at,id),
    FOREIGN KEY(operation_id) REFERENCES domain_operations(id),
    FOREIGN KEY(attempt_id) REFERENCES domain_registrar_attempts(id)
) ENGINE=InnoDB",

"CREATE TABLE domain_resolutions (
    id $id PRIMARY KEY,
    operation_id $id NOT NULL UNIQUE,
    inspection_id $id NOT NULL UNIQUE,
    outcome VARCHAR(24) CHARACTER SET ascii NOT NULL CHECK(outcome IN ('CONFIRMED_SUCCESS','CONFIRMED_FAILURE')),
    actor_id VARCHAR(64) CHARACTER SET ascii NOT NULL,
    trace_id $id NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
    FOREIGN KEY(operation_id) REFERENCES domain_operations(id),
    FOREIGN KEY(inspection_id) REFERENCES domain_inspections(id)
) ENGINE=InnoDB",

"CREATE TABLE domain_renewals (
    id $id PRIMARY KEY,
    domain_id $id NOT NULL,
    customer_id $id NOT NULL,
    generation INT UNSIGNED NOT NULL,
    order_id $id NOT NULL UNIQUE,
    period_id $id NOT NULL UNIQUE,
    operation_id $id NULL UNIQUE,
    term_years INT UNSIGNED NOT NULL CHECK(term_years BETWEEN 1 AND 10),
    status VARCHAR(24) CHARACTER SET ascii NOT NULL DEFAULT 'AWAITING_PAYMENT' CHECK(status IN ('AWAITING_PAYMENT','PENDING_PROVIDER','VERIFIED','FAILED','UNKNOWN')),
    created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
    UNIQUE KEY uq_domain_renewal(domain_id,generation),
    KEY ix_domain_renewal_status(status,created_at,id),
    FOREIGN KEY(domain_id) REFERENCES domains(id),
    FOREIGN KEY(customer_id) REFERENCES identity_customers(id),
    FOREIGN KEY(order_id) REFERENCES orders(id),
    FOREIGN KEY(period_id) REFERENCES svc_periods(id),
    FOREIGN KEY(operation_id) REFERENCES domain_operations(id)
) ENGINE=InnoDB",

"CREATE TRIGGER domain_operation_binding BEFORE UPDATE ON domain_operations FOR EACH ROW BEGIN
    IF OLD.domain_id<>NEW.domain_id OR OLD.service_id<>NEW.service_id OR OLD.order_id<>NEW.order_id OR OLD.kind<>NEW.kind OR OLD.generation<>NEW.generation OR OLD.request_fingerprint<>NEW.request_fingerprint OR (OLD.status IN ('SUCCEEDED','FAILED') AND NEW.status<>OLD.status) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable domain operation binding';
    END IF;
END",

"CREATE TRIGGER domain_attempt_binding BEFORE UPDATE ON domain_registrar_attempts FOR EACH ROW BEGIN
    IF OLD.operation_id<>NEW.operation_id OR OLD.account_id<>NEW.account_id OR OLD.attempt_no<>NEW.attempt_no OR OLD.request_fingerprint<>NEW.request_fingerprint OR (OLD.status IN ('SUCCEEDED','DEFINITIVE_FAILURE') AND NEW.status<>OLD.status) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable domain attempt binding';
    END IF;
END",
];

foreach (['domain_attempt_events','domain_evidence','domain_inspections','domain_resolutions'] as $table) {
    foreach (['UPDATE','DELETE'] as $verb) {
        $sql[] = "CREATE TRIGGER {$table}_".strtolower($verb)." BEFORE $verb ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable domain audit record'";
    }
}
$sql[] = "CREATE TRIGGER domain_registrar_attempts_delete BEFORE DELETE ON domain_registrar_attempts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable domain attempt'";
$sql[] = "CREATE TRIGGER domain_operations_delete BEFORE DELETE ON domain_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable domain operation'";

return $sql;
