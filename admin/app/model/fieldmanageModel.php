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

class fieldmanageModel{// extends model{

	private $d_name='module_field';

	private $v_name='view_field';

    /**
     * 管理信息
     *
     * @return array
     */
	public function findone($where=""){
		return D($this->v_name)->where($where)->getone();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function findall($where=""){
		return D($this->v_name)->where($where)->sort("sort")->getall();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function delete($where=""){
		return D($this->d_name)->where($where)->delete();
	}

    public function fielddel($id){
        $arr=D($this->v_name)->where('id="'.$id.'"')->getone();
        return D($arr['project'])->dropalter($arr['name']);
    }

    /**
     * 管理信息
     *
     * @return array
     */
	public function status($where="",$status){
		return D($this->d_name)->where($where)->update(array(
			'status'	=>	$status
			));
	}	

    /**
     * 管理信息
     *
     * @return array
     */
	public function fielddefault($id){
		if(D($this->d_name)->insert(array(
		/* 标题 */
		"module_id"	=>   $id,
		"sort"		=>	 1,
		"name"		=>   "title",
		"aliases"	=>   "标题",
		"type"		=>   1,
		"must"		=>   1,
		"length"	=>   "1-255",
		"enable"	=>	 "1",
		"search"	=>	 "1"
		))&&D($this->d_name)->insert(array(
		/* 导读 */
		"module_id"	=>   $id,
		"sort"		=>	 94,
		"name"		=>   "info",
		"aliases"	=>   "导读",
		"type"		=>   8,
		"enable"	=>	 "0"
		))&&D($this->d_name)->insert(array(
		/* 内容 */
		"module_id"	=>   $id,
		"sort"		=>	 95,
		"name"		=>   "content",
		"aliases"	=>   "内容",
		"type"		=>   9,
		"enable"	=>	 "0"
		))&&D($this->d_name)->insert(array(
		/* 图片 */
		"module_id"	=>   $id,
		"sort"		=>	 96,
		"name"		=>   "img",
		"aliases"	=>   "图片",
		"type"		=>   10,
		"enable"	=>	 "0"
		))&&D($this->d_name)->insert(array(
		/* 时间 */
		"module_id"	=>   $id,
		"sort"		=>	 97,
		"name"		=>   "time",
		"aliases"	=>   "时间",
		"type"		=>   13,
		"enable"	=>	 "0"
		))&&D($this->d_name)->insert(array(
		/* 状态 */
		"module_id"	=>   $id,
		"sort"		=>	 98,
		"name"		=>   "status",
		"aliases"	=>   "状态",
		"type"		=>   15,
		"defaults"	=>   1,
		"length"	=>   "1-1",
		"enable"	=>	 "1"
		))&&D($this->d_name)->insert(array(
		/* 链接地址 */
		"module_id"	=>   $id,
		"sort"		=>	 99,
		"name"		=>   "html",
		"aliases"	=>   "地址",
		"type"		=>   15,
		"length"	=>   "6-50",
		))){
			return true;
		}else{
			return false;
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function sort($array){
		foreach($array['id'] as $k => $v){
			$re=D($this->d_name)->where('id="'.$v.'"')->update('sort="'.$array['sort'][$k].'",time="'.time().'"');
			if(!$re)return array('status' => 'n');
		}
			return array('status' => 'y');
	}
    /**
     * 保存信息
     *
     * @return array
     */
	public function save($array){

		if($array['id']){

			$id=$array['id'];unset($array['id']);unset($array['project']);

			$array['enable']=($array['enable']?1:0);
			$array['search']=($array['search']?1:0);
			$re=D($this->d_name)->where('id="'.$id.'"')->update($array);

		}else{
			$project=$array['project'];unset($array['project']);

			$type=$this->fieldtype($array['name'],$array['type']);

			$array['sort']=intval(D($this->v_name)->where('module_id="'.$array['module_id'].'"')->getcount()-1);

			D($this->model)->query("ALTER TABLE `".DB_PREFIX.$project."`".$type.";");

			$re=D($this->d_name)->insert($array);

		}

		if($re){

			return array('status' => 'y','id' => ($id)?$id:$re);
			
		}else{

			return array('status' => 'n','id' => ($id)?$id:$re);
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function systemsave($array){

		if(!is_array($array['record'])){
			return array('status' => 'e');
		}else{
 
			$module=D("module")->where('project="'.$array['project'].'"')->getone();
			foreach($array['record'] as $k => $v){

				$filed=D($this->d_name.'_type')->where('id="'.$v.'"')->getone();
				
				$arr['module_id']	= $module['id'];
				$arr['sort']		= intval(D($this->v_name)->where('module_id="'.$arr['module_id'].'"')->getcount()-1);
				$arr['name']		= $filed['class'];
				$arr['aliases']		= $filed['name'];
				$arr['type']		= $filed['id'];
				$arr['enable']		= $this->enable($filed['class']);

				$type=$this->fieldtype($arr['name'],$arr['type']);

				D($this->model)->query("ALTER TABLE `".DB_PREFIX.$array['project']."`".$type.";");

				$re=D($this->d_name)->insert($arr);

				if(!$re) return array('status' => 'n'); 
			}
				return array('status' => 'y');
		}
	}

    /**
     * 字段类型
     *
     * @return array
     */
	public function gefieldtype($int){
		$array=D("module_field_type")->where('id="'.$int.'"')->getone();
		return $array['class'];
	}

	public function enable($string){
		
		switch($string){

			case "classid":

				return '1';

			break;

			case "attrib":

				return '1';

			break;

			case "inputer":

				return '1';

			break;

			case "date":

				return '1';

			break;

			default:

				return '0';

			break;
		}
	}

    /**
     * 字段类型
     *
     * @return array
     */
	public function fieldtype($name,$type){
		$string=$this->gefieldtype($type);
		switch($string){


			case "classify":

				return " ADD COLUMN `".$name."` varchar(50) NULL AFTER `id`";

			break;

			case "attrib":

				return "  ADD COLUMN `attrib_j` int(1) NULL DEFAULT '0' AFTER `id`,
						  ADD COLUMN `attrib_g` int(1) NULL DEFAULT '0' AFTER `attrib_j`,
						  ADD COLUMN `attrib_t` int(1) NULL DEFAULT '0' AFTER `attrib_g`,
						  ADD COLUMN `attrib_r` int(1) NULL DEFAULT '0' AFTER `attrib_t`,
						  ADD COLUMN `attrib_d` int(1) NULL DEFAULT '0' AFTER `attrib_r`,
						  ADD COLUMN `attrib_h` int(1) NULL DEFAULT '0' AFTER `attrib_d`;";

			case "date":

				return " ADD COLUMN `".$name."` date NULL AFTER `id`";

			break;

			case "text":

				return " ADD COLUMN `".$name."` varchar(255) NULL AFTER `id`";

			break;

			case "textarea":

				return " ADD COLUMN `".$name."` text NULL AFTER `id`";

			break;

			case "htmledit":

				return " ADD COLUMN `".$name."` longtext NULL AFTER `id`";

			break;

			case "img":

				return " ADD COLUMN `".$name."` varchar(255) NULL AFTER `id`";

			break;

			case "batchpic":

				return " ADD COLUMN `".$name."` text NULL AFTER `id`";

			break;

			case "hidden":

				return " ADD COLUMN `".$name."` varchar(255) NULL AFTER `id`";

			break;

			default:

				return " ADD COLUMN `".$name."` varchar(255) NULL AFTER `id`";

			break;
		}
	}

}

?>