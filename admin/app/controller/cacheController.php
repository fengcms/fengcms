<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 后台首页
include "admin.php";

class cacheController extends Controller{

    /**
     * 后台首页
     * @access public
     */
	public function index(){

		$qcache = ROOT_PATH.'/app/cache/compile/';

		$hcache = ABS_PATH.'/app/cache/compile/';

		if(file_exists($qcache) and file_exists($hcache)){
			
			if(rmdirs($qcache) and rmdirs($hcache)){

					echo '<script type="text/javascript">alert("缓存更新成功");</script>';
					echo '<meta http-equiv="refresh" content="0;url=?controller=home&operate=main">';
				exit();

				}else{
					echo '<script type="text/javascript">alert("缓存更新失败");</script>';
					echo '<meta http-equiv="refresh" content="0;url=?controller=home&operate=main">';
				exit();
			}
		}else{
			echo '<script type="text/javascript">alert("缓存已经更新");</script>';
			echo '<meta http-equiv="refresh" content="0;url=?controller=home&operate=main">';
		}
	}

}

?>