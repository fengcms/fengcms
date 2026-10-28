<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$guard = migration_connection();
if (!$guard->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
    exit("Refusing database tests outside the disposable development database.\n");
}

$prefix = 'qa_cache_'.bin2hex(random_bytes(5)).'_';
$classTable = $prefix.'classify';
$moduleTable = $prefix.'module';
$fieldTable = $prefix.'view_field';
$guard->query("CREATE TABLE `$classTable` (id INT PRIMARY KEY,classid INT, name VARCHAR(80),html VARCHAR(120),nav INT,url_type INT,type VARCHAR(20),sort INT,project VARCHAR(40)) ENGINE=InnoDB");
$guard->query("CREATE TABLE `$moduleTable` (project VARCHAR(40),type INT,status INT) ENGINE=InnoDB");
$guard->query("CREATE TABLE `$fieldTable` (id INT PRIMARY KEY,project VARCHAR(40),name VARCHAR(40)) ENGINE=InnoDB");

define('TPL_INCLUDE', true);
define('ROOT_PATH', dirname(__DIR__));
define('SYSTEM_PATH', ROOT_PATH.'/system/');
define('MODEL_PATH', ROOT_PATH.'/app/model/');
define('DRIVER_PATH', ROOT_PATH.'/system/driver/');
define('DB_TYPE', 'mysqli');
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_PREFIX', $prefix);
define('URL_TYPE', 0);
require ROOT_PATH.'/system/core/database.php';
require ROOT_PATH.'/system/core/model.php';
require ROOT_PATH.'/system/common/security.php';
require ROOT_PATH.'/system/common/function.php';

function nav_name(): string
{
    return M('classify')->navigationRows(0, ['id','name'], 10)[0]['name'];
}

function map_html(): string
{
    return (string)M('classify')->map();
}

function field_exists(): bool
{
    $module = M('module');
    return (new ReflectionMethod($module, 'attrib'))->invoke($module, 'article', 'tags') === 'tags';
}

try {
    $guard->query("INSERT INTO `$classTable` (id,classid,name,html,nav,url_type,type,sort) VALUES (1,0,'before_commit','/test',1,0,'module',0)");
    $guard->query("UPDATE `$classTable` SET project='article' WHERE id=1");
    $guard->query("INSERT INTO `$moduleTable` VALUES ('article',1,1)");
    $guard->query("INSERT INTO `$fieldTable` (id,project,name) VALUES (1,'article','tags')");

    if (map_html() === '' || nav_name() !== 'before_commit') { throw new RuntimeException('Initial category snapshot is incorrect.'); }
    $category = D('classify');
    $category->affairs('begin');
    $category->where(['id'=>1])->update(['name'=>'after_commit']);
    if (!str_contains(map_html(),'before_commit') || str_contains(map_html(),'after_commit')) { throw new RuntimeException('Uncommitted category write became visible.'); }
    $category->affairs('commit');
    if (!str_contains(map_html(),'after_commit') || nav_name() !== 'after_commit' ||
        $guard->query("SELECT name FROM `$classTable` WHERE id=1")->fetch_assoc()['name'] !== 'after_commit') {
        throw new RuntimeException('Category cache stayed stale after commit.');
    }
    echo "PASS category cache refreshes after affairs commit\n";

    $category->affairs('begin');
    $category->where(['id'=>1])->update(['name'=>'rolled_back']);
    if (!str_contains(map_html(),'after_commit') || str_contains(map_html(),'rolled_back')) { throw new RuntimeException('Uncommitted category write changed navigation.'); }
    $category->affairs('rollback');
    if (!str_contains(map_html(),'after_commit') || str_contains(map_html(),'rolled_back')) { throw new RuntimeException('Category cache changed after rollback.'); }
    echo "PASS category cache stays correct after affairs rollback\n";

    $raw = D('classify');
    $raw->query('START TRANSACTION');
    $raw->where(['id'=>1])->update(['name'=>'raw_commit']);
    if (!str_contains(map_html(),'after_commit') || str_contains(map_html(),'raw_commit')) { throw new RuntimeException('Uncommitted raw write became visible.'); }
    $raw->query('COMMIT');
    if (!str_contains(map_html(),'raw_commit') || nav_name() !== 'raw_commit') { throw new RuntimeException('Raw COMMIT did not invalidate category cache.'); }
    echo "PASS category cache refreshes after native COMMIT\n";

    $raw = D('classify');
    $raw->excsql('START TRANSACTION');
    $raw->where(['id'=>1])->update(['name'=>'raw_execsql']);
    if (!str_contains(map_html(),'raw_commit') || str_contains(map_html(),'raw_execsql')) { throw new RuntimeException('Uncommitted excsql write became visible.'); }
    $raw->excsql('COMMIT');
    if (!str_contains(map_html(),'raw_execsql') || nav_name() !== 'raw_execsql') { throw new RuntimeException('excsql COMMIT did not invalidate category cache.'); }
    echo "PASS category cache refreshes after excsql COMMIT\n";

    if (!field_exists()) { throw new RuntimeException('Initial field capability is missing.'); }
    $field = D('view_field');
    $field->affairs('begin');
    $field->where(['id'=>1])->update(['name'=>'other']);
    if (!field_exists()) { throw new RuntimeException('Uncommitted field metadata became visible.'); }
    $field->affairs('commit');
    if (field_exists()) { throw new RuntimeException('Field capability cache stayed stale after commit.'); }
    echo "PASS field capability cache refreshes after commit\n";

    $field->affairs('begin');
    $field->where(['id'=>1])->update(['name'=>'tags']);
    if (field_exists()) { throw new RuntimeException('Uncommitted field metadata became visible.'); }
    $field->affairs('rollback');
    if (field_exists()) { throw new RuntimeException('Field capability cache changed after rollback.'); }
    D('view_field')->where(['id'=>1])->update(['name'=>'tags']);
    if (!field_exists()) { throw new RuntimeException('Ordinary committed metadata write did not invalidate cache.'); }
    echo "PASS field capability cache handles rollback and ordinary writes\n";
} finally {
    $guard->query("DROP TABLE IF EXISTS `$fieldTable`");
    $guard->query("DROP TABLE IF EXISTS `$classTable`");
    $guard->query("DROP TABLE IF EXISTS `$moduleTable`");
}
