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

class messageModel extends model{

	private $d_name		 = "message";

	public function page(){
		return sqlpage($this->d_name,"20","*","","id desc");
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save($array){

		if($_SESSION['authnum']!=$array['vcode']||$_SESSION['authnum']=="")
		{
			return array('status' => 'c');
			exit;
		}
 
		unset($array['vcode']);
		//转义、过滤
		$array = array_filter(addslashes_array($array));
		 
		$re=D($this->d_name)->insert($array);
 
		if($re){
			$_SESSION['authnum']="";
			return array('status' => 'y','id' => $re);

		}else{

			return array('status' => 'n','id' => $re);
		}
	}
}



?>