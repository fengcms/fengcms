<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$guard=migration_connection();
if (!$guard->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) { throw new RuntimeException('Development database required.'); }
$prefix='qa_helpers_'.bin2hex(random_bytes(5)).'_';
define('TPL_INCLUDE',true); define('ROOT_PATH',dirname(__DIR__));
define('SYSTEM_PATH',ROOT_PATH.'/system/'); define('MODEL_PATH',ROOT_PATH.'/app/model/');
define('DRIVER_PATH',SYSTEM_PATH.'driver/'); define('DB_TYPE','mysqli');
foreach (['DB_HOST','DB_USER','DB_PASS','DB_NAME'] as $key) { define($key,getenv($key)); }
define('DB_PREFIX',$prefix);
require SYSTEM_PATH.'core/database.php'; require SYSTEM_PATH.'core/model.php';
require SYSTEM_PATH.'common/security.php'; require SYSTEM_PATH.'common/function.php';
require MODEL_PATH.'moduleModel.php';
set_error_handler(static function($level,$message,$file,$line) { throw new ErrorException($message,0,$level,$file,$line); });
function helper_check(bool $ok,string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
    echo "PASS $message\n";
}
$tables=['module','view_field','classify','article','disabled'];
try {
    $guard->query("CREATE TABLE `{$prefix}module` (id INT PRIMARY KEY,project VARCHAR(30),type INT,status INT)");
    $guard->query("CREATE TABLE `{$prefix}view_field` (id INT AUTO_INCREMENT PRIMARY KEY,project VARCHAR(30),name VARCHAR(30))");
    $guard->query("CREATE TABLE `{$prefix}classify` (id INT PRIMARY KEY,classid INT,name VARCHAR(30),html VARCHAR(40),nav INT,url_type INT,type VARCHAR(20),sort INT,project VARCHAR(30))");
    $guard->query("INSERT INTO `{$prefix}module` VALUES (1,'article',1,1),(2,'disabled',1,0)");
    $guard->query("INSERT INTO `{$prefix}classify` VALUES (1,0,'parent','/parent',1,0,'module',1,'article'),(2,1,'child','/child',1,0,'module',2,'article')");
    foreach (['article','disabled'] as $project) {
        $guard->query("CREATE TABLE `{$prefix}{$project}` (id INT PRIMARY KEY,classid INT,title VARCHAR(80),img VARCHAR(80),html VARCHAR(80),info TEXT,status INT,attrib_t INT,attrib_h INT)");
        foreach (['title','img','attrib'] as $field) {
            $guard->execute_query("INSERT INTO `{$prefix}view_field` (project,name) VALUES (?,?)",[$project,$field]);
        }
    }
    $guard->query("INSERT INTO `{$prefix}article` VALUES (1,1,'published','/one.jpg','/one','one',1,1,1),(2,1,'draft','/two.jpg','/two','two',0,1,1),(3,1,'recycled','/three.jpg','/three','three',-1,1,1),(4,2,'child','/four.jpg','/four','four',1,1,1),(5,9,'other','/five.jpg','/five','five',1,1,1)");
    $guard->query("INSERT INTO `{$prefix}disabled` VALUES (10,1,'disabled','/ten.jpg','/ten','ten',1,1,1)");
    $module=M('module');
    $pictures=$module->pic('article',1,10,'info');
    helper_check(array_column($pictures,'title')===['child','published'],'pic excludes draft/recycled rows and preserves descendant ordering');
    helper_check(array_keys($pictures[0])===['info','id','title','img','html'] && $pictures[0]['html']==='/four','pic preserves fields and URLs');
    helper_check(count($module->pic('article',1,1))===1,'pic preserves requested limit');
    foreach (['wh','wt','wn','wpic'] as $method) {
        helper_check(array_column($module->$method(10),'title')===['other','child','published'],"$method excludes disabled model and unpublished rows");
        D('module')->where(['project'=>'disabled'])->update(['status'=>1]);
        helper_check(array_column($module->$method(1),'title')===['disabled'],"$method reflects re-enable and preserves limit/order");
        D('module')->where(['project'=>'disabled'])->update(['status'=>0]);
    }
    D('module')->where(['project'=>'article'])->update(['status'=>0]);
    foreach (['wh','wt','wn','wpic'] as $method) {
        helper_check($module->$method(10)===[],"$method returns empty array with no enabled model");
    }
} finally {
    foreach (array_reverse($tables) as $table) { $guard->query("DROP TABLE IF EXISTS `{$prefix}{$table}`"); }
    restore_error_handler();
}
