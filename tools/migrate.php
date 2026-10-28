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

function migrate_roles(mysqli $db, string $prefix): void
{
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $prefix)) {
        throw new RuntimeException('Invalid database prefix.');
    }
    $table = $prefix.'manage';
    $columns = array_column($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC), 'Field');
    if (!in_array('role', $columns, true)) {
        // Existing administrators retain their original full access.
        $db->query("ALTER TABLE `{$table}` ADD `role` varchar(20) NOT NULL DEFAULT 'super_admin'");
    }
    if (!in_array('enabled', $columns, true)) {
        $db->query("ALTER TABLE `{$table}` ADD `enabled` tinyint(1) NOT NULL DEFAULT 1");
    }
    if (!in_array('auth_version', $columns, true)) {
        $db->query("ALTER TABLE `{$table}` ADD `auth_version` int unsigned NOT NULL DEFAULT 1");
    }
    if (!in_array('login_code_hash', $columns, true)) {
        $db->query("ALTER TABLE `{$table}` ADD `login_code_hash` varchar(255) DEFAULT NULL");
    }
    $db->query("ALTER TABLE `{$table}` ALTER COLUMN `role` SET DEFAULT 'editor'");
    $duplicates = $db->query("SELECT `admin`, COUNT(*) AS total FROM `{$table}` WHERE `admin` IS NOT NULL GROUP BY `admin` HAVING total>1 LIMIT 1")->fetch_assoc();
    if ($duplicates) {
        throw new RuntimeException('Duplicate administrator usernames must be resolved before adding the unique index.');
    }
    $indexes = array_column($db->query('SHOW INDEX FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC), 'Key_name');
    if (!in_array('admin_unique', $indexes, true)) {
        $db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `admin_unique` (`admin`)");
    }
}

function migrate_content_write(mysqli $db, string $prefix): void
{
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $prefix)) {
        throw new RuntimeException('Invalid database prefix.');
    }
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}content_recycle_state` (
        `project` varchar(30) NOT NULL, `record_id` int unsigned NOT NULL,
        `previous_status` tinyint NOT NULL, PRIMARY KEY (`project`,`record_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}content_create_request` (
        `account_id` int unsigned NOT NULL, `project` varchar(30) NOT NULL,
        `request_key` char(32) NOT NULL, `payload_hash` char(64) NOT NULL,
        `state` varchar(16) NOT NULL, `record_id` int unsigned DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`account_id`,`project`,`request_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function migrate_model_schema(mysqli $db,string $prefix): void
{
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$prefix)){
        throw new RuntimeException('Invalid database prefix.');
    }
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}model_schema_operation` (
        `project` varchar(30) NOT NULL, `module_id` int unsigned NOT NULL,
        `field_id` int unsigned DEFAULT NULL, `action` varchar(20) NOT NULL,
        `field_name` varchar(50) NOT NULL DEFAULT '', `stage` varchar(20) NOT NULL,
        `payload` text NOT NULL, `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`project`,`action`,`field_name`), KEY (`module_id`,`field_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function migrate_upload_request(mysqli $db,string $prefix): void
{
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$prefix)){
        throw new RuntimeException('Invalid database prefix.');
    }
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}upload_request` (
        `account_id` int unsigned NOT NULL,
        `request_key` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `directory` varchar(8) NOT NULL,
        `payload_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `relative_path` varchar(255) NOT NULL, `status` varchar(8) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`account_id`,`request_key`), KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function migrate_message_workflow(mysqli $db,string $prefix): void
{
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$prefix)){
        throw new RuntimeException('Invalid database prefix.');
    }
    $table=$prefix.'message';
    $columns=array_column($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC),'Field');
    if(!in_array('submission_key',$columns,true)){
        $db->query("ALTER TABLE `{$table}` ADD `submission_key` char(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL");
    }
    if(!in_array('edit_version',$columns,true)){
        $db->query("ALTER TABLE `{$table}` ADD `edit_version` char(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL");
    }
    $indexes=array_column($db->query('SHOW INDEX FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC),'Key_name');
    if(!in_array('message_submission_unique',$indexes,true)){
        $db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `message_submission_unique` (`submission_key`)");
    }
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
    migrate_roles($db, $prefix);
    migrate_content_write($db, $prefix);
    migrate_model_schema($db, $prefix);
    migrate_upload_request($db, $prefix);
    migrate_message_workflow($db, $prefix);
    $updated = migrate_license_notice($db, $prefix);
    echo "Security, content-write and message workflow schema migration complete; replaced $updated legacy sample license footer(s).\n";
}
