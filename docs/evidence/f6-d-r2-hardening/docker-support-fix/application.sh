#!/bin/sh
cd /workspace/repo
php tools/ci/source-proof.php || exit 1
php tests/support-http.php
result=$?
df -k /workspace
php tools/ci/source-proof.php || exit 1
exit "$result"
