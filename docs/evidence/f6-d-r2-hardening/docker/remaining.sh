#!/bin/sh
cd /workspace/repo
php tools/ci/source-proof.php || exit 1
php /input/remaining-support.php
result=$?
php tools/ci/source-proof.php || exit 1
exit "$result"
