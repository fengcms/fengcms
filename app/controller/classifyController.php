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

class classifyController extends Controller{

	private $d_name		=	"classify";
	private $classify	=	array();

	public function __construct(){

		$_GET['classid']	= (int)$_GET['classid'];

		$this->classify=D($this->d_name)->where(array(
			'enname'	=>	lib_replace_end_tag($_GET['classify']),
			'id'		=>	intval($_GET['classid'])
			))->getone();
	}

	public function index(){

		if($this->classify['type']=="module"){

			if(M($this->d_name)->where('classid="'.$this->classify['id'].'"')->getcount()>0){

				return $this->display($this->classify['channel_template'],is_array($this->classify)?$this->classify:"");

			}else{

				return $this->display($this->classify['classify_template'],is_array($this->classify)?$this->classify:"");

			}
		}elseif($this->classify['type']=="single"){

			$array=D("single")->where('classid="'.$this->classify['id'].'"')->getone();

			if($array['id']){

			$_GET['classid']=$this->classify['id'];

				return $this->display($array['template'],$array);

			}else{

				return $this->display($this->classify['content_template']);

			}
		}

	}
}







?>