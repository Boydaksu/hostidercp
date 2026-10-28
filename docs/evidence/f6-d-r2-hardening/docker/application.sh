#!/bin/sh
set -u
cd /workspace/repo
php tools/ci/source-proof.php || exit 1
php -r 'echo "LOCAL_PHP ".PHP_VERSION.PHP_EOL;'
printf 'LOCAL_STEP app_broad\n'
if php tools/ci/run.php broad; then result=0; else result=$?; fi
printf 'LOCAL_STEP_RESULT app_broad %s\n' "$result"
php tools/ci/source-proof.php || exit 1
printf 'LOCAL_APPLICATION_RESULT %s\n' "$result"
exit "$result"
