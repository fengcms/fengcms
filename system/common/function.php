<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//	公用函数

defined('TPL_INCLUDE') or die( 'Restricted access'); 

//--------------------------------------------------------
//	系统函数
//--------------------------------------------------------
	/**
	* 文件载入
	*
	* @access public
	* @param  string $name	文件名称
	* @param  array  $ext	文件后缀
    * @return bool
	*/
	function import($name, $ext = '.php') {
		static $_loads = array();
		$path = substr($name, 0, 4) == 'root' ? ROOT_PATH : SYSTEM_PATH;

		$name = str_replace('root.', '', $name);
		$name = str_replace('.', '/', $name);
		$file = $path . $name . $ext;


		if(isset($_loads[$file])) {       //如果已经载入过直接返回
			return true;
		}
		if(strpos($file, '*') > 1){       //如果有*号存在, 代表载入指定目录下的所有

			$files = glob($file);
			$len   = count($files);

			for($i = 0; $i < $len; $i++) {
				if(file_exists($files[$i])) {
					include_once($files[$i]);
					$_loads[$file] = true;
				}
			}
			return true;
		}elseif(file_exists($file)) {
				include_once($file);
				$_loads[$file] = true;
				return true;
			}
		return false;
	}

	/**
	 * 模型方法
	 *
	 * @param	string $path   文件夹路径
	 * @param	int    $mode   权限
	 * @return	bool
	 */
	 function M($model_name){

		 $default_model='Model';

		 $definition_model=strtolower($model_name).$default_model; 

		 $model_file=MODEL_PATH.$definition_model.'.php';

		 if(file_exists($model_file)){

			include_once $model_file;

			if(class_exists($definition_model)){

				return new $definition_model($model_name);

			}else{
				throwexce(sprintf('%s Model does not exist!', $definition_model));
			}

		}else{
				throwexce(sprintf('%s Model File does not exist!', $definition_model));
		}
	 }

	/**
	 * 库方法
	 *
	 * @param	string $path   文件夹路径
	 * @param	int    $mode   权限
	 * @return	bool
	 */
	 function D($model_name,$prefix=""){

		$model='model';

		if(class_exists($model)){

			return new $model($model_name,$prefix);

		}else{
			throwexce(sprintf('Model does not exist!'));
		}
	 }

//--------------------------------------------------------
//	文件目录函数
//--------------------------------------------------------
	/**
	 * 批量创建目录
	 *
 	 * @access  public
	 * @param	string $path   文件夹路径
	 * @param	int    $mode   权限
	 * @return	bool
	 */
	function mkdirs($path, $mode = 0775){
		if (!is_dir($path)) {
			mkdirs(dirname($path), $mode);
			$error_level = error_reporting(0);
			$result      = mkdir($path, $mode);
			error_reporting($error_level);
			return $result;
		}
		return true;
	}

	/**
	 * 删除文件夹
	 *
 	 * @access  public
	 * @param	string $path		要删除的文件夹路径
	 * @return	bool
	 */
	function rmdirs($path){
		$error_level = error_reporting(0);
		if ($dh = opendir($path)) {
			while (false !== ($file=readdir($dh))) {
				if ($file != '.' && $file != '..') {
					$file_path = $path.'/'.$file;
					is_dir($file_path) ? rmdirs($file_path) : unlink($file_path);
				}
			}
			closedir($dh);
		}
		$result = rmdir($path);
		error_reporting($error_level);
		return $result;
	}

	/**
	 * 读取目录列表
	 *
 	 * @access  public
	 * @return	bool
	 */
	function getDir($dir) {
		$dirArray[]=NULL;
		if (false != ($handle = opendir ( $dir ))) {
			$i=0;
			while ( false !== ($file = readdir ( $handle )) ) {
				//去掉"“.”、“..”以及带“.xxx”后缀的文件

				if ($file != "." && $file != ".."&&!strpos($file,".")) {
					$dirArray[$i]=$file;
					$i++;
				}
			}
			//关闭句柄

			closedir ( $handle );
		}
		return $dirArray;
	}

	/**
	 * 读取文件列表
	 *
 	 * @access  public
	 * @return	bool
	 */
	function getFile($dir) { 
		 $fileArray[]=NULL; 
		 if (false != ($handle = opendir ( $dir ))) { 
			 $i=0; 
			 while( false !== ($file = readdir ( $handle )) ) { 


				 //去掉"“.”、“..”以及带“.xxx”后缀的文件 
				 if ($file != "." && $file != ".."&&strpos($file,".")) { 
					 $fileArray[$i]=$file; 
					 if($i==100){ 
						 break; 
					 } 
					 $i++; 
				 } 
			 } 
			 //关闭句柄 
			 closedir ( $handle ); 
		 } 

		 return $fileArray; 

	 } 

	/**
	 * 写入文件
	 *
 	 * @access  public
	 * @param	string $files		文件名
	 * @return	bool
	 */
	function fileWrite($content,$files,$path){
		mkdirs($path);
		$fp = fopen($path.'/'.$files, 'a+');
		$re=fputs($fp, $content);
		fclose($fp);
		if($re){
			return true;
		}else{
			return false;
		}
	}

	 /**
	 * 读取或设置缓存
	 *
	 * @access  public
	 * @param   string  $name   缓存名称
	 * @param   mixed   $value  缓存内容, null删除缓存
	 * @param   string  $path   缓存路径
	 * @return  mixed
	 */
	 function cache($name, $value ="" , $path ="" ){
	//     return false;   //调试阶段, 不进行缓存
	 $path = empty($path) ? CACHE_PATH ."data/" : $path;
	 $file = $path . $name . ".php";
		 if (empty($value)) {
			 //缓存不存在
			 if (!is_file($file)) {
				 return false;
			 }
			 // 删除缓存
			 if (is_null($value)) {
				 unlink($file);
				 return true;
			 }
			 $data = include $file;
			 return $data;
		 }
		 $value = var_export($value, true);
		 $value = "<?php defined('TPL_INCLUDE') or exit('Access Denied'); return {$value}; ?>";
		 return file_put_contents($file, $value);
	}

