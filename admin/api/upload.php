<?php
require_once dirname(__DIR__, 2).'/system/common/security.php';
require_once dirname(__DIR__, 2).'/system/common/upload_request.php';
require_once dirname(__DIR__, 2).'/system/common/database_gate.php';
cms_session_start();
cms_admin_required();
cms_post_required();
$accountId=(int)$_SESSION['manage_id'];
$requestKey=$_POST['_upload_key']??null;
if($requestKey!==null && !is_string($requestKey)){cms_fail(400,'上传请求标识不正确。');}
cms_session_release();
ignore_user_abort(true);
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
if (strlen($file['name']) > 255 || strpbrk($file['name'], "/\\\0\r\n") !== false ||
    preg_match('~(?:^|\.)(?:php[0-9]*|phtml|phar|cgi|pl|asp|aspx|jsp|shtml|html?|svg|js)(?=\.|$)~i', $file['name'])) {
    cms_fail(400, '文件名不允许。');
}
if (!is_string($dir) || !isset($groups[$dir]) || !in_array($ext, $groups[$dir], true)) {
    cms_fail(400, '文件类型不允许。');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mime, $mimeTypes[$ext], true)) { cms_fail(400, '文件内容与扩展名不符。'); }
if ($dir === 'image') {
    $size = @getimagesize($file['tmp_name']);
    if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 20000000 ||
        !in_array($size['mime'], $mimeTypes[$ext], true)) {
        cms_fail(400, '图片不正确或尺寸过大。');
    }
    // GD may need several pixel buffers while decoding. Reject images that exceed
    // the current PHP process budget before loading their complete byte stream.
    $memoryLimit=trim((string)ini_get('memory_limit'));
    if(preg_match('/^([1-9][0-9]*)([KMG]?)$/iD',$memoryLimit,$memoryParts)){
        $multiplier=match(strtoupper($memoryParts[2])){'G'=>1073741824,'M'=>1048576,'K'=>1024,default=>1};
        $available=(int)$memoryParts[1]*$multiplier-memory_get_usage(true);
        if($size[0]*$size[1]*8+(int)$file['size']+16*1048576>$available){
            cms_fail(413,'图片尺寸超过服务器可处理范围。');
        }
    }
    $bytes=@file_get_contents($file['tmp_name']);
    $decoded=$bytes===false?false:@imagecreatefromstring($bytes);
    unset($bytes);
    if(!$decoded || imagesx($decoded)!==$size[0] || imagesy($decoded)!==$size[1]){
        cms_fail(400,'图片无法解码，请检查文件是否损坏。');
    }
    unset($decoded);
}
// Binary images/documents may legitimately contain PHP/ASP-looking byte sequences.
// Security relies on the allowlists and non-executable upload directory.
try{
    $url=CmsDatabaseGate::write(fn()=>CmsUploadRequest::save($file['tmp_name'],$file['name'],
        (int)$file['size'],$dir,$ext,$accountId,$requestKey));
}catch(CmsDatabaseGateBusy $e){
    cms_fail(409,$e->getMessage());
}catch(CmsUploadRequestException $e){
    cms_fail($e->httpStatus,$e->getMessage());
}catch(Throwable $e){
    error_log('Upload storage failed: '.$e);
    cms_fail(500,'文件保存失败，请稍后重试。');
}
header('Content-Type: text/html; charset=UTF-8');
echo json_encode(['error' => 0, 'url' => $url]);
