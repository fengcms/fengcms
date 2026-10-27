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

class controller{

	public $tpl;
	public $cfile;

    function __construct(){
		$this->cfile=md5($_SERVER ['HTTP_HOST'].$_SERVER['PHP_SELF'].$_SERVER['REQUEST_URI']).".php";
	}

	/**
	 * 模版方法
	 *
	 * @return string
	 */
    public function display($path,$data=""){
		if(!isset($path)){
			return throwexce(sprintf('Template file does not exist!'));
		}else{
			$tpl= new template();
			if(!empty($data)){
				extract($data,EXTR_SKIP);
			}
			// Legacy templates read optional query keys directly. Supply their empty state
			// only during rendering, after routing and authorization have completed.
			foreach(['controller'=>'','project'=>'','classid'=>'0','id'=>'0','tags'=>'',
				'title'=>'','field'=>'','type'=>'','operate'=>'',
				'attrib_j'=>'','attrib_g'=>'','attrib_t'=>'','attrib_r'=>'','attrib_d'=>'','attrib_h'=>''] as $key=>$default){
				if(!array_key_exists($key,$_GET)){$_GET[$key]=$default;}
			}
			$topid=$topid??0;
			$template=$template??'';
			$content_template=$content_template??'';
            // GET controllers have completed routing, authentication and any
            // search-window write by the time rendering begins. Templates may
            // still read the in-memory token after the file lock is released.
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') { cms_session_release(); }
			include template::tpl($path);
		}
	}

	public static function validate(){
		$_vc = new validatecode();		//实例化一个对象
		return $_vc->doimg();		
		//$_SESSION['authnum_session'] = $_vc->getcode();//验证码保存到SESSION中
	}

	/**
	 * 文件缓存
	 *
	 * @return string
	 */
	public function fcache($data,$op=false){
		if(!is_null($op)){
			if($op){
				return cache($this->cfile,$data);
			}else{
				return cache($this->cfile);
			}
		}else{
			return cache($this->cfile,null);
		}
	}

}

?>
