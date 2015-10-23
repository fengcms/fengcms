<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//	模板基类

defined('TPL_INCLUDE') or die( 'Restricted access'); 



if(is_mobile_request()&&is_dir(ABS_PATH.'/template_mobile/')){
	define('TPL_PATH',ABS_PATH.'/template_mobile/');			//定义模版路径
	define('TPL_CPLE_PATH',CACHE_PATH.'/compile_mobile/');		//定义模版缓存路径
}else{
	define('TPL_PATH',ABS_PATH.'/template/');			//定义模版路径
	define('TPL_CPLE_PATH',CACHE_PATH.'/compile/');		//定义模版缓存路径
}

class template{

    private static $cpl_dir;// 编译目录
    private static $tpl_dir;// 编译目录
    private static $cpl_file_postfix;// 编译文件扩展名

    /**
     * 构造函数
     */
    function __construct($cpl_file_postfix="php"){
        self::$cpl_dir = TPL_CPLE_PATH;							// 默认的编译目录
        self::$tpl_dir = TPL_PATH;								// 默认模板目录
        self::$cpl_file_postfix = $cpl_file_postfix;			// 默认后缀
    }

    /**
     * 模板输出
     */
    public static function tpl($tpl_path){
        $tpl_dir = self::gettpldir();
        if(!empty($tpl_dir)){
            $tpl_path = $tpl_dir.$tpl_path;//.".html";
        }else{
            if(!file_exists($tpl_path)){
				throwexce(sprintf('Can not find the template file:'.$tpl_path.' Make sure the template file has been created.'));
            }
        }
        return self::createfile($tpl_path);
    }

    /**
     * 设置模板目录
     */
    public static function settpldir($dir){
        if(!self::mkdirs($dir)){
				throwexce(sprintf('Can not automatically create a template directory:'.$dir.' Can try to manually create or modify directory permissions.'));
        }
        self::$tpl_dir = $dir;
    }

    /**
     * 取得模板文件存放目录
     */
    public static function gettpldir(){
        return self::$tpl_dir;
    }

    /**
     * 设置编译目录
     */
    public static function setcpldir($dir){
        if(!self::mkdirs($dir)){
			throwexce(sprintf('Can not automatically create a build directory:'.$dir.', Can try to manually create or modify directory permissions'));
        }
        self::$cpl_dir = $dir;
    }

    /**
     * 取得编译文件存放目录
     */
    public static function getcpldir(){
        return self::$cpl_dir;
    }

    /**
     * 取无后缀的文件名
     */
    private static function cetfilepurename($file_name){
        $pfix_pos = strrpos($file_name, '.');
        return substr($file_name, 0, $pfix_pos);
    }

    /**
     * 设置编译文件后缀名
     */
    public static function setcplfilepostfix($postfix){
        if(!preg_match('/^[a-z][a-z0-9]*$/is', $postfix)){
			throwexce(sprintf('Compiled file extension errors, please use English letters and numbers to specify, and must begin with a letter in English'));
        }
        self::$cpl_file_postfix = $postfix;
    }

    /**
     * 取得编译文件后缀名
     */
    public static function getcplfilepostfix(){
        if(empty(self::$cpl_file_postfix)){
			throwexce(sprintf('Compile the file suffix is not specified, please call setcplfilepostfix specify the file extension is recommended to use php as the extension'));
        }
        return self::$cpl_file_postfix;
    }

    /**
     * 按指定路径生成目录
     */
    private static function mkdirs($path){
        if(file_exists($path)){
            return $path;
        }
        $adir = explode('/',$path);
        $dir_list = '';
        foreach($adir as $k=>$v){
            if($v != '.' && $v != ".."){
                $sep = $k==0?'':'/';
                $dir_list .= $sep.$v;
            }else{
                $dir_list .= $v;
            }

            if(!file_exists($dir_list))
            {
                @mkdir($dir_list);
                @chmod($dir_list,0777);
            }
        }
        return $path;
    }

    /**
     * 写文件
     */
    private static function wfile($file_path, $str, $mode='w'){
        $oldmask = @umask(0);
        $fp = @fopen($file_path, $mode);
        @flock($fp, 3);
        if(!$fp){
            return false;
        }else{
            @fwrite($fp,$str);
            @fclose($fp);
            @umask($oldmask);
            return true;
        }
    }

