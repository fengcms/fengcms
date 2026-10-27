<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('TPL_INCLUDE', true);
define('URL_TYPE', 0);
require dirname(__DIR__).'/system/common/security.php';
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

$exactHundred=new page(1000,10,[],$context,100);
$overHundred=new page(1001,10,[],$context,100);
if ($exactHundred->getinfo()['pagenum']!==100 || $overHundred->getinfo()['pagenum']!==100 ||
    $overHundred->getinfo()['total']!==1001 ||
    str_contains((string)$overHundred->getpage(),'page=101')) {
    throw new RuntimeException('Search page limit disagrees with its visible links or total.');
}
echo "PASS bounded pager retains the true total and never links past page 100\n";

$GLOBALS['_CMS_RENDER_QUERY']=['controller'=>'search','tags'=>'测试'];
$_GET=['controller'=>'search','tags'=>'测试','operate'=>'','id'=>'0','classid'=>'0'];
$renderPager=new page(20,10);
$renderLink=html_entity_decode($renderPager->geturl(2),ENT_QUOTES,'UTF-8');
unset($GLOBALS['_CMS_RENDER_QUERY']);
if (str_contains($renderLink,'operate=') || str_contains($renderLink,'id=0') ||
    !str_contains($renderLink,'tags=%E6%B5%8B%E8%AF%95&page=2')) {
    throw new RuntimeException('Template-only query defaults leaked into pagination.');
}
echo "PASS pagination excludes temporary template query defaults\n";
