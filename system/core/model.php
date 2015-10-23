<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//	模型基类

defined('TPL_INCLUDE') or die( 'Restricted access'); 

// 类型

include_once DRIVER_PATH.'db_'.DB_TYPE.'.php';

class model{

	private	 $db = "";
    
	// 数据表前缀
    protected $prefix      =   '';

    // 查询表达式参数
    protected $options          =   array();//['limit']限制，['sort']排序，['where']条件 ['field']字段

	static private $_instance = NULL;


	 /**
     * 构函数
     * 取得DB类的实例对象 字段检查
     * @access public
     * @param string $name 模型名称
	 * @param mixed $prefix 表扩展名
     */
    public function __construct($name='',$prefix='') {

	    // 获取模型名称
        if(isset($name)){
			if(is_array($name)){
				foreach($name as $v){
					$names[].=$this->prefixname($prefix).$v;
				}
				$this->name=implode(",",$names);
			}else{
				$this->name=$this->prefixname($prefix).$name;
			}
		}elseif(empty($this->name)){
            $this->name =	$this->modelname();
        }
        

		// 数据库初始化
		$classname='db_'.DB_TYPE;
		if(!is_object($classname)){
			$this->db=new $classname(DB_HOST,DB_USER,DB_PASS,DB_NAME);
		}
    }

	/*
	 * 获取当前模型名称
	 */
	public function modelname(){
		if(empty($this->name))$this->name	= substr(get_class($this),0,-5);
		return $this->name;		
	}

	/*
	 * 获取当前扩展名称
	 */
	public function prefixname($prefix){
		return $prefix?$prefix:DB_PREFIX;
	}

    /**
     * 获取多条数据组
     *
     * @return array
     */
    public function getall(){
        return $this->db->fetch($this->db->query($this->buildselectsql())); 
	}

    /**
     * 获取单条数据组
     *
     * @return array
     */
    public function getone(){
        return $this->db->assoc($this->db->query($this->buildselectsql())); 
	}
 
    /**
     * 获取记录统计数
	 *
     * @return	number
     */
    public function getcount(){
		$this ->options['field']=$this ->options['field']?"count(".$this ->options['field'].")":"count(id)";
        $result = $this->db->fetch($this->db->query($this->buildselectsql()));
		return $result[0][$this ->options['field']]; 
    }

	/**
     * 插入数据
	 *
     * @param array $row
	 * @return bool|void
     */
    public function insert($row){ 
        if (!is_array($row)) { 
            return false; 
        } 

        foreach ($row as $key => $value) { 
            $cols[] = htmlencode($key); 
            $vals[] = $this->db->escape($value); 
        } 

        $col = join('`,`', $cols); 
        $val = join(',', $vals);

		$this->db->query('insert into `'.$this->name.'` (`'.$col.'`) values ('.$val.')');
		return $this->lastinsertid();		
    } 

	/**
     * 修改数据
	 *
     * @param array $row
	 * @return bool|void
     */
    public function update($row){

        if (!is_array($row)) { 
			$values=$row;
        }else{ 
        foreach ($row as $key => $value) { 
            $vals[] = '`'.$key.'` = '.$this->db->escape($value); 
        } 
        $values = join(',', $vals); 
		}
        $this->db->query('update '.$this->name.' set '.$values.$this->options['where']);
		return $this->affected_num();		
    } 
	

    /**
     * 删除记录
	 *
	 * @return bool|void
     */
    public function delete(){ 
        $this->db->query('delete from '.$this->name.$this->options['where']);
		return $this->db->affected_rows();		
    }

    /**
     * 删除记录
	 *
	 * @return bool|void
     */
    public function droptable(){ 
        $this->db->query('drop table '.$this->name);
		return $this->db->affected_rows();		
    }
    
    /**
     * 删除表格
	 *
	 * @return bool|void
     */
    public function showtable(){ 
        $array=$this->excsql('show create table '.$this->name);
		return $array[0]['Create Table'].';';
    }

    /**
     * 建立表格
	 *
	 * @return bool|void
     */
	 public function create(){
		$this->db->query('CREATE TABLE `'.$this->name.'` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  PRIMARY KEY (`id`)
			);');
		return $this->db->affected_rows();	
	 }

    /**
     * 建立字段
	 *
	 * @return bool|void
     */
	 public function alter($tables,$field,$after="id",$type="varchar",$length=255){

		$this->db->query('ALTER TABLE `'.DB_PREFIX.$tables.'` ADD COLUMN `'.$field.'` '.$type.'('.$length.') NULL DEFAULT NULL AFTER `'.$after.'`;');
		return $this->db->affected_rows();	
	 }

    /**
     * 删除字段
	 *
	 * @return bool|void
     */
    public function dropalter($fiend){ 
        $this->db->query('ALTER TABLE `'.$this->name.'` DROP COLUMN `'.$fiend.'`;');
		return $this->db->affected_rows();		
    }

    /**
     * 获取条件
     *
     * @param string $where
     * @return string
     */
    public function where($where=""){
		 $this->options['where'] =   isset($where)?$this->formatwhere($where):"";
        return $this;
	}

    /**
     * 指定查询数量
     * @access public
     * @param mixed $field 起始位置
     * @param mixed $length 查询数量
     * @return Model
     */
    public function field($field="*",$except=false){
		if($except){
			$res=explode(",",trim($field));
			$array=$this->getdbfields($this->name);
			foreach($res as $v){
				$key=array_keys($array,$v);
				unset($array[$key[0]]);
			
			}
			$this ->options['field']=implode(",",$array);
		}else{
			if(is_array($field)){
				foreach($field as $v){
					$f.=$this->prefixname.$v.',';
				}
				return $f;
			}else{
				$this ->options['field']=$field;
			}
		}
        return $this;
    }

