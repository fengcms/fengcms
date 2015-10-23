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

		if($this->findcount('project="'.$array['project'].'"')>0){

				return array('status' => 'e');

		}else{

			if($array['id']){

				$id=$array['id'];unset($array['id']);

				$array['recover']=($array['recover']?1:0);
				$array['search']=($array['search']?1:0);

				$re=D($this->d_name)->where('id="'.$id.'"')->update($array);

			}else{

				$create=D($this->d_name)->query("CREATE TABLE `".DB_PREFIX.$array['project']."` (`id` int(11) NOT NULL auto_increment,`title` VarChar(255) COMMENT '标题',`info` text COMMENT '导读',`content` longtext COMMENT '内容',`img` text COMMENT '图片',`status` int(1) default 1,`time` int(11) default NULL,`html` VarChar(255) COMMENT 'HTML',PRIMARY KEY  (`id`)) ENGINE = MyISAM DEFAULT CHARSET=utf8 COMMENT='".$array['name']."';");

				$id=D($this->d_name)->insert($array);

				$re=M("fieldmanage")->fielddefault($id)&&$id&&$create;

			}

			if($re){

				return array('status' => 'y','id' => $id);
				
			}else{

				return array('status' => 'n','id' => $id);
			}
		}
	}

	public function elicit($array,$path){

		$sql.= str_replace(DB_PREFIX.$array['project'],"#_#project",D($array['project'])->showtable());
		$sql.= str_replace($array['project'],"#project",$this->show($this->d_name,'id='.$array['id']));
		$sql.= $this->show("module_field",'module_id='.$array['id']);

		return $sql;
	}

	public function loading($array){

		if($this->findcount('project="'.$array['project'].'"')<1){

			$modelcode=encrypt($array['modulecode'],"D");
			$modelcode=str_replace("#project",$array['project'],$modelcode);
			$modelcode=str_replace("#_",DB_PREFIX,$modelcode);		
			$modelcode=str_replace("''","NULL",$modelcode);	

			foreach (array_filter(explode(";",$modelcode)) as $k => $line) {
				D($this->d_name)->excsql($line);
				}
			$arr=D($this->d_name)->field('id')->sort("id","order","desc")->getone();

			if(D("module_field")->where('module_id=0')->update('module_id="'.$arr['id'].'"')){
	
				return array('status' => 'y');

			}else{

				return array('status' => 'n');

			}
		}else{
			return array('status' => 'e');
		}
	}


	private function show($table,$where=""){
		
		if($where)$where=" where ".$where;

		$array=D($this->d_name)->excsql("select * from `".DB_PREFIX.$table."` ".$where);

		foreach(array_filter($array) as $data){
			
			$keys = array_keys($data);

			$keys = array_map('addslashes', $keys);

			$filed=$keys;
			
			$keys = join('`,`', $keys);

			$keys = "`" . $keys . "`";

			$vals = array_values($data);

			$vals = array_map('addslashes', $vals);

			$vals = M("field")->fieldrepl($filed,$vals,"id");
			
			$vals = M("field")->fieldrepl($filed,$vals,"module_id","0");

			$vals = join("','", $vals);

			$vals = "'" . $vals . "'";

			$sql .= "insert into `#_".$table."`(".$keys.") values(".$vals.");";
			
		}

			return $sql;
	}
}

?>