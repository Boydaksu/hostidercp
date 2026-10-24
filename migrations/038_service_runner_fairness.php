<?php
declare(strict_types=1);
return [
"ALTER TABLE svc_operations ADD runner_checked_at DATETIME(6) NULL, ADD runner_hold_code VARCHAR(64) CHARACTER SET ascii NULL, ADD KEY ix_svc_runner_fairness(status,runner_checked_at,created_at,id)",
];
