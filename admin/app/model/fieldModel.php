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

class fieldModel extends model{

	private $d_name='module_field';
	private $v_name='view_field';
    /**
     * 管理信息
     *
     * @return array
     */
	public function findone($where){
		return D($this->v_name)->where($where)->getone();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function findall($where){
		return D($this->v_name)->sort("sort,id asc")->where($where)->getall();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function findexists($project,$field){
		if(D($this->v_name)->where('name="'.$field.'" and project="'.$project.'"')->getcount()>0){
			return true;
		}else{
			return false;
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save($array){

		if(D($this->d_name)->update($array)){

			return array('status' => 'y');

		}else{

			return array('status' => 'n');
		}
	}

	public function fieldrepl($arr,$arrs,$fieldname,$value=NULL){

		foreach($arr as $k => $v){
			if($v==$fieldname){
				$array[]=$value;
			}else{
				$array[]=$arrs[$k];
			}
		}

		return $array;
	}
}

?>