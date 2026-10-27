<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 内容
require_once dirname(__DIR__).'/contentRequest.php';

class contentController extends Controller{

	private $content;


	public function __construct(){
		try {
			$request=CmsContentRequest::fromInput($_GET);
		} catch (InvalidArgumentException $error) {
			cms_fail(400,$error->getMessage());
		}
		if($request->project!=='single' &&
			!D('module')->where(['project'=>$request->project,'type'=>1,'status'=>1])->getone()){
			cms_fail(404,'内容模型不存在。');
		}
		$this->content=M('module')->content($request->project,$request->id);
	}

	public function index(){
		$hadClassid=array_key_exists('classid',$_GET);
		$originalClassid=$_GET['classid']??null;
		$_GET['classid']=$this->content['classid'];
		try {
			return $this->display($this->content['template'],$this->content);
		} finally {
			if($hadClassid){$_GET['classid']=$originalClassid;}
			else{unset($_GET['classid']);}
		}
	}
}







?>
