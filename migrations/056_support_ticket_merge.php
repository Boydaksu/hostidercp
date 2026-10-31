<?php
declare(strict_types=1);
$id='CHAR(36) CHARACTER SET ascii COLLATE ascii_bin';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$sql=[
"INSERT INTO identity_permissions(code) VALUES ('support.ticket.merge')",
"INSERT INTO identity_role_permissions(role_id,permission) SELECT id,'support.ticket.merge' FROM identity_roles WHERE name IN ('Super Admin','Destek')",
"CREATE TABLE support_ticket_merges(id $id PRIMARY KEY,source_id $id NOT NULL UNIQUE,target_id $id NOT NULL UNIQUE,customer_id $id NOT NULL,department_id $id NOT NULL,source_number VARCHAR(191) NOT NULL,source_version INT UNSIGNED NOT NULL,target_version INT UNSIGNED NOT NULL,actor_id $id NOT NULL,session_id $id NOT NULL,identity_version INT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),CHECK(source_id<>target_id),FOREIGN KEY(id) REFERENCES support_work_events(id),FOREIGN KEY(source_id,customer_id) REFERENCES support_tickets(id,customer_id),FOREIGN KEY(target_id,customer_id) REFERENCES support_tickets(id,customer_id),FOREIGN KEY(department_id) REFERENCES support_departments(id),FOREIGN KEY(actor_id) REFERENCES identity_users(id),FOREIGN KEY(session_id) REFERENCES identity_sessions(id))$e",
"CREATE TABLE support_merge_members(ticket_id $id PRIMARY KEY,merge_id $id NOT NULL,side VARCHAR(8) NOT NULL CHECK(side IN ('SOURCE','TARGET')),UNIQUE KEY uq_merge_side(merge_id,side),FOREIGN KEY(ticket_id) REFERENCES support_tickets(id),FOREIGN KEY(merge_id) REFERENCES support_ticket_merges(id))$e",
];
foreach(['support_ticket_merges','support_merge_members'] as $table){foreach(['UPDATE','DELETE'] as $verb){$suffix=strtolower($verb);$sql[]="CREATE TRIGGER {$table}_no_{$suffix} BEFORE $verb ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable ticket merge'";}}
return $sql;
