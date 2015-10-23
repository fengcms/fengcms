<?php
/*******************************************************************
* @authors FengCms 
* @web     http://www.fengcms.com
* @email   web@fengcms.com
* @date    2014-01-04 16:05:22
* @version FengCms Beta 1.0
* @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
*******************************************************************/


include $config_file;

$config=file_get_contents($config_file);

$config=str_replace(DB_HOST,$_GET['host'],$config);
$config=str_replace(DB_USER,$_GET['user'],$config);
$config=str_replace(DB_PASS,$_GET['password'],$config);
$config=str_replace(DB_NAME,$_GET['dbname'],$config);
$config=str_replace(DB_PREFIX,$_GET['prefix'],$config);

if(extension_loaded("mysqli")){
	$config=str_replace("define('DB_TYPE','".DB_TYPE."');","define('DB_TYPE','mysqli');",$config);
}elseif(extension_loaded("mysql")){
	$config=str_replace("define('DB_TYPE','".DB_TYPE."');","define('DB_TYPE','mysql');",$config);
}

if($_GET['url_type']=="1"){
	$config=str_replace("define('URL_TYPE', ".URL_TYPE.");","define('URL_TYPE', 1);",$config);
}elseif($_GET['url_type']=="0"){
	$config=str_replace("define('URL_TYPE', ".URL_TYPE.");","define('URL_TYPE', 0);",$config);
}
if(file_put_contents($config_file,$config)){
	
	include $config_file;

	$install_sql=file_get_contents($install_file);
	$install_sql=str_replace("#prefix_",$_GET['prefix'],$install_sql);

	include ABS_PATH."/step/step4.php";

	mysql_query("SET NAMES utf8;", $conn);
	foreach (explode(';',$install_sql) as $line) {
		$sql_str .= $line;
			if(!mysql_query(trim($sql_str),$conn)) //return false;
			unset($sql_str);
			$sql_str = '';
	}
 
  echo '<script type="text/javascript">alert("安装成功！");</script>';
  echo '<meta http-equiv="refresh" content="0;url=index.php?step=5">';

  exit();
}else{

  echo '<script type="text/javascript">alert("数据写入失败，请用phpmyadmin直接导入/install/daoru.sql文件！");history.go(-1);</script>';
  exit();

}
?>