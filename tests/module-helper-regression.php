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
$tables=['module','view_field','classify','article','disabled','tags'];
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
    $guard->query("CREATE TABLE `{$prefix}tags` (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80))");
    foreach (['中文','A&B','0'] as $name) { $guard->execute_query("INSERT INTO `{$prefix}tags` (name) VALUES (?)",[$name]); }
    $module=M('module');
    $renderTags=new ReflectionMethod($module,'atags');
    $repository=D('tags');
    $selects=static fn()=>(int)$repository->excsql("SHOW SESSION STATUS LIKE 'Com_select'",[],true)[0]['Value'];
    $revision=model::requestWriteRevision(); $before=$selects();
    $html=$renderTags->invoke($module,'中文,A&B,missing,<x>,中文,0');
    $expected='<a href="/?controller=search&amp;tags=%E4%B8%AD%E6%96%87" target="_blank">中文</a>,<a href="/?controller=search&amp;tags=A%26B" target="_blank">A&amp;B</a>,missing,&lt;x&gt;,<a href="/?controller=search&amp;tags=%E4%B8%AD%E6%96%87" target="_blank">中文</a>,<a href="/?controller=search&amp;tags=0" target="_blank">0</a>';
    helper_check($html===$expected,'tag links preserve escaping, URLs, duplicates, zero tag and order');
    helper_check(model::requestWriteRevision()===$revision && $selects()===$before+1,
        'tag read uses one shared SELECT without invalidating metadata snapshots');
    $before=$selects();
    helper_check($renderTags->invoke($module,'')==='' && $selects()===$before,
        'empty tags avoid database work');

    $pictures=$module->pic('article',1,10,'info');
    helper_check(array_column($pictures,'title')===['child','published'],'pic excludes draft/recycled rows and preserves descendant ordering');
    helper_check(array_keys($pictures[0])===['info','id','title','img','html'] && $pictures[0]['html']==='/four','pic preserves fields and URLs');
    helper_check(count($module->pic('article',1,1))===1,'pic preserves requested limit');
    helper_check(array_column($module->module(),'project')===['article'],'module compatibility entry returns enabled content models');
    $first=$module->l('article','w[status=1];f[title];n[1];s[id,2]');
    helper_check($first===[['title'=>'published']],'explicit l expression preserves fields/order/limit');
    $second=$module->l('article','');
    helper_check(array_column($second,'id')===['5','4','3','2','1'] && isset($second[0]['info']),
        'reused l object does not retain where/field/sort/limit');
    helper_check($second===M('module')->l('article',''),'reused and fresh l objects agree');
    helper_check(array_column($module->l('article','s[id]'),'id')===['5','4','3','2','1'],
        'incomplete sorting uses legacy default without diagnostics');
    helper_check(array_column($module->l('article','w[status=1,1];f[id];s[id,2]'),'id')===['1','4'],
        'l preserves combined condition and descendant syntax');
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
