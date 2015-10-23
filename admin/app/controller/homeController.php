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

class homeController extends Controller{

    /**
     * 后台首页
     * @access public
     */
	public function index(){
		return $this->display('index.html');
	}

    /**
     * 内容
     * @access public
     */
	public function main(){
		return $this->display('main.html');
	}

    /**
     * 保存
     * @access public
     */
	public function execution(){
		return $this->display('execution.html');
	}
}







?>