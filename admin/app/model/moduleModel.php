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
	public function hits($table,$name){
		if(!in_array($table,['tags','author','inputer','origin'],true) || !is_string($name) || $name==='')return false;
		$re=false;
		foreach(explode(',', $name) as $value){
			$value=trim($value);
			if($value==='')continue;
			$re=D($table)->where(['name'=>$value])->update('hits=hits+1');
		}
		return $re;
	}

	/**
     * 保存信息
     *
     * @return array
     */
	public function save($array){
		$project=$_GET['project'];
		$rawId=$array['id']??null;
		if($rawId!==null && (!is_string($rawId) || !preg_match('/^[1-9][0-9]{0,9}$/D',$rawId))){cms_fail(400,'记录 ID 不正确。');}
		$id=$rawId===null?null:(int)$rawId;
		$columns=D($project)->getdbfields(DB_PREFIX.$project);
		$physical=array_fill_keys($columns,true);
		$fields=D('view_field')->field('name')->where(['project'=>$project,'status'=>1])->getall();
		$writable=[];
		$hasAttrib=false;
		foreach($fields as $field){
			$name=$field['name'];
			if($name==='attrib'){$hasAttrib=true;continue;}
			if(isset($physical[$name]) && !in_array($name,['id','hits','html','time','password'],true)){$writable[$name]=true;}
		}
		$attributes=['attrib_j','attrib_g','attrib_t','attrib_r','attrib_d','attrib_h'];
		if($hasAttrib){
			foreach($attributes as $name){if(isset($physical[$name])){$writable[$name]=true;}}
		}
		$array=array_intersect_key($array,$writable);
		foreach($array as $name=>$value){if(!is_string($value) && !is_int($value)){cms_fail(400,'字段内容不正确。');}}
		if($project==='tags' && isset($array['name'])){$this->validateTag($array['name']);}
		if($project==='friend' && isset($array['url']) && $array['url']!=='' && cms_safe_url($array['url'])===''){
			cms_fail(400,'链接地址只允许安全的链接。');
		}
		if(isset($array['status']) && !in_array((string)$array['status'],['0','1'],true)){cms_fail(400,'状态不正确。');}
		$array=cms_clean_record($array,$project);
		if($hasAttrib && $id!==null){
			foreach($attributes as $name){if(isset($writable[$name])){$array[$name]=($array[$name]??null)==='1'?1:0;}}
		}
		if(($array['info']??null)==='' && isset($array['content'])){
			$array['info']=mb_substr(compress_html(mbStrreplace(strip_tags($array['content']))),0,200,'utf8');
		}
		if(isset($physical['time']) && array_key_exists('time',$_POST)){$array['time']=time();}
		if($id!==null){
			$re=$array?D($project)->where(['id'=>$id])->update($array):false;
		}else{
			if(isset($physical['hits'])){$array['hits']=0;}
			foreach(['tags','author','inputer','origin'] as $counter){
				if(isset($array[$counter]) && $array[$counter]!==''){$this->hits($counter,$array[$counter]);}
			}
			$id=D($project)->insert($array);
			$re=$id;
			if($id && isset($physical['html'])){
				$re=D($project)->where(['id'=>$id])->update(['html'=>'/content_'.$project.'_'.$id.'.html']);
			}
		}

		if($re){
			return array('status' => 'y','id' => ($id)?$id:$re);

		}else{

			return array('status' => 'n','id' => ($id)?$id:$re);
		}
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
