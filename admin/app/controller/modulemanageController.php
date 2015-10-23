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

class modulemanageController extends Controller{

	private $model = 'modulemanage';


    /**
     * 网站基础信息
     * @access public
     * @return template,array
     */
	public function index(){
		return $this->display('modulemanage/index.html',array(
			'module' =>	M($this->model)->findall("type=1")
			));
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function add(){
		return $this->display('modulemanage/add.html');
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function update(){
		return $this->display('modulemanage/update.html',M($this->model)->findone('id="'.$_GET['id'].'"'));
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function disabled(){
		if(M($this->model)->update($_GET['f'],'id="'.$_GET['id'].'"')){
			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage">';
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage">';
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function delete(){
		$arr=M($this->model)->findone('id="'.$_GET['id'].'"');

		$deltable=D($arr['project'])->droptable();
		$delmodule=M($this->model)->delete('id="'.$arr['id'].'"');
		$delfield=M("fieldmanage")->delete('module_id="'.$arr['id'].'"');

		if($deltable&&$delmodule&&$delfield){
			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage">';
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage">';
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
     * 模块导出
     *
     * @return array
     */
	public function elicit(){

		$array=D("module")->where('id="'.$_GET['id'].'"')->getone();
		
		$path=FILES_PATH.'module/';

		$file=$array['module'].md5(time()).".txt";

		$sql=encrypt(M($this->model)->elicit($array,$path),"E");

		if(fileWrite($sql,$file,$path)){

			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage&operate=elicitcution&re=1&url='.urlencode(replpath($path.$file)).'">';

		}else{

			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage&operate=elicitcution&re=0">';
			
		}
	}

    /**
     * 模块导入
     *
     * @return array
     */
	public function load(){
		return $this->display('modulemanage/load.html');
	}

    /**
     * 模块导入
     *
     * @return array
     */
	public function loading(){
		echo json_encode(M($this->model)->loading($_POST));
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function execution(){
		return $this->display('modulemanage/execution.html');
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function elicitcution(){
		return $this->display('modulemanage/elicitcution.html');
	}
}






?>