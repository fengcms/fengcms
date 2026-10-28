<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 后台首页
include "admin.php";

class cacheController extends Controller{

    /**
     * 后台首页
     * @access public
     */
	public function index(){
        require_once ROOT_PATH.'/system/common/template_cache.php';
        try{
            $removed=CmsTemplateCache::clear();
            $message=$removed>0?'已更新 '.$removed.' 处编译缓存':'编译缓存已经是空的';
        }catch(Throwable $e){
            error_log('Template cache clear failed: '.$e);
            cms_fail(500,'编译缓存更新失败，请检查私有错误日志。');
        }
        echo '<script type="text/javascript">alert('.json_encode($message,JSON_UNESCAPED_UNICODE).');</script>';
        echo '<meta http-equiv="refresh" content="0;url=?controller=home&operate=main">';
	}

}

?>
