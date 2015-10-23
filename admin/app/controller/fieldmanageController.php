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

class fieldmanageController extends Controller{

	private $model = 'fieldmanage';


    /**
     * 网站基础信息
     * @access public
     * @return template,array
     */
	public function index(){
		return $this->display('fieldmanage/index.html',array(
			'field' =>	M($this->model)->findall('project="'.$_GET['project'].'"')
			));
	}

    /**
     * 网站基础信息
     * @access public
     * @return template,array
     */
	public function system(){
		return $this->display('fieldmanage/system.html',array(
			'systemfield'	=>	M("fieldtype")->findall('f=0'),
			'field'			=>	M($this->model)->findall('project="'.$_GET['project'].'"')
			));
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function add(){
		return $this->display('fieldmanage/add.html',M("module")->findone('project="'.$_GET['project'].'"'));
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function update(){
		return $this->display('fieldmanage/update.html',M("module")->findone('project="'.$_GET['project'].'"'));
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function systemsave(){
		echo json_encode(M($this->model)->systemsave(array_filter($_POST)));
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
     * 保存信息
     *
     * @return array
     */
    public function enable(){
        if(M($this->model)->status('id="'.$_GET['id'].'"',$_GET['status'])){
            echo '<meta http-equiv="refresh" content="0;url=?controller=fieldmanage&project='.$_GET['project'].'">';
        }else{
            echo '<meta http-equiv="refresh" content="0;url=?controller=fieldmanage&project='.$_GET['project'].'">';
        }
    }

    /**
     * 保存信息
     *
     * @return array
     */
	public function sort(){
		echo json_encode(M($this->model)->sort(array_filter($_POST)));
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function delete(){
        $id=$_GET['id'];
		M($this->model)->fielddel($id);
		$re=M($this->model)->delete('id="'.$id.'"');
		if($re){
			echo '<meta http-equiv="refresh" content="0;url=?controller=fieldmanage&project='.$_GET['project'].'&operate=execution&type=exc&re=1">';
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=fieldmanage&project='.$_GET['project'].'&operate=execution&type=exc&re=0">';
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function execution(){
		return $this->display('fieldmanage/execution.html');
	}
}






?>