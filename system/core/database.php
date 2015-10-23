<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//	数据库基类

defined('TPL_INCLUDE') or die('Restricted access'); 
 

abstract class database{

    public $link_id		= 0;
	public $query_id	= -1;

    /**
     * 类构造
     * @access abstract
     * @param array $config
     * @return void
     */
    abstract function __construct($host,$user,$pass,$name);

    /**
     * 连接
     * @access abstract
     * @return void
     */
    abstract function connect($host,$user,$pass);

    /**
     * 执行SQL语句
     * @access abstract
     * @param string $sql
     * @return void
     */
    abstract function query($sql);

    /**
     * 转义字符串
     * @access abstract
     * @abstract
     * @param mixed $value
     * @return string
     */
    abstract function escape($value);

    /**
     * 关闭链接
     * @access abstract
     * @abstract
     * @return void
     */
    abstract function close();

    /**
     * 类析构
     *
     * @abstract
     * @return void
     */
    abstract function __destruct();

}



?>