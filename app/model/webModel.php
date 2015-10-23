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

class webModel extends model{

	private $d_name		 = "base";
	public $information = array();

	public function __construct(){
		parent::__construct($this->d_name);
		$this->information=$this->getone();
	}
}







?>