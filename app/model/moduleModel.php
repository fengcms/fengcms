<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 首页模型

class moduleModel extends model{

	private $d_name		 = "module";

	private $op			 = array();
/*
	public function __construct(){
		unset($this->options);
		parent::__construct($this->d_name);
	}
*/	
	/*
	栏目模块
	*/
	public function module(){
		return D($table)->where("type=1 and status=1")->findall();
	}

	/*
	万能标签解析
	*/
	private function tagsresolve($string){

		$str=substr(substr($string,2),0,-1);

		switch(substr($string,0,1)){


		case "w":

		$exp=explode(',',$str);

			if($exp[1]){

			  return $this->op['where']=$exp[0].$this->whereclass($exp[1]);
			  
			}elseif($str){

			  return $this->op['where']=$str;

			}

		  break;

		case "n":

			  return $this->op['limit']=$str;

		  break;

		case "f":
			if($str){

				return $this->op['field']=$str;

			}

		  break;

		case "s":
		
		  $exp=explode(',',$str);
		  if($exp[1]==1){

			return $this->op['sort']=array(
				'f'	=>	$exp[0],
				's'	=>	'order',
				'p'	=>	'desc',
				);

		  }elseif($exp[1]==2){

			return $this->op['sort']=array(
				'f'	=>	$exp[0],
				's'	=>	'order',
				'p'	=>	'asc',
				);
		  }

		  break;

		default:
		  return $this->op['limit']=10;
		}
	}

	/*
	栏目模块
	*/
	public function l($table,$func=""){

		$array=explode(";",$func);

		if(count($array)>1){
			foreach($array as $v){
				$this->tagsresolve($v);
			}
		}else{
			$this->tagsresolve($func);
		}
		$field=$this->op['field']?$this->op['field']:'*';
		$where=$this->op['where']?$this->op['where']:'id';
		$sortf=$this->op['sort']['f']?$this->op['sort']['f']:'id';
		$sorts=$this->op['sort']['s']?$this->op['sort']['s']:'order';
		$sortp=$this->op['sort']['p']?$this->op['sort']['p']:'desc';
		$limit=$this->op['limit']?$this->op['limit']:10;

		return D($table)->field($field)->where($where)->sort($sortf,$sorts,$sortp)->limit($limit)->getall();
		//return $this->getall();
	}

