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

/*---------------- MYSQL 数据库选项 ------------------------*/

	define('DB_HOST','localhost');												//地址

	define('DB_USER','root');													//用户

	define('DB_PASS','123456');													//密码

	define('DB_NAME','fengcms');												//库名

	define('DB_TYPE','mysqli');													//类型 支持mysql和mysqli的方式，默认安装为mysqli方式

	define('DB_PREFIX','f_');													//表扩展名 安装后请勿修改

	define('DB_VIEW_PREFIX',DB_PREFIX.'view_');									//视图表扩展名 请勿修改

/*---------------- 网站设置项（伪静态和后台认证码）---------*/

	define('URL_TYPE', 0);														//访问地址方法，0动态1伪静态

	define('ADMIN_CODE','8888');												//认证码  只能是四位

/*---------------- 网站调试模式是否开启 --------------------*/

	define('DEBUGS', true);													//调试模式 true 开 false 关

/*---------------- 系统核心选项（请勿修改） ----------------*/

	define('CONTROLLER','Controller');											//控制器名称

	define('DEFAULT_CONTROLLER','home'.CONTROLLER);								//默认控制器

	define('DEFAULT_OPERATE','index');											//默认方法

	define('KEYS', 'admin123');													//键值

	define('SESSION_EXT', 'se_');												//SESSION扩展

	define('SESSION_DOMAIN', '');												//SESSION域名

	define('CACHE_PATH',APP_PATH.'/cache/');									//缓存路径