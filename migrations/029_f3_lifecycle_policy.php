<?php
declare(strict_types=1);
return [
"CREATE TABLE svc_policies (scope_kind VARCHAR(16) CHARACTER SET ascii NOT NULL,scope_id VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,policy_json LONGTEXT NOT NULL CHECK(JSON_VALID(policy_json)),version BIGINT UNSIGNED NOT NULL DEFAULT 1,updated_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),PRIMARY KEY(scope_kind,scope_id),CHECK(scope_kind IN ('GLOBAL','CATEGORY','PRODUCT','SERVICE'))) ENGINE=InnoDB",
"CREATE TABLE svc_approvals (service_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,actor_id VARCHAR(64) CHARACTER SET ascii NOT NULL,trace_id CHAR(36) CHARACTER SET ascii NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),FOREIGN KEY(service_id) REFERENCES svc_services(id)) ENGINE=InnoDB",
"ALTER TABLE svc_operations DROP CONSTRAINT CONSTRAINT_1,ADD CONSTRAINT ck_svc_operation_kind CHECK(kind IN ('CREATE','RENEW','SUSPEND','UNSUSPEND','CHANGE_PACKAGE','TERMINATE'))",
"CREATE TRIGGER svc_approval_update BEFORE UPDATE ON svc_approvals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable service approval'",
"CREATE TRIGGER svc_approval_delete BEFORE DELETE ON svc_approvals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable service approval'",
];
