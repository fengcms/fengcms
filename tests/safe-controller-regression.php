<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('TPL_INCLUDE',true);
require dirname(__DIR__).'/system/core/controller.php';
$_SESSION=['manage'=>'qa_safe_runtime'];
require dirname(__DIR__).'/admin/app/controller/safeController.php';
error_reporting(E_ALL);
set_error_handler(static function($level,$message,$file,$line) { throw new ErrorException($message,0,$level,$file,$line); });
try {
    $controller=(new ReflectionClass(safeController::class))->newInstanceWithoutConstructor();
    $fallback=$controller->safe('');
    if($fallback==='' || $controller->safe([])!==$fallback){throw new RuntimeException('Invalid input fallback changed.');}
    // 使用控制器固定 HTTPS 目标及三秒上限；不依赖第三方返回内容或可达性。
    $result=$controller->safe('example.invalid');
    if(!is_string($result) || $result===''){throw new RuntimeException('Safety check must return text or its fallback.');}
    echo "PASS safety controller returns text/fallback without runtime diagnostics\n";
} finally { restore_error_handler(); }
