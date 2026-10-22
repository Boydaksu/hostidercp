<?php
declare(strict_types=1);
return [
    "CREATE TABLE pricing_fx_rates (id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,source_currency CHAR(3) CHARACTER SET ascii NOT NULL,target_currency CHAR(3) CHARACTER SET ascii NOT NULL,rate DECIMAL(24,12) NOT NULL CHECK(rate>0),spread DECIMAL(16,12) NOT NULL CHECK(spread>=0),source_reference VARCHAR(128) CHARACTER SET ascii NOT NULL,observed_at DATETIME(6) NOT NULL,trusted BOOLEAN NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),KEY ix_pricing_fx(source_currency,target_currency,observed_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
    "CREATE TABLE pricing_quotes (id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,currency CHAR(3) CHARACTER SET ascii NOT NULL,total_minor BIGINT NOT NULL CHECK(total_minor>=0),snapshot LONGTEXT NOT NULL CHECK(JSON_VALID(snapshot)),fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,created_at DATETIME(6) NOT NULL,expires_at DATETIME(6) NOT NULL,KEY ix_pricing_customer(customer_id,expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
    "CREATE TRIGGER pricing_quotes_update BEFORE UPDATE ON pricing_quotes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable quote'",
    "CREATE TRIGGER pricing_quotes_delete BEFORE DELETE ON pricing_quotes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable quote'",
    "CREATE TRIGGER pricing_fx_update BEFORE UPDATE ON pricing_fx_rates FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable FX rate'",
    "CREATE TRIGGER pricing_fx_delete BEFORE DELETE ON pricing_fx_rates FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable FX rate'",
];
