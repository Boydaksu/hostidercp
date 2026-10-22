<?php
declare(strict_types=1);
return [
    "CREATE TABLE finance_credits (
        id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        original_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        credit_entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        amount_minor BIGINT NOT NULL CHECK (amount_minor>0),
        reference VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        FOREIGN KEY (original_entry_id) REFERENCES finance_wallet_entries(id),
        FOREIGN KEY (credit_entry_id) REFERENCES finance_wallet_entries(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
    "CREATE TRIGGER finance_credits_update BEFORE UPDATE ON finance_credits FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable financial record'",
    "CREATE TRIGGER finance_credits_delete BEFORE DELETE ON finance_credits FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable financial record'",
];
