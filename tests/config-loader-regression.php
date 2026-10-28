<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/system/common/config_loader.php';
$root=sys_get_temp_dir().'/fengcms-config-qa-'.bin2hex(random_bytes(6));
mkdir($root,0700);
$names=['DB_HOST','DB_USER','DB_PASS','DB_NAME','ADMIN_CODE','APP_KEY','DB_PREFIX','APP_DEBUG'];
$saved=[];
foreach($names as $name){$saved[$name]=getenv($name);putenv($name);}
$passed=0;
function check_config(bool $ok,string $message): void {
    global $passed;
    if(!$ok){throw new RuntimeException('FAIL: '.$message);}
    $passed++; echo "PASS $message\n";
}
function config_values_fixture(string $host='fixture-db'): array {
    return ['db_host'=>$host,'db_user'=>'fixture-user','db_pass'=>'fixture-password',
        'db_name'=>'fixture-database','db_prefix'=>'qa_','admin_code'=>'Q7A2','app_key'=>str_repeat('k',64),'url_type'=>1];
}
function write_private_config(string $path,array $values): void {
    file_put_contents($path,"<?php\n// FengCMS private configuration data v1.\nreturn ".var_export($values,true).";\n");
    chmod($path,0600);
}
try {
    copy(dirname(__DIR__).'/config.example.php',$root.'/config.php');
    $placeholderRejected=false;
    try{cms_config_values($root);}catch(RuntimeException){$placeholderRejected=true;}
    check_config(cms_config_is_private_file($root.'/config.php') && $placeholderRejected,
        'configuration example is data-only but unusable until placeholders are replaced');

    $values=config_values_fixture();
    write_private_config($root.'/config.php',$values);
    $loaded=cms_config_values($root);
    check_config($loaded['DB_HOST']==='fixture-db' && $loaded['DB_PREFIX']==='qa_' && $loaded['URL_TYPE']===1,
        'data-only config.php loads private settings');

    putenv('DB_HOST=environment-db');
    $loaded=cms_config_values($root);
    check_config($loaded['DB_HOST']==='environment-db' && $loaded['DB_USER']==='fixture-user',
        'environment variables override file values individually');
    putenv('DB_HOST');

    unlink($root.'/config.php');
    write_private_config($root.'/config.local.php',$values);
    $loaded=cms_config_values($root);
    check_config($loaded['DB_HOST']==='fixture-db','legacy config.local.php remains supported');

    $marker=$root.'/executed';
    file_put_contents($root.'/config.php',"<?php file_put_contents(".var_export($marker,true).",'yes');\n");
    $loaded=cms_config_values($root);
    check_config($loaded['DB_HOST']==='fixture-db' && !is_file($marker),
        'unrecognized legacy config.php code is never executed');

    $markerAfterArray=$root.'/executed-after-array';
    file_put_contents($root.'/config.php',"<?php\n// FengCMS private configuration data v1.\nreturn []; file_put_contents(".
        var_export($markerAfterArray,true).",'yes');\n");
    $loaded=cms_config_values($root);
    check_config($loaded['DB_HOST']==='fixture-db' && !is_file($markerAfterArray),
        'configuration data files reject executable statements');

    write_private_config($root.'/config.php',$values);
    $conflict=false;
    try{cms_config_values($root);}catch(RuntimeException){$conflict=true;}
    check_config($conflict,'two private config sources are rejected as ambiguous');

    unlink($root.'/config.local.php');
    $weak=$values; $weak['app_key']='admin123';
    write_private_config($root.'/config.php',$weak);
    $rejected=false;
    try{cms_config_values($root);}catch(RuntimeException){$rejected=true;}
    check_config($rejected,'known weak application keys are rejected');
    echo "Configuration loader regressions passed: $passed checks.\n";
} finally {
    foreach($names as $name){
        if($saved[$name]!==false){putenv($name.'='.$saved[$name]);}
        else{putenv($name);}
    }
    foreach(glob($root.'/*')?:[] as $path){if(is_file($path)||is_link($path)){unlink($path);}}
    rmdir($root);
}
