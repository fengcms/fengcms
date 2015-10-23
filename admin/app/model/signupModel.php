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

class signupModel extends model{

	private $d_name='signup';

    /**
     * 管理信息
     *
     * @return array
     */
	public function page($where){
		return sqlpage($this->d_name,"20","*",$where,"id desc");
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function find(){
		return D($this->d_name.'_base')->getone();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function signupinfo($id){
		return D($this->d_name)->where('id="'.$id.'"')->getone();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function detail($id){
		if($id){
			return D($this->d_name)->where('id="'.$id.'"')->getone();
		}else{
			return throwexce('Exception Error ID does not exist.');
		}
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function delete($id){
		if($id){
			return D($this->d_name)->where('id="'.$id.'"')->delete();
		}else{
			return false;	
		}
	}

	public function batch($array){
		if(is_array($array)){
			foreach($array as $v){
				if(!$this->delete($v)) return array('status' => 'n');
			}
			return array('status' => 'y');
		}else{
			return array('status' => 'e');
		}	
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save($array){

		if(D($this->d_name."_base")->update($array)){

			return array('status' => 'y');

		}else{

			return array('status' => 'n');
		}
	}

}

?>