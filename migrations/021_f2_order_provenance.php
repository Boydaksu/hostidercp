<?php
declare(strict_types=1);
return [
    "ALTER TABLE orders ADD origin_environment VARCHAR(24) CHARACTER SET ascii NOT NULL DEFAULT 'LEGACY_UNKNOWN' CHECK(origin_environment IN ('test','staging','production','LEGACY_UNKNOWN')),ADD KEY ix_handoff_candidate(origin_environment,status,id)",
    "CREATE TRIGGER order_origin_immutable BEFORE UPDATE ON orders FOR EACH ROW BEGIN IF NOT(OLD.origin_environment <=> NEW.origin_environment) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable order environment'; END IF; END",
];
