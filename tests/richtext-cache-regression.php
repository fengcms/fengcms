<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/system/common/richtext.php';
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message,0,$severity,$file,$line);
});
try {
    foreach (['<p>正文<img src="/upload/image/sample.png" alt="图片"></p>',
        '<p>示例 &lt;img src="data:image/png;base64,AQIDBA=="&gt;</p>',
        '<p><a href="https://example.com/?a=1&amp;b=2">正常链接</a></p>'] as $html) {
        $clean=cms_clean_html_for_save($html);
        if ($clean==='' || cms_clean_html_for_save($clean)!==$clean) {
            throw new RuntimeException('富文本保存清洗结果应稳定且非空。');
        }
    }
} finally {
    restore_error_handler();
}
echo "PASS richtext lexer and sanitizer need no writable vendor cache and emit no PHP diagnostics\n";
