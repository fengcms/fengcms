<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 请确保 php.ini 配置文件中 output_buffering 值为 On 或者 4096，为 Off 则会导致下载文件乱码。

class downController extends Controller{

	public function index(){

		$_GET['file']=addslashes(base64_decode($_GET['file']));
		$_GET['file']=str_replace("..","",$_GET['file']);
		$exp=explode("/",$_GET['file']);
		if($exp[1]=="upload"){
			if(file_exists(ROOT_PATH.$_GET['file'])){			
				header("Content-Type: application/force-download");
				header("Content-Disposition: attachment; filename=".basename($_GET['file'])); 
				readfile(ROOT_PATH.$_GET['file']);
			}else{
				echo '<script type="text/javascript">alert("您要下载的文件不存在！");history.back();</script>';
			}
		}else{
				echo '<script type="text/javascript">alert("您要下载的文件不存在！");history.back();</script>';
		}
	}
}
?>