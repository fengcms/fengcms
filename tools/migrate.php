<?php
// CLI only. Back up the database before applying this to an existing site.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function migration_connection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(getenv('DB_HOST') ?: 'db', getenv('DB_USER') ?: 'fengcms', getenv('DB_PASS') ?: '', getenv('DB_NAME') ?: 'fengcms');
    $db->set_charset('utf8mb4');
    return $db;
}

function migrate_security(mysqli $db, string $prefix): void
{
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $prefix)) {
        throw new RuntimeException('Invalid database prefix.');
    }
    $db->query("ALTER TABLE `{$prefix}manage` MODIFY `password` varchar(255) DEFAULT NULL COMMENT '密码'");
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}login_attempts` (
        bucket char(64) NOT NULL PRIMARY KEY,
        attempts int unsigned NOT NULL DEFAULT 0,
        expires_at int unsigned NOT NULL,
        KEY (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    migrate_security(migration_connection(), getenv('DB_PREFIX') ?: 'f_');
    echo "Security schema migration complete.\n";
}
