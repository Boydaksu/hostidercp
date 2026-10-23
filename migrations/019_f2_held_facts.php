<?php
declare(strict_types=1);
return [
    "CREATE TABLE commerce_event_receipts (event_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,event_type VARCHAR(128) CHARACTER SET ascii NOT NULL,aggregate_id VARCHAR(64) CHARACTER SET ascii NOT NULL,status VARCHAR(24) CHARACTER SET ascii NOT NULL CHECK(status IN ('RECORDED','HELD_FOR_F3','MAIL_HELD')),trace_id CHAR(36) CHARACTER SET ascii NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6)) ENGINE=InnoDB",
    "CREATE TRIGGER commerce_receipts_update BEFORE UPDATE ON commerce_event_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable local event receipt'",
    "CREATE TRIGGER commerce_receipts_delete BEFORE DELETE ON commerce_event_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable local event receipt'",
];
