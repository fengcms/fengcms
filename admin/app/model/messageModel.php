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

class messageModel{// extends model{

	private $d_name='message';

    /**
     * 管理信息
     *
     * @return array
     */
	public function page($where){
		return sqlpage($this->d_name,"20","*",$where,"id desc");
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function reply($id){
		if($id){
			return D($this->d_name)->where(['id'=>(int)$id])->getone();
		}
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function delete($id){
		if($id){
			return D($this->d_name)->where(['id'=>(int)$id])->delete();
		}else{
			return false;	
		}
	}

	public function batch($array){
		if(is_array($array)){
			foreach($array as $v){
				if(!$this->delete($v)) return array('status' => 'n');
			}
			return array('status' => 'y');
		}else{
			return array('status' => 'e');
		}	
	}

    /**
     * 保存信息
     *
     * @return array
     */
    public function save($array){
        $id=$array['id']??'';
        if(!is_string($id) || !preg_match('/^[0-9]{1,10}$/D',$id) || (int)$id<1){cms_fail(400,'留言 ID 不正确。');}
        $array=array_intersect_key($array,array_fill_keys(['title','name','qq','tel','mail','content','reply','status','rtime'],true));
        foreach($array as $value){if(!is_string($value) && !is_int($value)){cms_fail(400,'留言内容不正确。');}}
        if(isset($array['status']) && !in_array((string)$array['status'],['0','1'],true)){cms_fail(400,'留言状态不正确。');}
        $exists=D($this->d_name)->where(['id'=>$id])->getone();
        $saved=$exists && $array && D($this->d_name)->where(['id'=>$id])->update($array)>=0;
        return ['status'=>$saved?'y':'n','id'=>$id];
    }

}

?>
