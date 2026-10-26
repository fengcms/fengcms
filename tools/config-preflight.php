<?php
// Read-only deployment check. Never prints private values.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$path = $root.'/config.local.php';
$local = is_file($path) ? require $path : [];
if (!is_array($local)) { fwrite(STDERR,"私有配置文件格式错误。\n"); exit(1); }
$required = [
    'DB_HOST'=>'db_host','DB_USER'=>'db_user','DB_PASS'=>'db_pass',
    'DB_NAME'=>'db_name','ADMIN_CODE'=>'admin_code','APP_KEY'=>'app_key',
];
$values = []; $errors = [];
foreach ($required as $environment=>$key) {
    $value=getenv($environment);
    if ($value===false) { $value=$local[$key]??null; }
    if (!is_string($value) || $value==='') { $errors[]=$environment.' 未配置'; }
    else { $values[$environment]=$value; }
}
if (($values['DB_USER']??null)==='root') { $errors[]='数据库账号使用 root，请改为当前库专用账号'; }
if (in_array($values['DB_PASS']??null,['123456','password'],true)) { $errors[]='数据库密码仍为已知示例值'; }
if (($values['ADMIN_CODE']??null)==='8888') { $errors[]='后台认证码仍为已知示例值'; }
if (($values['APP_KEY']??null)==='admin123' || strlen($values['APP_KEY']??'')<32) { $errors[]='应用密钥过短或仍为已知示例值'; }
if (isset($values['DB_HOST'],$values['DB_USER'],$values['DB_PASS'],$values['DB_NAME'])) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db=new mysqli($values['DB_HOST'],$values['DB_USER'],$values['DB_PASS'],$values['DB_NAME']);
        $grants=$db->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all(MYSQLI_NUM);
        foreach ($grants as [$grant]) {
            if (preg_match('/\bALL PRIVILEGES\b.*\bON\s+\*\.\*/i',$grant)) {
                $errors[]='数据库账号具有全局权限，请改为当前库专用账号';
                break;
            }
        }
        $db->close();
    } catch (Throwable) { $errors[]='无法用当前配置连接数据库，请检查主机、账号及权限'; }
}
if ($errors) {
    foreach ($errors as $error) { fwrite(STDERR,'- '.$error."\n"); }
    fwrite(STDERR,"请先备份旧站点并补齐私有配置；不会自动修改旧密码或数据库。\n");
    exit(1);
}
echo "私有配置预检查通过；未输出任何密钥。\n";
