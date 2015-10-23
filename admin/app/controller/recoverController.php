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

class recoverController extends Controller{

	private $model;

    function __construct(){
		if($_GET['project']==""){
		$array=M("module")->findone("type=1");
		$_GET['project']=$array['project'];
		}
		$this->model=$_GET['project'];
		
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function index(){
		$where='status="-1"';
		
		if($_GET['title']){
			if($where)$where.=" and ";
			$where.='`'.$_GET['field'].'` like "%'.$_GET['title'].'%"';
		}

		return $this->display('recover/index.html',(D("module")->where("type=1")->getcount()>0)?sqlpage($this->model,'20','*',$where):"");
	}

	public function recoverone(){
		if($_GET['id']){
			if(D($this->model)->where('id="'.$_GET['id'].'"')->update(array(
				"status"	 => 1
			))){
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&operate=execution&type=exc&re=1">';
			}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&operate=execution&type=exc&re=0">';
			}
		}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&operate=execution&type=exc&re=0">';
		}
	}

	public function delete(){
		if($_GET['id']){
			if(D($this->model)->where('id="'.$_GET['id'].'"')->delete()){
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&operate=execution&type=exc&re=1">';
			}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&operate=execution&type=exc&re=0">';
			}
		}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&operate=execution&type=exc&re=0">';	
		}
	}


	public function batch(){
		if(!$_POST['article']){
			echo json_encode(array('status' => 'a'));
		}else{
			if($this->$_POST['method']($_POST['article'],$_POST['parameter'])){
				echo json_encode(array('status' => 'y'));
			}else{
				echo json_encode(array('status' => 'n'));
			}
		}
	}

	public function recovers($array){
		foreach($array as $v){
		if(!D($this->model)->where('id="'.$v.'"')->update(array(
			"status"	=> "1"
		)))return false;
		}
		return true;
	}

	public function thorough($array){
		foreach($array as $v){
			if(!D($this->model)->where('id="'.$v.'"')->delete())return false;
		}
		return true;
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function project($project){
		if($project){
			return $project;
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function execution(){
		return $this->display('recover/execution.html',D($this->model)->where('id="'.$_GET['id'].'"')->getone());
	}
}







?>