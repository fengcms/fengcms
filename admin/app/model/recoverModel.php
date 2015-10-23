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

class recoverModel extends model{

	private $d_name='article';

    /**
     * 管理信息
     *
     * @return array
     */
	public function findall(){
		return D($this->d_name)->getall();
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save($array){

		if(D($this->d_name)->update($_POST)){

			return array('status' => 'y');

		}else{

			return array('status' => 'n');
		}
	}

}

?>