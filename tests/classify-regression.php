<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$guard = migration_connection();
if (!$guard->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
    exit("Refusing database tests outside the disposable development database.\n");
}

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
define('DB_PREFIX', getenv('DB_PREFIX') ?: 'f_');
require ROOT_PATH.'/system/core/database.php';
require ROOT_PATH.'/system/core/model.php';
require ROOT_PATH.'/system/common/security.php';
require ROOT_PATH.'/system/common/function.php';

function expected_navigation(int $parent, string $fields): array
{
    $rows = D('classify')->field($fields)->where('classid='.$parent.'&&nav=1')
        ->sort('sort', 'order', 'asc')->limit(100)->getall();
    foreach ($rows as &$row) { $row['html'] = cms_safe_url($row['html']); }
    unset($row);
    return $rows;
}

$fields = 'id,html,name,url_type,type';
$root = M('module')->l('classify', 'w[classid=0&&nav=1];f['.$fields.'];n[100];s[sort,2]');
$expectedRoot = expected_navigation(0, $fields);
if ($root !== $expectedRoot) {
    throw new RuntimeException('Navigation root no longer matches its original SQL query: '.json_encode([
        'actual_ids'=>array_column($root,'id'),'expected_ids'=>array_column($expectedRoot,'id'),
        'actual_fields'=>array_keys($root[0]??[]),'expected_fields'=>array_keys($expectedRoot[0]??[]),
    ]));
}
echo "PASS navigation root keeps SQL results and order\n";

$parents = array_map(static fn($row) => (int)$row['id'], $root);
if (!$parents) { $parents = [0]; }
$status = D('classify');
$before = (int)$status->excsql("SHOW SESSION STATUS LIKE 'Questions'")[0]['Value'];
foreach ($parents as $parent) {
    $actual = M('module')->l('classify',
        'w[classid='.$parent.'&&nav=1];f[id,html,name,url_type];n[100];s[sort,2]');
    if ($actual !== expected_navigation($parent, 'id,html,name,url_type')) {
        throw new RuntimeException('Navigation child differs from its original SQL query.');
    }
}
$after = (int)$status->excsql("SHOW SESSION STATUS LIKE 'Questions'")[0]['Value'];
if ($after - $before !== count($parents) + 1) {
    throw new RuntimeException('Navigation cache still issues a query for each parent.');
}
echo "PASS navigation children reuse one request-local category snapshot\n";

$first = M('classify')->children(0);
$before = (int)$status->excsql("SHOW SESSION STATUS LIKE 'Questions'")[0]['Value'];
$second = M('classify')->children(0);
$after = (int)$status->excsql("SHOW SESSION STATUS LIKE 'Questions'")[0]['Value'];
if ($first !== $second || $after - $before !== 1) {
    throw new RuntimeException('Category descendants did not reuse the request snapshot.');
}
echo "PASS category descendants reuse the snapshot across model instances\n";
