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

function migrate_license_notice(mysqli $db, string $prefix): int
{
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $prefix)) {
        throw new RuntimeException('Invalid database prefix.');
    }
    // Replace only the untouched legacy sample footer; preserve site-specific copyright text.
    $old = 'Copyright 20013-2015 Powered by FengCms.COM，地方网络工作室 All Rights Reserved.<br />地方网络工作室 版权所有 未经许可严禁复制或建立镜像 <br />版本号：FengCms Beta 1.0';
    $new = '© 2013-2026 地方网络工作室及 FengCMS 贡献者。FengCMS 软件采用 MIT License；软件许可见仓库 LICENSE。网站内容版权由站点所有者自行设置。';
    $stmt = $db->prepare("UPDATE `{$prefix}base` SET copyright=? WHERE copyright=?");
    $stmt->bind_param('ss', $new, $old);
    $stmt->execute();
    return $stmt->affected_rows;
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $db = migration_connection();
    $prefix = getenv('DB_PREFIX') ?: 'f_';
    migrate_security($db, $prefix);
    $updated = migrate_license_notice($db, $prefix);
    echo "Security schema migration complete; replaced $updated legacy sample license footer(s).\n";
}
