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
		if($table && $name){
			$array=explode(",",$name);
			if(count($array)>0){
				foreach($array as $v){
					$re=D($table)->where('name="'.$v.'"')->update("hits=hits+1");
				}
				return $re;
			}else{
				return D($table)->where('name="'.$name.'"')->update("hits=hits+1");
			}
		}else{
			return false;
		}
	}

	/**
     * 保存信息
     *
     * @return array
     */
	public function save($array){

		if($array['id']){

			$id=$array['id'];unset($array['id']);

			if(M("field")->findexists($_GET['project'],"attrib")){

				$array['attrib_j']=($array['attrib_j']?1:0);
				$array['attrib_g']=($array['attrib_g']?1:0);
				$array['attrib_t']=($array['attrib_t']?1:0);
				$array['attrib_r']=($array['attrib_r']?1:0);
				$array['attrib_d']=($array['attrib_d']?1:0);
				$array['attrib_h']=($array['attrib_h']?1:0);
			}

			if($array['info']=="" && $this->isfield($_GET['project'],"info")){
				$array['info']=mb_substr(compress_html(mbStrreplace(strip_tags($array['content']))),0,200,"utf8");
			}
			
			$re=D($_GET['project'])->where('id="'.$id.'"')->update($array);

		}else{

			if($array['info']=="" && $this->isfield($_GET['project'],"info")){
				$array['info']=mb_substr(compress_html(mbStrreplace(strip_tags($array['content']))),0,200,"utf8");
			}


			if($array["tags"]!="")$this->hits("tags",$array['tags']);
			if($array["author"]!="")$this->hits("author",$array['author']);
			if($array["inputer"]!="")$this->hits("inputer",$array['inputer']);
			if($array["origin"]!="")$this->hits("origin",$array['origin']);

			$id=D($_GET['project'])->insert($array);

			if(M("field")->findexists($_GET['project'],"html")){
				$html='/content_'.$_GET['project'].'_'.$id.'.html';
				$re=D($_GET['project'])->where('id="'.$id.'"')->update(array(
					'html'	=>	$html
					));
			}else{
			
				$re=$id;

			}

		}

		if($re){
			return array('status' => 'y','id' => ($id)?$id:$re);

		}else{

			return array('status' => 'n','id' => ($id)?$id:$re);
		}
	}

	public function isfield($project,$field){
		if(D("view_field")->where('project="'.$project.'" and  name="'.$field.'"')->getcount()>0){
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
		foreach(explode("\n",$arr['batch']) as $v){

			if($_GET['project']=="tags"){

				$array['name']			=	trim($v);

			}else{

				$c=explode("|",$v);

				if(count($c)<1){

					return array('status' => 'n');

				}else{

					$array['name']		=	$c[0];
					$array['url']		=	trim($c[1]);
				}
			}

			$re=D($_GET['project'])->insert($array);

			if(!$re) return array('status' => 'n');
		}
		 return array('status' => 'y');
	}

}

?>