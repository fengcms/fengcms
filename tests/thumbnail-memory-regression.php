<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
require dirname(__DIR__).'/tools/migrate.php';
$db=migration_connection();
if (!$db->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) { throw new RuntimeException('Development database required.'); }
define('TPL_INCLUDE',true);define('ROOT_PATH',dirname(__DIR__));define('APP_PATH',ROOT_PATH.'/admin/app');
require_once ROOT_PATH.'/system/common/config_loader.php';cms_config_bootstrap(ROOT_PATH);
require ROOT_PATH.'/system/common/security.php';
$tag='qa_thumb_'.bin2hex(random_bytes(6));$accountId=null;
$folder=ROOT_PATH.'/upload/image/'.$tag;mkdir($folder,0755);
try {
    $account=['admin'=>$tag,'password'=>password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),
        'role'=>'editor','enabled'=>1,'auth_version'=>1,'login_code_hash'=>null];
    $db->execute_query('INSERT INTO f_manage (admin,password,role,enabled,auth_version) VALUES (?,?,?,?,?)',
        [$account['admin'],$account['password'],$account['role'],1,1]);
    $accountId=$db->insert_id;
    cms_session_start();
    $_SESSION += ['manage_id'=>$accountId,'manage'=>$tag,'manage_role'=>'editor','auth_at'=>time(),
        'last_seen'=>time(),'auth_fingerprint'=>cms_admin_fingerprint($account)];
    $sessionId=session_id();cms_session_release();
    foreach (['large.png'=>[4000,4000],'small.png'=>[640,480]] as $name=>[$width,$height]) {
        $canvas=imagecreatetruecolor($width,$height);imagepng($canvas,$folder.'/'.$name);unset($canvas);
    }
    $child= <<<'CHILD'
$input=json_decode(stream_get_contents(STDIN),true);
session_name('fengcms_session');session_id($input['session']);
$_GET=['path'=>$input['path']];
register_shutdown_function(static function(){fwrite(STDERR,'HTTP '.http_response_code());});
require 'admin/api/thumbnail.php';
CHILD;
    foreach (['large.png'=>413,'small.png'=>200] as $name=>$expected) {
        $process=proc_open([PHP_BINARY,'-d','memory_limit=32M','-r',$child],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_PATH);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start thumbnail probe.'); }
        fwrite($pipes[0],json_encode(['session'=>$sessionId,'path'=>$tag.'/'.$name]));fclose($pipes[0]);
        $output=stream_get_contents($pipes[1]);fclose($pipes[1]);
        $diagnostics=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($process);
        if ($expected===413) {
            $ok=$code===0 && $diagnostics==='HTTP 413' &&
                (json_decode($output,true)['message']??'')==='图片尺寸超过服务器可处理范围。';
        } else {
            $size=@getimagesizefromstring($output);
            $ok=$code===0 && in_array($diagnostics,['HTTP ','HTTP 200'],true) && $size && $size[0]===320 && $size[1]===240;
        }
        if (!$ok) { throw new RuntimeException('Thumbnail probe failed: '.json_encode(['exit'=>$code,'diagnostics'=>$diagnostics,'json'=>json_decode($output,true)])); }
        echo "PASS authenticated thumbnail at 32M: $name expected $expected\n";
    }
} finally {
    if ($accountId) { $db->execute_query('DELETE FROM f_manage WHERE id=?',[$accountId]); }
    if (isset($sessionId)) { session_id($sessionId);session_start();session_destroy(); }
    foreach (glob($folder.'/*') as $file) { unlink($file); }rmdir($folder);
}
