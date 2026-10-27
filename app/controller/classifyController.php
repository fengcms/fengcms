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
