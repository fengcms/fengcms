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

#[AllowDynamicProperties]
class model{

	private	 $db = "";
    private bool $transactionConnection = false;
    private static int $requestWriteRevision = 0;
    
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
        

		// Reject table identifiers before they can reach SQL.
        foreach (explode(',', $this->name) as $table) { self::identifier($table); }
        // 数据库初始化
		$classname='db_'.DB_TYPE;
        $this->db = new $classname(DB_HOST, DB_USER, DB_PASS, DB_NAME, true);
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
        return $this->db->fetch($this->db->query($this->buildselectsql(), $this->options['params'] ?? []));
	}

    /**
     * 获取单条数据组
     *
     * @return array
     */
    public function getone(){
        return $this->db->assoc($this->db->query($this->buildselectsql(), $this->options['params'] ?? []));
	}
 
    /**
     * 获取记录统计数
	 *
     * @return	number
     */
    public function getcount(){
        $originalOptions = $this->options;
        $countField = !empty($originalOptions['field']) ? 'count('.$originalOptions['field'].')' : 'count(id)';
        $this->options['field'] = $countField;
        unset($this->options['sort'], $this->options['limit'], $this->options['page']);
        try {
            $result = $this->db->fetch($this->db->query($this->buildselectsql(), $this->options['params'] ?? []));
            return $result[0][$countField];
        } finally {
            $this->options = $originalOptions;
        }
    }

	/**
     * 插入数据
	 *
     * @param array $row
	 * @return bool|void
     */
    public function insert($row){
        if (!is_array($row)) { return false; }
        unset($row['_csrf']);
        if (!$row) { return false; }
        $cols = array_map([self::class, 'identifier'], array_keys($row));
        $sql = 'INSERT INTO `'.$this->name.'` ('.implode(',', $cols).') VALUES ('.implode(',', array_fill(0, count($row), '?')).')';
        $this->db->query($sql, array_values($row));
        self::$requestWriteRevision++;
        return $this->lastinsertid();
    }


	/**
     * 修改数据
	 *
     * @param array $row
	 * @return bool|void
     */
    public function update($row){
        $params = [];
        if (is_array($row)) {
            unset($row['_csrf']);
            if (!$row) { return false; }
            $vals = [];
            foreach ($row as $key => $value) { $vals[] = self::identifier($key).' = ?'; $params[] = $value; }
            $values = implode(',', $vals);
        } else { $values = $row; }
        $this->db->query('UPDATE '.$this->name.' SET '.$values.($this->options['where'] ?? ''), array_merge($params, $this->options['params'] ?? []));
        self::$requestWriteRevision++;
        return $this->affected_num();
    }


    /**
     * 删除记录
	 *
	 * @return bool|void
     */
    public function delete(){ 
        $this->db->query('delete from '.$this->name.($this->options['where'] ?? ''), $this->options['params'] ?? []);
        self::$requestWriteRevision++;
		return $this->db->affected_rows();		
    }

    /**
     * 删除记录
	 *
	 * @return bool|void
     */
    public function droptable(){ 
		$this->db->query('drop table '.self::identifier($this->name));
        self::$requestWriteRevision++;
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
        self::$requestWriteRevision++;
		return $this->db->affected_rows();	
	 }

    /**
     * 建立字段
	 *
	 * @return bool|void
     */
	 public function alter($tables,$field,$after="id",$type="varchar",$length=255){

		$this->db->query('ALTER TABLE `'.DB_PREFIX.$tables.'` ADD COLUMN `'.$field.'` '.$type.'('.$length.') NULL DEFAULT NULL AFTER `'.$after.'`;');
        self::$requestWriteRevision++;
		return $this->db->affected_rows();	
	 }

    /**
     * 删除字段
	 *
	 * @return bool|void
     */
    public function dropalter($fiend){ 
        $this->db->query('ALTER TABLE '.self::identifier($this->name).' DROP COLUMN '.self::identifier($fiend));
        self::$requestWriteRevision++;
		return $this->db->affected_rows();		
    }

    /**
     * 获取条件
     *
     * @param string $where
     * @return string
     */
    public function where($where="", array $params = []){
		 $this->options['where'] =   isset($where)?$this->formatwhere($where):"";
        if (!is_array($where)) { $this->options['params'] = $params; }
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
				$this->options['field']=implode(',',array_map([self::class,'identifier'],$field));
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
        $this->options['params'] = [];
        if (is_array($where)) {
            $join = [];
            foreach ($where as $key => $value) {
                $join[] = self::identifier($key).' = ?';
                $this->options['params'][] = $value;
            }
            return $join ? ' WHERE '.implode(' AND ', $join) : '';
        }
        return $where ? ' WHERE '.$where : '';
    }

    public static function identifier($name){
        if (!is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
            throw new InvalidArgumentException('Invalid SQL identifier.');
        }
        return '`'.$name.'`';
    }


	/*
	 * 生成查询SQL
	 */
	public function buildselectsql(){
		$options=$this->options;
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
			$this->options['limit']= $offset.','.$listRows;
		}

		$limit	= !empty($this ->options['limit'])?' limit '.$this->options['limit']:'';//解析limit
		$field	= $this ->options['field']??'*';//解析查询字段
		$sort	= $this ->options['sort']??'';//解析查询字段
		$table	= $this ->options['table']??$this->name;//解析表名
		$where	= $this ->options['where']??'';//解析where条件
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
	 public function excsql($sql, array $params = []){
        $this->isolateForSessionSql($sql);
        return $this->db->fetch($this->db->query($sql, $params));
	 }

	/**
	 * 执行语句
	 *
     * @param string $sql
	 * @return bool
	 */
	 public function query($sql, array $params = []){
        $this->isolateForSessionSql($sql);
		if($this->db->query($sql, $params)){
            if (preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b/i', $sql)) {
                self::$requestWriteRevision++;
            }
			return true;
		}else{
			return false;
		}
	 }

    private function isolateForSessionSql(string $sql): void {
        if (preg_match('/^\s*(?:BEGIN|START\s+TRANSACTION|SET|LOCK\s+TABLES|UNLOCK\s+TABLES|USE)\b/i', $sql)) {
            $this->isolateConnection();
        }
    }

    private function isolateConnection(): void {
        if ($this->transactionConnection) { return; }
        $classname = 'db_'.DB_TYPE;
        $this->db = new $classname(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $this->transactionConnection = true;
    }

    /** Invalidate request-local read snapshots after model writes. */
    public static function requestWriteRevision(): int {
        return self::$requestWriteRevision;
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
                // Transactions keep their own connection, as they did before
                // ordinary model queries began sharing a request connection.
                $this->isolateConnection();
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

    // 回调方法 初始化模型
    protected function _initialize() {}
}


?>
