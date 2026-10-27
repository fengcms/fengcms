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

    $default->sort('id', 'order', 'desc')->limit(1, 1);
    $pagedSql = $default->buildselectsql();
    if ((int)$default->getcount() !== 2 || $default->buildselectsql() !== $pagedSql ||
        count($default->getall()) !== 1) {
        throw new RuntimeException('Count must ignore pagination without changing the later list query.');
    }
    echo "PASS count ignores pagination and restores list query options\n";

    $bound = new model($suffix);
    $bound->where('status=?', [1]);
    if ((int)$bound->getcount() !== 1 || (int)$bound->getall()[0]['id'] !== 1) {
        throw new RuntimeException('Count lost bound raw-condition parameters.');
    }
    echo "PASS count preserves bound raw-condition parameters\n";

    $other = new model($suffix);
    $other->where(['status' => 0])->field('id');
    $sharedId = (int)$model->excsql('SELECT CONNECTION_ID() AS id')[0]['id'];
    $otherId = (int)$other->excsql('SELECT CONNECTION_ID() AS id')[0]['id'];
    $otherRows = $other->getall();
    if ($sharedId !== $otherId || count($otherRows) !== 1 || (int)$otherRows[0]['id'] !== 2 ||
        (int)$model->getall()[0]['id'] !== 1) {
        throw new RuntimeException('Models must share only the connection, not query options.');
    }
    echo "PASS models share a connection without sharing query options\n";

    $transaction = new model($suffix);
    $transaction->affairs('begin');
    try {
        $transaction->insert(['id' => 3, 'status' => 1]);
        $transactionId = (int)$transaction->excsql('SELECT CONNECTION_ID() AS id')[0]['id'];
        if ($transactionId === $sharedId || (int)$model->getcount() !== 1) {
            throw new RuntimeException('A model transaction leaked into ordinary queries.');
        }
    } finally { $transaction->affairs('rollback'); }
    echo "PASS model transactions retain an isolated connection\n";

    $revision = model::requestWriteRevision();
    $model->where(['id' => 1])->update(['status' => 1]);
    if (model::requestWriteRevision() <= $revision) {
        throw new RuntimeException('Model writes did not invalidate request-local read snapshots.');
    }
    echo "PASS model writes invalidate request-local read snapshots\n";
} finally {
    $guard->query("DROP TABLE IF EXISTS `$table`");
}
