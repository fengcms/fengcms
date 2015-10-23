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

class dbmanageModel extends model{

    /**
     * 备份数据
     *
     * @return array
     */
	public function backups(){
		if($this->dbbackup(time())){
			return array('status' => 'y');

		}else{

			return array('status' => 'n');
		}
	}

    /**
     * 恢复数据
     *
     * @return array
     */
	public function regain(){
		if($this->dbregain($_POST['dbname'])){
		
			return array('status' => 'y');
		}else{

			return array('status' => 'n');
		}
	}

	 /**
     * 数据管理
     *
     * @return array
     */

	public function deletefile($array){

		$dir=DBBACKUP_PATH.str_replace('/', "",$array['name']).'/';
		
		if(file_exists($dir)){

			if(rmdirs($dir)){

				return true;

			}else{

				return false;

			}

		}else{
			return false;
		}
	}

}

?>