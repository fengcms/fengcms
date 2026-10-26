<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-12-10 09:31:48
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
include "admin.php";

class safeController extends Controller{

	private $model = 'safe';


    /**
     * 网站基础信息
     * @access public
     * @return template,array
     */
	public function index(){
		$base=M("base")->findone();
		$array=array('safe'=>$this->safe($base['domran']??''));
		return $this->display('safe/index.html',$array);
	}

	/* 固定目标、限制时间和响应大小；第三方页面只作为纯文本显示。 */
	public function safe($url){
		$fallback='在线安全检测暂不可用，请结合服务端日志和项目安全更新记录检查站点。';
		if(!is_string($url) || $url==='' || !function_exists('curl_init')){return $fallback;}
		$handle=curl_init('https://www.so.com/s?ie=utf-8&q='.rawurlencode('site://'.$url));
		if($handle===false){return $fallback;}
		$body='';
		$protocolOption=defined('CURLOPT_PROTOCOLS_STR')?CURLOPT_PROTOCOLS_STR:CURLOPT_PROTOCOLS;
		curl_setopt_array($handle,[
			CURLOPT_FOLLOWLOCATION=>false,
			$protocolOption=>defined('CURLOPT_PROTOCOLS_STR')?'https':CURLPROTO_HTTPS,
			CURLOPT_CONNECTTIMEOUT_MS=>1000,
			CURLOPT_TIMEOUT_MS=>3000,
			CURLOPT_WRITEFUNCTION=>static function($handle,$chunk) use (&$body){
				if(strlen($body)+strlen($chunk)>65536){return 0;}
				$body.=$chunk;
				return strlen($chunk);
			},
		]);
		$ok=curl_exec($handle);
		$status=curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
		curl_close($handle);
		if(!$ok || $status!==200 || !preg_match('/<div\s+class="detail"[^>]*>(.*?)<\/div>/is',$body,$match)){
			return $fallback;
		}
		$text=trim(html_entity_decode(strip_tags($match[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
		return $text!==''?$text:$fallback;
	}
	}
?>
