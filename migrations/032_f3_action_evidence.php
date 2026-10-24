<?php
declare(strict_types=1);
return [
"CREATE TABLE svc_action_evidence (operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,evidence_encrypted LONGTEXT NOT NULL,fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),FOREIGN KEY(operation_id) REFERENCES svc_operations(id)) ENGINE=InnoDB",
"CREATE TRIGGER svc_action_evidence_update BEFORE UPDATE ON svc_action_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable action evidence'",
"CREATE TRIGGER svc_action_evidence_delete BEFORE DELETE ON svc_action_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable action evidence'",
];
