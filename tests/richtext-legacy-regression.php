<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/system/common/richtext.php';
function cms_fail(int $status, string $message): never { throw new RuntimeException($message,$status); }
set_error_handler(static function (int $severity,string $message,string $file,int $line): bool {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message,0,$severity,$file,$line);
});
function check(bool $ok,string $message): void { if (!$ok) { throw new RuntimeException($message); } }
try {
    $html=file_get_contents(__DIR__.'/fixtures/kindeditor-legacy.html');
    $clean=cms_clean_html_for_save($html);
    foreach (['font-family:SimSun','line-height:1.8','text-indent:2em','margin:12px','padding:4px','size="4"',
        'color="#ff0000"','width="80%"','cellpadding="6"','cellspacing="0"','colspan="2"','rowspan="2"',
        'width="200"','height="40"','float:left','margin-right:8px','target="_blank"','/image/logo.png',
        '<pre>预格式文本', '<sup>上标</sup>','<sub>下标</sub>'] as $expected) {
        check(stripos($clean,$expected)!==false,'Legacy layout lost: '.$expected);
    }
    check(cms_clean_html_for_save($clean)===$clean,'Legacy sanitization must be idempotent');
    $dangerous='<div id="save" class="ke-script" onclick="alert(1)" style="position:fixed;z-index:999999;background-image:url(https://example.com/tracker);width:expression(alert(1));color:red"><script>alert(1)</script><iframe src="https://example.com"></iframe><object data="x"></object><svg onload="alert(1)"></svg><a href="javascript:alert(1)">链接</a><img src="/image/logo.png" onerror="alert(1)" data-ke-src="javascript:alert(1)" /></div>';
    $safe=cms_clean_html_for_save($dangerous);
    foreach (['onclick','onerror','javascript:','expression','<script','<iframe','<object','<svg','position:','z-index','background-image','data-ke-','id="save"','ke-script'] as $forbidden) {
        check(stripos($safe,$forbidden)===false,'Unsafe legacy content survived: '.$forbidden);
    }
    check(str_contains($safe,'/image/logo.png'),'Safe image must survive');
    try { cms_clean_html_for_save('<img src="data:image/png;base64,AAAA" />'); throw new LogicException('Inline images must be rejected'); }
    catch (RuntimeException $error) { check($error->getCode()===400,'Inline rejection status'); }
} finally { restore_error_handler(); }
echo "PASS legacy richtext typography, tables, linked images, idempotence and active-content rejection\n";
