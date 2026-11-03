<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final class Catalog
{
    /** Fixed, source-derived checks; no input may supply an explanation or action. */
    public static function definitions(): array
    {
        return [
            'PHP_VERSION'=>['core','REQUIRED','bin/console.php','64-bit PHP 8.3+ native baseline.','Select PHP 8.3 or newer for CLI and independently verify the web runtime.'],
            'PHP_BITS'=>['core','REQUIRED','bin/console.php','Native identifiers and exact integer operations require a 64-bit build.','Use a 64-bit PHP build.'],
            'CLI_RUNTIME'=>['queue','REQUIRED','bin/console.php','This probe and bounded native commands require CLI PHP.','Run this tool with the hosting CLI PHP binary.'],
            'PDO_MYSQL'=>['core','REQUIRED','src/Core/Database/Database.php','Native persistence uses PDO MySQL.','Enable PDO and its mysql driver in the applicable PHP runtime.'],
            'SODIUM'=>['core','REQUIRED','src/Core/Security/Crypto.php','Native protected records use sodium XChaCha20-Poly1305.','Enable the sodium extension.'],
            'MBSTRING'=>['core','REQUIRED','src/Core/Validation.php','Native text validation uses mb_check_encoding and mb_strlen.','Enable mbstring.'],
            'SESSION'=>['identity','REQUIRED','src/Core/Security/Session.php','Native Identity uses PHP session handling.','Enable session support and verify its backend separately.'],
            'CORE_FUNCTIONS'=>['core','REQUIRED','src/Core/Id.php','Core JSON/hash/filter/random/time/config functions must be callable.','Enable the source-derived core functions; review disabled_functions.'],
            'ARGON2ID'=>['identity','REQUIRED','src/Identity/Passwords.php','Native password hashing explicitly uses PASSWORD_ARGON2ID.','Use PHP compiled with Argon2id password support.'],
            'WEBROOT_PATH'=>['core','REQUIRED','docs/F0_SETUP.md','The declared document root must resolve exactly to the native public directory.','Provide deployment metadata through a private FD; point the virtualhost only at public.'],
            'CONFIG_FILE'=>['core','OPERATIONAL_VALIDATION','docs/F0_SETUP.md','A declared secret configuration file must be private and outside the webroot.','If a file is used, provide its existing owner-private path; environment-injected secrets need separate panel validation. Never supply secret contents to this report.'],
            'STORAGE_PATH'=>['core','REQUIRED','src/Support/Mail/Factory.php','Native storage must be outside the public directory and locally accessible.','Provide an existing protected storage directory; provision permissions outside this tool.'],
            'SESSION_PATH'=>['identity','REQUIRED','src/Core/Security/Session.php','The files session backend needs a usable non-public directory.','Verify the actual web and CLI session backend and private session-file permissions.'],
            'OPEN_BASEDIR'=>['core','OPERATIONAL_VALIDATION','docs/F0_SETUP.md','Path probes respect the effective open_basedir policy.','Independently ensure all deployment paths are allowed for CLI and web PHP.'],
            'WRITE_AND_LOCK'=>['core','OPERATIONAL_VALIDATION','src/Support/Mail/FileStore.php','Read-only permission metadata does not prove write, lock, rename or durability.','Arrange a separately authorized disposable write/lock/durability check; none is performed here.'],
            'DISK_CAPACITY'=>['core','OPERATIONAL_VALIDATION','docs/14_OPEN_VALIDATIONS.md','An observed positive free-space value is not a hosting capacity or quota guarantee.','Measure application, database, quota and retention budgets on the actual host.'],
            'FILEINFO'=>['documents','FEATURE_SPECIFIC','src/Billing/DocumentStore.php','Official PDF inspection uses the finfo MIME inspector.','Enable fileinfo before enabling official PDF upload/inspection.'],
            'INTL_IDN'=>['domains','FEATURE_SPECIFIC','src/Domains/DomainName.php','Native IDN normalization uses idn_to_ascii.','Enable intl before enabling native IDN domain operations.'],
            'FILE_LOCKS'=>['documents','FEATURE_SPECIFIC','src/Billing/DocumentStore.php','Managed native uploads require fopen/flock/flush and file operations.','Enable native file operations and independently verify storage locking.'],
            'BACKUP_FILE_FUNCTIONS'=>['offline_backup','FEATURE_SPECIFIC','src/OfflineBackup/Guard.php','F6-F also needs callable native file, lock and secretstream functions.','Keep F6-F disabled when its native storage or secretstream functions are disabled.'],
            'POSIX_BACKUP'=>['offline_backup','FEATURE_SPECIFIC','src/OfflineBackup/Guard.php','F6-F requires posix_geteuid and a non-root process.','Keep F6-F disabled unless its own non-root and ownership requirements are satisfied.'],
            'FSYNC_BACKUP'=>['offline_backup','FEATURE_SPECIFIC','src/OfflineBackup/Guard.php','F6-F requires file and directory fsync.','Keep F6-F disabled if fsync is unavailable; validate directory fsync independently.'],
            'BACKUP_HEADROOM'=>['offline_backup','FEATURE_SPECIFIC','src/OfflineBackup/Guard.php','F6-F requires at least 128 MiB of measured app-filesystem free headroom.','Independently validate F6-F app and DB headroom; this is not a global installation minimum.'],
            'POSIX_DISCOVERY'=>['source_discovery','FEATURE_SPECIFIC','src/Migration/Discovery/DiscoveryCli.php','Offline discovery private-descriptor ownership checks require POSIX UID support.','Keep that discovery CLI disabled if its own private-descriptor requirements cannot be met.'],
            'OPENSSL_OBSERVED'=>['transport','OPERATIONAL_VALIDATION','docs/14_OPEN_VALIDATIONS.md','openssl availability is observed; no direct openssl_* core requirement is invented.','Validate actual HTTPS/certificate and transport integration separately; this tool makes no network request.'],
            'WEB_PHP'=>['core','OPERATIONAL_VALIDATION','docs/14_OPEN_VALIDATIONS.md','A caller-declared web version may reveal a mismatch but cannot attest the web process.','Independently inspect the web PHP version/extensions/settings without exposing phpinfo or secrets.'],
            'CRON'=>['queue','OPERATIONAL_VALIDATION','docs/F0_SETUP.md','Minute cron permission, command routing and overlap behavior are unverified.','Verify one-minute hosting cron and bounded outbox/worker invocations; do not install cron from this tool.'],
            'MEMORY_TIMEOUT'=>['queue','OPERATIONAL_VALIDATION','src/Core/Queue/Queue.php','Hosting memory, timeout and concurrent worker admission require operational measurement.','Validate native worker lease/runtime and memory budgets on the intended hosting plan.'],
            'WEB_MAPPING_TLS'=>['core','OPERATIONAL_VALIDATION','docs/F0_SETUP.md','Local path metadata does not prove virtualhost routing, HTTPS or web isolation.','Verify actual HTTPS and denial of source, config, storage and directory listing over the web.'],
            'DB_CONNECTION'=>['core','REQUIRED','src/Core/Database/Database.php','Database metadata is only queried after an explicit private read-only config FD.','Provide a dedicated least-privilege read-only metadata account via the explicit DB config FD.'],
            'DB_SCHEMA'=>['core','REQUIRED','src/Core/Config.php','The explicit metadata connection must select its declared native database.','Provide a DB-scoped DSN through the read-only private FD.'],
            'DB_MARIADB'=>['core','REQUIRED','docs/F0_SETUP.md','The documented MariaDB 10.11+ baseline is checked from server version metadata.','Use the documented MariaDB baseline; version detection is not a full SQL contract test.'],
            'DB_INNODB'=>['core','REQUIRED','migrations/001_f0_infrastructure.php','Native schema and queue depend on InnoDB transactions.','Ensure InnoDB is available; validate migration and transaction behavior separately.'],
            'DB_CHARSET'=>['core','REQUIRED','src/Core/Config.php','Native configuration requires utf8mb4 connection encoding.','Use utf8mb4 in the explicitly supplied metadata DSN and application configuration.'],
            'DB_SQL_MODE'=>['core','OPERATIONAL_VALIDATION','src/Core/Database/Database.php','Current metadata session SQL mode is observed without issuing SET.','Validate that native application session SQL-mode initialization works with its actual account.'],
            'DB_UTC'=>['core','OPERATIONAL_VALIDATION','src/Core/Database/Database.php','Current UTC offset is observed without modifying session time_zone.','Validate native application UTC initialization and actual scheduling semantics.'],
            'DB_SQL_FEATURES'=>['queue','OPERATIONAL_VALIDATION','src/Core/Queue/Queue.php','Version metadata does not execute or attest queue SKIP LOCKED, triggers, CHECKs or migrations.','Perform separately authorized migration/transaction/queue contract tests in an isolated environment.'],
            'DB_DISKS'=>['offline_backup','FEATURE_SPECIFIC','src/OfflineBackup/Guard.php','F6-F DISKS metadata visibility is optional to general hosting installation.','Keep F6-F disabled if DISKS is missing/denied; do not install a plugin from this tool.'],
            'BACKUP_PRIVILEGES'=>['offline_backup','OPERATIONAL_VALIDATION','src/OfflineBackup/Workspace.php','CREATE/DROP/LOCK/TRIGGER and actual recovery privileges are not tested.','Independently authorize and validate isolated backup/restore prerequisites; never infer them from grants or flags.'],
            'PITR_OFFSITE'=>['recovery','OPERATIONAL_VALIDATION','docs/13_ACCEPTANCE_TESTS.md','Offsite custody, retention, PITR and actual restore are not tested.','Obtain independent OPS-002/003 and G-O02 operational evidence.'],
        ];
    }
}
