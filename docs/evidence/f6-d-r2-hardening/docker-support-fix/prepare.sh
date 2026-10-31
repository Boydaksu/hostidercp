#!/bin/sh
set -eu
git init -q /workspace/repo
cd /workspace/repo
git fetch -q /input/source.bundle HEAD
git checkout -q --detach "$GITHUB_SHA"
git config user.name Codex
git config user.email codex@openai.com
printf 'LOCAL_PREPARED %s\n' "$(git rev-parse HEAD)"
composer install --working-dir=tools/analysis --no-interaction --prefer-dist --no-plugins --no-scripts
printf 'LOCAL_PREP_RESULT 0\n'
