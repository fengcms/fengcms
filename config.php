<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.FengCms.com
 * @email   web@FengCms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/

defined('TPL_INCLUDE') or die( 'Restricted access'); 

// The one-time web installer writes this private file; environment variables
// keep precedence for existing container deployments.
$localConfigFile = ROOT_PATH.'/config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($localConfig)) { throw new RuntimeException('Invalid local configuration.'); }

function cms_required_setting(string $environment, string $localKey): string
{
    global $localConfig;
    $value = getenv($environment);
    if ($value === false) { $value = $localConfig[$localKey] ?? null; }
    if (!is_string($value) || $value === '') {
        throw new RuntimeException('Missing private configuration: '.$environment);
    }
    return $value;
}

foreach (['DB_HOST'=>'db_host','DB_USER'=>'db_user','DB_PASS'=>'db_pass','DB_NAME'=>'db_name','ADMIN_CODE'=>'admin_code','APP_KEY'=>'app_key'] as $environment=>$key) {
    cms_required_setting($environment,$key);
}
if (in_array(cms_required_setting('DB_PASS','db_pass'), ['123456','password'], true) ||
    cms_required_setting('ADMIN_CODE','admin_code') === '8888' ||
    cms_required_setting('APP_KEY','app_key') === 'admin123') {
    throw new RuntimeException('Unsafe private configuration; run the deployment preflight.');
}


/*---------------- MYSQL 数据库选项 ------------------------*/

	define('DB_HOST', cms_required_setting('DB_HOST','db_host'));												//地址

	define('DB_USER', cms_required_setting('DB_USER','db_user'));													//用户

	define('DB_PASS', cms_required_setting('DB_PASS','db_pass'));													//密码

	define('DB_NAME', cms_required_setting('DB_NAME','db_name'));												//库名

	define('DB_TYPE','mysqli');													//类型 支持mysql和mysqli的方式，默认安装为mysqli方式

	define('DB_PREFIX', getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : ($localConfig['db_prefix'] ?? 'f_'));													//表扩展名 安装后请勿修改

	define('DB_VIEW_PREFIX',DB_PREFIX.'view_');									//视图表扩展名 请勿修改

/*---------------- 网站设置项（伪静态和后台认证码）---------*/

	$runtimePath = getenv('APP_RUNTIME_PATH') ?: ROOT_PATH.'/var/runtime';
    $siteSettings = is_readable($runtimePath.'/site.json') ? json_decode(file_get_contents($runtimePath.'/site.json'), true) : [];
    define('URL_TYPE', (int)($siteSettings['url_type'] ?? ($localConfig['url_type'] ?? 0)));														//访问地址方法，0动态1伪静态

	define('ADMIN_CODE', cms_required_setting('ADMIN_CODE','admin_code'));												//认证码  只能是四位

/*---------------- 网站调试模式是否开启 --------------------*/

	define('DEBUGS', getenv('APP_DEBUG') === '1');													//调试模式 true 开 false 关

/*---------------- 系统核心选项（请勿修改） ----------------*/

	define('CONTROLLER','Controller');											//控制器名称

	define('DEFAULT_CONTROLLER','home'.CONTROLLER);								//默认控制器

	define('DEFAULT_OPERATE','index');											//默认方法

	define('KEYS', cms_required_setting('APP_KEY','app_key'));													//键值

	define('SESSION_EXT', 'se_');												//SESSION扩展

	define('SESSION_DOMAIN', '');												//SESSION域名

	define('CACHE_PATH',APP_PATH.'/cache/');									//缓存路径