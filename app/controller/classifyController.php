<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 栏目

class classifyController extends Controller{

	private $d_name		=	"classify";
	private $classify	=	array();

	public function __construct(){
		if(array_key_exists('route',$_GET)){
			$route=$_GET['route'];
			if(!is_string($route) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,90}$/D',$route)){
				cms_fail(404,'栏目不存在。');
			}
			$this->classify=$this->categoryForRoute($route);
			if(!$this->classify){
				$separator=strrpos($route,'_');
				$page=$separator===false?'':substr($route,$separator+1);
				if(!preg_match('/^[1-9][0-9]{0,9}$/D',$page)){
					cms_fail(404,'栏目不存在。');
				}
				$this->classify=$this->categoryForRoute(substr($route,0,$separator));
				if(!$this->classify){cms_fail(404,'栏目不存在。');}
				$_GET['page']=$page;
			}
			$this->ensureEnabledModelCategory();
			$_GET['project']=$this->classify['project'];
			$_GET['classify']=$this->classify['enname'];
			$_GET['classid']=(int)$this->classify['id'];
			return;
		}
		$slug=$_GET['classify']??null;
		$rawId=$_GET['classid']??null;
		$project=$_GET['project']??null;
		if(!is_string($slug) || !preg_match('/^[A-Za-z0-9_-]{1,50}$/D',$slug) ||
			!is_string($rawId) || !preg_match('/^[1-9][0-9]{0,9}$/D',$rawId) ||
			!is_string($project) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D',$project)){
			cms_fail(404,'栏目不存在。');
		}
		$_GET['classid']=(int)$rawId;

		$this->classify=D($this->d_name)->where(array(
			'enname'	=>	$slug,
			'id'		=>	(int)$rawId,
			'project'	=>	$project
			))->getone();
		if(!$this->classify){cms_fail(404,'栏目不存在。');}
		$this->ensureEnabledModelCategory();
	}

	private function ensureEnabledModelCategory(): void
	{
		if(($this->classify['type']??'')!=='module'){return;}
		$module=D('module')->where(['project'=>$this->classify['project'],'type'=>1,'status'=>1])->getone();
		if(!$module){cms_fail(404,'内容模型不存在。');}
	}

	private function categoryForRoute(string $route): array
	{
		if(!preg_match('/^(.+)_([0-9]{1,10})$/D',$route,$parts) ||
			(int)$parts[2]<1 || (int)$parts[2]>2147483647){return [];}
		$row=D($this->d_name)->where(['id'=>(int)$parts[2]])->getone();
		return $row && $parts[1]===$row['project'].'_'.$row['enname'] ? $row : [];
	}

	public function index(){

		if($this->classify['type']=="module"){

			if(M($this->d_name)->where('classid="'.$this->classify['id'].'"')->getcount()>0){

				return $this->display($this->classify['channel_template'],is_array($this->classify)?$this->classify:"");

			}else{

				return $this->display($this->classify['classify_template'],is_array($this->classify)?$this->classify:"");

			}
		}elseif($this->classify['type']=="single"){

			$array=D('single')->where(['classid'=>(int)$this->classify['id'],'status'=>1])->getone();

			if($array['id']){
                foreach (cms_richtext_fields('single') as $field) { $array[$field]=cms_html($array[$field] ?? ''); }

			$_GET['classid']=$this->classify['id'];

				return $this->display($array['template'],$array);

			}else{

				return $this->display($this->classify['content_template']);

			}
		}

	}
}







?>
