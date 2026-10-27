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
require_once ROOT_PATH.'/system/common/model_schema.php';

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
			'systemfield'	=>	M($this->model)->systemfields($_GET['project']),
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
		echo json_encode(M($this->model)->systemsave($_POST));
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
        if(!in_array($_GET['status']??null,['0','1'],true)){cms_fail(400,'字段状态不正确。');}
        $changed=CmsModelSchema::withLock((string)($_GET['project']??''),
            fn()=>M($this->model)->status((int)$_GET['id'],$_GET['status'],$_GET['project']));
        if($changed){
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
		echo json_encode(CmsModelSchema::withLock((string)($_GET['project']??''),
            fn()=>M($this->model)->sort(array_filter($_POST),$_GET['project'])));
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function delete(){
        $result=M($this->model)->deleteField((int)($_GET['id']??0),(string)($_GET['project']??''));
        if($result['status']!=='y'){cms_fail(409,$result['message']);}
        echo '<meta http-equiv="refresh" content="0;url=?controller=fieldmanage&project='.$_GET['project'].'&operate=execution&type=exc&re=1">';
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
