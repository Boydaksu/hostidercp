<?php
declare(strict_types=1);
return [
"CREATE TABLE svc_cancellations (id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,service_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id VARCHAR(64) CHARACTER SET ascii NOT NULL,previous_status VARCHAR(24) CHARACTER SET ascii NOT NULL,requested_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),execute_after DATETIME(6) NOT NULL,status VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'PENDING',trace_id CHAR(36) CHARACTER SET ascii NOT NULL,KEY ix_svc_cancel(service_id,status,execute_after),FOREIGN KEY(service_id) REFERENCES svc_services(id),CHECK(status IN ('PENDING','CANCELLED','EXECUTED'))) ENGINE=InnoDB",
"CREATE TRIGGER svc_cancellation_binding BEFORE UPDATE ON svc_cancellations FOR EACH ROW BEGIN IF OLD.service_id<>NEW.service_id OR OLD.actor_id<>NEW.actor_id OR OLD.previous_status<>NEW.previous_status OR OLD.requested_at<>NEW.requested_at OR OLD.execute_after<>NEW.execute_after OR OLD.trace_id<>NEW.trace_id OR (OLD.status<>'PENDING' AND NEW.status<>OLD.status) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable cancellation safeguard'; END IF; END",
];
