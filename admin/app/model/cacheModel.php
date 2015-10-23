<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2014-01-04 13:55:08
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/



		if(rmdirs(ROOT_PATH.'/app/cache/compile/') and rmdirs(ABS_PATH.'/app/cache/compile/')){

			  echo '<script type="text/javascript">alert("缓存更新成功");close();</script>';
			  exit();

		}else{
			  echo '<script type="text/javascript">alert("删除失败，请手工删除网站根目录下install目录！");close();</script>';
			  exit();
		}

?>