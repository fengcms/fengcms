<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 栏目

class messageController extends Controller{

	private $model		=	"message";


	public function index(){
		return $this->display("message.html");//,M($this->model)->page());
	}

	public function add(){
		return $this->display("message_add.html");//,M($this->model)->page());
	}

	public function save(){
		$_POST=removexss_array($_POST);
		echo json_encode(M($this->model)->save(lib_replace_end_tag_array($_POST)));
	}
}







?>