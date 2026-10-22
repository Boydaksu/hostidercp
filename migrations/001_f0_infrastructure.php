<?php
declare(strict_types=1);
// F0 only: no orders, payments, customers or provider commerce tables.
return [
    "CREATE TABLE outbox_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        event_type VARCHAR(128) NOT NULL,
        aggregate_id VARCHAR(64) NOT NULL,
        payload LONGTEXT NOT NULL CHECK (JSON_VALID(payload)),
        correlation_id CHAR(36) CHARACTER SET ascii NOT NULL,
        causation_id CHAR(36) CHARACTER SET ascii NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        published_at DATETIME(6) NULL,
        KEY ix_outbox_pending (published_at,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE jobs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        job_type VARCHAR(128) NOT NULL,
        payload LONGTEXT NOT NULL CHECK (JSON_VALID(payload)),
        status VARCHAR(16) NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING','RUNNING','SUCCEEDED','FAILED','UNKNOWN')),
        retry_safe BOOLEAN NOT NULL DEFAULT FALSE,
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3 CHECK (max_attempts BETWEEN 1 AND 10),
        run_after DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        leased_until DATETIME(6) NULL,
        lease_token CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
        dedupe_key VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        correlation_id CHAR(36) CHARACTER SET ascii NOT NULL,
        last_error_safe VARCHAR(64) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        KEY ix_job_claim (status,run_after,id), KEY ix_job_lease (status,leased_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE job_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        job_id BIGINT UNSIGNED NOT NULL,
        lease_token CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        outcome VARCHAR(24) NOT NULL DEFAULT 'RUNNING',
        started_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6), finished_at DATETIME(6) NULL,
        FOREIGN KEY (job_id) REFERENCES jobs(id)
    ) ENGINE=InnoDB",
    "CREATE TABLE dead_letters (
        job_id BIGINT UNSIGNED PRIMARY KEY,
        reason VARCHAR(64) NOT NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6),
        FOREIGN KEY (job_id) REFERENCES jobs(id)
    ) ENGINE=InnoDB",
    "CREATE TABLE idempotency_keys (
        scope_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
        response_json LONGTEXT NULL CHECK (response_json IS NULL OR JSON_VALID(response_json)),
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6)
    ) ENGINE=InnoDB",
    "CREATE TABLE inbox_events (
        consumer VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        event_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        consumed_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6), PRIMARY KEY (consumer,event_uuid)
    ) ENGINE=InnoDB",
    "CREATE TABLE audit_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        actor_id VARCHAR(64) CHARACTER SET ascii NOT NULL,
        effective_customer_id VARCHAR(64) CHARACTER SET ascii NULL,
        action VARCHAR(128) CHARACTER SET ascii NOT NULL,
        subject_id VARCHAR(64) CHARACTER SET ascii NOT NULL,
        correlation_id CHAR(36) CHARACTER SET ascii NOT NULL,
        causation_id CHAR(36) CHARACTER SET ascii NULL,
        created_at DATETIME(6) NOT NULL DEFAULT UTC_TIMESTAMP(6), KEY ix_audit_subject (subject_id,created_at)
    ) ENGINE=InnoDB",
    "CREATE TABLE rate_limits (
        bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        window_start BIGINT UNSIGNED NOT NULL, hits INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB",
];
