<?php
declare(strict_types=1);
return [
"ALTER TABLE svc_services ADD scheduler_checked_at DATETIME(6) NULL, ADD KEY ix_svc_scheduler_fairness(status,scheduler_checked_at,id)",
];
