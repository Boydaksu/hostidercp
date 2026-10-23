<?php
declare(strict_types=1);
return [
"ALTER TABLE svc_services ADD KEY ix_svc_customer_created(customer_id,created_at,id)",
"ALTER TABLE svc_operations ADD KEY ix_svc_operation_history(service_id,created_at,id)",
"ALTER TABLE hosting_mappings ADD KEY ix_host_product_selection(product_id,selection_hash,enabled,pool_id)",
"CREATE TRIGGER svc_renewal_binding BEFORE UPDATE ON svc_renewals FOR EACH ROW BEGIN IF OLD.service_id<>NEW.service_id OR OLD.customer_id<>NEW.customer_id OR OLD.generation<>NEW.generation OR OLD.order_id<>NEW.order_id OR OLD.period_id<>NEW.period_id OR (OLD.operation_id IS NOT NULL AND NOT(OLD.operation_id <=> NEW.operation_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable renewal binding'; END IF; END",
"CREATE TRIGGER svc_confirmation_binding BEFORE UPDATE ON svc_confirmations FOR EACH ROW BEGIN IF OLD.actor_id<>NEW.actor_id OR OLD.session_id<>NEW.session_id OR OLD.identity_version<>NEW.identity_version OR NOT(OLD.customer_id <=> NEW.customer_id) OR NOT(OLD.service_id <=> NEW.service_id) OR OLD.action<>NEW.action OR OLD.payload_hmac<>NEW.payload_hmac OR OLD.state_hmac<>NEW.state_hmac OR OLD.plan_encrypted<>NEW.plan_encrypted OR OLD.plan_hmac<>NEW.plan_hmac OR OLD.planned_at<>NEW.planned_at OR OLD.expires_at<>NEW.expires_at OR (OLD.used_at IS NOT NULL AND NOT(OLD.used_at <=> NEW.used_at)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable service confirmation'; END IF; END",
"CREATE TRIGGER svc_inspection_job_binding BEFORE UPDATE ON svc_inspection_jobs FOR EACH ROW BEGIN IF OLD.service_id<>NEW.service_id OR OLD.customer_id<>NEW.customer_id OR OLD.requested_by<>NEW.requested_by OR OLD.authorization_encrypted<>NEW.authorization_encrypted OR (OLD.status IN ('SUCCEEDED','FAILED') AND NEW.status<>OLD.status) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable inspection job binding'; END IF; END",
];