//--------------------------------------------------------
//	XML函数
//--------------------------------------------------------
	/**
	* 数据转XML
	*
	* @access  public
	* @param   string  $array  数组
	* @param   mixed   $dom	   dom定义，编码
	* @param   string  $item   item定义
	* @return  mixed
	*/
	function arrtoxml($array,$dom=0,$item=0){
		if (!$dom){
			$dom = new DOMDocument("1.0","utf-8");
		}

		if(!$item){
			$item = $dom->createElement("root"); 
			$dom->appendChild($item);
		}
		foreach ($array as $key=>$val){
			$itemx = $dom->createElement(is_string($key)?$key:"item");
			$item->appendChild($itemx);
			if (!is_array($val)){
				$text = $dom->createTextNode($val);
				$itemx->appendChild($text);
				
			}else {
				arrtoxml($val,$dom,$itemx);
			}
		}
		return $dom->saveXML();
	}

//--------------------------------------------------------
//	数组函数
//--------------------------------------------------------
	/**
	* 二维数组转一维数组
	*
	* @access  public
	* @param   string  $array  数组
	* @return  array
	*/
	function array_multi2single($array){ 
		static $result_array=array(); 
		foreach($array as $value){ 
		if(is_array($value)){ 
			array_multi2single($value); 
		} 
		else 
			$result_array[]=$value; 
		} 
		return $result_array; 
	}

