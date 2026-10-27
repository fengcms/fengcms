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
defined('TPL_INCLUDE') or die( 'Restricted access'); 
require_once __DIR__.'/contentWrite.php';

class moduleModel extends model{

	private $d_name='module';

    /**
     * 管理信息
     *
     * @return array
     */
	public function findone($where=""){
		return D($this->d_name)->where($where)->getone();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function findall($where=""){
		return D($this->d_name)->where($where)->getall();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function findname($where){
		$arr=$this->findone($where);
		return $arr['name'];
	}

	/**
     * 保存信息
     *
     * @return array
     */
	public function save($array, ?string $project = null){
        // The optional fallback keeps existing PHP callers compatible while
        // the controller passes the validated route value explicitly.
        $project ??= $_GET['project'] ?? null;
        if (!is_string($project) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $project)) {
            cms_fail(400, '模型名称不正确。');
        }
        $updateTime = array_key_exists('time', $array);
		$rawId=$array['id']??null;
		if($rawId!==null && (!is_string($rawId) || !preg_match('/^[1-9][0-9]{0,9}$/D',$rawId))){cms_fail(400,'记录 ID 不正确。');}
		$id=$rawId===null?null:(int)$rawId;
		$columns=D($project)->getdbfields(DB_PREFIX.$project);
		$physical=array_fill_keys($columns,true);
		$fields=D('view_field')->field('name,aliases,must,length,type_quote')->where(['project'=>$project,'status'=>1])->getall();
		$writable=[];
		$fieldRules=[];
		$urlFields=['url'=>true];
		$hasAttrib=false;
		foreach($fields as $field){
			$name=$field['name'];
			if($name==='attrib'){$hasAttrib=true;continue;}
			if(isset($physical[$name]) && !in_array($name,['id','hits','html','time','password'],true)){
				$writable[$name]=true;
				$fieldRules[$name]=$field;
				if($field['type_quote']==='text_url.html'){$urlFields[$name]=true;}
			}
		}
		$attributes=['attrib_j','attrib_g','attrib_t','attrib_r','attrib_d','attrib_h'];
		if($hasAttrib){
			foreach($attributes as $name){if(isset($physical[$name])){$writable[$name]=true;}}
		}
		$array=array_intersect_key($array,$writable);
		foreach($array as $name=>$value){if(!is_string($value) && !is_int($value)){cms_fail(400,'字段内容不正确。');}}
		$this->validateSubmittedFields($project,$array,$fieldRules,$id===null);
		if(array_key_exists('classid',$array)){$this->validateCategory($project,$array['classid']);}
		if($project==='tags' && isset($array['name'])){$this->validateTag($array['name']);}
		foreach($urlFields as $urlField=>$_){
			if(isset($array[$urlField]) && $array[$urlField]!=='' && cms_safe_url($array[$urlField])===''){
				cms_fail(400,'链接地址只允许安全的链接。');
			}
		}
		if(isset($array['status']) && !in_array((string)$array['status'],['0','1'],true)){cms_fail(400,'状态不正确。');}
		$array=cms_clean_record($array,$project);
		if($hasAttrib && $id!==null){
			foreach($attributes as $name){if(isset($writable[$name])){$array[$name]=($array[$name]??null)==='1'?1:0;}}
		}
		if(($array['info']??null)==='' && isset($array['content'])){
			$array['info']=mb_substr(compress_html(mbStrreplace(strip_tags($array['content']))),0,200,'utf8');
		}
		$createHashValues=$array;
		ksort($createHashValues);
		$createHash=hash('sha256',json_encode($createHashValues,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
		if(isset($physical['time']) && $updateTime){$array['time']=time();}
		$writer=new ContentWrite($project);
		if($id!==null){
			return $writer->edit($id,$array,is_string($_POST['_write_token']??null)?$_POST['_write_token']:'');
		}
		if(isset($physical['hits'])){$array['hits']=0;}
		return $writer->create($array,is_string($_POST['_create_key']??null)?$_POST['_create_key']:'',
			(int)($_SESSION['manage_id']??0),$createHash);
	}

	/** An absent edit field keeps its old value; a supplied value follows its field contract. */
	private function validateSubmittedFields(string $project,array $values,array $rules,bool $creating): void
	{
		$columns=D($project)->excsql('SELECT COLUMN_NAME,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
			[DB_NAME,DB_PREFIX.$project],true);
		$limits=[];
		foreach($columns as $column){$limits[$column['COLUMN_NAME']]=$column['CHARACTER_MAXIMUM_LENGTH'];}
		foreach($rules as $name=>$rule){
			if(!array_key_exists($name,$values)){
				if($creating && (int)$rule['must']===1){cms_fail(400,($rule['aliases']?:$name).'不能为空。');}
				continue;
			}
			$value=(string)$values[$name];
			$label=(string)($rule['aliases']?:$name);
			if((int)$rule['must']===1 && trim($value)===''){cms_fail(400,$label.'不能为空。');}
			if($value==='' && (int)$rule['must']!==1){continue;}
			if($rule['type_quote']==='text_n.html' && !preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D',$value)){
				cms_fail(400,$label.'必须是数字。');
			}
			// Rich text, textareas and generated values use their database limits, not Validform's input length hint.
			if(in_array($rule['type_quote'],['title.html','text_n.html','text_w.html','text_s.html','text_z.html','text_p.html','text_m.html','text_e.html','text_url.html'],true) &&
				is_string($rule['length']) && preg_match('/^([0-9]+)-([0-9]+)$/D',$rule['length'],$range) &&
				(mb_strlen($value,'UTF-8')<(int)$range[1] || mb_strlen($value,'UTF-8')>(int)$range[2])){
				cms_fail(400,$label.'长度不正确。');
			}
			if(isset($limits[$name]) && $limits[$name]!==null && mb_strlen($value,'UTF-8')>(int)$limits[$name]){
				cms_fail(400,$label.'超过字段长度。');
			}
		}
	}

	/** Zero remains valid for legacy uncategorized content. */
	public function validateCategory(string $project,string|int $classid): void
	{
		$value=(string)$classid;
		if(!preg_match('/^[0-9]{1,10}$/D',$value) || (int)$value>2147483647){cms_fail(400,'栏目编号不正确。');}
		if((int)$value===0){return;}
		$category=D('classify')->where(['id'=>(int)$value,'project'=>$project,
			'type'=>$project==='single'?'single':'module'])->getone();
		if(!$category || $category['project']!==$project){cms_fail(400,'栏目不属于当前模型。');}
	}

	public function isfield($project,$field){
		if(D("view_field")->where(['project'=>$project,'name'=>$field])->getcount()>0){
			return true;
		}else{
			return false;
		}
	}

	/**
     * 保存信息
     *
     * @return array
     */
	public function batchsave($arr){
		$batch=$arr['batch']??null;
		if(!is_string($batch) || $batch===''){cms_fail(400,'批量内容不正确。');}
		$rows=[];
		foreach(explode("\n",$batch) as $v){
			if(trim($v)===''){continue;}
			$array=[];

			if($_GET['project']=="tags"){

				$array['name']			=	trim($v);
				$this->validateTag($array['name']);

			}else{

				$c=explode("|",$v,2);
				if(trim($c[0])===''){cms_fail(400,'批量内容不正确。');}
				$array['name']		=	trim($c[0]);
				$array['url']		=	trim($c[1]??'');
				if($array['url']!=='' && cms_safe_url($array['url'])===''){
					cms_fail(400,'链接地址只允许安全的链接。');
				}
			}
			$rows[]=$array;
		}
		if(!$rows){cms_fail(400,'批量内容不正确。');}
		foreach($rows as $array){
			$re=D($_GET['project'])->insert($array);

			if(!$re) return array('status' => 'n');
		}
		 return array('status' => 'y');
	}

	private function validateTag(string $name): void{
		if($name==='' || mb_strlen($name,'UTF-8')>50 || preg_match('/[<>\x00-\x1f\x7f]/',$name)){
			cms_fail(400,'标签名称不正确。');
		}
	}

}

?>
