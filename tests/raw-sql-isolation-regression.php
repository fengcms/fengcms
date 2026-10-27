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

$suffix = 'qa_raw_sql_'.bin2hex(random_bytes(5));
$table = DB_PREFIX.$suffix;
$guard->query("CREATE TABLE `$table` (id INT PRIMARY KEY, status INT NOT NULL) ENGINE=InnoDB");
try {
    $beginnings = [
        'block comment' => '/* probe */ START TRANSACTION',
        'line comment' => "-- probe\nSTART TRANSACTION",
        'executable comment' => '/*!80000 START TRANSACTION */',
        'plain BEGIN' => 'BEGIN',
        'plain START TRANSACTION' => 'START TRANSACTION',
    ];
    $number = 10;
    foreach ($beginnings as $label => $begin) {
        $transaction = new model($suffix);
        $ordinary = new model($suffix);
        $ordinaryId = (int)$ordinary->excsql('SELECT CONNECTION_ID() AS id')[0]['id'];
        $transaction->query($begin);
        $transactionId = (int)$transaction->excsql('SELECT CONNECTION_ID() AS id')[0]['id'];
        $transaction->insert(['id'=>$number, 'status'=>1]);
        $ordinary->insert(['id'=>$number+1, 'status'=>1]);
        $transaction->query('ROLLBACK');
        if ($transactionId === $ordinaryId ||
            (new model($suffix))->where(['id'=>$number])->getone() !== null ||
            (new model($suffix))->where(['id'=>$number+1])->getone() === null) {
            throw new RuntimeException('Native transaction affected another model: '.$label);
        }
        echo 'PASS native transaction isolation: '.$label.PHP_EOL;
        $number += 10;
    }

    $session = new model($suffix);
    $session->excsql('/* probe */ SET @fengcms_raw_sql_probe = 7');
    $private = $session->excsql('SELECT @fengcms_raw_sql_probe AS value')[0]['value'];
    $shared = (new model($suffix))->field('@fengcms_raw_sql_probe AS value')->getone()['value'];
    if ((int)$private !== 7 || $shared !== null) {
        throw new RuntimeException('A raw session variable leaked into the shared connection.');
    }
    echo "PASS commented session SQL remains private\n";

    $temporary = DB_PREFIX.'qa_temp_'.bin2hex(random_bytes(5));
    $session->query("CREATE TEMPORARY TABLE `$temporary` (id INT PRIMARY KEY)");
    try {
        $session->query("INSERT INTO `$temporary` (id) VALUES (1)");
        if ((int)$session->excsql("SELECT COUNT(*) AS total FROM `$temporary`")[0]['total'] !== 1) {
            throw new RuntimeException('Temporary table is unavailable to its owner.');
        }
        try {
            (new model(substr($temporary, strlen(DB_PREFIX))))->getcount();
            throw new RuntimeException('Temporary table leaked into a shared connection.');
        } catch (mysqli_sql_exception) {}
        echo "PASS temporary table stays on its model connection\n";
    } finally { $session->query("DROP TEMPORARY TABLE IF EXISTS `$temporary`"); }

    $lockName = 'fengcms-probe-'.bin2hex(random_bytes(8));
    $holder = new model($suffix);
    try {
        $acquired = $holder->excsql('SELECT GET_LOCK(?,0) AS acquired', [$lockName]);
        $holderId = (int)$holder->excsql('SELECT CONNECTION_ID() AS id')[0]['id'];
        $probe = new db_mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        try { $usedBy = $probe->assoc($probe->query('SELECT IS_USED_LOCK(?) AS holder', [$lockName]))['holder']; }
        finally { $probe->close(); }
        if ((int)$acquired[0]['acquired'] !== 1 || (int)$usedBy !== $holderId || $holderId === $ordinaryId) {
            throw new RuntimeException('Connection-level lock owner is incorrect.');
        }
        echo "PASS connection-level lock stays on its model connection\n";
    } finally { $holder->excsql('SELECT RELEASE_LOCK(?) AS released', [$lockName]); }
} finally { $guard->query("DROP TABLE IF EXISTS `$table`"); }
