<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/system/common/image_memory.php';
$original=ini_get('memory_limit');
try {
    foreach (['32M','32768K','33554432'] as $limit) {
        ini_set('memory_limit',$limit);
        if (cms_image_decode_fits_memory(4000,4000,100000,320*240) ||
            !cms_image_decode_fits_memory(640,480,100000,320*240)) {
            throw new RuntimeException('Image budget failed for '.$limit);
        }
    }
    ini_set('memory_limit','256M');
    if (!cms_image_decode_fits_memory(4000,4000,100000,320*240) ||
        cms_image_decode_fits_memory(PHP_INT_MAX,PHP_INT_MAX,0)) {
        throw new RuntimeException('Normal budget or overflow handling failed.');
    }
    ini_set('memory_limit','-1');
    if (!cms_image_decode_fits_memory(4000,4000,100000) || cms_image_decode_fits_memory(0,1,1)) {
        throw new RuntimeException('Unlimited memory or invalid dimensions failed.');
    }
    echo "PASS image decode budgets handle byte/K/M units, unlimited memory and overflow\n";
} finally { ini_set('memory_limit',$original); }
