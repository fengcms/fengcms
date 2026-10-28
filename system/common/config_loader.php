<?php
/** Read-only configuration data loading and application constant bootstrap. */

function cms_config_array_data_only(string $source): bool
{
    try { $tokens=token_get_all($source,TOKEN_PARSE); }
    catch (ParseError) { return false; }
    $allowed=[T_OPEN_TAG,T_COMMENT,T_WHITESPACE,T_RETURN,T_ARRAY,T_CONSTANT_ENCAPSED_STRING,T_LNUMBER,T_DOUBLE_ARROW];
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if (!in_array($token[0],$allowed,true)) { return false; }
        } elseif (!str_contains('[](),;',$token)) { return false; }
    }
    return true;
}

function cms_config_is_private_file(string $path): bool
{
    if (is_link($path) || !is_file($path)) { return false; }
    $source=@file_get_contents($path);
    return is_string($source) && str_starts_with($source,"<?php\n// FengCMS private configuration data v1.\nreturn ") &&
        cms_config_array_data_only($source);
}

function cms_config_values(string $root): array
{
    $newFile=$root.'/config.php';
    $legacyFile=$root.'/config.local.php';
    $hasNew=cms_config_is_private_file($newFile);
    if (is_link($legacyFile)) { throw new RuntimeException('Legacy private configuration cannot be a symbolic link.'); }
    $hasLegacy=is_file($legacyFile);
    if ($hasNew && $hasLegacy) {
        throw new RuntimeException('Both config.php and legacy config.local.php exist; remove the obsolete private file after migration.');
    }
    $sourcePath=$hasNew?$newFile:($hasLegacy?$legacyFile:null);
    if ($sourcePath!==null) {
        $permissions=@fileperms($sourcePath);
        if ($permissions===false || ($permissions&0077)!==0) {
            throw new RuntimeException('Private configuration permissions must exclude group and other access.');
        }
    }
    $fileValues=[];
    if ($hasNew) { $fileValues=require $newFile; }
    elseif ($hasLegacy) {
        $legacySource=@file_get_contents($legacyFile);
        if (!is_string($legacySource) || !cms_config_array_data_only($legacySource)) {
            throw new RuntimeException('Legacy private configuration must contain only a returned PHP array.');
        }
        $fileValues=require $legacyFile;
    }
    if (!is_array($fileValues)) { throw new RuntimeException('Private configuration must return an array.'); }

    $mapping=['DB_HOST'=>'db_host','DB_USER'=>'db_user','DB_PASS'=>'db_pass','DB_NAME'=>'db_name',
        'ADMIN_CODE'=>'admin_code','APP_KEY'=>'app_key'];
    $values=[];
    foreach ($mapping as $environment=>$key) {
        $value=getenv($environment);
        if ($value===false) { $value=$fileValues[$key]??null; }
        if (!is_string($value) || $value==='') { throw new RuntimeException('Missing private configuration: '.$environment); }
        $values[$environment]=$value;
    }
    foreach ($values as $value) {
        if (str_starts_with($value,'REPLACE_')) { throw new RuntimeException('Private configuration still contains a placeholder.'); }
    }
    if (in_array($values['DB_PASS'],['123456','password'],true) || $values['ADMIN_CODE']==='8888' ||
        $values['APP_KEY']==='admin123' || strlen($values['APP_KEY'])<32) {
        throw new RuntimeException('Unsafe private configuration; run the deployment preflight.');
    }
    $prefix=getenv('DB_PREFIX');
    if ($prefix===false) { $prefix=$fileValues['db_prefix']??'f_'; }
    if (!is_string($prefix) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/D',$prefix)) {
        throw new RuntimeException('Invalid private configuration: DB_PREFIX');
    }
    $urlType=$fileValues['url_type']??0;
    if (!is_int($urlType) && !(is_string($urlType) && preg_match('/^[01]$/D',$urlType))) { $urlType=0; }
    return $values+['DB_PREFIX'=>$prefix,'URL_TYPE'=>(int)$urlType,'DEBUGS'=>getenv('APP_DEBUG')==='1'];
}

function cms_config_bootstrap(string $root): void
{
    if (defined('DB_HOST')) { return; }
    $values=cms_config_values($root);
    foreach (['DB_HOST','DB_USER','DB_PASS','DB_NAME','DB_PREFIX'] as $name) { define($name,$values[$name]); }
    define('DB_TYPE','mysqli');
    define('DB_VIEW_PREFIX',DB_PREFIX.'view_');
    define('ADMIN_CODE',$values['ADMIN_CODE']);
    define('DEBUGS',$values['DEBUGS']);
    define('CONTROLLER','Controller');
    define('DEFAULT_CONTROLLER','home'.CONTROLLER);
    define('DEFAULT_OPERATE','index');
    define('KEYS',$values['APP_KEY']);
    define('SESSION_EXT','se_');
    define('SESSION_DOMAIN','');
    define('CACHE_PATH',APP_PATH.'/cache/');

    $runtimePath=getenv('APP_RUNTIME_PATH') ?: $root.'/var/runtime';
    require_once $root.'/system/common/site_settings.php';
    try { CmsSiteSettings::recoverPending(); }
    catch (Throwable $e) {
        error_log('Site settings recovery failed: '.$e);
        cms_fail(503,'网站设置正在恢复，请稍后重试。');
    }
    $path=$runtimePath.'/site.json';
    $settings=is_readable($path) ? json_decode((string)file_get_contents($path),true) : [];
    if (!is_array($settings)) { $settings=[]; }
    define('URL_TYPE',(int)($settings['url_type']??$values['URL_TYPE']));
}
