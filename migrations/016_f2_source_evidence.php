<?php
declare(strict_types=1);
return [
    "ALTER TABLE finance_payments ADD original_journal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,ADD FOREIGN KEY(original_journal_id) REFERENCES finance_journals(id)",
    "ALTER TABLE catalog_cost_proposals ADD proposed_sale_currency CHAR(3) CHARACTER SET ascii NULL",
    "ALTER TABLE payment_bank_notices ADD reference_encrypted LONGTEXT NULL",
];
