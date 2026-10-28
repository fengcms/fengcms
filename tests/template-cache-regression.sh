#!/bin/sh
set -eu
cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
qa_root="/tmp/fengcms_cache_qa_$(openssl rand -hex 6)"
trap 'docker compose exec -T php rm -rf -- "$qa_root" >/dev/null 2>&1 || true' EXIT HUP INT TERM
docker compose exec -T -u www-data -e CMS_CACHE_QA_ROOT="$qa_root" -e APP_RUNTIME_PATH="$qa_root/runtime" php php tests/template-cache-regression.php
