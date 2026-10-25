<?php
declare(strict_types=1);
$id='CHAR(36) CHARACTER SET ascii COLLATE ascii_bin';
return [
"INSERT INTO identity_permissions(code) VALUES ('cloud.power')",
"INSERT INTO identity_role_permissions(role_id,permission) SELECT id,'cloud.power' FROM identity_roles WHERE name IN ('Super Admin','Müşteri Sahibi')",
"CREATE TABLE panel_confirmations(id $id PRIMARY KEY,actor_id $id NOT NULL,session_id $id NOT NULL,identity_version BIGINT UNSIGNED NOT NULL,customer_id $id NOT NULL,service_id $id NOT NULL,action VARCHAR(40) NOT NULL,payload_hmac CHAR(64) NOT NULL,state_hmac CHAR(64) NOT NULL,native_confirmation $id NULL,expires_at DATETIME(6) NOT NULL,used_at DATETIME(6) NULL,FOREIGN KEY(service_id) REFERENCES svc_services(id)) ENGINE=InnoDB",
"CREATE TABLE panel_inspections(id $id PRIMARY KEY,service_id $id NOT NULL,customer_id $id NOT NULL,operation_id $id NOT NULL,kind VARCHAR(12) NOT NULL CHECK(kind IN ('DOMAIN','CLOUD')),actor_id $id NOT NULL,session_id $id NOT NULL,identity_version BIGINT UNSIGNED NOT NULL,authorization_encrypted LONGTEXT NOT NULL,state_hmac CHAR(64) NOT NULL,status VARCHAR(16) NOT NULL DEFAULT 'PENDING' CHECK(status IN ('PENDING','RUNNING','SUCCEEDED','REVIEW')),lease_token $id NULL,lease_until DATETIME(6) NULL,result_id $id NULL,safe_code VARCHAR(64) NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),KEY ix_panel_inspection(status,created_at,id),FOREIGN KEY(service_id) REFERENCES svc_services(id)) ENGINE=InnoDB",
];
