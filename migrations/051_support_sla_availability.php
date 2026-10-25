<?php
declare(strict_types=1);
return [
    "ALTER TABLE support_work_tickets ADD sla_status VARCHAR(16) NOT NULL DEFAULT 'UNCONFIGURED' CHECK(sla_status IN ('UNCONFIGURED','ACTIVE','UNAVAILABLE'))",
    "UPDATE support_work_tickets SET sla_status='ACTIVE' WHERE policy_id IS NOT NULL AND cycle_id IS NOT NULL",
];
