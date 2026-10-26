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
		$params=[];
		
		if(!empty($array['title'])){
			if (!is_string($array['title']) || strlen($array['title'])>200 ||
				!is_string($array['field']??null) ||
				!D('view_field')->where(['project'=>$project,'name'=>$array['field'],'search'=>1])->getone()) {
				cms_fail(400,'搜索字段不正确。');
			}
			if($where)$where.=" and ";
			$where.=model::identifier($array['field']).' LIKE ?';
			$params[]='%'.$array['title'].'%';
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


		return sqlpage($project,"20","*",$where,"id desc",$params);
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
        $method=$_POST['method']??null;
        if (!in_array($method, ['recover', 'thorough', 'attrib', 'attribc'], true)) { cms_fail(400, '批量操作不正确。'); }
        $parameter=$_POST['parameter']??null;
        if (($method==='attrib' || $method==='attribc') &&
            (!is_string($parameter) || !in_array($parameter,['attrib_j','attrib_g','attrib_t','attrib_r','attrib_d','attrib_h'],true))) {
            cms_fail(400,'文章属性不正确。');
        }
		if(!$_POST['record']){
			echo json_encode(array('status' => 'a'));
		}else{
			if($this->{$method}($_GET['project'],$_POST['record'],$parameter)){
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
		$classid=$_POST['classid'] ?? null;
		$classarray=$_POST['classarray'] ?? null;
		if (!is_string($classid) || !preg_match('/^[0-9]{1,10}$/D',$classid) ||
			!is_string($classarray) || !preg_match('/^[1-9][0-9]{0,9}(?:,[1-9][0-9]{0,9}){0,499}$/D',$classarray)) {
			cms_fail(400, '转移参数不正确。');
		}
		$ids=array_values(array_unique(array_map('intval',explode(',',$classarray))));
		$placeholders=implode(',',array_fill(0,count($ids),'?'));
		if ($ids) {
			if(D($_GET['project'])->where('id IN ('.$placeholders.')',$ids)->update(['classid'=>(int)$classid])){
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
		if(!D($project)->where(['id'=>$v])->update(array(
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
			if(!D($project)->where(['id'=>$v])->delete())return false;
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
		if(!D($project)->where(['id'=>$v])->update(array(
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
		if(!D($project)->where(['id'=>$v])->update(array(
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
