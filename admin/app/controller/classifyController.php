<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 栏目管理
include "admin.php";
require_once __DIR__.'/../model/contentWrite.php';

class classifyController extends Controller{

	private $model = 'classify';

    /**
     * 栏目列表
     * @access public
     * @return template,array
     */
	public function index(){
		$where=['classid'=>(int)($_GET['id']??0)];
		return $this->display('classify/index.html',array("classify"	=>	M($this->model)->findall($where)));
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function add(){
		return $this->display('classify/add.html');
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function shift(){
		return $this->display('classify/shift.html');
	}

     /**
     * 栏目排序
     * @access public
     * @return template,array
     */
    public function sorts(){
		$where=['classid'=>(int)($_GET['classid']??0)];
        return $this->display("classify/sorts.html",array("classify" => M($this->model)->findall($where)));
    }
    /**
     * 栏目排序执行
     * @access public
     * @return template,array
     */
    public function order(){
        echo json_encode(M($this->model)->order($_POST));
    }
    /**
     * 栏目修改
     * @access public
     * @return template,array
     */
	public function update(){
		return $this->display('classify/update.html',M($this->model)->findone(['id'=>(int)$_GET['id']]));
	}

    /**
     * 栏目频道
     * @access public
     * @return template,array
     */
	public function classify(){
		$where='type="module"';
		$params=[];
		if(!empty($_GET['p'])){
			if (!is_string($_GET['p']) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$_GET['p'])) { cms_fail(400,'模型名称不正确。'); }
			if($where)$where.=' and ';
			$where.='project=?';
			$params[]=$_GET['p'];
		}

			return $this->display('classify/classify.html',[
				'classifyselect'=>M($this->model)->procoption(M($this->model)->classifytree(D('classify')->where($where,$params)->sort('sort,id')->getall()),0,false),
			]);
	}

    /**
     * 栏目类型
     * @access public
     * @return template,array
     */
	public function type(){
		if (!in_array($_GET['t']??null,['module','single','url'],true)) { cms_fail(400,'栏目类型不正确。'); }
		return $this->display('classify/type_'.$_GET['t'].'.html');
	}

    /**
     * 栏目类型
     * @access public
     * @return template,array
     */
	public function tps(){
		if($_GET['t']=="single"){
			return $this->display('classify/type_single.html');
		}else{
			return $this->display('classify/type_classify.html');
		}		
	}
    /**
     * 栏目添加方法
     * @access public
     * @return template,array
     */
	public function genre(){
		if (!in_array($_GET['f']??null,['one','batch'],true)) { cms_fail(400,'栏目方式不正确。'); }
		return $this->display('classify/type_module_'.$_GET['f'].'.html');
	}

    /**
     * 单条栏目删除
     *
     * @return array
     */
	public function delete(){
        $arr=M($this->model)->where(['id'=>(int)$_GET['id']])->getone();
        if (!$arr) { cms_fail(404,'栏目不存在。'); }
        ContentWrite::single(fn()=> ContentWrite::categoryLocked($arr['project'],function() {
        $arr=M($this->model)->where(['id'=>(int)$_GET['id']])->getone();
        if (!$arr) { cms_fail(404,'栏目不存在。'); }

        if(M($this->model)->where(['classid'=>(int)$_GET['id']])->getcount()>0){
            if($arr['type']==='module' || $arr['type']==='single'){
                D($arr['project'])->where(['classid'=>(int)$arr['id']])->delete();
            }
                $re=$this->traversaldelete(M($this->model)->classifytree(M($this->model)->findall(),$_GET['id']))&&D($this->model)->where(['id'=>(int)$_GET['id']])->delete();

            if($re){
                echo '<script type="text/javascript">alert("删除成功！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }

        }else{
            if($arr['type']==='module' || $arr['type']==='single'){
                D($arr['project'])->where(['classid'=>(int)$arr['id']])->delete();
            }
            $re=D($this->model)->where(['id'=>(int)$arr['id']])->delete();

            if($re){
                echo '<script type="text/javascript">alert("删除成功！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }else{
                echo '<script type="text/javascript">alert("删除失败！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }
        }
		}));
	}

    /**
     * 清空栏目
     *
     * @return array
     */
	public function emptys(){

        $arr=M($this->model)->where(['id'=>(int)$_GET['id']])->getone();
        if (!$arr) { cms_fail(404,'栏目不存在。'); }
        ContentWrite::single(fn()=> ContentWrite::categoryLocked($arr['project'],function() {
        $arr=M($this->model)->where(['id'=>(int)$_GET['id']])->getone();
        if (!$arr) { cms_fail(404,'栏目不存在。'); }
        
        $children=M($this->model)->classifytree(M($this->model)->findall(),$arr['id']);
        $re=$this->traversaldelete($children,false);
        if($re && ($arr['type']==='module' || $arr['type']==='single')){
            D($arr['project'])->where(['classid'=>(int)$arr['id']])->delete();
        }
        echo '<script type="text/javascript">alert("'.($re?'清空成功！':'清空失败！').'");</script>';
        echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
		}));
	}

    /**
     * 批量删除
     *
     * @return array
     */
	public function batch(){
        if(!$_POST['classify']){
            echo json_encode(array('status' => 'a'));
        }else{
            if($this->batchdelete($_POST['classify'])){
                echo json_encode(array('status' => 'y'));
            }else{
                echo json_encode(array('status' => 'n'));
            }
        }
	}

    /**
     * 批量删除
     *
     * @return array
     */
    public function batchdelete($array){

        foreach($array as $v){

            $arr=M($this->model)->findone(['id'=>(int)$v]);
            if (!$arr) { return false; }
            $re=ContentWrite::single(fn()=> ContentWrite::categoryLocked($arr['project'],function() use ($v) {
            $arr=M($this->model)->findone(['id'=>(int)$v]);
            if (!$arr) { return false; }

            if($arr['type']==='module' || $arr['type']==='single'){
                D($arr['project'])->where(['classid'=>(int)$arr['id']])->delete();
            }
            $children=M($this->model)->classifytree(M($this->model)->findall(),$arr['id']);
            $re=$this->traversaldelete($children) &&
                D($this->model)->where(['id'=>(int)$arr['id']])->delete();
            return (bool)($re??false);
            }));
            if(!$re){ return false; }
        }
        return true;
    }


    /**
     * 带有下级栏目删除
     *
     * @return array
     */
    private function traversaldelete(array $array,bool $remove=true): bool{
        foreach($array as $v){
            if($v['classid'] && !$this->traversaldelete($v['classid'],$remove)){ return false; }
            if($v['type']==='module' || $v['type']==='single'){
                D($v['project'])->where(['classid'=>(int)$v['id']])->delete();
            }
            if($remove && !D($this->model)->where(['id'=>(int)$v['id']])->delete()){
                return false;
            }
        }
        return true;
    }

    /**
     * 保存信息
     *
     * @return array
     */
	public function save(){
		$genre=$_POST['genre']??'';
		unset($_POST['genre']);
		if($genre=="one" || $genre==""){
			echo json_encode(ContentWrite::single(fn()=>ContentWrite::categoryLocked('classify',
                fn()=>M($this->model)->save($_POST))));
		}elseif($genre=="batch"){
			echo json_encode(ContentWrite::single(fn()=>ContentWrite::categoryLocked('classify',
                fn()=>M($this->model)->batchsave($_POST))));
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function execution(){
		$type=1;
		if(($_GET['type']??null)==='save' && (int)($_GET['id']??0)>0){
			$row=D($this->model)->where(['id'=>(int)$_GET['id']])->getone();
			$type=($row['type']??null)==='module'?0:1;
		}
		return $this->display('classify/execution.html',['type'=>$type]);
	}
}






?>
