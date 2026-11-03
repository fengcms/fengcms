<?php
require_once dirname(__DIR__, 2).'/system/common/security.php';
require_once dirname(__DIR__, 2).'/system/common/image_memory.php';
cms_session_start();
cms_admin_required();
cms_session_release();
$relative=$_GET['path'] ?? null;
if (!is_string($relative) || $relative==='') { cms_fail(400,'图片路径不正确。'); }
$path=cms_path_within(dirname(__DIR__,2).'/upload/image',$relative);
if (!is_file($path) || !in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['jpg','jpeg','png','gif','bmp'],true)) { cms_fail(400,'文件不是图片。'); }
$size=@getimagesize($path);
if (filesize($path)>10*1024*1024 || !$size || $size[0]<1 || $size[1]<1 || $size[0]*$size[1]>20000000 || !in_array($size['mime'],['image/jpeg','image/png','image/gif','image/bmp','image/x-ms-bmp'],true)) { cms_fail(413,'图片过大或无法预览。'); }
if (!cms_image_decode_fits_memory($size[0],$size[1],(int)filesize($path),320*240)) {
    cms_fail(413,'图片尺寸超过服务器可处理范围。');
}
$bytes=@file_get_contents($path);
$source=$bytes===false ? false : @imagecreatefromstring($bytes);
unset($bytes);
if (!$source) { cms_fail(400,'图片无法预览。'); }
$ratio=min(320/$size[0],240/$size[1],1);
$width=max(1,(int)round($size[0]*$ratio));$height=max(1,(int)round($size[1]*$ratio));
$thumb=imagecreatetruecolor($width,$height);
imagealphablending($thumb,false); imagesavealpha($thumb,true);
imagefill($thumb,0,0,imagecolorallocatealpha($thumb,255,255,255,127));
imagecopyresampled($thumb,$source,0,0,0,0,$width,$height,$size[0],$size[1]);
header('Content-Type: image/png');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
imagepng($thumb);