	/*
	栏目图片属性
	*/
	public function pic($table,$classid,$num=3,$field=""){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,img,html")->where('img!=""'.$where)->sort("id","order","desc")->limit($num)->getall();
	}
	/*
	栏目最新记录
	*/
	public function n($table,$classid,$num=10,$field=""){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where('status="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
	}

	/*
	栏目头条属性
	*/
	public function t($table,$classid,$num=1,$field=""){
		if($this->attrib($table,"attrib")){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where('status="1" and attrib_t="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
		}
	}

	/*
	栏目推荐属性
	*/
	public function j($table,$classid,$num=10,$field=""){		
		if($this->attrib($table,"attrib")){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where('status="1" and attrib_j="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
		}
	}

	/*
	栏目热门属性
	*/
	public function r($table,$classid,$num=10,$field=""){
		if($this->attrib($table,"attrib")){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where('status="1" and attrib_r="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
		}
	}

	/*
	栏目幻灯属性
	*/
	public function h($table,$classid,$num=5,$field=""){
		if($this->attrib($table,"attrib")){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,img,html")->where('status="1" and attrib_h="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
		}
	}

	/*
	查询关联栏目
	*/
	public function whereclass($classid){
		$classarray=array_filter(explode(",",M("classify")->children($classid)));
		if(!empty($classarray)){
			return ' and ('.implode(" or ",$classarray).')';
		}
	}

	/*
	搜索函数
	*/
	public function search($arrays,$field="",$num="20"){

		$arrays['project']=preg_replace("/(%7E|%60|%21|%40|%23|%24|%25|%5E|%26|%27|%2A|%28|%29|%2B|%7C|%5C|%3D|\-|_|%5B|%5D|%7D|%7B|%3B|%22|%3A|%3F|%3E|%3C|%2C|\.|%2F|%A3%BF|%A1%B7|%A1%B6|%A1%A2|%A1%A3|%A3%AC|%7D|%A1%B0|%A3%BA|%A3%BB|%A1%AE|%A1%AF|%A1%B1|%A3%FC|%A3%BD|%A1%AA|%A3%A9|%A3%A8|%A1%AD|%A3%A4|%A1%A4|%A3%A1|%E3%80%82|%EF%BC%81|%EF%BC%8C|%EF%BC%9B|%EF%BC%9F|%EF%BC%9A|%E3%80%81|%E2%80%A6%E2%80%A6|%E2%80%9D|%E2%80%9C|%E2%80%98|%E2%80%99)+/",'',$arrays['project']);

		if($arrays['project']){

			$sql='select * from `'.DB_PREFIX.$arrays['project'].'` where title like "%'.$arrays['tags'].'%" or tags like "%'.$arrays['tags'].'%"';

			return arraypage(D($this->d_name)->excsql($sql.' order by id desc'),$num);

		}else{
			$arr=D($this->d_name)->field("project")->where("type=1&&search=1")->getall();
			if(count($arr)>1)$union="union";
			foreach($arr as $k => $v){
				if($this->attrib($v['project'],'tags')){
					$array[]='select '.$this->fieldhandle($field).'id,title,html,time from `'.DB_PREFIX.$v['project'].'` where title like "%'.$arrays['tags'].'%" or tags like "%'.$arrays['tags'].'%" and status=1';
				}else{
					$array[]='select '.$this->fieldhandle($field).'id,title,html,time from `'.DB_PREFIX.$v['project'].'` where title like "%'.$arrays['tags'].'%" and status=1';
				}
			}
			return arraypage(D($this->d_name)->excsql("select  * from (".implode(" union ",$array).") h order by time desc"),$num);
		}
	}

	/*
	记录分页
	*/
	public function page($table,$classid,$num=20){
		$where=$this->whereclass($classid);
		return sqlpage($table,$num,'*','status=1 '.$where,'id desc');
	}

	/*
	内容
	*/
	public function content($table,$id){

		$arr=D($table)->where('id="'.$id.'"')->getone();
		
		if($table!="single"){

			if($this->attrib($table,"hits")){

				D($table)->where('id="'.$id.'"')->update("hits=hits+1");
			}
			
			$arr['classname']=M("classify")->classname($arr['classid']);

			$arr['origin']=$this->aurl('origin',$arr['origin']);

			$arr['author']=$this->aurl('author',$arr['author']);

			$arr['inputer']=$this->aurl('inputer',$arr['inputer']);

			$arr['tags']=$this->atags($arr['tags']);
		}
		return $arr;
		
	}

	private function aurl($table,$string){
		$aurl=D($table)->where('name="'.$string.'"')->getone();
		if($aurl['url']){
			return '<a href="'.$aurl['url'].'" target="_blank">'.$aurl['name'].'</a>';
		}else{
			return $string;
		}
	}

	private function atags($string){
		$arr=explode(",",$string);
		foreach($arr as $v){
			if(D("tags")->where('name="'.$v.'"')->getcount()>0){
				$atags=D("tags")->where('name="'.$v.'"')->getone();
				$array[]='<a href="/?controller=search&tags='.$atags['name'].'" target="_blank">'.$atags['name'].'</a>';
			}else{
				$array[]=$v;
			}
		}
		return implode(",",$array);
	}

	/*
	整站幻灯属性
	*/
	public function wh($num=5,$field=""){
		$arr=D($this->d_name)->field("project")->where("type=1")->getall();
		if(count($arr)>1)$union="union";
		foreach($arr as $k => $v){
			if($this->attrib($v['project'],"attrib") && $this->attrib($v['project'],"img")){
				$array[]='select '.$this->fieldhandle($field).'id,title,img,html from `'.DB_PREFIX.$v['project'].'` where img!="" and attrib_h=1 and status=1';
			}
		}
		if(is_array($array)){
			return D($this->d_name)->excsql("select * from (".implode(" union ",$array).") h order by id desc limit ".$num);
		}
	}

	/*
	整站头条
	*/
	public function wt($num=1,$field=""){

		$arr=D($this->d_name)->field("project")->where("type=1")->getall();

		if(count($arr)>1)$union="union";

		foreach($arr as $k => $v){
			if($this->attrib($v['project'],"title") && $this->attrib($v['project'],"attrib")){
				$array[]='select '.$this->fieldhandle($field).'id,title,info,html from `'.DB_PREFIX.$v['project'].'` where attrib_t=1 and status=1';
			}
		}
		if(is_array($array)){
			return D($this->d_name)->excsql("select  * from (".implode(" union ",$array).") t order by id desc limit ".$num);
		}
	}

	/*
	整站最新
	*/
	public function wn($num=15,$field=""){

		$arr=D($this->d_name)->field("project")->where("type=1")->getall();

		if(count($arr)>1)$union="union";

		foreach($arr as $k => $v){
			if($this->attrib($v['project'],"title") && $this->attrib($v['project'],"attrib")){
				$array[]='select '.$this->fieldhandle($field).'id,title,html from `'.DB_PREFIX.$v['project'].'` where status=1';
			}
		}
		if(is_array($array)){
			return D($this->d_name)->excsql("select  * from (".implode(" union ",$array).") n order by id desc limit ".$num);
		}
	}

	/*
	整站最新图片
	*/
	public function wpic($num=3,$field=""){

		$arr=D($this->d_name)->field("project")->where("type=1")->getall();

		if(count($arr)>1)$union="union";

		foreach($arr as $k => $v){

			if($this->attrib($v['project'],"title") && $this->attrib($v['project'],"img")){
				$array[]='select '.$this->fieldhandle($field).'id,title,img,html from `'.DB_PREFIX.$v['project'].'` where img!="" and status=1';
			}
		}
		if(is_array($array)){
			return D($this->d_name)->excsql("select  * from (".implode(" union ",$array).") n order by id desc limit ".$num);
		}
	}

	/*
	相关文章
	*/
	public function related($table,$string,$num=10,$field=""){

		$arr=explode(",",$string);
		
		if(is_array($arr)){

			foreach($arr as $v){
				$array[]='title like "%'.$v.'%" or tags like "%'.$v.'%"';
			}
			
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where(implode(" or ",$array))->limit($num)->getall();
		}
	}

	/*
	位置
	*/
	public function location($classid,$str="&gt;&gt;"){
		$array=explode(',',M("classify")->wz($classid,$str));
		return array_reverse(array_unique(array_filter($array)));
	}

	/*
	网站标题
	*/
	public function title($classid,$str="-"){
		$array=explode(',',M("classify")->title($classid,$str));
		return array_unique(array_filter($array));
	}

	private function attrib($project,$attrib){
		
		if(D("view_field")->where('project="'.$project.'" and name="'.$attrib.'"')->getcount()>0){

			switch($attrib){

			case "attrib":

				return "attrib_j,attrib_g,attrib_t,attrib_r,attrib_d,attrib_h";

			  break;

			default:

			  return $attrib;
			}
		}else{

			return false;
		}
	}

	private function fieldhandle($field){
		if($field){
			return implode(",",array_filter(explode(",",$field))).",";
		}
	}
}







?>