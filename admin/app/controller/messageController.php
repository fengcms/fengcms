<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 留言板

include "admin.php";

class messageController extends Controller{

	private $model = 'message';


    /**
     * 留言板列表
     * @access public
     * @return template,array
     */
	public function index(){
		if($_GET['status']=="0"){$where='status=0';}
		return $this->display('message/index.html',sqlpage($this->model,"20","*",$where,"id desc"));
	}

    /**
     * 留言板回复
     * @access public
     * @return template,array
     */
	public function reply(){
		return $this->display('message/reply.html',M($this->model)->reply($_GET['id']));
	}

    /**
     * 单条删除数据
     * @access public
     * @return template,array
     */
	public function deleteone(){
		if(M($this->model)->delete($_GET['id'])){
			echo '<script type="text/javascript">alert("删除成功！");</script>';
			echo '<meta http-equiv="refresh" content="0;url=?controller=message">';
		}else{
			echo '<script type="text/javascript">alert("删除失败！");</script>';
			echo '<meta http-equiv="refresh" content="0;url=?controller=message">';
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
		echo json_encode(M($this->model)->save(lib_replace_end_tag_array($_POST)));
	}

    /**
     * 执行结果
     *
     * @return array
     */
	public function execution(){
		return $this->display('message/execution.html');
	}
}






?>