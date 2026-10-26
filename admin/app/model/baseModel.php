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
        return D($this->d_name)->getone();
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
        $db=new db_mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
        $table=model::identifier(DB_PREFIX.$this->d_name);
        $row=$db->assoc($db->query("SELECT COUNT(*) AS total FROM $table"));
        if((int)$row['total']!==1){cms_fail(409,'网站基本信息必须恰好有一行，保存已取消。');}
        $allowed=['name','title','domran','key','description','copyright','template'];
        $values=array_intersect_key($array,array_fill_keys($allowed,true));
        foreach($values as $value){if(!is_string($value)){cms_fail(400,'网站设置内容不正确。');}}
        if(array_key_exists('copyright',$values)){$values['copyright']=cms_clean_html($values['copyright']);}
        if(!$this->url_type($urlType)){return ['status'=>'n'];}
        $values['time']=(string)time();
        $sets=[];
        foreach($values as $field=>$value){$sets[]=model::identifier($field).'=?';}
        $db->query('UPDATE '.$table.' SET '.implode(',',$sets).' LIMIT 1',array_values($values));
        return ['status'=>'y'];
    }

    /**
     * 配置伪静态
     *
     * @return array
     */

    private function url_type($url_type){
        if (!in_array((string)$url_type, ['0','1'], true)) { return false; }
        $root = getenv('APP_RUNTIME_PATH') ?: ROOT_PATH.'/var/runtime';
        if (!is_dir($root) && !mkdir($root, 0750, true)) { return false; }
        $temp = $root.'/site.'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($temp, json_encode(['url_type'=>(int)$url_type]), LOCK_EX) === false) { return false; }
        chmod($temp, 0600);
        return rename($temp, $root.'/site.json');
    }

}




?>
