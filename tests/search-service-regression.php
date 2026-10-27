<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$guard = migration_connection();
if (!$guard->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
    exit("Refusing database tests outside the disposable development database.\n");
}

define('TPL_INCLUDE', true);
define('URL_TYPE', 0);
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
require ROOT_PATH.'/system/common/security.php';
require ROOT_PATH.'/system/common/function.php';
require ROOT_PATH.'/system/core/database.php';
require ROOT_PATH.'/system/core/model.php';
require ROOT_PATH.'/system/core/page.php';
require ROOT_PATH.'/app/model/moduleModel.php';

$tag = 'qa_search_contract_'.bin2hex(random_bytes(5));
$ids = [];
$statement = $guard->prepare('INSERT INTO f_article (title,html,status,time,date) VALUES (?,"",1,?,CURDATE())');
try {
    for ($i = 0; $i < 12; $i++) {
        $title = $tag.'_'.$i;
        $time = 1700000000 + $i;
        $statement->bind_param('si', $title, $time);
        $statement->execute();
        $ids[] = $guard->insert_id;
    }
    $statement->close();

    $criteria = CmsSearchCriteria::fromInput(['tags'=>$tag,'project'=>'article'], '2');
    $query = ['controller'=>'search','project'=>'article','tags'=>$tag,'page'=>'2'];
    $context = new CmsPageContext(2, $query, '/index.php?'.http_build_query($query), '/index.php');
    $_GET = ['controller'=>'wrong','tags'=>'wrong','page'=>'1'];
    $_SERVER['REQUEST_URI'] = '/wrong.php?page=1';
    $_SERVER['PHP_SELF'] = '/wrong.php';
    $explicit = (new moduleModel('module'))->searchPage($criteria, $context, '', 10);
    $titles = array_column($explicit['list'], 'title');
    $pageHtml = (string)$explicit['fy'];
    if ($explicit['total'] !== 12 || $titles !== [$tag.'_1', $tag.'_0'] ||
        str_contains($pageHtml, 'wrong') || !str_contains($pageHtml, 'tags='.$tag) ||
        $_GET['page'] !== '1') {
        throw new RuntimeException('Explicit search used ambient request state or returned the wrong page.');
    }
    echo "PASS explicit search returns page two without ambient request state\n";

    $_GET = $query;
    $_SERVER['REQUEST_URI'] = $context->requestUri;
    $_SERVER['PHP_SELF'] = $context->scriptPath;
    $legacy = (new moduleModel('module'))->search($_GET, '', 10);
    if ($legacy['total'] !== $explicit['total'] ||
        array_column($legacy['list'], 'id') !== array_column($explicit['list'], 'id') ||
        (string)$legacy['fy'] !== $pageHtml) {
        throw new RuntimeException('Legacy template search differs from the explicit search API.');
    }
    echo "PASS legacy template search delegates without changing results\n";
} finally {
    if ($ids) { $guard->query('DELETE FROM f_article WHERE id IN ('.implode(',', array_map('intval', $ids)).')'); }
}
