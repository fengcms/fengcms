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
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING); 
// 脚本开始时间
define('__BEGIN__', microtime(true));
// 设置编码
header("Content-Type:text/html;charset=UTF-8");
// 设置时区
date_default_timezone_set("PRC");
// 设置程序运行时间
ini_set('max_execution_time','0');
// 打开绝对刷新
ob_start();ob_implicit_flush();
// 设置页面时间
set_time_limit(0);
// 打开SESSION
session_start();

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

// 加载配置
include_once ROOT_PATH.'/config.php'; 

// 加载函数
include_once COMMON_PATH.'function.php';

// 加载函数
include_once COMMON_PATH.'filter.php';

// 防注
include_once COMMON_PATH.'waf.php'; 

// 加载类库
import('core.*');

 /*------------------ 过滤 ------------------*/
//php 批量过滤post,get敏感数据 
if (get_magic_quotes_gpc()) { 
	$_GET	= stripslashes_array($_GET); 
	$_POST	= stripslashes_array($_POST); 
}

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

		$_WORKER['controller']=($_GET['controller']?$_GET['controller']:'home').'Controller';
		$_WORKER['operate']=($_GET['operate']?$_GET['operate']:'index');

		spl_autoload_register(array('app', '__autoload'));

		try {

			// 类名不存在，使用默认类名
			if(class_exists($_WORKER['controller'])){

                $object = new $_WORKER['controller'];

				//方法不存在，使用默认方法
			   if(method_exists($object, $_WORKER['operate'])){	
					
					return $object->$_WORKER['operate']();

				}else{
					throwexce(sprintf('%s is not found in the %s!',$_WORKER['operate'], $_WORKER['controller']));
				}
			}else{
				throwexce(sprintf('%s Object does not exist!', $_WORKER['controller']));
			}

        } catch (exceptions $e) {
			
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
    final private static function _header() {
			// 程序必须开启缓冲区
			ob_start();
            header('X-Powered-By: Worker/'. VERSION . ' (AirPHP)');
            header('X-Run-Period: '.(microtime(true) - __BEGIN__));
			session_start();
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
    final public static function __autoload($class_name) {

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
	  * __call
	  *
	  * @access	public
	  * @param	string	$name
	  * @param	array	$args
	  * @return	mixed
	  */
    final public function __call($name, $args) {
        $method = substr($name, 3);
        // 前置过滤器
        $before = '_before_' . $method;
        if (method_exists($this, $before)) {
            if ($result = call_user_func_array(array(&$this, $before), $args)) {
                $args = $result;
            }
        }
        // 执行过滤器
        $return = call_user_func_array(array(&$this, $method), $args);
        // 后置过滤器
        $after = '_after_' . $method;
        if (method_exists($this, $after)) {
            $return = call_user_func(array(&$this, $after), $return);
        }
        return $return;
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