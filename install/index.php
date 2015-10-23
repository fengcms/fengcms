<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//

header("Content-type:text/html;charset=utf-8");

define("TPL_INCLUDE",1);

// 定义当前路径
define('ABS_PATH',dirname(__FILE__));
define('ROOT_PATH',dirname(ABS_PATH));

if(!$_GET['step'])$_GET['step']=1;

$config_file=ROOT_PATH.'/config.php';
$install_file=ABS_PATH.'/install.sql';

if(file_exists(ROOT_PATH.'/upload/INSTALL')){
  echo '<script type="text/javascript">alert("系统已安装，如需要重新安装，请手工删除upload目录下的INSTALL文件！");</script>';
  echo '<meta http-equiv="refresh" content="0;url=/">';
}

switch($_GET['step']){

    case '1': //安装许可协议

		include ABS_PATH."/step/step1.php";

	break;


    case '2': //检查安装环境是否满足要求

		$PHP_GD  = '';
		if(extension_loaded('gd')) {
			if(function_exists('imagepng')) $PHP_GD .= 'png';
			if(function_exists('imagejpeg')) $PHP_GD .= ' jpg';
			if(function_exists('imagegif')) $PHP_GD .= ' gif';
		}
		$PHP_JSON = '0';
		if(extension_loaded('json')) {
			if(function_exists('json_decode') && function_exists('json_encode')) $PHP_JSON = '1';
		}
		//新加fsockopen 函数判断,此函数影响安装后会员注册及登录操作。
		if(function_exists('fsockopen')) {
			$PHP_FSOCKOPEN = '1';
		}
		$PHP_DNS = preg_match("/^[0-9.]{7,15}$/", @gethostbyname('www.baidu.cn')) ? 1 : 0;

		//是否满足FengCms安装需求

		$is_right = (phpversion() >= '5.2.0' && extension_loaded('mysql') && $PHP_JSON && $PHP_GD && $PHP_FSOCKOPEN) ? 1 : 0;

		//配置文件是否存在和可写

		$is_config = (is_readable($config_file) && is_writable($config_file)) ? 1 : 0;

		//上传目录是否可写
		$is_upload = (dir_writeable(ROOT_PATH."/upload")) ? 1 : 0;
		
		//模块导出目录是否可写
		$is_module = (dir_writeable(ROOT_PATH."/upload/module")) ? 1 : 0;

		//前台缓存目录是否可写
		$is_cache = (dir_writeable(ROOT_PATH."/app/cache")) ? 1 : 0;

		include ABS_PATH."/step/step2.php";

	break;

    case '3': //填写数据库信息

		include ABS_PATH."/step/step3.php";

	break;

    case '4': //正在安装

		$conn=@mysql_connect($_GET['host'],$_GET['user'],$_GET['password']);

		if(!$conn){

				echo '<script type="text/javascript">alert("链接主机失败，请检查主机地址、用户名和密码是否正确！");history.go(-1);</script>';
				exit();  
		}elseif(intval(mysql_get_server_info($conn))<5){

				echo '<script type="text/javascript">alert("您的MYSQL版本为'. mysql_get_server_info($conn).'，版本太低，不能安装FengCms!");history.go(-1);</script>';
				exit();  

		}else{  

			if(!mysql_select_db($_GET['dbname'],$conn)){   //如果数据库不存在，我们就进行创建。

				if (!mysql_query("CREATE DATABASE ". $_GET['dbname'] ." default character set utf8;",$conn))

					{
					
						echo '<script type="text/javascript">alert("数据库不存在，创建不成功");history.go(-1);</script>';

					}else{

						if(!mysql_select_db($_GET['dbname'],$conn)){
							
							echo '<script type="text/javascript">alert("链接数据库失败，请检查数据库是否正确！");history.go(-1);</script>';

						}else{

							include "data.php";

					}

				}

			}else{

				include "data.php";

		   }
		}
	break;

    case '5': //安装完成

		include ABS_PATH."/step/step5.php";
		$in = fopen(ROOT_PATH.'/upload/INSTALL','w');
		fclose ($in);
	break;
/****
    case '6': //删除安装目录

		if(rmdirs(ROOT_PATH.'/install') or rmdir(ROOT_PATH.'/install')){

			  echo '<script type="text/javascript">alert("删除成功！");close();</script>';
			  exit();

		}else{
			  echo '<script type="text/javascript">alert("删除失败，请手工删除网站根目录下install目录！");close();</script>';
			  exit();
		}

	break;
***/
}

function dir_writeable($dir) { 
    /** 
     * $dir如果不是目录将创建一个可读写的目录 
     */ 
    if(!is_dir($dir)) { 
        @mkdir($dir, 0777); 
    } 
    if(is_dir($dir)) {  //如果目录已存在 
        if($fp = @fopen("$dir/test.test", 'w')) {    //创建一个名为test.test的文件来测试 
            @fclose($fp);             //关闭文件流             
            @unlink("$dir/test.test");    //删除测试文件 
            $writeable = 1;            //能创建则说明可读取，返回值为 1 
        } else { 
            $writeable = 0;          //不能创建，返回值为 0  
        } 
    } 
    return $writeable;              //返回值 
}
?>