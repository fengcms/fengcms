<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 报名系统

include "admin.php";

class signupController extends Controller{

	private $model = 'signup';


    /**
     * 报名系统列表
     * @access public
     * @return template,array
     */
	public function index(){
		return $this->display('signup/index.html',sqlpage($this->model,"20","*",$where,"id desc"));
	}

    /**
     * 报名系统列表
     * @access public
     * @return template,array
     */
	public function signup(){
		return $this->display('signup/signup.html',M($this->model)->find());
	}

    /**
     * 查看记录详情
     * @access public
     * @return template,array
     */
	public function detail(){
		return $this->display('signup/detail.html',M($this->model)->detail($_GET['id']));
	}

    /**
     * 单条删除数据
     * @access public
     * @return template,array
     */
	public function deleteone(){
		if(M($this->model)->delete($_GET['id'])){
			echo '<script type="text/javascript">alert("删除成功！");</script>';
			echo '<meta http-equiv="refresh" content="0;url=?controller=signup">';
		}else{
			echo '<script type="text/javascript">alert("删除失败！");</script>';
			echo '<meta http-equiv="refresh" content="0;url=?controller=signup">';
		}
	}

    /**
     * 单条删除数据
     * @access public
     * @return template,array
     */
	public function deletebatch(){
		echo json_encode(M($this->model)->batch($_POST['record']));
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save(){
		echo json_encode(M($this->model)->save($_POST));
	}

    /**
     * 报名系统列表
     * @access public
     * @return template,array
     */
	public function signupinfo(){
		return $this->display('signup/signupinfo.html',M($this->model)->signupinfo($_GET['id']));
	}

    /**
     * 执行结果
     *
     * @return array
     */
	public function execution(){
		return $this->display('signup/execution.html');
	}
}






?>