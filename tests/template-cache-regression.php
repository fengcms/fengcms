<?php
$root=getenv('CMS_CACHE_QA_ROOT')?:'';
if(PHP_SAPI!=='cli' || !preg_match('~^/tmp/fengcms_cache_qa_[a-f0-9]{12}$~D',$root)){
    exit("Isolated cache QA root required.\n");
}
define('TPL_INCLUDE',true);define('ROOT_PATH',dirname(__DIR__));define('ABS_PATH',$root);
define('CACHE_PATH',$root.'/app/cache');
function is_mobile_request(): bool {return false;}
require dirname(__DIR__).'/system/common/security.php';
require dirname(__DIR__).'/system/common/template_cache.php';
require dirname(__DIR__).'/system/core/template.php';
function cache_check(bool $ok,string $name): void {
    if(!$ok){throw new RuntimeException('FAIL '.$name);}
    echo 'PASS '.$name."\n";flush();
}
function cache_dir(string $path): void {if(!is_dir($path)){mkdir($path,0750,true);}}
if(($argv[1]??'')==='hold'){
    $lock=CmsTemplateCache::lock(false);
    file_put_contents($root.'/holding','1');
    usleep(800000);
    CmsTemplateCache::unlock($lock);
    exit;
}
cache_dir($root.'/template');
cache_dir($root.'/app/cache/compile');
file_put_contents($root.'/template/page.html','<p>source A</p>');
$tpl=new template();
$compiled=template::tpl('page.html');
cache_check(is_file($compiled) && str_contains(file_get_contents($compiled),'source A'),
    'warm cache contains first compiled source');
file_put_contents($root.'/template/page.html','<p>source B</p>');
touch($root.'/template/page.html',time()+5);clearstatcache(true);
chmod(dirname($compiled),0500);
$failed=false;
try{template::tpl('page.html');}catch(RuntimeException){$failed=true;}
cache_check($failed && str_contains(file_get_contents($compiled),'source A'),
    'warm compilation failure never returns the stale artifact as successful');
chmod(dirname($compiled),0750);unlink($compiled);chmod(dirname($compiled),0500);
$failed=false;
try{template::tpl('page.html');}catch(RuntimeException){$failed=true;}
cache_check($failed && !is_file($compiled),'cold compilation failure never returns a missing artifact');
chmod(dirname($compiled),0750);
$compiled=template::tpl('page.html');
cache_check(str_contains(file_get_contents($compiled),'source B'),
    'compilation retries successfully after cache directory is repaired');
touch($compiled,time()+20);clearstatcache(true,$compiled);
$compiledTime=filemtime($compiled);
file_put_contents($root.'/template/page.html','<p>source C</p>');
touch($root.'/template/page.html',$compiledTime-10);clearstatcache(true);
cache_check(str_contains(file_get_contents(template::tpl('page.html')),'source B'),
    'preserved source timestamp keeps the old artifact until explicit clear');
cache_check(CmsTemplateCache::clear($root)===1 &&
    str_contains(file_get_contents(template::tpl('page.html')),'source C'),
    'explicit clear publishes source with a preserved old timestamp');
$site=$root.'/site';
foreach(['app/cache/compile','admin/app/cache/compile','app/cache/compile_mobile'] as $relative){
    cache_dir($site.'/'.$relative);file_put_contents($site.'/'.$relative.'/page.php','cached');
}
cache_check(CmsTemplateCache::clear($site)===3 &&
    !is_dir($site.'/app/cache/compile') && !is_dir($site.'/admin/app/cache/compile') &&
    !is_dir($site.'/app/cache/compile_mobile'),
    'clear removes desktop, admin and mobile compilation directories');
cache_check(CmsTemplateCache::clear($site)===0,'repeated clear is idempotent');
foreach(['app/cache/compile','admin/app/cache/compile','app/cache/compile_mobile'] as $relative){
    cache_dir($site.'/'.$relative);file_put_contents($site.'/'.$relative.'/page.php','cached');
    cache_check(CmsTemplateCache::clear($site)===1 && !is_dir($site.'/'.$relative),
        'clear handles a single existing cache: '.$relative);
}
cache_dir($site.'/app/cache/keep');file_put_contents($site.'/app/cache/keep/retained','safe');
symlink($site.'/app/cache/keep',$site.'/app/cache/compile');
$rejected=false;
try{CmsTemplateCache::clear($site);}catch(RuntimeException){$rejected=true;}
cache_check($rejected && is_file($site.'/app/cache/keep/retained'),
    'clear refuses a symlinked cache directory without following it');
unlink($site.'/app/cache/compile');
cache_dir($site.'/app/cache/compile');file_put_contents($site.'/app/cache/compile/page.php','cached');
$command=[PHP_BINARY,__FILE__,'hold'];
$proc=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
if(!is_resource($proc)){throw new RuntimeException('Cannot start cache lock worker');}
fclose($pipes[0]);
$deadline=microtime(true)+4;
while(!is_file($root.'/holding') && microtime(true)<$deadline){usleep(10000);}
$start=microtime(true);$removed=CmsTemplateCache::clear($site);
$elapsed=microtime(true)-$start;
fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);
cache_check($removed===1 && $elapsed>=0.55 && !is_dir($site.'/app/cache/compile'),
    'clear waits for a reader before removing its compiled artifact');
echo "Isolated template cache regressions passed.\n";
