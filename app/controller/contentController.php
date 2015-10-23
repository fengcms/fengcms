<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 内容

class contentController extends Controller{

	private $content;


	public function __construct(){
		$this->content=M("module")->content(lib_replace_end_tag($_GET['project']),intval($_GET['id']));
	}

	public function index(){
		$_GET['classid']=$this->content['classid'];
		return $this->display($this->content['template'],$this->content);
	}
}







?>