<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 搜索

class searchController extends Controller{

	public function index(){
		$_POST=removexss_array($_POST);
		if($_POST['project']){
			if(!$this->project_exist(lib_replace_end_tag($_POST['project']))){
				echo '<script type="text/javascript">alert("参数错误！");history.go(-1)</script>';
			}
		}
		$_GET=lib_replace_end_tag_array($_GET);
		$_POST=lib_replace_end_tag_array($_POST);
		if(!empty($_POST)){

			$_POST['tags']=strip_tags($_POST['tags']);
			if(URL_TYPE==1){
                echo '<meta http-equiv="refresh" content="0;url='.(($_POST['project'])?'?controller=search&project='.$_POST['project'].'&tags='.$_POST['tags']:'/tags/'.$_POST['tags'].'.html').'">';  
			}else{
				echo '<meta http-equiv="refresh" content="0;url=?controller=search'.(($_POST['project'])?'&project='.$_POST['project'].'&tags='.$_POST['tags']:'&tags='.$_POST['tags']).'">';
			}
		}else{

			if ($_GET['tags'] != '') {
				$encode = mb_detect_encoding ( $_GET['tags'], array ("ASCII", "UTF-8", "GB2312", "GBK", "BIG5" ) );
				if ($encode != "UTF-8") {
					$_GET['tags'] = iconv ( "gb2312", "UTF-8", $_GET['tags'] );
				}

			}

			if($_GET['tags']){
				if($_GET['project']!=""){
					return $this->display($_GET['project'].'_search.html');
				}else{
					return $this->display('search.html');
				}
			}else{
				echo '<script type="text/javascript">alert("请输入关键词！");history.go(-1)</script>';
			}
		}

	}

	public function project_exist($string){
		if(D("module")->where('project="'.$string.'" and status=1')->getcount()>0){
			return $string;
		}else{
			return false;
		}
	}

}

?>