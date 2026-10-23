<?php
declare(strict_types=1);
return [
    "CREATE TABLE commerce_state_versions (aggregate_name VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,version BIGINT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB",
    "INSERT INTO commerce_state_versions(aggregate_name) VALUES ('catalog'),('fx'),('cash')",
];
