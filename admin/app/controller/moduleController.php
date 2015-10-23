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

class moduleController extends Controller{

	private $model = 'module';

    /**
     * 网站基础信息
     * @access public
     * @return template,array
     */
	public function index(){
		return $this->display('module/index.html',$this->listarray($_GET));
	}

	public function listarray($array){
		$module	= $this->module($array['project']);
		$field	= $this->field("enable=1 and module_id=".$module['id']." and status=1");
		$record	= $this->record($array['project'],$array);
		return array(
			'module'	=>	$module,
			'field'		=>	$field,
			'record'	=>	$record,
		);
	}

	public function module($project){
		return M($this->model)->findone('project="'.$project.'"');
	}

	public function field($where){
		return M("field")->findall($where);
	}

	public function record($project,$array){

		$where="status>=0";
		
		if($array['title']){
			if($where)$where.=" and ";
			$where.='`'.$array['field'].'` like "%'.$array['title'].'%"';
		}

		if($array['attrib_j']==1){
			if($where)$where.=" and ";
			$where.="attrib_j=1";
		}
		if($array['attrib_g']==1){
			if($where)$where.=" and ";
			$where.="attrib_g=1";
		}
		if($array['attrib_t']==1){
			if($where)$where.=" and ";
			$where.="attrib_t=1";
		}
		if($array['attrib_r']==1){
			if($where)$where.=" and ";
			$where.="attrib_r=1";
		}
		if($array['attrib_d']==1){
			if($where)$where.=" and ";
			$where.="attrib_d=1";
		}
		if($array['attrib_h']==1){
			if($where)$where.=" and ";
			$where.="attrib_h=1";
		}
		
		if($array['classid']){
			if($where)$where.=" and ";
			$where.=M("classify")->children($array['classid'])." classid=".$array['classid'];
		}


		return sqlpage($project,"20","*",$where,"id desc");
	}

    /**
     * 文章修改
     * @access public
     * @return template,array
     */
	public function add(){
		return $this->display('module/add.html',M($this->model)->findone('project="'.$_GET['project'].'"'));
	}

    /**
     * 批量添加
     *
     * @return array
     */
	public function batchadd(){
		return $this->display('module/batchadd.html',M($this->model)->findone('project="'.$_GET['project'].'"'));
	}

    /**
     * 文章修改
     * @access public
     * @return template,array
     */
	public function update(){
		return $this->display('module/update.html',M($this->model)->findone('project="'.$_GET['project'].'"'));

	}

    /**
     * 单条删除数据
     * @access public
     * @return template,array
     */
	public function deleteone(){
		if(D($_GET['project'])->where('id="'.$_GET['id'].'"')->delete()){
			echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=1">';
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=0">';
		}
	}

    /**
     * 单条回收数据
     * @access public
     * @return template,array
     */
	public function recoverone(){
		if(D($_GET['project'])->where('id="'.$_GET['id'].'"')->update("status=-1")){
			echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=1">';
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=0">';
		}
	}

    /**
     * 批量处理
     * @access public
     * @return template,array
     */
	public function batch(){
		if(!$_POST['record']){
			echo json_encode(array('status' => 'a'));
		}else{
			if($this->$_POST['method']($_GET['project'],$_POST['record'],$_POST['parameter'])){
				echo json_encode(array('status' => 'y'));
			}else{
				echo json_encode(array('status' => 'n'));
			}
		}
	}

    /**
     * 批量处理
     * @access public
     * @return template,array
     */
	public function shift(){
		if($_POST['classid']&&$_POST['classarray']){

			if(D($_GET['project'])->where('id='.implode(" or id=",array_filter(explode(",",$_POST['classarray']))))->update('classid="'.$_POST['classid'].'"')){
				echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=1">';
			}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=0">';
			}
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=0">';
		}
	}

    /**
     * 回收站
     * @access public
     * @return template,array
     */
	public function recover($project,$array){
		foreach($array as $v){
		if(!D($project)->where('id="'.$v.'"')->update(array(
			"status"	=> "-1"
		)))return false;
		}
		return true;
	}

    /**
     * 彻底删除
     * @access public
     * @return template,array
     */
	public function thorough($project,$array){
		foreach($array as $v){
			if(!D($project)->where('id="'.$v.'"')->delete())return false;
		}
		return true;
	}

    /**
     * 属性设置
     * @access public
     * @return template,array
     */
	public function attrib($project,$array,$friend){
		foreach($array as $v){
		if(!D($project)->where('id="'.$v.'"')->update(array(
			$friend	=> 1,
			'time'	=> time()
		)))return false;
		}
		return true;
	}

    /**
     * 属性设置
     * @access public
     * @return template,array
     */
	public function attribc($project,$array,$friend){
		foreach($array as $v){
		if(!D($project)->where('id="'.$v.'"')->update(array(
			$friend	=> 0,
			'time'	=> time()
		)))return false;
		}
		return true;
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
	public function batchsave(){

		echo json_encode(M($this->model)->batchsave($_POST));
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function execution(){
		return $this->display('module/execution.html');
	}
}




?>