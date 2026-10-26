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
include "admin.php";

class manageController extends Controller{

	private $model="manage";




    /**
     * 修改密码
     * @access public
     * @return template,array
     */
	public function password(){
		return $this->display('manage/password.html');
	}
		
    /**
     * 保存密码
     * @access public
     * @return json
     */
	public function save(){
			if(M($this->model)->findcount($_POST)>0){

				echo json_encode(M($this->model)->save($_POST));
			}else{
				echo json_encode(array('status' => 'n'));
			}
	}
	
    /**
     * 退出登录
     * @access public
     * @return url
     */
	public function quit(){
		$_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		echo '<meta http-equiv="refresh" content="0;url=/">';
	}
}







?>