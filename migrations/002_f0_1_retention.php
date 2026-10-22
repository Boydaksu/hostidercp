<?php
declare(strict_types=1);
return [
    'ALTER TABLE rate_limits ADD COLUMN expires_at BIGINT UNSIGNED NULL, ADD KEY ix_rate_expiry (expires_at)',
    'ALTER TABLE jobs ADD COLUMN payload_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, ADD COLUMN payload_purged_at DATETIME(6) NULL, ADD KEY ix_job_retention (status,payload_purged_at,updated_at)',
];
