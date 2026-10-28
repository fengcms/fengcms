#!/bin/sh
set -eu
cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
qa_db="fengcms_message_qa_$(openssl rand -hex 6)"
created=1
cleanup() {
    if [ "$created" -eq 1 ]; then
        docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root' >/dev/null 2>&1 <<SQL || true
REVOKE ALL PRIVILEGES ON \`$qa_db\`.* FROM 'fengcms'@'%';
DROP DATABASE IF EXISTS \`$qa_db\`;
SQL
    fi
}
trap cleanup EXIT HUP INT TERM
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root' <<SQL
CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`$qa_db\`.* TO 'fengcms'@'%';
SQL
docker compose exec -T -e DB_NAME="$qa_db" php php tests/message-workflow-regression.php
