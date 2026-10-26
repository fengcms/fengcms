<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 首页

class homeController extends Controller{

	public function index(){
		return $this->display(M("web")->information['template'],M("web")->information);
	}

	public function vccore(){
		if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
			header('Allow: POST');
			cms_fail(405,'此操作需要 POST 请求。');
		}
		if(cms_captcha_verify($_POST['param']??null)){
			echo 'y';//json_encode(array('y'));
		}else{
			echo '验证码不正确';//json_encode(array('n'));
		}
	}
}







?>
