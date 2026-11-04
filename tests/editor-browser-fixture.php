<?php
// CLI-only, uniquely marked fixtures for the real editor browser regression.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$db=migration_connection();
if (!$db->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) { throw new RuntimeException('Development database required'); }
$action=$argv[1]??'';
$tag=$argv[2]??'';
if (!preg_match('/^[a-f0-9]{24}$/D',$tag)) { throw new RuntimeException('Invalid fixture marker'); }
$title='ke_browser_'.$tag;
if ($action==='create') {
    $password=bin2hex(random_bytes(24));
    $db->execute_query("INSERT INTO f_manage (admin,password,role,datetime) VALUES (?,?,'super_admin',NOW())",[$title,password_hash($password,PASSWORD_DEFAULT)]);
    $html=file_get_contents(__DIR__.'/fixtures/kindeditor-legacy.html');
    $db->execute_query('INSERT INTO f_article (title,content,info,template,classid,status,time,date) VALUES (?,?,?,"/article_content.html",0,0,UNIX_TIMESTAMP(),CURDATE())',[$title,$html,$title]);
    echo json_encode(['id'=>$db->insert_id,'user'=>$title,'password'=>$password,'admincode'=>getenv('ADMIN_CODE')],JSON_THROW_ON_ERROR);
} elseif ($action==='read') {
    echo json_encode($db->execute_query('SELECT id,title,content,status FROM f_article WHERE info=?',[$title])->fetch_assoc(),JSON_THROW_ON_ERROR);
} elseif ($action==='publish' || $action==='unpublish') {
    $db->execute_query('UPDATE f_article SET status=? WHERE info=?',[$action==='publish'?1:0,$title]);
} elseif ($action==='inline') {
    $png=file_get_contents(dirname(__DIR__).'/admin/public/kindeditor-4.1.7/themes/default/default.png');
    $html='<div style="text-align:center;line-height:2"><p>内嵌图片</p><img src="data:image/png;base64,'.base64_encode($png).'" alt="内嵌" /></div>';
    $db->execute_query('UPDATE f_article SET content=? WHERE info=?',[$html,$title]);
} elseif ($action==='cleanup') {
    $db->execute_query('DELETE FROM f_article WHERE info=?',[$title]);
    $db->execute_query('DELETE FROM f_manage WHERE admin=?',[$title]);
    // Only exact upload URLs returned to this test are supplied by the caller.
    foreach (json_decode($argv[3]??'[]',true,16,JSON_THROW_ON_ERROR) as $url) {
        if (!is_string($url) || !preg_match('~^/upload/(image|file)/[0-9]{8}/[a-f0-9]{32}\.[a-z0-9]+$~D',$url)) { throw new RuntimeException('Invalid test upload path'); }
        $path=dirname(__DIR__).$url;
        if (is_file($path)) { unlink($path); }
    }
} else { throw new RuntimeException('Invalid fixture action'); }
