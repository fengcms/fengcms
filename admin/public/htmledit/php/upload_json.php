<?php
require_once dirname(__DIR__, 4).'/system/common/security.php';
cms_session_start();
cms_admin_required();
cms_post_required();
header('Cache-Control: no-store');

$file = $_FILES['imgFile'] ?? null;
if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name'], $file['size']) ||
    !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK ||
    !is_string($file['tmp_name']) || !is_string($file['name']) || !is_uploaded_file($file['tmp_name'])) {
    cms_fail(400, '请选择有效文件。');
}
if ($file['size'] <= 0 || $file['size'] > 10 * 1024 * 1024) {
    cms_fail(413, '文件大小须在 10MB 以内。');
}
$dir = $_GET['dir'] ?? 'image';
$groups = [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp'],
    'media' => ['mp3', 'mp4', 'wav'],
    'file' => ['pdf', 'txt', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
];
$mimeTypes = [
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    'gif' => ['image/gif'], 'bmp' => ['image/bmp', 'image/x-ms-bmp'],
    'mp3' => ['audio/mpeg'], 'mp4' => ['video/mp4', 'audio/mp4'],
    'wav' => ['audio/x-wav', 'audio/wav'], 'pdf' => ['application/pdf'],
    'txt' => ['text/plain'], 'zip' => ['application/zip'],
    'doc' => ['application/msword', 'application/CDFV2'],
    'xls' => ['application/vnd.ms-excel', 'application/CDFV2'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!is_string($dir) || !isset($groups[$dir]) || !in_array($ext, $groups[$dir], true)) {
    cms_fail(400, '文件类型不允许。');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mime, $mimeTypes[$ext], true)) { cms_fail(400, '文件内容与扩展名不符。'); }
if ($dir === 'image') {
    $size = @getimagesize($file['tmp_name']);
    if (!$size || $size[0] * $size[1] > 20000000 || $size['mime'] !== $mime) {
        cms_fail(400, '图片不正确或尺寸过大。');
    }
}
// Reject server-side code even in files that would only be served as static data.
if (preg_match('/<\?(?:php|=)|<%/i', file_get_contents($file['tmp_name']))) {
    cms_fail(400, '文件包含不允许的内容。');
}
$relative = $dir.'/'.date('Ymd');
$path = dirname(__DIR__, 4).'/upload/'.$relative;
if (!is_dir($path) && !mkdir($path, 0755, true)) { cms_fail(500, '无法创建上传目录。'); }
$name = bin2hex(random_bytes(16)).'.'.$ext;
if (!move_uploaded_file($file['tmp_name'], $path.'/'.$name)) { cms_fail(500, '文件保存失败。'); }
chmod($path.'/'.$name, 0644);
header('Content-Type: text/html; charset=UTF-8');
echo json_encode(['error' => 0, 'url' => '/upload/'.$relative.'/'.$name]);
