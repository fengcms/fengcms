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
require_once __DIR__.'/../model/contentWrite.php';

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
		return M($this->model)->findone(['project'=>$project]);
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

		if(($array['attrib_j']??null)==1){
			if($where)$where.=" and ";
			$where.="attrib_j=1";
		}
		if(($array['attrib_g']??null)==1){
			if($where)$where.=" and ";
			$where.="attrib_g=1";
		}
		if(($array['attrib_t']??null)==1){
			if($where)$where.=" and ";
			$where.="attrib_t=1";
		}
		if(($array['attrib_r']??null)==1){
			if($where)$where.=" and ";
			$where.="attrib_r=1";
		}
		if(($array['attrib_d']??null)==1){
			if($where)$where.=" and ";
			$where.="attrib_d=1";
		}
		if(($array['attrib_h']??null)==1){
			if($where)$where.=" and ";
			$where.="attrib_h=1";
		}
		
		if(!empty($array['classid'])){
			if($where)$where.=" and ";
			$classIds=M('classify')->descendantIds((int)$array['classid']);
			$where.='classid IN ('.implode(',',array_fill(0,count($classIds),'?')).')';
			array_push($params,...$classIds);
		}


		return sqlpage($project,"20","*",$where,"id desc",$params);
	}

    /**
     * 文章修改
     * @access public
     * @return template,array
     */
	public function add(){
		$_GET['classid']=$_GET['classid']??'0';
		$module=M($this->model)->findone(['project'=>$_GET['project']]);
		$columns=D($_GET['project'])->getdbfields(DB_PREFIX.$_GET['project']);
		$module['info']=array_fill_keys($columns,'');
		$module['isEdit']=false;
		$module['createKey']=bin2hex(random_bytes(16));
		return $this->display('module/add.html',$module);
	}

    /**
     * 批量添加
     *
     * @return array
     */
	public function batchadd(){
		return $this->display('module/batchadd.html',M($this->model)->findone(['project'=>$_GET['project']]));
	}

    /**
     * 文章修改
     * @access public
     * @return template,array
     */
	public function update(){
		$_GET['classid']=$_GET['classid']??'0';
		$module=M($this->model)->findone(['project'=>$_GET['project']]);
		$info=D($_GET['project'])->where(['id'=>(int)($_GET['id']??0)])->getone();
		if(!$info){cms_fail(404,'内容不存在。');}
		$module['info']=$info;
		$module['isEdit']=true;
		$module['writeToken']=ContentWrite::editToken($info);
		return $this->display('module/update.html',$module);

	}

    /**
     * 单条删除数据
     * @access public
     * @return template,array
     */
	public function deleteone(){
		if((new ContentWrite($_GET['project']))->change((int)$_GET['id'],'delete')==='changed'){
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
		if((new ContentWrite($_GET['project']))->change((int)$_GET['id'],'recycle')==='changed'){
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
			$action=$method==='recover'?'recycle':($method==='thorough'?'delete':$method);
			echo json_encode((new ContentWrite($_GET['project']))->batch($_POST['record'],$action,$parameter));
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
		M($this->model)->validateCategory($_GET['project'],$classid);
		if ($ids) {
			if((new ContentWrite($_GET['project']))->shift($ids,(int)$classid)){
				echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=1">';
			}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=0">';
			}
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=module&project='.$_GET['project'].'&operate=execution&type=exc&re=0">';
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save(){
        echo json_encode(M($this->model)->save($_POST, $_GET['project'] ?? null));
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