    /**
     * 生成文件
     */
    private static function createfile($tpl_path){
        if(!file_exists($tpl_path)){
				throwexce(sprintf('Template file does not exist!'));
        }
        /**/
        $cpl_dir = self::getcpldir();// 取编译目录
        $tpl_dir = self::gettpldir();// 取模板目录

        $cpl_arr = array();
        $cpl_tmp_path = !empty($tpl_dir)
            && preg_match('~^'.preg_quote($tpl_dir).'(.*)~', $tpl_path, $cpl_arr)
            ?$cpl_dir.$cpl_arr[1]:$cpl_dir."/".$tpl_path;

        $cpl_dir_arr  = explode('/', $cpl_tmp_path);
        $file_name    = array_pop($cpl_dir_arr);// 文件名
        $file_pure_name   = self::cetfilepurename($file_name);
        $cpl_file_postfix = self::getcplfilepostfix();

        // 建目录
        $cpl_file_dir = implode('/',$cpl_dir_arr);
        self::mkdirs($cpl_file_dir);

        $cpl_file = $cpl_file_dir."/".$file_pure_name.".".$cpl_file_postfix;

        // 判断文件是否存在，不存在或过期就创建一个
        if(!file_exists($cpl_file) || (@filemtime($tpl_path) > filemtime($cpl_file))){
			$parsed_str = self::parse($tpl_path);
            self::wfile($cpl_file, $parsed_str, 'w');
        }
        return $cpl_file;
    }

	/**
     * 解析文件
     */
    private static function parse($tpl_file){
        if(!defined("TPL_INCLUDE")){
				throwexce(sprintf('For your security, please call before the procedure to define a template called TPL_INCLUDE constant'),'Template');
        }
        $d = "<?php defined('TPL_INCLUDE') OR exit('Access Denied'); ?>\r\n";
        $s = $d.file_get_contents($tpl_file);
        $s = @preg_replace("/\<\!\-\-\s*\{(.+?)\}\s*\-\-\>/is", "", $s);
        $s = @preg_replace("/\{include\s+(.+?)\}/is", "<?php include Template::Tpl(\\1); ?>", $s);
        $s = @preg_replace("/\{([A-Z][A-Z0-9_]*)\}/s", "<?php if(defined('\\1')){echo \\1;}?>", $s);
		$s = @preg_replace("/\{(\\\$[a-zA-Z0-9_\[\]\'\"\$\x7f-\xff]+)\}/is", "<?php if((\\1)){echo \\1;}?>", $s);
		$s = @preg_replace("/\{\\\$(.+?)\}/is", "<?php if(($\\1)){ $\\1;}?>", $s);
        $s = @preg_replace("/\{([@&\\\$a-zA-Z0-9_]+)\(([ ,\?\.=&%-\:a-zA-Z0-9_\[\]\'\"\\\$\x7f-\xff\(\)]*)\)\}/is", "<?php echo \\1(\\2);?>", $s);
        for($i = 0; $i <= 5; $i++){
            $s = @preg_replace("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\}\s*(.+?)\s*\{\/loop\}[\n\r\t]*/ies",
                "template::strip('<?php if((\\1) && is_array(\\1)) { foreach(\\1 as \\2) { ?>','\\3<?php }} ?>')", $s);
            $s = @preg_replace("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}\s*(.+?)\s*\{\/loop\}[\n\r\t]*/ies",
                "template::strip('<?php if((\\1) && is_array(\\1)) { foreach(\\1 as \\2 => \\3) { ?>','\\4<?php }} ?>')", $s);
            $s = @preg_replace("/[\n\r\t]*\{if\s+(.+?)\}(\s*)(.+?)(\s*)\{\/if\}[\n\r\t]*/ies",
                "template::strip('<?php if(\\1){?>\\2','\\3\\4<?php }?>')", $s);
        }
        $s = @preg_replace("/[\n\r\t]*\{elseif\s+(.+?)\}[\n\r\t]*/ies",
            "template::strip('<?php }elseif(\\1) { ?>','')", $s);
        $s = @preg_replace("/[\n\r\t]*\{else\}[\n\r\t]*/is", "<?php } else { ?>", $s);

        return $s;
    }


    /**
     *  过滤
     */
    private static function strip($expr, $statement){
        $expr = str_replace("\\\"", "\"", @preg_replace("/\<\?\=(\\\$.+?)\?\>/s", "\\1", $expr));
        $statement = str_replace("\\\"", "\"", $statement);
        return $expr.$statement;
    }
}


?>