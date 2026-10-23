<?php
declare(strict_types=1);
return [
"ALTER TABLE hosting_mappings ADD COLUMN selection_hash CHAR(64) CHARACTER SET ascii NULL",
"CREATE TRIGGER hosting_reservation_binding BEFORE UPDATE ON hosting_reservations FOR EACH ROW BEGIN IF OLD.operation_id<>NEW.operation_id OR OLD.server_id<>NEW.server_id OR OLD.service_id<>NEW.service_id OR OLD.mapping_id<>NEW.mapping_id OR OLD.package_name<>NEW.package_name OR OLD.reseller_scope<>NEW.reseller_scope OR (OLD.status='RELEASED' AND NEW.status<>'RELEASED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable hosting reservation binding'; END IF; END",
];
