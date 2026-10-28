<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$guard=migration_connection();
if (!$guard->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
    exit("Refusing frontend content-read tests outside the disposable development database.\n");
}
$prefix='qa_frontread_'.bin2hex(random_bytes(5)).'_';
$classTable=$prefix.'classify'; $moduleTable=$prefix.'module'; $articleTable=$prefix.'article'; $fieldTable=$prefix.'view_field';
$guard->query("CREATE TABLE `$classTable` (id INT PRIMARY KEY,classid INT,name VARCHAR(80),html VARCHAR(120),nav INT,url_type INT,type VARCHAR(20),sort INT,project VARCHAR(40)) ENGINE=InnoDB");
$guard->query("CREATE TABLE `$moduleTable` (project VARCHAR(40),type INT,status INT) ENGINE=InnoDB");
$guard->query("CREATE TABLE `$articleTable` (id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(255),tags VARCHAR(255),html VARCHAR(120),status INT,time INT,KEY status_id(status,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$guard->query("CREATE TABLE `$fieldTable` (id INT AUTO_INCREMENT PRIMARY KEY,project VARCHAR(40),name VARCHAR(40)) ENGINE=InnoDB");
try {
    define('TPL_INCLUDE',true); define('ROOT_PATH',dirname(__DIR__)); define('SYSTEM_PATH',ROOT_PATH.'/system/');
    define('MODEL_PATH',ROOT_PATH.'/app/model/'); define('DRIVER_PATH',SYSTEM_PATH.'driver/');
    define('DB_TYPE','mysqli'); define('DB_HOST',getenv('DB_HOST')); define('DB_USER',getenv('DB_USER'));
    define('DB_PASS',getenv('DB_PASS')); define('DB_NAME',getenv('DB_NAME')); define('DB_PREFIX',$prefix); define('URL_TYPE',0);
    require SYSTEM_PATH.'core/database.php'; require SYSTEM_PATH.'core/model.php';
    require SYSTEM_PATH.'common/security.php'; require SYSTEM_PATH.'common/function.php';
    require MODEL_PATH.'moduleModel.php'; require MODEL_PATH.'classifyModel.php';
    function check_front_read(bool $condition,string $message): void {
        if (!$condition) { throw new RuntimeException('FAIL: '.$message); }
        echo "PASS $message\n";
    }
    $guard->query("INSERT INTO `$moduleTable` VALUES ('active',1,1),('disabled',1,0)");
    $guard->query("INSERT INTO `$fieldTable` (project,name) VALUES ('article','title'),('article','tags')");
    $guard->query("INSERT INTO `$classTable` VALUES
        (1,0,'active category','/active',1,0,'module',1,'active'),
        (2,0,'disabled category','/disabled',1,0,'module',2,'disabled'),
        (3,0,'external','https://example.test/',1,0,'url',3,'')");
    $navigation=M('classify')->navigationRows(0,['id','name'],10);
    check_front_read(count($navigation)===2 && array_column($navigation,'name')===['active category','external'],
        'limited navigation hides disabled model categories and keeps other category types');
    $limited=M('classify')->navigationRows(0,['id','name'],1);
    check_front_read(count($limited)===1,'navigation SQL applies its requested row limit');
    $map=(string)M('classify')->map();
    check_front_read(str_contains($map,'active category') && str_contains($map,'external') && !str_contains($map,'disabled category'),
        'site map excludes disabled model categories');
    D('module')->where(['project'=>'disabled'])->update(['status'=>1]);
    check_front_read(str_contains((string)M('classify')->map(),'disabled category'),
        'site map reflects a model re-enabled during the request');
    D('module')->where(['project'=>'disabled'])->update(['status'=>0]);

    $insert=$guard->prepare("INSERT INTO `$articleTable` (title,tags,html,status,time) VALUES (?,?,?,1,?)");
    $insert->bind_param('sssi',$title,$tags,$html,$time);
    $title='current article'; $tags='alpha'; $html='/current'; $time=1; $insert->execute(); $currentId=$guard->insert_id;
    $title='old-only-match'; $tags='legacyneedle'; $html='/old'; $time=2; $insert->execute();
    for($i=0;$i<500;$i++){
        $title='unrelated-'.$i; $tags='other'; $html='/other-'.$i; $time=$i+3; $insert->execute();
    }
    $title='newer-alpha'; $tags='alpha'; $html='/newer'; $time=900; $insert->execute();
    $title='newest-alpha'; $tags='alpha'; $html='/newest'; $time=901; $insert->execute();
    $title='ninth-only'; $tags='ninthneedle'; $html='/ninth'; $time=902; $insert->execute();
    $title='collation-match'; $tags='café'; $html='/accent'; $time=903; $insert->execute();
    $insert->close();
    $_GET=['project'=>'article','id'=>(string)$currentId];
    $related=M('module')->related('article','alpha',10);
    check_front_read(array_column($related,'title')===['newest-alpha','newer-alpha'],
        'related articles exclude the current article and return stable newest-first matches');
    check_front_read(M('module')->related('article','alpha',0)===[],'related article limit zero returns no rows');
    check_front_read(array_column(M('module')->related('article','cafe',10),'title')===['collation-match'],
        'related matching preserves the database collation semantics');
    $bounded=M('module')->related('article','legacyneedle',10);
    check_front_read($bounded===[],'related search is bounded to the newest 500 published candidates');
    $tags9='alpha1,alpha2,alpha3,alpha4,alpha5,alpha6,alpha7,alpha8,ninthneedle';
    check_front_read(M('module')->related('article',$tags9,10)===[],
        'related search evaluates no more than eight tags per request');
    echo "Frontend content-read isolated regressions passed.\n";
} finally {
    $guard->query("DROP TABLE IF EXISTS `$articleTable`");
    $guard->query("DROP TABLE IF EXISTS `$classTable`");
    $guard->query("DROP TABLE IF EXISTS `$moduleTable`");
    $guard->query("DROP TABLE IF EXISTS `$fieldTable`");
}
