<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-12-10 09:31:48
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
include "admin.php";

class safeController extends Controller{

	private $model = 'safe';


    /**
     * 网站基础信息
     * @access public
     * @return template,array
     */
	public function index(){
		$base=M("base")->findone();
		$array=array('safe'=>$this->safe($base['domran']));
		return $this->display('safe/index.html',$array);
	}

	/**
     * 网站本地版权信息
     * @access public
     * @return template,array
     */
	public function serverversion(){
		echo file_get_contents("http://www.fengcms.com/version.txt");
	}


	/*读取360网站安全信息*/
	function safe($url){
		$safeurl=file_get_contents("http://www.so.com/s?ie=utf-8&src=360sou_home&ms=s34438&q=site%3A%2F%2F".$url);

		$array=explode('<div class="detail">',$safeurl);

		$array=explode("</div>",$array[1]);
			if($array[0]){
				return $array[0];
			}
			else{
				return "<div id='nosafe'>360网站安全检测系统没有收录您的网站，请登陆到 <a href='http://webscan.360.cn/' target='_blank'>360网站安全检测中心</a> 递交您的网站信息</div>";
			}

		}
	}
?>