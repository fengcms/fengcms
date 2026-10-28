<?php
if(PHP_SAPI!=='cli' || !preg_match('/^fengcms_settings_qa_[a-f0-9]{12}$/D',getenv('DB_NAME')?:'')){
    exit("Isolated settings QA database required.\n");
}
foreach(['HOST','USER','PASS','NAME'] as $part){define('DB_'.$part,getenv('DB_'.$part));}
define('DB_PREFIX','f_');define('KEYS','settings-qa-key');
require dirname(__DIR__).'/system/common/site_settings.php';
function settings_check(bool $ok,string $name): void {
    if(!$ok){throw new RuntimeException('FAIL '.$name);}
    echo 'PASS '.$name."\n";
}
$db=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
$root=getenv('APP_RUNTIME_PATH');
if(!is_dir($root)){mkdir($root,0750,true);}
foreach(['InnoDB','MyISAM'] as $engine){
    $db->query('DROP TABLE IF EXISTS f_base');
    $db->query("CREATE TABLE f_base (name varchar(255),title varchar(255),domran varchar(255),
        `key` varchar(255),description varchar(255),copyright text,template varchar(255),time varchar(15)) ENGINE=$engine");
    $db->query("INSERT INTO f_base (name,title) VALUES ('original','old title')");
    file_put_contents($root.'/site.json','{"url_type":0}');
    $baseline=CmsSiteSettings::snapshot()['_settings_version'];
    $db->query("CREATE TRIGGER qa_settings_fail BEFORE UPDATE ON f_base FOR EACH ROW
        BEGIN IF NEW.name='blocked' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA failure'; END IF; END");
    $failed=false;
    try{CmsSiteSettings::save(['name'=>'blocked'],'1',$baseline);}catch(Throwable){$failed=true;}
    settings_check($failed && $db->query('SELECT name FROM f_base')->fetch_row()[0]==='original' &&
        file_get_contents($root.'/site.json')==='{"url_type":0}' &&
        !is_file($root.'/.site-settings-pending.json'),
        "$engine SQL failure leaves database and URL mode unchanged");
    $db->query('DROP TRIGGER qa_settings_fail');
    // A directory at the publication target forces a real rename failure
    // after the single-row SQL update, without changing host permissions.
    unlink($root.'/site.json');mkdir($root.'/site.json');
    $baseline=CmsSiteSettings::snapshot()['_settings_version'];
    $failed=false;
    try{CmsSiteSettings::save(['name'=>'new'],'1',$baseline);}catch(Throwable){$failed=true;}
    settings_check($failed && $db->query('SELECT name FROM f_base')->fetch_row()[0]==='original' &&
        is_dir($root.'/site.json') && !is_file($root.'/.site-settings-pending.json'),
        "$engine file publication failure compensates database update");
    rmdir($root.'/site.json');file_put_contents($root.'/site.json','{"url_type":0}');
    $baseline=CmsSiteSettings::snapshot()['_settings_version'];
    CmsSiteSettings::save(['name'=>'confirmed'],'1',$baseline);
    $conflict=false;
    try{CmsSiteSettings::save(['title'=>'stale'],'0',$baseline);}catch(CmsSiteSettingsConflict){$conflict=true;}
    $site=json_decode(file_get_contents($root.'/site.json'),true);
    settings_check($conflict && $db->query('SELECT name,title FROM f_base')->fetch_assoc()===
        ['name'=>'confirmed','title'=>'old title'] && ($site['url_type']??null)===1,
        "$engine stale form cannot overwrite a confirmed save");
    $oldRow=$db->query('SELECT * FROM f_base')->fetch_assoc();
    $oldFile=file_get_contents($root.'/site.json');
    $newRow=array_replace($oldRow,['name'=>'interrupted']);
    $newFile='{"url_type":0,"revision":"interrupted"}';
    file_put_contents($root.'/.site-settings-pending.json',json_encode([
        'database'=>DB_NAME,'old_row'=>$oldRow,'new_row'=>$newRow,
        'old_file'=>base64_encode($oldFile),'new_file'=>base64_encode($newFile),
    ]));
    $db->query("UPDATE f_base SET name='interrupted'");
    file_put_contents($root.'/site.json',$newFile);
    CmsSiteSettings::recoverPending();
    settings_check($db->query('SELECT * FROM f_base')->fetch_assoc()===$oldRow &&
        file_get_contents($root.'/site.json')===$oldFile &&
        !is_file($root.'/.site-settings-pending.json'),
        "$engine interrupted publication restores its exact prior pair");
}
$db->close();
echo "Isolated site settings regressions passed.\n";
