<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 应用驱动类

//配置

defined('TPL_INCLUDE') or die( 'Restricted access'); 

/*------------------ 系统配置 ------------------*/
// 项目版本
define('VERSION', '2.0');
// 设置错误等级
error_reporting(E_ALL);
// 脚本开始时间
define('__BEGIN__', microtime(true));
// 设置编码
header("Content-Type:text/html;charset=UTF-8");
// 设置时区
date_default_timezone_set("PRC");
// 设置程序运行时间
ini_set('max_execution_time','30');
// 打开绝对刷新
ob_start();ob_implicit_flush();
// 设置页面时间
set_time_limit(30);
// 打开SESSION
require_once __DIR__.'/common/security.php';
cms_session_start();

/*------------------ 路径定义 ------------------*/

// 定义应用路径
define('APP_PATH',ABS_PATH.'/app/');
// 定义控制器路径
define('CONTROLLER_PATH',APP_PATH.'/controller/');
// 定义模型路径
define('MODEL_PATH',APP_PATH.'/model/');
// 定义数据备份路径
define('DBBACKUP_PATH',APP_PATH.'dbbackup/');
// 定义文件导出路径
define('FILES_PATH',ROOT_PATH.'/upload/');
// 定义系统路径
define('SYSTEM_PATH',ROOT_PATH.'/system/');
// 定义系统公用
define('COMMON_PATH',SYSTEM_PATH.'/common/');
// 定义系统路径
define('CORE_PATH',SYSTEM_PATH.'/core/');
// 定义系统路径
define('DRIVER_PATH',SYSTEM_PATH.'/driver/');
// 定义错误信息路径
define('ERROR_INFO_PATH',APP_PATH.'/error/');

// 定义公用路径
define('PUBLIC_PATH','public/');
define('JS_PATH','js/');
define('CSS_PATH','css/');
define('IMAGE_PATH','image/');


define('IS_PATH',$_SERVER["PHP_SELF"]);

ini_set("memory_limit","256M");

 /*------------------ 加载库 ------------------*/

// 加载私有配置数据并初始化应用常量
require_once __DIR__.'/common/config_loader.php';
cms_config_bootstrap(ROOT_PATH);
require_once __DIR__.'/common/database_gate.php';

// 加载函数
include_once COMMON_PATH.'function.php';

// 加载函数
include_once COMMON_PATH.'filter.php';

// 加载类库
import('core.*');

 /*------------------ 过滤 ------------------*/
final class app {

	static protected $_instance = NULL;

	/**
	 * 执行
	 *
	 * @access public
     * @return bool
	 */
	public static function run(){

		global $_WORKER;

        $controller = $_GET['controller'] ?? 'home';
        $action = $_GET['operate'] ?? 'index';
        if (!is_string($controller) || !is_string($action) ||
            !preg_match('/^[a-z][a-z0-9]*$/D', $controller) ||
            !preg_match('/^[a-z][a-z0-9]*$/D', $action)) {
            cms_fail(400, '路由参数不正确。');
        }
        $admin = realpath(ABS_PATH) === realpath(ROOT_PATH.'/admin');
        cms_route_guard($controller, $action, $admin);
        $_WORKER['controller'] = $controller.'Controller';
        $_WORKER['operate'] = $action;
        spl_autoload_register(['app', 'loadController']);
        $className=$_WORKER['controller'];
        $dispatch=static function() use ($action,$className): mixed {
            $class = $className;
            if (!class_exists($class)) { cms_fail(404, '页面不存在。'); }
            $object = new $class;
            if (!is_callable([$object, $action])) { cms_fail(404, '页面不存在。'); }
            return $object->$action();
        };
        try {
            if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' &&
                !($admin && (($controller==='admin' && $action==='index') ||
                    ($controller==='dbmanage' && in_array($action,['create','runimport','recoverimport'],true)) ||
                    ($controller==='modulemanage' && $action==='loading')))){
                return CmsDatabaseGate::write($dispatch);
            }
            return $dispatch();
        } catch (CmsDatabaseGateBusy $e) {
            cms_fail(409,$e->getMessage());
        } catch (Throwable $e) {
            systemerror($e);
        }

        self::_header();
	}

	/**
	 * 程序结束输出 header
	 *
	 * @access private
	 * @static
	 * @return void
	 */
    private static function _header() {
			// 程序必须开启缓冲区
			ob_start();
            header('X-Powered-By: Worker/'. VERSION . ' (AirPHP)');
            header('X-Run-Period: '.(microtime(true) - __BEGIN__));
			cms_session_start();
            // 清理缓冲区，输出到浏览器
            ob_end_flush(); flush();

        }

	/**
	 * 自动加载类方法
	 *
	 * @access	private
	 * @static
	 * @param	string	$class_name
	 * @return	string
	 */
    final public static function loadController($class_name) {

		//控制器文件调用
		$controller_file = CONTROLLER_PATH.$class_name.'.php';

		//验证控制器
	   if (file_exists($controller_file)) {
			require_once $controller_file;
	   }else{
			throwexce(sprintf('%s File does not exist!', $class_name));
	   }
	}

	/**
     *  静态方法, 单例统一访问入口
	 *
 	 *  @access	public
 	 *  @static
     *  @return  object  返回对象的唯一实例
     */
	static public function _getinstance() {
			if (is_null(self::$_instance) || !isset(self::$_instance)) {
				self::$_instance = new self();
			}
			return self::$_instance;
	}

    /**
     * 默认
     *
 	 * @static
     * @return void
     */
	public static function defaults(){
        echo 'Restricted access!';
		exit;
	}

}







?>
