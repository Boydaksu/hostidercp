<?php
declare(strict_types=1);
$id='CHAR(36) CHARACTER SET ascii COLLATE ascii_bin';
$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$sql=[
"CREATE TABLE support_auto_close_policies(id $id PRIMARY KEY,department_id $id NOT NULL,revision INT UNSIGNED NOT NULL,enabled BOOLEAN NOT NULL,wait_seconds INT UNSIGNED NULL,CHECK((enabled=FALSE AND wait_seconds IS NULL) OR (enabled=TRUE AND wait_seconds BETWEEN 1 AND 31536000)),actor_id $id NOT NULL,session_id $id NOT NULL,identity_version INT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),UNIQUE KEY uq_auto_close_policy(department_id,revision),FOREIGN KEY(department_id) REFERENCES support_departments(id),FOREIGN KEY(actor_id) REFERENCES identity_users(id),FOREIGN KEY(session_id) REFERENCES identity_sessions(id))$e",
"CREATE TABLE support_auto_close_heads(department_id $id PRIMARY KEY,policy_id $id NOT NULL,version INT UNSIGNED NOT NULL,FOREIGN KEY(department_id) REFERENCES support_departments(id),FOREIGN KEY(policy_id) REFERENCES support_auto_close_policies(id))$e",
"CREATE TABLE support_auto_close_events(id $id PRIMARY KEY,ticket_id $id NOT NULL,customer_id $id NOT NULL,department_id $id NOT NULL,policy_id $id NOT NULL,policy_version INT UNSIGNED NOT NULL,resolution_event $id NOT NULL,from_version INT UNSIGNED NOT NULL,to_version INT UNSIGNED NOT NULL,CHECK(to_version=from_version+1),actor_kind VARCHAR(24) NOT NULL CHECK(actor_kind='SYSTEM_AUTO_CLOSE'),closed_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),UNIQUE KEY uq_auto_close_resolution(ticket_id,resolution_event),FOREIGN KEY(id) REFERENCES support_work_events(id),FOREIGN KEY(ticket_id,customer_id) REFERENCES support_tickets(id,customer_id),FOREIGN KEY(department_id) REFERENCES support_departments(id),FOREIGN KEY(policy_id) REFERENCES support_auto_close_policies(id),FOREIGN KEY(resolution_event) REFERENCES support_events(id),KEY ix_auto_close_recent(department_id,closed_at,id))$e",
];
foreach(['support_auto_close_policies','support_auto_close_events'] as $table){
    foreach(['UPDATE','DELETE'] as $verb){$suffix=strtolower($verb);$sql[]="CREATE TRIGGER {$table}_no_{$suffix} BEFORE $verb ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable auto-close history'";}
}
return $sql;
