<?php
// Read-only deployment check. Never prints private values.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$errors=[];
require_once $root.'/system/common/config_loader.php';
try { $values=cms_config_values($root); }
catch (Throwable $failure) { $values=[]; $errors[]=$failure->getMessage(); }
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
