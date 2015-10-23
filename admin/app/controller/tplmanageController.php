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

class tplmanageController extends Controller{

	private $model = 'tplmanage';

    /**
     * 模板管理
     * @access public
     * @return template,array
     */
	public function index(){
		return $this->display('tplmanage/index.html');
	}

    /**
     * 模板添加
     * @access public
     * @return template,array
     */
	public function add(){
		return $this->display('tplmanage/add.html');
	}

    /**
     * 模板修改
     * @access public
     * @return template,array
     */
	public function update(){
		return $this->display('tplmanage/update.html',array(
				'name'		=> $_GET['name'],
				'content'	=> file_get_contents(M($this->model)->pathset($_GET['name'],$_GET['project']))
				)
			);
	}
	/**
     * 删除信息
     *
     * @return array
     */
	public function delete(){

		if(M($this->model)->deletefile($_GET)){

                echo '<script type="text/javascript">alert("删除成功！");window.history.back()</script>';
            }else{
                echo '<script type="text/javascript">alert("删除失败！");window.history.back()</script>';
            }
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
     * 保存结果
     *
     * @return array
     */
	public function execution(){
		return $this->display('tplmanage/execution.html');
	}
}






?>