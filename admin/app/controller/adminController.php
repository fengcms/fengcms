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
defined('TPL_INCLUDE') or die( 'Restricted access'); 

class adminController extends Controller{


    /**
     * 登录验证
     * @access public
     * @return json
     */
	public function index(){
		$_POST=lib_replace_end_tag_array($_POST);
		if($_POST['admincode']==ADMIN_CODE){
			if(D("manage")->where('admin="'.$_POST['user'].'" and password="'.md5(md5(md5($_POST['password']))).'"')->getcount()>0){
				$_SESSION['manage']=$_POST['user'];			
				echo json_encode(array('status' => 'y'));
			}else{
				echo json_encode(array('status' => 'n'));
			}
		}else{
			echo json_encode(array('status' => 'c'));
		}

	}

}







?>