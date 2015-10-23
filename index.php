<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//	管理

define("TPL_INCLUDE",1);

// 定义当前路径
define('ABS_PATH',dirname(__FILE__).'/');
define('ROOT_PATH',ABS_PATH);


include_once ABS_PATH.'system/app.php';

app::run();




?>