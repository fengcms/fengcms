<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 首页模型

class tagsModel extends model{

	private $d_name = "tags";

	public function __construct(){
		unset($this->options);
		parent::__construct($this->d_name);
	}

	public function index(){
		return $this->where("status=1")->sort("hits","order","desc")->getall();
	}

	public function side($num=30){
		return $this->where("status=1")->sort("hits","order","desc")->limit($num)->getall();
	}

}







?>