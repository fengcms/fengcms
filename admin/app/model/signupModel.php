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

class signupModel extends model{

	private $d_name='signup';

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
	public function find(){
		return D($this->d_name.'_base')->getone();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function signupinfo($id){
		return D($this->d_name)->where(['id'=>(int)$id])->getone();
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function detail($id){
		if($id){
			return D($this->d_name)->where(['id'=>(int)$id])->getone();
		}else{
			return throwexce('Exception Error ID does not exist.');
		}
	}

    /**
     * 管理信息
     *
     * @return array
     */
	public function delete($id=null){
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
		$table=$this->d_name.'_base';
		try{
			$columns=D($table)->getdbfields(DB_PREFIX.$table);
			$keys=D($table)->excsql('SHOW INDEX FROM '.model::identifier(DB_PREFIX.$table).' WHERE Key_name=?',['PRIMARY']);
			$count=(int)D($table)->field('*')->getcount();
		}catch(Throwable $e){
			error_log('Signup settings schema check failed: '.$e->getMessage());
			cms_fail(409,'报名设置表不可用，请先核对表结构。');
		}
		if(!$keys || $count!==1){cms_fail(409,'报名设置必须有主键且只有一行。');}
		$primary=[];
		foreach($keys as $key){$primary[]=$key['Column_name'];}
		if(array_diff($primary,$columns)){cms_fail(409,'报名设置主键不完整。');}
		$record=D($table)->getone();
		if(!$record){return ['status'=>'n'];}
		$where=[];
		foreach($primary as $column){$where[$column]=$record[$column];}
		// 客户端仅能修改表单字段；修改时间由服务器生成，其他列保持原值。
		$allowed=array_intersect(['name','phone','qq','pnumber','fmail','smail'],$columns);
		$values=array_intersect_key($array,array_fill_keys($allowed,true));
		foreach($values as $value){if(!is_string($value) && !is_int($value)){cms_fail(400,'报名设置内容不正确。');}}
		if(in_array('time',$columns,true) && array_key_exists('time',$array)){$values['time']=time();}
		$re=$values?D($table)->where($where)->update($values):false;
		return ['status'=>$re?'y':'n'];
	}

}

?>
