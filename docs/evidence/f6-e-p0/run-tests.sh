#!/bin/sh
set -eu
tar -C /input --exclude=.git --exclude=storage --exclude=tools/local --exclude=tools/analysis/vendor --exclude=tools/browser/node_modules -cf - . | tar -C /workspace -xf -
mkdir -p /workspace/storage
cd /workspace
php -r '$m=json_decode(file_get_contents("/run-input/source-manifest.json"),true,512,JSON_THROW_ON_ERROR);foreach($m as $p=>$h){if(hash_file("sha256",$p)!==$h){exit(91);}}echo "P0_SOURCE_MANIFEST_MATCH ".count($m).PHP_EOL;'
php -r 'echo "P0_PHP ".PHP_VERSION.PHP_EOL;'
if [ "${P0_MODE:-all}" = "all" ]; then
for suite in tests/migration-cutover-preflight.php tests/migration-reconciliation.php tests/migration-recovery-integrity.php tests/migration-recovery-target.php tests/migration-recovery.php tests/migration-contract.php tests/finance-unit.php tests/identity-security.php tests/hardening-unit.php; do
 echo "P0_SUITE_BEGIN $suite"
 if php "$suite"; then echo "P0_SUITE_RESULT $suite 0"; else code=$?; echo "P0_SUITE_RESULT $suite $code"; exit "$code"; fi
done
fi
for quality in check audit; do
 echo "P0_QUALITY_BEGIN $quality"
 if php "tools/$quality.php"; then echo "P0_QUALITY_RESULT $quality 0"; else code=$?; echo "P0_QUALITY_RESULT $quality $code"; exit "$code"; fi
done
echo P0_QUALITY_BEGIN phpstan
if php /analysis/phpstan.phar analyse --no-progress --memory-limit=512M --error-format=json; then printf '\nP0_QUALITY_RESULT phpstan 0\n'; else code=$?; printf '\nP0_QUALITY_RESULT phpstan %s\n' "$code"; exit "$code"; fi
git init -q
git add -- README.md docs
echo P0_QUALITY_BEGIN documentation
if php tools/ci/doc-check.php; then echo P0_QUALITY_RESULT documentation 0; else code=$?; echo "P0_QUALITY_RESULT documentation $code"; exit "$code"; fi
php -r '$m=json_decode(file_get_contents("/run-input/source-manifest.json"),true,512,JSON_THROW_ON_ERROR);foreach($m as $p=>$h){if(hash_file("sha256",$p)!==$h){exit(91);}}echo "P0_SOURCE_MANIFEST_MATCH ".count($m).PHP_EOL;'
