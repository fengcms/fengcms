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

class modulemanageModel extends model{

	private $d_name='module';

    /**
     * 管理信息
     *
     * @return array
     */
	public function findone($where=""){
		return D($this->d_name)->where($where)->getone();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function findall($where=""){
		return D($this->d_name)->where($where)->getall();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function findcount($where=""){
		return D($this->d_name)->where($where)->getcount();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function update($status,$where=""){
		return D($this->d_name)->where($where)->update("status=".$status);
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function delete($where=""){
		return D($this->d_name)->where($where)->delete();
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save($array){
        $id=$array['id']??null;
        $current=null;
        if($id!==null && $id!==''){
            if(!is_string($id) || !preg_match('/^[0-9]{1,10}$/D',$id)){cms_fail(400,'模型编号不正确。');}
            $current=D($this->d_name)->where(['id'=>(int)$id])->getone();
            if(!$current){return ['status'=>'e'];}
        }
        $project=$current['project']??($array['project']??null);
        if($current && isset($array['project']) && $array['project']!==$project){return ['status'=>'e'];}
        $name=$array['name']??null;
        if(!is_string($project) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D',$project) ||
            !is_string($name) || trim($name)==='' || mb_strlen($name)>50){
            cms_fail(400,'模型名称或数据表名不正确。');
        }
        $values=[
            'name'=>$name, 'item'=>$array['item']??'', 'unit'=>$array['unit']??'',
            'description'=>$array['description']??'',
            'recover'=>empty($array['recover'])?0:1, 'search'=>empty($array['search'])?0:1,
        ];
        foreach(['item'=>50,'unit'=>10,'description'=>255] as $key=>$max){
            if(!is_string($values[$key]) || mb_strlen($values[$key])>$max){cms_fail(400,'模型属性不正确。');}
        }
        $existing=D($this->d_name)->where(['project'=>$project])->getone();
        if($id!==null && $id!==''){
            if(!$current || $current['project']!==$project || ($existing && (int)$existing['id']!==(int)$id)){
                return ['status'=>'e'];
            }
            $values['status']=isset($array['status']) && (string)$array['status']==='0'?0:1;
            $changed=D($this->d_name)->where(['id'=>(int)$id])->update($values);
            return ['status'=>$changed>=0?'y':'n','id'=>(int)$id];
        }
        if($existing){return ['status'=>'e'];}
        $table=model::identifier(DB_PREFIX.$project);
        $created=false; $newId=null;
        try{
            D($this->d_name)->query('CREATE TABLE '.$table.' (`id` int NOT NULL AUTO_INCREMENT,`title` varchar(255),`info` text,`content` longtext,`img` text,`status` int DEFAULT 1,`time` int DEFAULT NULL,`html` varchar(255),PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $created=true;
            $newId=D($this->d_name)->insert($values+['project'=>$project,'type'=>1,'status'=>1,'time'=>time()]);
            if(!$newId || !M('fieldmanage')->fielddefault($newId)){
                throw new RuntimeException('模型字段创建失败。');
            }
            return ['status'=>'y','id'=>$newId];
        }catch(Throwable $e){
            error_log('Module creation failed: '.$e);
            if($newId){
                D('module_field')->where(['module_id'=>$newId])->delete();
                D($this->d_name)->where(['id'=>$newId])->delete();
            }
            if($created){D($this->d_name)->query('DROP TABLE '.$table);}
            return ['status'=>'n'];
        }
    }

}

?>
