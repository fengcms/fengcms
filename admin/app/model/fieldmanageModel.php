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
        $arr=D($this->v_name)->where(['id'=>(int)$id])->getone();
        if(!$arr){return false;}
        return D($arr['project'])->dropalter($arr['name']);
    }

    /**
     * 管理信息
     *
     * @return array
     */
	public function status($id,$status){
		if(!in_array((string)$status,['0','1'],true)){cms_fail(400,'字段状态不正确。');}
		return D($this->d_name)->where(['id'=>(int)$id])->update(array(
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
        $pairs=cms_sort_pairs($array['id']??null,$array['sort']??null);
        if(!$pairs){return ['status'=>'a'];}
        foreach($pairs as [$id,$sort]){
            if(!D($this->d_name)->where(['id'=>$id])->getone()){return ['status'=>'n'];}
        }
        foreach($pairs as [$id,$sort]){
            D($this->d_name)->where(['id'=>$id])->update(['sort'=>$sort,'time'=>time()]);
        }
        return ['status'=>'y'];
    }

    /**
     * 保存信息
     *
     * @return array
     */
    public function save($array){
        $id=$array['id']??'';
        if($id){
            $exists=D($this->d_name)->where(['id'=>$id])->getone();
            // Physical column identity and ordering have dedicated operations.
            // Only metadata exposed by the edit form may be changed here.
            $array=array_intersect_key($array,array_fill_keys([
                'aliases','defaults','must','length','enable','search','nullmsg','errormsg'
            ],true));
            foreach($array as $value){
                if(!is_string($value) && !is_int($value)){cms_fail(400,'字段内容不正确。');}
            }
            $array['enable']=empty($array['enable'])?0:1;
            $array['search']=empty($array['search'])?0:1;
            $saved=$exists && D($this->d_name)->where(['id'=>$id])->update($array)>=0;
        }else{
            $project=$array['project']??null;
            $name=$array['name']??null;
            $type=$array['type']??null;
            if(!is_string($project) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D',$project) ||
                !is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,49}$/D',$name) ||
                !is_string($type) || !preg_match('/^[0-9]{1,10}$/D',$type)){
                cms_fail(400,'字段参数不正确。');
            }
            $module=D('module')->where(['project'=>$project,'id'=>$array['module_id']??''])->getone();
            $fieldType=D('module_field_type')->where(['id'=>$type,'f'=>1])->getone();
            if(!$module || !$fieldType){cms_fail(400,'字段所属模型或类型不正确。');}
            if(D($this->d_name)->where(['module_id'=>$module['id'],'name'=>$name])->getone()){return ['status'=>'n'];}
            unset($array['project'],$array['id']);
            $array['sort']=(int)D($this->d_name)->where(['module_id'=>$module['id']])->getcount()-1;
            D($this->d_name)->query('ALTER TABLE '.model::identifier(DB_PREFIX.$project).$this->fieldtype($name,$type));
            $id=D($this->d_name)->insert($array);
            $saved=(bool)$id;
        }
        return ['status'=>$saved?'y':'n','id'=>$id?:null];
    }

    /**
     * 保存信息
     *
     * @return array
     */
    // Physical names differ from type classes for the built-in hidden fields.
    public function systemfields($project){
        $existing=array_column($this->findall(['project'=>$project]), 'name');
        $fields=D($this->d_name.'_type')->where(['f'=>0])->getall();
        foreach($fields as &$field){
            $field['field_name']=match((int)$field['id']){15=>'status',16=>'html',default=>$field['class']};
            $field['selected']=in_array($field['field_name'],$existing,true);
        }
        unset($field);
        return $fields;
    }

    public function systemsave($array){
        $project=$array['project']??null;
        if(!is_string($project) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D',$project)){
            cms_fail(400,'模型名称不正确。');
        }
        $module=D('module')->where(['project'=>$project])->getone();
        if(!$module){cms_fail(404,'模型不存在。');}
        $records=$array['record']??[];
        if(!is_array($records) || count($records)>100){cms_fail(400,'字段选择不正确。');}
        if(!$records){return ['status'=>'e'];}
        $available=array_column($this->systemfields($project),null,'id');
        $selected=[];
        // Validate the whole batch before changing the table or field metadata.
        foreach($records as $id){
            if(!is_string($id) || !preg_match('/^[0-9]{1,10}$/D',$id) || !isset($available[(int)$id])){
                cms_fail(400,'系统字段不正确。');
            }
            $selected[(int)$id]=$available[(int)$id];
        }
        $table=model::identifier(DB_PREFIX.$project);
        foreach($selected as $field){
            if($field['selected']){continue;}
            $name=$field['field_name'];
            model::identifier($name);
            $record=[
                'module_id'=>$module['id'],
                'sort'=>(int)D($this->d_name)->where(['module_id'=>$module['id']])->getcount()-1,
                'name'=>$name,'aliases'=>$field['name'],'type'=>$field['id'],
                'enable'=>$this->enable($name),
            ];
            D($this->d_name)->query('ALTER TABLE '.$table.$this->fieldtype($name,$field['id']));
            if(!D($this->d_name)->insert($record)){return ['status'=>'n'];}
        }
        return ['status'=>'y'];
    }

    /**
     * 字段类型
     *
     * @return array
     */
	public function gefieldtype($int){
		$array=D("module_field_type")->where(['id'=>$int])->getone();
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
