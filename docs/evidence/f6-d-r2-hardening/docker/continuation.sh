#!/bin/sh
set -u
cd /workspace/repo
php tools/ci/source-proof.php || exit 1
result=0
for suite in support-http support-e migration-contract migration-visibility migration-import migration-reconciliation migration-operations migration-recovery migration-recovery-target migration-recovery-integrity; do
 php tools/ci/source-proof.php || exit 1
 echo "CONTINUATION_SUITE tests/$suite.php"
 php "tests/$suite.php"
 code=$?
 echo "CONTINUATION_RESULT $suite $code"
 if [ "$code" -ne 0 ]; then result=1; fi
done
php tools/ci/source-proof.php || exit 1
exit "$result"
