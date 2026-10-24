<?php
declare(strict_types=1);
$id='CHAR(36) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii';
$sql=[
"INSERT IGNORE INTO identity_permissions(code) VALUES ('services.manage')",
"INSERT IGNORE INTO identity_role_permissions(role_id,permission) SELECT id,'services.manage' FROM identity_roles WHERE name='Super Admin'",
"CREATE TABLE catalog_cloud_fixed_offers(id $id PRIMARY KEY,product_id $id NOT NULL,price_id $id NOT NULL UNIQUE,catalog_id $id NOT NULL,spec_json LONGTEXT NOT NULL CHECK(JSON_VALID(spec_json)),created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),FOREIGN KEY(product_id) REFERENCES catalog_products(id),FOREIGN KEY(price_id) REFERENCES catalog_prices(id),FOREIGN KEY(catalog_id) REFERENCES cloud_catalog(id)) ENGINE=InnoDB",
"CREATE TABLE cloud_purchased_entitlements(instance_id $id PRIMARY KEY,service_id $id NOT NULL UNIQUE,order_id $id NOT NULL,order_item_id $id NOT NULL,unit_no INT UNSIGNED NOT NULL,offer_id $id NOT NULL,source_encrypted LONGTEXT NOT NULL,fingerprint $hash NOT NULL,UNIQUE KEY uq_cloud_purchased_unit(order_item_id,unit_no),FOREIGN KEY(instance_id) REFERENCES cloud_instances(id),FOREIGN KEY(offer_id) REFERENCES catalog_cloud_fixed_offers(id)) ENGINE=InnoDB",
"ALTER TABLE cloud_operations ADD financial_exception BOOLEAN NOT NULL DEFAULT FALSE",
"CREATE TABLE cloud_safety_confirmations(id $id PRIMARY KEY,instance_id $id NOT NULL,instance_version BIGINT UNSIGNED NOT NULL,kind VARCHAR(24) NOT NULL CHECK(kind IN ('STOP','TERMINATE')),session_id $id NOT NULL,actor_id $id NOT NULL,identity_version BIGINT UNSIGNED NOT NULL,payload_encrypted LONGTEXT NOT NULL,fingerprint $hash NOT NULL,expires_at DATETIME(6) NOT NULL,not_before DATETIME(6) NOT NULL,consumed BOOLEAN NOT NULL DEFAULT FALSE,FOREIGN KEY(instance_id) REFERENCES cloud_instances(id)) ENGINE=InnoDB",
"CREATE TABLE cloud_containments(operation_id $id PRIMARY KEY,instance_id $id NOT NULL,actor_id $id NOT NULL,session_id $id NOT NULL,identity_version BIGINT UNSIGNED NOT NULL,context_encrypted LONGTEXT NOT NULL,fingerprint $hash NOT NULL,financial_exception BOOLEAN NOT NULL CHECK(financial_exception=TRUE),created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),FOREIGN KEY(operation_id) REFERENCES cloud_operations(id)) ENGINE=InnoDB",
"CREATE TRIGGER cloud_exception_binding BEFORE UPDATE ON cloud_operations FOR EACH ROW BEGIN IF OLD.financial_exception<>NEW.financial_exception THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Cloud exception mode'; END IF; END",
];
$fields=['id','instance_id','instance_version','kind','session_id','actor_id','identity_version','payload_encrypted','fingerprint','expires_at','not_before'];
$changed=implode(' OR ',array_map(static fn(string $f): string=>"NOT (OLD.$f <=> NEW.$f)",$fields));
$sql[]="CREATE TRIGGER cloud_safety_confirmation_binding BEFORE UPDATE ON cloud_safety_confirmations FOR EACH ROW BEGIN IF $changed OR OLD.consumed=TRUE OR NEW.consumed<>TRUE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Cloud safety confirmation'; END IF; END";
$sql[]="CREATE TRIGGER cloud_safety_confirmation_delete BEFORE DELETE ON cloud_safety_confirmations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Cloud safety confirmation'";
foreach(['catalog_cloud_fixed_offers','cloud_purchased_entitlements','cloud_containments'] as $table){foreach(['UPDATE','DELETE'] as $verb){$sql[]="CREATE TRIGGER {$table}_".strtolower($verb)." BEFORE $verb ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable Cloud purchase or containment';";}}
return $sql;
