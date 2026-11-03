<?php
/**
 * GD 解码前估算内存：源图缓冲、文件字节、目标画布和运行余量。
 * 调用者仍须检查 MIME、尺寸上限及实际解码结果；这不是图片有效性检查。
 */
function cms_image_decode_fits_memory(int $width, int $height, int $fileBytes, int $targetPixels=0): bool
{
    if ($width<1 || $height<1 || $fileBytes<0 || $targetPixels<0) { return false; }
    $limit=trim((string)ini_get('memory_limit'));
    if ($limit==='-1') { return true; }
    if (!preg_match('/^([0-9]+)([KMG]?)$/iD',$limit,$parts)) { return false; }
    $multiplier=match(strtoupper($parts[2])) {'G'=>1073741824,'M'=>1048576,'K'=>1024,default=>1};
    $available=(float)$parts[1]*$multiplier-memory_get_usage(true);
    // 浮点乘法避免异常尺寸导致整数溢出；接口另有 2,000 万像素硬上限。
    $required=(float)$width*$height*8+$fileBytes+(float)$targetPixels*8+16*1048576;
    return $required<=$available;
}
