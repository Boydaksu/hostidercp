#!/bin/sh
set -u
cd /workspace/repo
overall=0
step() {
    name=$1
    shift
    printf 'LOCAL_STEP %s\n' "$name"
    if "$@"; then result=0; else result=$?; overall=1; fi
    printf 'LOCAL_STEP_RESULT %s %s\n' "$name" "$result"
}
step source_before php tools/ci/source-proof.php
if [ "$overall" != 0 ]; then exit 1; fi
php -r 'echo "LOCAL_PHP ".PHP_VERSION.PHP_EOL;'
step scope php tools/ci/plan.php
step documentation php tools/ci/doc-check.php
step helper php tools/ci/self-test.php
step composer_audit composer audit --working-dir=tools/analysis --locked --no-interaction
step phpstan php tools/analysis/vendor/phpstan/phpstan/phpstan.phar analyse --no-progress --memory-limit=512M
step lint php tools/check.php
step patterns php tools/audit.php
step js_support node --check public/assets/support.js
step js_commerce node --check public/assets/commerce.js
step js_services node --check public/assets/services.js
step js_providers node --check public/assets/provider-panel.js
step js_state node tests/support-e-state.cjs
step npm_audit npm audit --prefix tools/browser --package-lock-only --audit-level=high
step source_after php tools/ci/source-proof.php
printf 'LOCAL_QUALITY_RESULT %s\n' "$overall"
exit "$overall"
