<?php
declare(strict_types=1);
// A single equality prefix permits ordered LIMIT admission without a locking filesort.
return [
"ALTER TABLE cloud_cost_work ADD admissible BOOLEAN GENERATED ALWAYS AS (status IN ('PENDING','UNVERIFIED')) PERSISTENT, ADD INDEX ix_cloud_cost_admission(admissible,checked_at,created_at,id), ADD INDEX ix_cloud_cost_proposal_admission(status,proposal_checked_at,created_at,id)",
];
