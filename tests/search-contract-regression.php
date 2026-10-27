<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('TPL_INCLUDE', true);
define('URL_TYPE', 0);
require dirname(__DIR__).'/app/searchCriteria.php';
require dirname(__DIR__).'/system/core/page.php';

$criteria = CmsSearchCriteria::fromInput(['tags'=>'测试','project'=>'article'], '2');
if ($criteria->tags !== '测试' || $criteria->project !== 'article' || $criteria->page !== 2) {
    throw new RuntimeException('Search criteria lost validated values.');
}
foreach ([
    [['tags'=>[]], '1'],
    [['tags'=>'ok','project'=>'../admin'], '1'],
    [['tags'=>'ok'], ['2']],
    [['tags'=>'ok'], '101'],
] as [$input, $page]) {
    try {
        CmsSearchCriteria::fromInput($input, $page);
        throw new RuntimeException('Malformed search input was accepted.');
    } catch (InvalidArgumentException) {}
}
echo "PASS search criteria validate explicit input\n";

$_GET = ['controller'=>'wrong','tags'=>'wrong','page'=>'99'];
$_SERVER['REQUEST_URI'] = '/wrong.php?page=99';
$_SERVER['PHP_SELF'] = '/wrong.php';
$context = new CmsPageContext(2, ['controller'=>'search','tags'=>'测试','page'=>'2'],
    '/index.php?controller=search&tags=%E6%B5%8B%E8%AF%95&page=2', '/index.php');
$pager = new page(35, 10, [], $context);
$url = html_entity_decode($pager->geturl(3), ENT_QUOTES, 'UTF-8');
if ($pager->getinfo()['curpage'] !== 2 ||
    $url !== '/index.php?controller=search&tags=%E6%B5%8B%E8%AF%95&page=3') {
    throw new RuntimeException('Explicit pagination leaked global request values.');
}
echo "PASS explicit pagination ignores request globals\n";

$_GET = ['controller'=>'search','tags'=>'测试','page'=>'2'];
$_SERVER['REQUEST_URI'] = '/index.php?controller=search&tags=%E6%B5%8B%E8%AF%95&page=2';
$_SERVER['PHP_SELF'] = '/index.php';
$legacy = new page(35, 10);
if ($legacy->getinfo()['curpage'] !== 2 ||
    html_entity_decode($legacy->geturl(3), ENT_QUOTES, 'UTF-8') !== $url) {
    throw new RuntimeException('Legacy pagination changed.');
}
echo "PASS legacy pagination still uses its request context\n";
