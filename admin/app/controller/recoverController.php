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
		$array=M("module")->findone("type=1 AND recover=1 AND status=1");
		if (!$array) { cms_fail(404, '没有可用的回收站。'); }
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
		$params=[];
		
		if(!empty($_GET['title'])){
			if (!is_string($_GET['title']) || strlen($_GET['title'])>200 ||
				!is_string($_GET['field']??null) ||
				!D('view_field')->where(['project'=>$this->model,'name'=>$_GET['field'],'search'=>1])->getone()) {
				cms_fail(400,'搜索字段不正确。');
			}
			if($where)$where.=" and ";
			$where.=model::identifier($_GET['field']).' LIKE ?';
			$params[]='%'.$_GET['title'].'%';
		}

		return $this->display('recover/index.html',(D("module")->where("type=1 AND recover=1 AND status=1")->getcount()>0)?sqlpage($this->model,'20','*',$where,'id desc',$params):"");
	}

	public function recoverone(){
		if($_GET['id']){
			if(D($this->model)->where(['id'=>$_GET['id'],'status'=>-1])->update(array(
				"status"	 => 1
			))){
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&project='.$this->model.'&operate=execution&type=exc&re=1">';
			}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&project='.$this->model.'&operate=execution&type=exc&re=0">';
			}
		}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&project='.$this->model.'&operate=execution&type=exc&re=0">';
		}
	}

	public function delete(){
		if($_GET['id']){
			if(D($this->model)->where(['id'=>$_GET['id'],'status'=>-1])->delete()){
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&project='.$this->model.'&operate=execution&type=exc&re=1">';
			}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&project='.$this->model.'&operate=execution&type=exc&re=0">';
			}
		}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=recover&project='.$this->model.'&operate=execution&type=exc&re=0">';
		}
	}


    public function batch(){
        $method=$_POST['method']??'';
        if(!is_string($method) || !in_array($method,['recovers','thorough'],true)){cms_fail(400,'批量操作不正确。');}
        $ids=$this->recordIds($_POST['article']??[]);
        $status=$ids ? ($this->$method($ids)?'y':'n') : 'a';
        echo json_encode(['status'=>$status,'project'=>$this->model]);
    }

    private function recordIds($values): array{
        if(!is_array($values) || count($values)>1000){cms_fail(400,'回收站记录参数不正确。');}
        foreach($values as $id){
            if(!is_string($id) || !preg_match('/^[0-9]{1,10}$/D',$id) || (int)$id<1){cms_fail(400,'回收站记录参数不正确。');}
        }
        return array_values(array_unique($values));
    }

    public function recovers($ids){
        foreach($ids as $id){
            if(!D($this->model)->where(['id'=>$id,'status'=>-1])->update(['status'=>1])){return false;}
        }
        return true;
    }

    public function thorough($ids){
        foreach($ids as $id){
            if(!D($this->model)->where(['id'=>$id,'status'=>-1])->delete()){return false;}
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
		return $this->display('recover/execution.html');
	}
}







?>
