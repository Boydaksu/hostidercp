<?php
declare(strict_types=1);
return [
    "ALTER TABLE finance_credits ADD fx_snapshot_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,ADD source_journal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,ADD FOREIGN KEY(fx_snapshot_id) REFERENCES finance_fx_snapshots(id),ADD FOREIGN KEY(source_journal_id) REFERENCES finance_journals(id)",
];
