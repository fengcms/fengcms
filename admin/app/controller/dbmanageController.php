
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
include "admin.php";

class dbmanageController extends Controller{


    /**
     * 恢复数据
     *
     * @return array
     */
	public function index(){
		echo '<script type="text/javascript">alert("操作成功！");</script>';
		echo '<meta http-equiv="refresh" content="0;url=?controller=dbmanage&operate=dbbackup">';
	}

    /**
     * 备份数据
     *
     * @return array
     */
	public function dbbackup(){
		$this->display('dbmanage/dbbackup.html');
	}

    /**
     * 恢复数据
     *
     * @return array
     */
	public function dbregain(){
		$this->display('dbmanage/dbregain.html',array("dblist" => array_filter(getDir(DBBACKUP_PATH))));
	}

    /**
     * 管理备份数据
     *
     * @return array
     */
    public function dbmanages(){
        $this->display('dbmanage/dbmanages.html',array("dblist" => array_filter(getDir(DBBACKUP_PATH))));
    }

    /**
     * 删除信息
     *
     * @return array
     */
    public function delete(){

        if(M('dbmanage')->deletefile($_GET)){

                echo '<script type="text/javascript">alert("删除成功！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=dbmanage&operate=dbmanages">';
            }else{
                echo '<script type="text/javascript">alert("删除失败！");window.history.back()</script>';
            }
    }

    /**
     * 执行
     *
     * @return array
     */
	public function save(){
		if($_GET['type']=="0"){
			echo json_encode(M("dbmanage")->backups());
		}
		if($_GET['type']=="1"){
			echo json_encode(M("dbmanage")->regain());

		}
	}

}



?>