    /**
     * 获取数据表字段信息
     * @access public
     * @return array
     */
    public function getdbfields($name){
        return $this->db->list_fields($name);
    }

    /**
     * 字段排序
     *
     * @param string $sort/$group
     * @return string
     */
    public function sort($field="id",$sort="order",$desc=""){
		 $this->options['sort'] =   "{$sort} by {$field} {$desc}";
        return $this;
	}

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return Model
     */
    public function limit($offset,$length=null){
        $this->options['limit'] =   is_null($length)?$offset:$offset.','.$length;
        return $this;
    }

    /**
     * 格式化条件
     *
     * @param string $array|$where
     * @return array|string
     */
    public function formatwhere($where){ 
        if (is_array($where)) { 
            foreach ($where as $key => $value) { 
                $join[] = $key.' = '.$this->db->escape($value); 
            } 
            return ' where '.join(' and ', $join); 
        } 
        return $where ? ' where '.$where : ''; 
    }
	/*
	 * 生成查询SQL
	 */
	public function buildselectsql(){
		if(isset($options['page'])){
			// 根据页数计算limit
			if(strpos($options['page'],',')){
				list($page,$listRows) = explode(',',$options['page']);
			}else{
				$page	= $options['page'];
			}

			$page		= $page?$page:1;//页码
			$listRows	= isset($listRows)?$listRows:(is_numeric($options['limit'])?$options['limit']:20); //当前页显示记录数
			$offset		= $listRows*((int)$page-1);//当前页起始记录数
			$options['limit']= $offset.','.$listRows;
		}

		$limit	= $this ->options['limit']?' limit '.$this->options['limit']:'';//解析limit
		$field	= $this ->options['field']?$this ->options['field']:'*';//解析查询字段
		$sort	= $this ->options['sort']?$this ->options['sort']:'';//解析查询字段
		$table	= $this ->options['table']?$this ->options['table']:$this->name;//解析表名
		$where	= $this ->options['where']?$this ->options['where']:'';//解析where条件
		$sql	= 'select '.$field.' from '.$table.' '.$where.' '.$sort.' '.$limit;
		//echo $sql;
		return $sql;
	}

	/**
	 * 执行语句
	 *
     * @param string $sql
	 * @return bool
	 */
	 public function excsql($sql){
        return $this->db->fetch($this->db->query($sql)); 
	 }

	/**
	 * 执行语句
	 *
     * @param string $sql
	 * @return bool
	 */
	 public function query($sql){
		if($this->db->query($sql)){
			return true;
		}else{
			return false;
		}
	 }

    /**
     * 获取最后插入ID值
     */
    public function lastinsertid(){ 
        return $this->db->insert_id(); 
    } 

    /**
     * 影响记录数
	 *
	 * @return number
     */
	public function affected_num(){
		return $this->db->affected_rows();
	}

	/**
	 * 事务
	 * program $string sting BEGIN() | ROLLBACK | COMMIT
	 */
	 public function affairs($string){
		switch($string){			
			case 'begin':		//开始一个事务
				$this->db->query('BEGIN');
			break;

			case 'commit':		//事务确认
				$this->db->query('COMMIT');
			break;

			case 'rollback':	//事务回滚
				$this->db->query('ROLLBACK');
			break;

			default:
				return throwexce('Command does not exist');
			break;
		}
	 }

    /**
     * 备份数据库
     */
    public function dbbackup(){ 

		$x=1;

		$path=DBBACKUP_PATH.date('Y-m-d_His').rand(100,999);

		$filename = date("Y-m-d_H.i.s"); //存放路径，默认存放到项目最外层
		$size=2048;

		$this->excsql("set names 'utf8'");
		$sql = "set charset utf8;\r\n";

		$q1 = $this->excsql("show tables");

		foreach(array_multi2single($q1) as $k => $table){

			$q2 = $this->excsql("show create table `$table`");

			if($q2[0]['Create Table']!=""){

			$sql ='DROP TABLE IF EXISTS `'.$q2[0]['Table'].'`'.";\r\n". $q2[0]['Create Table'].";\r\n";
				
				foreach($this->excsql("select * from `$table`") as $data){
					
					$keys = array_keys($data);

					$keys = array_map('addslashes', $keys);

					$keys = join('`,`', $keys);

					$keys = "`" . $keys . "`";

					$vals = array_values($data);

					$vals = array_map('addslashes', $vals);

					$vals = join("','", $vals);
					
					$vals = "'" . compress_html($vals) . "'";

					$sql .= "insert into `$table`($keys) values(".str_replace("''","NULL",$vals).");\r\n";

					if(filesize($path.'/'.sprintf("%05d", $x).".sql")>($size)*1024){

						$x++;

					}
						if(!fileWrite($csql.$sql,sprintf("%05d", $x).".sql",$path)){ return 0; }

						$sql="";
					}

			}else{
				unset($k);
			}
		}
		return 1;
    }

    /**
     * 恢复数据库
     */
	public function dbregain($fname) {

		$array=getFile(DBBACKUP_PATH.$fname);
		
		asort($array);

		foreach($array as $files){

			 $sql_contents = file(DBBACKUP_PATH.$fname.'/'.$files);

				foreach ($sql_contents as $line) {
					$sql_str .= $line;
					if (';' == substr(rtrim($line), -1, 1)) {
						//一条sql语句结束
						if($this->excsql(trim($sql_str))) return false;
						unset($sql_str);
						$sql_str = '';
					}

				}
//				sleep(5);
		}
		return true;
	}

    // 回调方法 初始化模型
    protected function _initialize() {}
}


?>