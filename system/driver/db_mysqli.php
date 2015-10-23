<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//	MYSQL 类

defined('TPL_INCLUDE') or die( 'Restricted access'); 

class db_mysqli extends database{
	
	private $host	= "localhost";

	private $user	= "root";

	private $pass	= "";

	public  $name	= "";

    /**
     * 初始化连接
     *
     * @param array $config          数据库设置
     * @return void
     */
    public function __construct($host,$user,$pass,$name){

		if(!function_exists('mysqli_query')){
			return throwexce(sprintf('Your PHP installation appears to be missing the %s extension which is required by Worker.', 'MySQLI'));
		}

		$host=$host?$host:$this->host;
		$user=$user?$user:$this->user;
		$pass=$pass?$pass:$this->pass;
		$name=$name?$name:$this->name;

		if(!$this->is_links()){
			$this->connect($host,$user,$pass);
			$this->select_db($name);
		}
	}

    /**
     * 连接服务器
     *
     *  @return bool|void
     */
    public function connect($host,$user,$pass){
	  $this->link_id=mysqli_connect($host,$user,$pass);
	  if(!$this->link_id){
			return throwexce(sprintf('%s connection failed: %s (%u)', 'MySQLI',mysqli_error(),mysqli_errno()));
	  }
	}

    /**
     * 选择数据库
     *
     * @param string $db (optional)
     * @return bool|void
     */
	public function select_db($name){
        if (!mysqli_select_db($this->link_id,$name)) {
            return throwexce(sprintf('%s name not found!',$name));
        }else{
			if (mysqli_character_set_name($this->link_id) != 'utf8'){
				$this->query("SET NAMES utf8;");
			}
				return true;
			}
	}

    /**
     * 指定函数执行SQL语句
     *
     * @param string $sql	sql语句
     * @return resource
     */
    public function query($sql){

		// 验证连接是否正确
        if (!$this->is_links())return throwexce(sprintf('Supplied argument is not a valid MySQLI-Link resource.'));
		$this->query_id = mysqli_query($this->link_id,$sql);
		if (!$this->query_id){
            return throwexce(sprintf('Invalid SQL:'.$sql));
		}
		$query_id=$this->query_id;
		$this->free_result();

	    return $query_id;
	}

    /**
     * 列出表里的所有字段
     *
     * @param string $table 表名
     * @return array
     */
    public function list_fields($table){
        $result = array();
        $res = $this->query(sprintf("show columns from `%s`;", $table));
        foreach($this->fetch($res) as $rs){
        	$result[] = $rs['Field'];
        }
        return $result;
    }

    /**
     * 取得数据集的记录
     *
     * @param resource  $result
     * @param int       $mode
     * @return array
     */
    public function fetch($result,$mode=1){
        switch (intval($mode)) {
            case 0: $mode = MYSQLI_NUM;break;
            case 1: $mode = MYSQLI_ASSOC;break;
            case 2: $mode = MYSQLI_BOTH;break;
        }
		while($row=mysqli_fetch_array($result,$mode)){
			$array[]=$row;
		}
        return @$array;
    }

    /**
     * 取得单数据的记录
     *
     * @param resource  $result
     * @return array
     */
	public function assoc($result){
		return mysqli_fetch_assoc($result);
	}

	/**
     * 转义SQL语句
     *
     * @param mixed $value
     * @return string
     */
    public function escape($value){

        // 空
        if ($value === null) return '';
		// 去除斜杠
		if (get_magic_quotes_gpc()){
			$value = stripslashes($value);
		  }

		// 如果不是数字则加引号
		if (!is_numeric($value)){

			$value = '"' . addslashes($value) . '"';

		  }

		return $value;
		}

	/**
     * 验证链接
     *
     * @param mixed $value
     * @return string
     */
    public function is_links(){
        // 验证连接是否正确
        if (0 == $this->link_id ) {
            return false;
        }else{
			return true;
		}
	}
 
    /**
     * 获取操作影响记录数
     */
    public function affected_rows(){ 
        return mysqli_affected_rows($this->link_id); 
    } 

    /**
     * 获取最后插入ID值
     */
    public function insert_id(){ 
        return mysqli_insert_id($this->link_id); 
    } 

    /**
     * 释放内存
     *
     * @return bool
     */
	public function free_result($result){
		if(mysqli_free_result($result)){
			return true;
		}else{
			return false;
		}
	}

    /**
     * 关闭 MySQLI 连接
     *
     * @return bool
     */
    public function close(){
        if (is_resource($this->link_id)) {
            return mysqli_close($this->link_id);
        }
    }

    /**
     * 类析构
     * 
     * @return void
     */
    public function __destruct(){
		$this->query_id=-1;
    	$this->close();
    }
}



?>