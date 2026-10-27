<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$guard = migration_connection();
if (!$guard->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
    exit("Refusing database tests outside the disposable development database.\n");
}

define('TPL_INCLUDE', true);
define('DRIVER_PATH', dirname(__DIR__).'/system/driver/');
define('DB_TYPE', 'mysqli');
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_PREFIX', getenv('DB_PREFIX') ?: 'f_');
require dirname(__DIR__).'/system/core/database.php';
require dirname(__DIR__).'/system/core/model.php';

$suffix = 'qa_model_state_'.bin2hex(random_bytes(5));
$table = DB_PREFIX.$suffix;
$guard->query("CREATE TABLE `$table` (id INT PRIMARY KEY, status INT NOT NULL) ENGINE=InnoDB");
try {
    $guard->query("INSERT INTO `$table` (id,status) VALUES (1,1),(2,0)");
    $model = new model($suffix);
    $model->where(['status' => 1])->field('id');
    $before = $model->buildselectsql();
    $first = $model->getcount();
    $second = $model->getcount();
    $after = $model->buildselectsql();
    $rows = $model->getall();
    if ($first != 1 || $second != 1 || $before !== $after ||
        count($rows) !== 1 || array_keys($rows[0]) !== ['id'] || (int)$rows[0]['id'] !== 1) {
        throw new RuntimeException('Count must preserve selected fields and bound conditions.');
    }
    echo "PASS repeated count preserves selected fields and bound conditions\n";

    $default = new model($suffix);
    $defaultBefore = $default->buildselectsql();
    $default->getcount();
    if ($default->buildselectsql() !== $defaultBefore) {
        throw new RuntimeException('Count must preserve the default field selection.');
    }
    echo "PASS count preserves default field selection\n";
} finally {
    $guard->query("DROP TABLE IF EXISTS `$table`");
}
