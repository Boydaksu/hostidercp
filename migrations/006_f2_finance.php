<?php
declare(strict_types=1);
$suffix=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
$sql=[
    "CREATE TABLE finance_wallets (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6)
    )".$suffix,
    "CREATE TABLE finance_journals (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        reference VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        actor_id VARCHAR(64) CHARACTER SET ascii NOT NULL,
        trace_id CHAR(36) CHARACTER SET ascii NOT NULL,
        reversal_of CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        FOREIGN KEY (reversal_of) REFERENCES finance_journals(id)
    )".$suffix,
    "CREATE TABLE finance_lines (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        journal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        account VARCHAR(48) CHARACTER SET ascii NOT NULL,
        side VARCHAR(6) CHARACTER SET ascii NOT NULL CHECK (side IN ('DEBIT','CREDIT')),
        amount_minor BIGINT NOT NULL CHECK (amount_minor>0),
        FOREIGN KEY (journal_id) REFERENCES finance_journals(id)
    )".$suffix,
    "CREATE TABLE finance_fx_snapshots (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        source_currency CHAR(3) CHARACTER SET ascii NOT NULL,
        target_currency CHAR(3) CHARACTER SET ascii NOT NULL,
        rate DECIMAL(24,12) NOT NULL CHECK (rate>0),
        spread DECIMAL(16,12) NOT NULL CHECK (spread>=0),
        source_reference VARCHAR(128) CHARACTER SET ascii NOT NULL,
        observed_at DATETIME(6) NOT NULL,
        rounding VARCHAR(16) CHARACTER SET ascii NOT NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6)
    )".$suffix,
    "CREATE TABLE finance_payments (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        method VARCHAR(16) CHARACTER SET ascii NOT NULL CHECK (method IN ('BANK','TEST')),
        external_reference VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        amount_minor BIGINT NOT NULL CHECK (amount_minor>0),
        currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        original_minor BIGINT NOT NULL CHECK (original_minor>0),
        original_currency CHAR(3) CHARACTER SET ascii NOT NULL,
        fx_snapshot_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
        fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        journal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        UNIQUE KEY uq_finance_external(method,external_reference),
        FOREIGN KEY (journal_id) REFERENCES finance_journals(id),
        FOREIGN KEY (fx_snapshot_id) REFERENCES finance_fx_snapshots(id),
        KEY ix_finance_customer(customer_id,created_at)
    )".$suffix,
    "CREATE TABLE finance_wallet_entries (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        wallet_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        amount_minor BIGINT NOT NULL CHECK (amount_minor<>0),
        reference VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        journal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        payment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        FOREIGN KEY (wallet_id) REFERENCES finance_wallets(id),
        FOREIGN KEY (journal_id) REFERENCES finance_journals(id),
        FOREIGN KEY (payment_id) REFERENCES finance_payments(id)
    )".$suffix,
    "CREATE TABLE finance_allocations (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        payment_request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        payment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
        wallet_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
        amount_minor BIGINT NOT NULL CHECK (amount_minor>0),
        currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        reference VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        journal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        CHECK ((payment_id IS NULL) <> (wallet_entry_id IS NULL)),
        FOREIGN KEY (payment_id) REFERENCES finance_payments(id),
        FOREIGN KEY (wallet_entry_id) REFERENCES finance_wallet_entries(id),
        FOREIGN KEY (journal_id) REFERENCES finance_journals(id),
        KEY ix_finance_request(payment_request_id)
    )".$suffix,
    "CREATE TABLE finance_refunds (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        payment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        amount_minor BIGINT NOT NULL CHECK (amount_minor>0),
        currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        destination VARCHAR(16) CHARACTER SET ascii NOT NULL CHECK (destination IN ('ORIGINAL','WALLET')),
        reference VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        journal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        FOREIGN KEY (payment_id) REFERENCES finance_payments(id),
        FOREIGN KEY (journal_id) REFERENCES finance_journals(id)
    )".$suffix,
    "CREATE TABLE finance_reconciliations (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        subject_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        code VARCHAR(32) CHARACTER SET ascii NOT NULL,
        actor_id VARCHAR(64) CHARACTER SET ascii NOT NULL,
        trace_id CHAR(36) CHARACTER SET ascii NOT NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6)
    )".$suffix,
];
foreach (['wallets','journals','lines','fx_snapshots','payments','wallet_entries','allocations','refunds','reconciliations'] as $table) {
    foreach (['UPDATE','DELETE'] as $operation) {
        $sql[]="CREATE TRIGGER finance_{$table}_".strtolower($operation)." BEFORE {$operation} ON finance_{$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable financial record'";
    }
}
return $sql;
