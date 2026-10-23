<?php
declare(strict_types=1);
return [
    "CREATE TABLE finance_reconciliation_resolutions (id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,case_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,action VARCHAR(32) CHARACTER SET ascii NOT NULL,amount_minor BIGINT NOT NULL CHECK(amount_minor>=0),currency CHAR(3) CHARACTER SET ascii NOT NULL,evidence_encrypted MEDIUMTEXT NOT NULL,evidence_sha256 CHAR(64) CHARACTER SET ascii NOT NULL,effect_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,actor_id VARCHAR(64) CHARACTER SET ascii NOT NULL,trace_id CHAR(36) CHARACTER SET ascii NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),FOREIGN KEY(case_id) REFERENCES finance_reconciliations(id),KEY ix_resolution_customer(customer_id)) ENGINE=InnoDB",
    "CREATE TRIGGER finance_resolution_update BEFORE UPDATE ON finance_reconciliation_resolutions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable reconciliation resolution'",
    "CREATE TRIGGER finance_resolution_delete BEFORE DELETE ON finance_reconciliation_resolutions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable reconciliation resolution'",
    "ALTER TABLE finance_allocations ADD KEY ix_allocation_customer(customer_id,id)",
    "ALTER TABLE billing_official_documents ADD KEY ix_document_customer(customer_id,id)",
];
