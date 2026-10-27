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
require_once ROOT_PATH.'/system/common/model_schema.php';

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
    /**
     * 管理信息
     *
     * @return array
     */
	public function status($id,$status,string $project){
		if(!in_array((string)$status,['0','1'],true)){cms_fail(400,'字段状态不正确。');}
		$module=D('module')->where(['project'=>$project])->getone();
		if(!$module || CmsModelSchema::pending(CmsModelSchema::heldConnection($project),$project) ||
			!D($this->d_name)->where(['id'=>(int)$id,'module_id'=>(int)$module['id']])->getone()){
			cms_fail(409,'字段已变更或结构操作尚未完成。');
		}
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
    public function sort($array,string $project){
        $pairs=cms_sort_pairs($array['id']??null,$array['sort']??null);
        if(!$pairs){return ['status'=>'a'];}
        $module=D('module')->where(['project'=>$project])->getone();
        if(!$module || CmsModelSchema::pending(CmsModelSchema::heldConnection($project),$project)){
            cms_fail(409,'模型结构操作尚未完成。');
        }
        foreach($pairs as [$id,$sort]){
            if(!D($this->d_name)->where(['id'=>$id,'module_id'=>(int)$module['id']])->getone()){
                return ['status'=>'n'];
            }
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
        $project=$id ? (D($this->v_name)->where(['id'=>(int)$id])->getone()['project']??null) : ($array['project']??null);
        if(!is_string($project) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D',$project)){
            cms_fail(400,'字段所属模型不正确。');
        }
        if(!CmsModelSchema::isHeld($project)){
            return CmsModelSchema::withLock($project,fn()=> $this->save($array));
        }
        if($id){
            $exists=D($this->d_name)->where(['id'=>$id])->getone();
            if(!$exists || CmsModelSchema::pending(CmsModelSchema::heldConnection($project),$project)){
                cms_fail(409,'字段已删除或结构操作尚未完成。');
            }
            // Physical column identity and ordering have dedicated operations.
            // Only metadata exposed by the edit form may be changed here.
            $array=array_intersect_key($array,array_fill_keys([
                'aliases','defaults','must','length','enable','search','nullmsg','errormsg'
            ],true));
            foreach($array as $value){
                if(!is_string($value) && !is_int($value)){cms_fail(400,'字段内容不正确。');}
            }
            foreach(['aliases'=>50,'defaults'=>255,'length'=>50,'nullmsg'=>100,'errormsg'=>100] as $key=>$limit){
                if(isset($array[$key]) && mb_strlen((string)$array[$key],'UTF-8')>$limit){
                    cms_fail(400,'字段配置超过存储长度。');
                }
            }
            $array['enable']=empty($array['enable'])?0:1;
            $array['search']=empty($array['search'])?0:1;
            $saved=$exists && D($this->d_name)->where(['id'=>$id])->update($array)>=0;
        }else{
            $name=$array['name']??null;
            $type=$array['type']??null;
            if(!is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,49}$/D',$name) ||
                !is_string($type) || !preg_match('/^[0-9]{1,10}$/D',$type)){
                cms_fail(400,'字段参数不正确。');
            }
            $module=D('module')->where(['project'=>$project,'id'=>$array['module_id']??''])->getone();
            $fieldType=D('module_field_type')->where(['id'=>$type,'f'=>1])->getone();
            if(!$module || !$fieldType){cms_fail(400,'字段所属模型或类型不正确。');}
            return $this->addCustomField($project,$module,$name,(int)$type,$array);
        }
        return ['status'=>$saved?'y':'n','id'=>$id?:null];
    }

    private function addCustomField(string $project,array $module,string $name,int $type,array $input): array
    {
        $db=CmsModelSchema::heldConnection($project);
        $reserved=[];
        foreach(D('module_field_type')->where(['f'=>0])->getall() as $system){
            $logical=match((int)$system['id']){15=>'status',16=>'html',default=>$system['class']};
            foreach($this->physicalNames($logical,(int)$system['id']) as $column){$reserved[$column]=true;}
        }
        if(isset($reserved[$name])){cms_fail(400,'该名称由系统字段保留。');}
        $values=['module_id'=>(int)$module['id'],'name'=>$name,'type'=>$type];
        foreach(['aliases'=>50,'defaults'=>255,'length'=>50,'nullmsg'=>100,'errormsg'=>100] as $key=>$limit){
            if(!array_key_exists($key,$input)){continue;}
            if(!is_string($input[$key]) || mb_strlen($input[$key],'UTF-8')>$limit){
                cms_fail(400,'字段配置超过存储长度。');
            }
            $values[$key]=$input[$key];
        }
        foreach(['must','enable','search'] as $key){
            if(!array_key_exists($key,$input)){continue;}
            if(!in_array((string)$input[$key],['0','1'],true)){
                cms_fail(400,'字段开关不正确。');
            }
            $values[$key]=(int)$input[$key];
        }
        ksort($values);
        $operation=CmsModelSchema::journal($db,$project,'field_add',$name);
        if(CmsModelSchema::pending($db,$project) && !$operation){
            cms_fail(409,'模型有待恢复的结构操作，请先完成原操作。');
        }
        if($operation && ((int)$operation['module_id']!==(int)$module['id'] ||
            json_decode($operation['payload'],true)!==$values)){
            cms_fail(409,'待恢复字段与本次请求不一致，请重试原请求。');
        }
        $existing=$db->execute_query('SELECT id FROM '.CmsModelSchema::table($this->d_name).
            ' WHERE module_id=? AND name=?',[(int)$module['id'],$name])->fetch_row();
        if($existing && !$operation){return ['status'=>'n'];}
        if(!$operation && CmsModelSchema::columnExists($db,$project,$name)){
            cms_fail(409,'物理列已存在，不能覆盖已有列或数据。');
        }
        if($operation && CmsModelSchema::columnExists($db,$project,$name) &&
            !$this->generatedColumnMatches($db,$project,$name,$type)){
            cms_fail(409,'待恢复字段的物理列定义已改变，请先核对。');
        }
        try{
            if(!$operation){
                CmsModelSchema::begin($db,$project,(int)$module['id'],'field_add',$name,null,
                    json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            }
            if(!CmsModelSchema::columnExists($db,$project,$name)){
                $db->query('ALTER TABLE '.CmsModelSchema::table($project).$this->fieldtype($name,$type));
            }
            CmsModelSchema::stage($db,$project,'field_add',$name,'column_added');
            if(!$existing){
                $values['sort']=(int)D($this->d_name)->where(['module_id'=>$module['id']])->getcount()-1;
                $id=D($this->d_name)->insert($values);
                if(!$id){throw new RuntimeException('字段配置写入失败。');}
            }else{$id=(int)$existing[0];}
            CmsModelSchema::clear($db,$project,'field_add',$name);
            return ['status'=>'y','id'=>(int)$id];
        }catch(Throwable $e){
            error_log('Field creation pending recovery: '.$e);
            return ['status'=>'p','id'=>null,'message'=>'字段结构操作未完成，请保持原请求并重试。'];
        }
    }

    public function physicalNames(string $name,int $type): array
    {
        $class=$this->gefieldtype($type);
        return $class==='attrib' ? ['attrib_j','attrib_g','attrib_t','attrib_r','attrib_d','attrib_h'] : [$name];
    }

    private function generatedColumnMatches(mysqli $db,string $project,string $column,int $type): bool
    {
        $actual=$db->execute_query('SELECT DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE FROM information_schema.COLUMNS '.
            'WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
            [DB_NAME,DB_PREFIX.$project,$column])->fetch_assoc();
        if(!$actual){return false;}
        $expected=match($this->gefieldtype($type)){
            'attrib'=>['int',null], 'classify'=>['varchar',50], 'date'=>['date',null],
            'textarea','batchpic'=>['text',null], 'htmledit'=>['longtext',null],
            default=>['varchar',255],
        };
        return $actual['DATA_TYPE']===$expected[0] &&
            ($expected[1]===null || (int)$actual['CHARACTER_MAXIMUM_LENGTH']===$expected[1]) &&
            $actual['IS_NULLABLE']==='YES';
    }

    public function deleteField(int $id,string $project): array
    {
        if($id<1 || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D',$project)){
            cms_fail(400,'字段或模型参数不正确。');
        }
        if(!CmsModelSchema::isHeld($project)){
            return CmsModelSchema::withLock($project,fn()=> $this->deleteField($id,$project));
        }
        $db=CmsModelSchema::heldConnection($project);
        $field=$db->execute_query('SELECT f.id,f.module_id,f.name,t.f AS field_kind FROM '.
            CmsModelSchema::table('module_field').' f JOIN '.CmsModelSchema::table('module').' m ON m.id=f.module_id '.
            'JOIN '.CmsModelSchema::table('module_field_type').' t ON t.id=f.type '.
            'WHERE f.id=? AND m.project=?',[$id,$project])->fetch_assoc();
        $operation=$db->execute_query('SELECT * FROM '.CmsModelSchema::table('model_schema_operation').
            ' WHERE project=? AND action=? AND field_id=?',[$project,'field_delete',$id])->fetch_assoc();
        if(!$field && !$operation){cms_fail(404,'字段不存在。');}
        if($field && (int)$field['field_kind']!==1){cms_fail(403,'系统字段不能删除。');}
        if($operation && $field && ((int)$operation['module_id']!==(int)$field['module_id'] ||
            $operation['field_name']!==$field['name'])){
            cms_fail(409,'待恢复字段身份不匹配。');
        }
        if(!$operation && CmsModelSchema::pending($db,$project)){
            cms_fail(409,'模型有待恢复的结构操作，请先完成原操作。');
        }
        $name=$field['name']??$operation['field_name'];
        $moduleId=(int)($field['module_id']??$operation['module_id']);
        if($operation && $operation['stage']!=='prepared' && CmsModelSchema::columnExists($db,$project,$name)){
            cms_fail(409,'已删除字段的同名物理列再次出现，请先人工核对。');
        }
        if(!$operation){CmsModelSchema::begin($db,$project,$moduleId,'field_delete',$name,$id);}
        try{
            if(CmsModelSchema::columnExists($db,$project,$name)){
                $db->query('ALTER TABLE '.CmsModelSchema::table($project).' DROP COLUMN '.model::identifier($name));
            }
            CmsModelSchema::stage($db,$project,'field_delete',$name,'column_dropped');
            $db->execute_query('DELETE FROM '.CmsModelSchema::table('module_field').
                ' WHERE id=? AND module_id=? AND name=?',[$id,$moduleId,$name]);
            CmsModelSchema::clear($db,$project,'field_delete',$name);
            return ['status'=>'y'];
        }catch(Throwable $e){
            error_log('Field deletion pending recovery: '.$e);
            $phase=CmsModelSchema::columnExists($db,$project,$name)?'物理列仍存在':'物理列已删除';
            return ['status'=>'p','message'=>'字段删除未完成（'.$phase.'），请重试同一字段。'];
        }
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
        if(!CmsModelSchema::isHeld($project)){
            return CmsModelSchema::withLock($project,fn()=> $this->systemsave($array));
        }
        $db=CmsModelSchema::heldConnection($project);
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
        $pending=$db->execute_query('SELECT action,field_name,module_id FROM '.
            CmsModelSchema::table('model_schema_operation').' WHERE project=?',[$project])->fetch_all(MYSQLI_ASSOC);
        $requestedNames=array_column($selected,'field_name');
        foreach($pending as $operation){
            if($operation['action']!=='field_add' || !in_array($operation['field_name'],$requestedNames,true) ||
                (int)$operation['module_id']!==(int)$module['id']){
                cms_fail(409,'模型有其他待恢复的结构操作，请先完成原操作。');
            }
        }
        // Check the complete physical plan before any DDL. A partial legacy composite
        // column set without our journal is a conflict, never permission to overwrite it.
        $planned=[]; $allColumns=[];
        foreach($selected as $field){
            $name=$field['field_name'];
            $columns=$this->physicalNames($name,(int)$field['id']);
            $operation=CmsModelSchema::journal($db,$project,'field_add',$name);
            if($operation && $operation['payload']!==(string)$field['id']){
                cms_fail(409,'待恢复系统字段与本次选择不一致。');
            }
            foreach($columns as $column){
                model::identifier($column);
                if(isset($allColumns[$column]) && $allColumns[$column]!==$name){
                    cms_fail(409,'系统字段的物理列相互冲突。');
                }
                $allColumns[$column]=$name;
                $exists=CmsModelSchema::columnExists($db,$project,$column);
                if($field['selected'] && !$exists){cms_fail(409,'已配置的系统字段缺少物理列，请先核对结构。');}
                if($operation && $exists &&
                    !$this->generatedColumnMatches($db,$project,$column,(int)$field['id'])){
                    cms_fail(409,'待恢复系统字段的物理列定义已改变，请先核对。');
                }
                if(!$field['selected'] && $exists && !$operation){
                    cms_fail(409,'系统字段与已有物理列冲突，整批未执行。');
                }
            }
            $planned[]=['field'=>$field,'columns'=>$columns,'operation'=>$operation];
        }
        $result=['changed'=>[],'already'=>[],'failed'=>[],'unprocessed'=>[]];
        foreach($planned as $index=>$item){
            $field=$item['field']; $name=$field['field_name'];
            if($field['selected']){
                if($item['operation']){CmsModelSchema::clear($db,$project,'field_add',$name);}
                $result['already'][]=$name;
                continue;
            }
            try{
                if(!$item['operation']){
                    CmsModelSchema::begin($db,$project,(int)$module['id'],'field_add',$name,null,(string)$field['id']);
                }
                $missing=array_values(array_filter($item['columns'],
                    fn($column)=>!CmsModelSchema::columnExists($db,$project,$column)));
                if($missing){
                    if(count($missing)===count($item['columns'])){
                        $db->query('ALTER TABLE '.CmsModelSchema::table($project).$this->fieldtype($name,$field['id']));
                    }elseif($name==='attrib'){
                        foreach($missing as $column){
                            $db->query('ALTER TABLE '.CmsModelSchema::table($project).' ADD COLUMN '.
                                model::identifier($column).' int(1) NULL DEFAULT 0');
                        }
                    }else{throw new RuntimeException('系统字段物理列不完整。');}
                }
                CmsModelSchema::stage($db,$project,'field_add',$name,'column_added');
                $existing=$db->execute_query('SELECT id FROM '.CmsModelSchema::table($this->d_name).
                    ' WHERE module_id=? AND name=?',[(int)$module['id'],$name])->fetch_row();
                if(!$existing){
                    $record=['module_id'=>$module['id'],
                        'sort'=>(int)D($this->d_name)->where(['module_id'=>$module['id']])->getcount()-1,
                        'name'=>$name,'aliases'=>$field['name'],'type'=>$field['id'],
                        'enable'=>$this->enable($name)];
                    if(!D($this->d_name)->insert($record)){throw new RuntimeException('系统字段配置写入失败。');}
                }
                CmsModelSchema::clear($db,$project,'field_add',$name);
                $result['changed'][]=$name;
            }catch(Throwable $e){
                error_log('System field batch pending recovery: '.$e);
                $result['failed'][]=$name;
                $result['unprocessed']=array_map(static fn($later)=>$later['field']['field_name'],
                    array_slice($planned,$index+1));
                break;
            }
        }
        $result['status']=$result['failed']?'p':'y';
        return $result;
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
