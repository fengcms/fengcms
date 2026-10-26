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
        if($this->url_type($array['url_type'])){
            
            unset($array['url_type']);

            if(D($this->d_name)->update($array)){

                return array('status' => 'y');

            }else{

                return array('status' => 'n');
            }
        }else{

            return array('status' => 'n');

        }
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