//--------------------------------------------------------
//	URL函数
//--------------------------------------------------------

	/**
	* 读取url数据
	*
	* @access  public
	* @param  string $url		链接地址
	* @param  array  $date		数据
	*/
	function sock_post($url, $data='') {
	  $url = parse_url($url);
	  $url['scheme'] || $url['scheme'] = 'http';
	  $url['host'] || $url['host'] = $_SERVER['HTTP_HOST'];
	  $url['path'][0] != '/' && $url['path'] = '/'.$url['path'];

	  $query = $data;
	  if(is_array($data)) $query = http_build_query($data);

	  $fp = @fsockopen($url['host'], $url['port'] ? $url['port'] : 80);
	  if (!$fp) return "Failed to open socket to $url[host]";

	  fputs($fp, sprintf("POST %s%s%s HTTP/1.0\n", $url['path'], $url['query'] ? "?" : "", $url['query']));
	  fputs($fp, "Host: $url[host]\n");
	  fputs($fp, "Content-type: application/x-www-form-urlencoded\n");
	  fputs($fp, "Content-length: " . strlen($query) . "\n");
	  fputs($fp, "Connection: close\n\n");

	  fputs($fp, "$query\n");

	  $line = fgets($fp,1024);
	  if (!eregi("^HTTP/1\.. 200", $line)) return;

	  $results = ""; $inheader = 1;
	  while(!feof($fp)) {
		$line = fgets($fp,1024);
		if ($inheader && ($line == "\n" || $line == "\r\n")) {
		  $inheader = 0;
		}elseif (!$inheader) {
		  $results .= $line;
		}
	  }
	  fclose($fp);

	  return $results;
	}


	function url($url){
		if(URL_TYPE=='1'){
			return $url;
		}elseif(URL_TYPE=='0'){
			if(substr($url,0,4)=="http"){
				return $url;
			}else{
				$u=explode('.',$url);
				$url=explode('_',$u[0]);

				if(substr($url[0],1)=='content'){
					return "?controller=content&project=".$url[1]."&id=".$url[2];
				}else{
					return "?controller=classify&project=".substr($url[0],1)."&classify=".$url[1]."&classid=".$url[2];
				}
			}
		}
	}

	function curl($string,$func="",$id=""){
		if(URL_TYPE=='1'){
			if($func){
			return $string.'_'.$func.'.html';
			}elseif($id){
			return $string.'_'.$func.'_'.$id.'.html';
			}else{
			return $string.'.html';
			}
		}elseif(URL_TYPE=='0'){
			if($func){
				return '/?controller='.$string.'&operate='.$func;
			}elseif($id){
			return '/?controller='.$string.'&operate='.$func.'&id='.$id;
			}else{
				return '/?controller='.$string;
			}
		}
	}

	function search($string){
		if(URL_TYPE=='1'){
			return '/tags/'.urlencode($string).'.html';
		}elseif(URL_TYPE=='0'){
			return '/?controller=search&tags='.$string;
		}
	}

	/* 字体转换 
	    $content        内容 
	    $to_encoding    目标编码，默认为UTF-8 
	    $from_encoding  源编码，默认为GBK 
	*/  
	function mbStrreplace($content,$to_encoding="UTF-8",$from_encoding="UTF-8") {  
	    $content=mb_convert_encoding($content,$to_encoding,$from_encoding);  
	    $str=mb_convert_encoding("　",$to_encoding,$from_encoding);  
	    $content=mb_eregi_replace($str,"",$content);  
	    $content=mb_convert_encoding($content,$from_encoding,$to_encoding);  
	    $content=trim($content);
	    return $content;  
	}

	/**
	* 压缩html : 清除换行符,清除制表符,去掉注释标记
	* @param $string
	* @return 压缩后的$string
	* from: www.jbxue.com
	* */
	function compress_html($string) {
		$string = str_replace("\r\n", '', $string); //清除换行符
		$string = str_replace("\n", '', $string); //清除换行符
		$string = str_replace("\t", '', $string); //清除制表符
		$pattern = array (
						"/> *([^ ]*) *</", //去掉注释标记
						"/[\s]+/",
						"/<!--[^!]*-->/",
						"/\" /",
						"/ \"/",
						"'/\*[^*]*\*/'"
					);
		$replace = array (
						">\\1<",
						" ",
						"",
						"\"",
						"\"",
						""
					);
		return preg_replace($pattern, $replace, $string);
	}

	/**
	* 加密解密
	* @param $string 	字符串
	* @param $operation 方式E/D
	* @param $key 		密钥
	* @return $string
	**/
	function encrypt($string,$operation,$key='123456789'){
        $key=md5($key);
        $key_length=strlen($key);
        $string=$operation=='D'?base64_decode($string):substr(md5($string.$key),0,8).$string;
        $string_length=strlen($string);
        $rndkey=$box=array();
        $result='';
        for($i=0;$i<=255;$i++){
            $rndkey[$i]=ord($key[$i%$key_length]);
            $box[$i]=$i;
        }
        for($j=$i=0;$i<256;$i++){
            $j=($j+$box[$i]+$rndkey[$i])%256;
            $tmp=$box[$i];
            $box[$i]=$box[$j];
            $box[$j]=$tmp;
        }
        for($a=$j=$i=0;$i<$string_length;$i++){
            $a=($a+1)%256;
            $j=($j+$box[$a])%256;
            $tmp=$box[$a];
            $box[$a]=$box[$j];
            $box[$j]=$tmp;
            $result.=chr(ord($string[$i])^($box[($box[$a]+$box[$j])%256]));
        }
        if($operation=='D'){
            if(substr($result,0,8)==substr(md5(substr($result,8).$key),0,8)){
                return substr($result,8);
            }else{
                return'';
            }
        }else{
            return str_replace('=','',base64_encode($result));
        }
    }
