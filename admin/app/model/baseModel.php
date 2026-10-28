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

class baseModel extends model{

    private $d_name='base';

    /**
     * 管理信息
     *
     * @return array
     */
    public function findone(){
        return CmsSiteSettings::snapshot();
    }


    /**
     * 网站本地版权信息
     * @access public
     * @return template,array
     */
    public function version(){
        return file_get_contents(ABS_PATH."/version.php");
    }



    /**
     * 保存信息
     *
     * @return array
     */
    public function save($array){
        $urlType=$array['url_type']??null;
        if(!in_array($urlType,['0','1'],true)){cms_fail(400,'伪静态设置不正确。');}
        $baseline=$array['_settings_version']??null;
        if(!is_string($baseline) || !preg_match('/^[a-f0-9]{64}$/D',$baseline)){
            cms_fail(400,'网站设置编辑版本不正确，请刷新页面。');
        }
        $allowed=['name','title','domran','key','description','copyright','template'];
        $values=array_intersect_key($array,array_fill_keys($allowed,true));
        foreach($values as $value){if(!is_string($value)){cms_fail(400,'网站设置内容不正确。');}}
        if(array_key_exists('copyright',$values)){$values['copyright']=cms_clean_html($values['copyright']);}
        CmsSiteSettings::save($values,$urlType,$baseline);
        return ['status'=>'y'];
    }

}




?>
