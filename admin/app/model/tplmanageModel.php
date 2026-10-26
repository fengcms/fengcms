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

class tplmanageModel extends model{


    public function pathset($name, $type){
        $roots = ['tpl'=>'template','css'=>'css','scrap'=>'template/scrap','inc'=>'template/inc','show'=>'template/show'];
        if (!is_string($name) || !is_string($type) || !isset($roots[$type])) { cms_fail(400, '模板参数不正确。'); }
        return cms_path_within(ROOT_PATH.'/'.$roots[$type], $name.($type === 'css' ? '.css' : '.html'), false);
    }


	public function deletefile($array){

		$files=$this->pathset($array['name'],$array['project']);
		if(file_exists($files)){

			if(unlink($files)){

				return true;

			}else{

				return false;

			}

		}else{
			return false;
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save($array){

		$files=$this->pathset($array['name'],$array['project']);
		
		if($array['type']==0){ if(file_exists($files))return array('status' => 'e');}

			if(file_put_contents($files,$array['content'])){

				return array('status' => 'y','project' => $array['project']);

			}else{

				return array('status' => 'n','project' => $array['project']);
			}
		}

}

?>