//--------------------------------------------------------
//	其他函数
//--------------------------------------------------------

	/**
	 * 获取当前IP
	 *
 	 * @access  public
	 * @return	string
	 */
	function getIP() { 
		if (@$_SERVER["HTTP_X_FORWARDED_FOR"]) 
		$ip = $_SERVER["HTTP_X_FORWARDED_FOR"]; 
		else if (@$_SERVER["HTTP_CLIENT_IP"]) 
		$ip = $_SERVER["HTTP_CLIENT_IP"]; 
		else if (@$_SERVER["REMOTE_ADDR"]) 
		$ip = $_SERVER["REMOTE_ADDR"]; 
		else if (@getenv("HTTP_X_FORWARDED_FOR"))
		$ip = getenv("HTTP_X_FORWARDED_FOR"); 
		else if (@getenv("HTTP_CLIENT_IP")) 
		$ip = getenv("HTTP_CLIENT_IP"); 
		else if (@getenv("REMOTE_ADDR")) 
		$ip = getenv("REMOTE_ADDR"); 
		else 
		$ip = "Unknown"; 
		return $ip; 
	}

	/**
	 * 替换文件路径以网站根目录开始，防止暴露文件的真实地址
	 *
	 * @param   string  $path
	 * @return  string  返回一个相对当前站点的文件路径
	 */
	function replpath($path){
		$root_path = str_replace(DIRECTORY_SEPARATOR, '/', ROOT_PATH);
		$src_path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
		return str_replace($root_path, '', $src_path);
	}


	function down($filename){
         echo '/?controller=down&file='.base64_encode($filename);
	}

	/*
	//	万能标签函数
	*/
	function l($table,$func=""){
		return M('module')->l($table,$func);
	}

	/*
	//	判断是否是手机
	*/
	function is_mobile_request(){   

	  $_SERVER['ALL_HTTP'] = isset($_SERVER['ALL_HTTP']) ? $_SERVER['ALL_HTTP'] : '';   
	  $mobile_browser = '0';   
	  if(preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', strtolower($_SERVER['HTTP_USER_AGENT'])))   
		$mobile_browser++;   
	  if((isset($_SERVER['HTTP_ACCEPT'])) and (strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') !== false))   
		$mobile_browser++;   
	  if(isset($_SERVER['HTTP_X_WAP_PROFILE']))   
		$mobile_browser++;   
	  if(isset($_SERVER['HTTP_PROFILE']))   
		$mobile_browser++;   
	  $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'],0,4));   
	  $mobile_agents = array(   
			'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',   
			'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',   
			'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',   
			'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',   
			'newt','noki','oper','palm','pana','pant','phil','play','port','prox',   
			'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',   
			'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',   
			'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',   
			'wapr','webc','winw','winw','xda','xda-'  
			);   
	  if(in_array($mobile_ua, $mobile_agents))   
		$mobile_browser++;   
	  if(strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false)   
		$mobile_browser++;   
	  // Pre-final check to reset everything if the user is on Windows   
	  if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false)   
		$mobile_browser=0;   
	  // But WP7 is also Windows, with a slightly different characteristic   
	  if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows phone') !== false)   
		$mobile_browser++;   
	  if($mobile_browser>0)   
		return true;   
	  else 
		return false;   
	}

	/** 
 * 生成缩略图函数（支持图片格式：gif、jpeg、png和bmp） 
 * 用法：{thumb($imgUrl,50,50)}
 * @author ruxing.li 
 * @param  string $src      源图片路径 
 * @param  int    $width    缩略图宽度（只指定高度时进行等比缩放） 
 * @param  int    $width    缩略图高度（只指定宽度时进行等比缩放） 
 * @param  string $filename 保存路径（不指定时直接输出到浏览器） 
 * @return bool 
 */  
function thumb($src, $width = null, $height = null, $filename = null) {  
 
	if (!isset($width) && !isset($height))  
        return false;  
    if (isset($width) && $width <= 0)  
        return false;  
    if (isset($height) && $height <= 0)  
        return false;  

    $size = getimagesize($src);  
    if (!$size)  
        return false;  
  
    list($src_w, $src_h, $src_type) = $size;  
    $src_mime = $size['mime'];  
    switch($src_type) {  
        case 1 :  
            $img_type = 'gif';  
            break;  
        case 2 :  
            $img_type = 'jpeg';  
            break;  
        case 3 :  
            $img_type = 'png';  
            break;  
        case 15 :  
            $img_type = 'wbmp';  
            break;  
        default :  
            return false;  
    }  
  
    if (!isset($width))  
        $width = $src_w * ($height / $src_h);  
    if (!isset($height))  
        $height = $src_h * ($width / $src_w);  
  
    $imagecreatefunc = 'imagecreatefrom' . $img_type;  
    $src_img = $imagecreatefunc($src);  
    $dest_img = imagecreatetruecolor($width, $height);  
    imagecopyresampled($dest_img, $src_img, 0, 0, 0, 0, $width, $height, $src_w, $src_h);  
  
    $imagefunc = 'image' . $img_type;  
    if ($filename) {  
        $imagefunc($dest_img, $filename);  
    } else {  
        header('Content-Type: ' . $src_mime);  
        $imagefunc($dest_img);
    }  
    imagedestroy($src_img);  
    imagedestroy($dest_img);  
    return true;  
}


?>