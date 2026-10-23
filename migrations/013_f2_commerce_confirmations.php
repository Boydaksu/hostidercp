<?php
declare(strict_types=1);
$sql=["CREATE TABLE commerce_confirmations (id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,session_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,identity_version BIGINT UNSIGNED NOT NULL,action VARCHAR(64) CHARACTER SET ascii NOT NULL,customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,payload_hmac CHAR(64) CHARACTER SET ascii NOT NULL,state_hmac CHAR(64) CHARACTER SET ascii NOT NULL,plan_hmac CHAR(64) CHARACTER SET ascii NOT NULL,expires_at DATETIME(6) NOT NULL,used_at DATETIME(6) NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6)) ENGINE=InnoDB"];
$permissions=['catalog.view','catalog.manage','pricing.manage','orders.create','orders.pay','orders.cancel','wallet.view','wallet.topup','payment.notify','finance.view','finance.approve','finance.manage','billing.documents.manage'];
foreach($permissions as $permission){$sql[]="INSERT INTO identity_permissions(code) VALUES ('$permission')";}
foreach($permissions as $permission){$sql[]="INSERT INTO identity_role_permissions(role_id,permission) SELECT id,'$permission' FROM identity_roles WHERE name='Super Admin'";}
foreach(['catalog.view','orders.create','orders.pay','orders.cancel','wallet.view','wallet.topup','payment.notify'] as $permission){$sql[]="INSERT INTO identity_role_permissions(role_id,permission) SELECT id,'$permission' FROM identity_roles WHERE name='Müşteri Sahibi'";}
foreach(['finance.view','finance.approve','finance.manage','billing.documents.manage','wallet.view'] as $permission){$sql[]="INSERT INTO identity_role_permissions(role_id,permission) SELECT id,'$permission' FROM identity_roles WHERE name='Muhasebe'";}
$sql[]="INSERT INTO identity_role_permissions(role_id,permission) SELECT id,'catalog.view' FROM identity_roles WHERE name='Alt Kullanıcı'";
$sql[]='UPDATE identity_users SET identity_version=identity_version+1';
$sql[]='UPDATE identity_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE revoked_at IS NULL';
return $sql;
