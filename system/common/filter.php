<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//过滤函数

	function stripslashes_array(&$array) { 
		while(list($key,$var) = each($array)) { 
			if ($key != 'argc' && $key != 'argv' && (strtoupper($key) != $key || ''.intval($key) == "$key")) { 
				if (is_string($var)){ 
					$array[$key] = stripslashes($var); 
				} 
				if (is_array($var)){ 
					$array[$key] = stripslashes_array($var); 
				} 
			} 
		} 
	return $array; 
	} 

	//过滤 
	function htmlencode($str){ 
		if(empty($str)) return; 
		if($str=="") return $str; 
		$str=trim($str); 
		$str=str_replace("&","&",$str); 
		$str=str_replace(">",">",$str); 
		$str=str_replace("<","<",$str); 
		$str=str_replace(chr(32)," ",$str); 
		$str=str_replace(chr(9)," ",$str); 
		$str=str_replace(chr(9)," ",$str); 
		$str=str_replace(chr(34),"&",$str); 
		$str=str_replace(chr(39),"'",$str); 
		$str=str_replace(chr(13),"<br />",$str); 
		$str=str_replace("'","''",$str); 
		$str=str_replace("select","select",$str); 
		$str=str_replace("SCRIPT","SCRIPT",$str); 
		$str=str_replace("script","script",$str); 
		$str=str_replace("join","join",$str); 
		$str=str_replace("union","union",$str); 
		$str=str_replace("where","where",$str); 
		$str=str_replace("insert","insert",$str); 
		$str=str_replace("delete","delete",$str); 
		$str=str_replace("update","update",$str); 
		$str=str_replace("like","like",$str); 
		$str=str_replace("drop","drop",$str); 
		$str=str_replace("create","create",$str); 
		$str=str_replace("modify","modify",$str); 
		$str=str_replace("rename","rename",$str); 
		$str=str_replace("alter","alter",$str); 
		$str=str_replace("cast","cas",$str); 
		return $str; 
	} 

	//解码 
	function htmldecode($str){ 
		if(empty($str)) return; 
		if($str=="") return $str; 
		$str=str_replace("select","select",$str); 
		$str=str_replace("join","join",$str); 
		$str=str_replace("union","union",$str); 
		$str=str_replace("where","where",$str); 
		$str=str_replace("insert","insert",$str); 
		$str=str_replace("delete","delete",$str); 
		$str=str_replace("update","update",$str); 
		$str=str_replace("like","like",$str); 
		$str=str_replace("drop","drop",$str); 
		$str=str_replace("create","create",$str); 
		$str=str_replace("modify","modify",$str); 
		$str=str_replace("rename","rename",$str); 
		$str=str_replace("alter","alter",$str); 
		$str=str_replace("cas","cast",$str); 
		$str=str_replace("&","&",$str); 
		$str=str_replace(">",">",$str); 
		$str=str_replace("<","<",$str); 
		$str=str_replace(" ",chr(32),$str); 
		$str=str_replace(" ",chr(9),$str); 
		$str=str_replace(" ",chr(9),$str); 
		$str=str_replace("&",chr(34),$str); 
		$str=str_replace("'",chr(39),$str); 
		$str=str_replace("<br />",chr(13),$str); 
		$str=str_replace("''","'",$str); 
		return $str; 
	} 

	// 函数：string_filter($string, $match_type=1) 
	// 功能：过滤非法内容 
	// 参数： 
	// $string 需要检查的字符串 
	// $match_type 匹配类型,1为精确匹配, 2为模糊匹配，默认为1 
	// 
	// 返回：有非法内容返回True，无非法内容返回False 
	// 其他：非法关键字列表保存在txt文件里, 分为普通非法关键字和严重非法关键字两个列表 
	// 作者：heiyeluren 
	// 时间：2006-1-18 
	// 
	//======================================================================
	function lib_lawless_string_filter($string, $match_type=1){ 
		//字符串空直接返回为非法 
		$string = trim($string); 
		if (empty($string)){ 
			return false; 
		} 
		//获取重要关键字列表和普通关键字列表 
		$common_file = "common_list.txt"; //通用过滤关键字列表 
		$signify_file = "signify_list.txt"; //重要过滤关键字列表 
		//如果任何列表文件不存在直接返回false，否则把两个文件列表读取到两个数组里 
		if (!file_exists($common_file) || !file_exists($signify_file)){ 
		return false; 
		} 
		$common_list = file($common_file); 
		$signify_list = file($signify_file); 

		//精确匹配 
		if ($match_type == 1){ 
		$is_lawless = exact_match($string, $common_list); 
		} 

		//模糊匹配 
		if ($match_type == 2){ 
		$is_lawless = blur_match($string, $common_list, $signify_list); 
		} 

		//判断检索结果数组中是否有数据，如果有，证明是非法的 
		if (is_array($is_lawless) && !empty($is_lawless)){ 
			return true; 
		}else{ 
			return false; 
		} 
	} 

	//--------------------- 
	// 精确匹配,为过滤服务 
	//--------------------- 
	function exact_match($string, $common_list){ 
		$string = trim($string); 
		$string = lib_replace_end_tag($string); 

		//检索普通过滤关键字列表 
		foreach($common_list as $block){ 
			$block = trim($block); 
			if (preg_match("/^$string$/i", $block)){ 
				$blist[] = $block; 
			} 
		} 
		//判断有没有过滤内容在数组里 
		if (!empty($blist)){ 
			return array_unique($blist); 
		} 
		return false; 
	} 

	//---------------------- 
	// 模糊匹配,为过滤服务 
	//---------------------- 
	function blur_match($string, $common_list, $signify_list){ 
		$string = trim($string); 
		$s_len = strlen($string); 
		$string = lib_replace_end_tag($string); 

		//检索普通过滤关键字列表 
		foreach($common_list as $block){ 
			$block = trim($block); 
			if (preg_match("/^$string$/i", $block)){ 
			$blist[] = $block; 
			} 
		} 
		//检索严重过滤关键字列表 
		foreach($signify_list as $block){ 
			$block = trim($block); 
			if ($s_len>=strlen($block) && preg_match("/$block/i", $string)){ 
				$blist[] = $block; 
			} 
		} 
		//判断有没有过滤内容在数组里 
		if (!empty($blist)){ 
		return array_unique($blist); 
		} 
		return false; 
	} 

	//-------------------------- 
	// 替换HTML尾标签,为过滤服务 
	//-------------------------- 
	function lib_replace_end_tag($str){ 
		if (empty($str)) return false; 
		$str = htmlspecialchars($str); 
		$str = str_replace( '/', "", $str); 
		$str = str_replace("\\", "", $str); 
		$str = str_replace("<SCRIPT>", "", $str); 
		$str = str_replace("</SCRIPT>", "", $str); 
		$str = str_replace("<script>", "", $str); 
		$str = str_replace("</script>", "", $str); 
		$str=str_replace("select","select",$str); 
		$str=str_replace("join","join",$str); 
		$str=str_replace("union","union",$str); 
		$str=str_replace("where","where",$str); 
		$str=str_replace("insert","insert",$str); 
		$str=str_replace("delete","delete",$str); 
		$str=str_replace("update","update",$str); 
		$str=str_replace("like","like",$str); 
		$str=str_replace("drop","drop",$str); 
		$str=str_replace("create","create",$str); 
		$str=str_replace("modify","modify",$str); 
		$str=str_replace("rename","rename",$str); 
		$str=str_replace("alter","alter",$str); 
		$str=str_replace("cas","cast",$str); 
		$str=str_replace("&","&",$str); 
		$str=str_replace(">","&gt;",$str); 
		$str=str_replace("<","&lt;",$str);
		$str=str_replace(" ",chr(32),$str); 
		$str=str_replace(" ",chr(9),$str); 
		$str=str_replace(" ",chr(9),$str); 
		$str=str_replace("'","&#39;",$str); 
		$str=str_replace("<br />",chr(13),$str); 

	//我自己加的	 
		$str=str_replace("|","&#124;",$str);
		$str=str_replace("+","&#43;",$str);
		$str=str_replace("-","&#45;",$str);
		$str=str_replace("!","&#33;",$str);
		$str=str_replace("$","&#36;",$str);
		$str=str_replace("%","&#37;",$str);
		$str=str_replace("'","&#39;",$str);
		$str=str_replace("(","&#40;",$str);
		$str=str_replace(")","&#41;",$str);
		$str=str_replace("*","&#42;",$str);
		$str=str_replace("+","&#43;",$str);
		$str=str_replace(",","&#44;",$str);
		$str=str_replace("-","&#45;",$str);
		$str=str_replace(".","&#46;",$str);
		$str=str_replace("/","&#47;",$str);
		$str=str_replace(":","&#58;",$str);
		$str=str_replace("=","&#61;",$str);
		$str=str_replace("?","&#63;",$str);
		$str=str_replace("@","&#64;",$str);
		return $str; 
	}

	//-------------------------- 
	// 过滤数组
	//-------------------------- 
	function lib_replace_end_tag_array($array){
		if(!is_array($array)) return false;
		foreach($array as $k => $v){
			$arr[$k]= lib_replace_end_tag($v);
		}
		return $arr;
	}

	//-------------------------- 
	// XSS数组过滤
	//-------------------------- 
	function removexss_array($array){
		if(!is_array($array)) return false;
		foreach($array as $k => $v){
			$arr[$k]= RemoveXSS($v);
		}
		return $arr;
	}

	/** 
	* @去除XSS（跨站脚本攻击）的函数 
	* @par $val 字符串参数，可能包含恶意的脚本代码如<script language="javascript">alert("hello world");</script> 
	* @return  处理后的字符串 
	* @Recoded By Androidyue 
	**/ 
	function RemoveXSS($val) {    
	   // remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed    
	   // this prevents some character re-spacing such as <java\0script>    
	   // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs    
	   $val = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/', '', $val);    
	   
	   // straight replacements, the user should never need these since they're normal characters    
	   // this prevents like <IMG SRC=@avascript:alert('XSS')>    
	   $search = 'abcdefghijklmnopqrstuvwxyz';   
	   $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';    
	   $search .= '1234567890!@#$%^&*()';   
	   $search .= '~`";:?+/={}[]-_|\'\\';   
	   for ($i = 0; $i < strlen($search); $i++) {   
		  // ;? matches the ;, which is optional   
		  // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars   
	   
		  // @ @ search for the hex values   
		  $val = preg_replace('/(&#[xX]0{0,8}'.dechex(ord($search[$i])).';?)/i', $search[$i], $val); // with a ;   
		  // @ @ 0{0,7} matches '0' zero to seven times    
		  $val = preg_replace('/(&#0{0,8}'.ord($search[$i]).';?)/', $search[$i], $val); // with a ;   
	   }   
	   
	   // now the only remaining whitespace attacks are \t, \n, and \r   
	   $ra1 = Array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');   
	   $ra2 = Array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');   
	   $ra = array_merge($ra1, $ra2);   
	   
	   $found = true; // keep replacing as long as the previous round replaced something   
	   while ($found == true) {   
		  $val_before = $val;   
		  for ($i = 0; $i < sizeof($ra); $i++) {   
			 $pattern = '/';   
			 for ($j = 0; $j < strlen($ra[$i]); $j++) {   
				if ($j > 0) {   
				   $pattern .= '(';    
				   $pattern .= '(&#[xX]0{0,8}([9ab]);)';   
				   $pattern .= '|';    
				   $pattern .= '|(&#0{0,8}([9|10|13]);)';   
				   $pattern .= ')*';   
				}   
				$pattern .= $ra[$i][$j];   
			 }   
			 $pattern .= '/i';    
			 $replacement = substr($ra[$i], 0, 2).'<x>'.substr($ra[$i], 2); // add in <> to nerf the tag    
			 $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags    
			 if ($val_before == $val) {    
				// no replacements were made, so exit the loop    
				$found = false;    
			 }    
		  }    
	   }    
	   return $val;    
	}  
	
	
	//post数据转义   add by hxl 
	function addslashes_array(&$data) {
	    if(!get_magic_quotes_gpc()){
	        if(is_array($data)){
	            foreach($data as $n=>$v){
	                $b[addslashes($n)]=addslashes_array($v);
	            }
	            return $b;
	        }else{
	            return addslashes($data);
	        }
	    } 
	    return $data;
	}
	
	 
?>
