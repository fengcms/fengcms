<?php
require_once dirname(__DIR__, 2).'/system/common/security.php';
cms_session_start();
cms_admin_required();
cms_post_required();
cms_session_release();
require_once dirname(__DIR__, 2).'/system/common/richtext.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
try {
    $html=cms_clean_html_for_save($_POST['html'] ?? null);
    echo json_encode(['error'=>0,'html'=>$html], JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Richtext preview cleaning failed: '.$error);
    cms_fail(500,'正文处理失败，请稍后重试。');
}
