<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$mode=$argv[1]??null;
if (!in_array($mode,['dynamic','rewrite'],true)) { throw new InvalidArgumentException('Specify link mode.'); }
define('TPL_INCLUDE',true);
define('URL_TYPE',$mode==='rewrite'?1:0);
require dirname(__DIR__).'/system/common/security.php';
require dirname(__DIR__).'/system/common/function.php';

$categories=['/article_news_7.html','/article_news_local_7.html','/qa_mod_news_7.html',
    '/qa_mod_news_local_7.html'];
foreach ($categories as $path) {
    $link=url($path);
    if ($mode==='rewrite' ? $link!==$path :
        $link!=='/?controller=classify&route='.rawurlencode(substr($path,1,-5))) {
        throw new RuntimeException('Category link lost its complete route.');
    }
}
$content='/content_qa_mod_7.html';
if (url($content)!==($mode==='rewrite'?$content:'/?controller=content&project=qa_mod&id=7')) {
    throw new RuntimeException('Content link lost its model name.');
}
foreach (['A&B','a/b','x=1','what?','part_2','part_2026','part_0','many_under_scores_2'] as $keyword) {
    if (search($keyword)!=='/?controller=search&tags='.rawurlencode($keyword)) {
        throw new RuntimeException('Ambiguous keyword did not use a dynamic link.');
    }
}
foreach (['普通中文','C++','literal%2F','under_score'] as $keyword) {
    $expected=$mode==='rewrite'?'/tags/'.rawurlencode($keyword).'.html':
        '/?controller=search&tags='.rawurlencode($keyword);
    if (search($keyword)!==$expected) { throw new RuntimeException('Ordinary keyword link changed.'); }
}
if (cms_date(null)!=='' || cms_date('')!=='' || cms_date('   ')!=='' || cms_date('0000-00-00')!=='' ||
    cms_date('0000-00-00 00:00:00')!=='' || cms_date('not-a-date')!=='' || cms_date([])!=='') {
    throw new RuntimeException('Date helper must render an empty string for empty or invalid values.');
}
if (cms_date('2026-10-27')!==date('m/d',strtotime('2026-10-27')) ||
    cms_date('2026-10-27','Y年m月d日')!==date('Y年m月d日',strtotime('2026-10-27'))) {
    throw new RuntimeException('Date helper changed the expected format for a valid date.');
}
echo "PASS $mode links preserve model, category and keyword boundaries; date helper keeps empty and formatted output\n";
