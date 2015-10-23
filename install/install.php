<?php  
header("Content-type:text/html;charset=utf-8"); //看你用的是什么编码，要保持一致。  
define("TPL_INCLUDE",1);

// 定义当前路径
define('ABS_PATH',dirname(__FILE__).'/');

$files=ABS_PATH."config.php";  //要写入的配置文件。  
if(!is_writable($files)){    //判断是否有可写的权限，linux操作系统要注意这一点，windows不必注意。  
    echo "<font color=red>文件不可写</font>";  
    exit();  
}
   if($_GET['step']=='2'){
?>
	 <table cellpadding="0" cellspacing="0" class="table_list">
                  <tr>
                    <th class="col1">检查项目</th>
                    <th class="col2">当前环境</th>
                    <th class="col3">PHPCMS 建议</th>
                    <th class="col4">功能影响</th>
                  </tr>
                  <tr>
                    <td>操作系统</td>
                    <td><?php echo php_uname();?></td>
                    <td>Windows_NT/Linux/Freebsd</td>
                    <td><span><img src="images/correct.gif" /></span></td>
                  </tr>
                  <tr>
                    <td>WEB 服务器</td>
                    <td><?php echo $_SERVER['SERVER_SOFTWARE'];?></td>
                    <td>Apache/Nginx/IIS</td>
                    <td><span><img src="images/correct.gif" /></span></td>
                  </tr>
                  <tr>
                    <td>PHP 版本</td>
                    <td>PHP <?php echo phpversion();?></td>
                    <td>PHP 5.2.0 及以上</td>
                    <td><?php if(phpversion() >= '5.2.0'){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;无法安装</font><?php }?></font></td>
                  </tr>
                  <tr>
                    <td>MYSQL 扩展</td>
                    <td><?php if(extension_loaded('mysql')){ ?>√<?php }else{ ?>×<?php }?></td>
                    <td>必须开启</td>
                    <td><?php if(extension_loaded('mysql')){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;无法安装</font><?php }?></td>
                  </tr>
                  
                  <tr>
                    <td>ICONV/MB_STRING 扩展</td>
                    <td><?php if(extension_loaded('iconv') || extension_loaded('mbstring')){ ?>√<?php }else{ ?>×<?php }?></td>
                    <td>必须开启</td>
                    <td><?php if(extension_loaded('iconv') || extension_loaded('mbstring')){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;字符集转换效率低</font><?php }?></td>
                  </tr>
                  
                  <tr>
                    <td>JSON扩展</td>
                    <td><?php if($PHP_JSON){ ?>√<?php }else{ ?>×<?php }?></td>
                    <td>必须开启</td>
                    <td><?php if($PHP_JSON){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;不只持json,<a href="http://pecl.php.net/package/json" target="_blank">安装 PECL扩展</a></font><?php }?></td>
                  </tr>
                  <tr>
                    <td>GD 扩展</td>
                    <td><?php if($PHP_GD){ ?>√ （支持 <?php echo $PHP_GD;?>）<?php }else{ ?>×<?php }?></td>
                    <td>建议开启</td>
                    <td><?php if($PHP_GD){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;不支持缩略图和水印</font><?php }?></td>
                  </tr>                                    
                  <tr>
                    <td>ZLIB 扩展</td>
                    <td><?php if(extension_loaded('zlib')){ ?>√<?php }else{ ?>×<?php }?></td>
                    <td>建议开启</td>
                    <td><?php if(extension_loaded('zlib')){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;不支持Gzip功能</font><?php }?></td>
                  </tr>
                  <tr>
                    <td>FTP 扩展</td>
                    <td><?php if(extension_loaded('ftp')){ ?>√<?php }else{ ?>×<?php }?></td>
                    <td>建议开启</td>
                    <td><?php if(extension_loaded('ftp')){ ?><span><img src="images/correct.gif" /></span><?php }elseif(ISUNIX){ ?><font class="red"><img src="images/error.gif" />&nbsp;不支持FTP形式文件传送</font><?php }?></td>
                  </tr>
                                    
                  <tr>
                    <td>allow_url_fopen</td>
                    <td><?php if(ini_get('allow_url_fopen')){ ?>√<?php }else{ ?>×<?php }?></td>
                    <td>建议打开</td>
                    <td><?php if(ini_get('allow_url_fopen')){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;不支持保存远程图片</font><?php }?></td>
                  </tr>
				  
				  <tr>
                    <td>fsockopen</td>
                    <td><?php if(function_exists('fsockopen')){ ?>√<?php }else{ ?>×<?php }?></td>
                    <td>建议打开</td>
                    <td><?php if($PHP_FSOCKOPEN=='1'){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;不支持fsockopen函数</font><?php }?></td>
                  </tr>
				  
                  <tr>
                    <td>DNS解析</td>
                    <td><?php if($PHP_DNS){ ?>√<?php }else{ ?>×<?php }?></td>
                    <td>建议设置正确</td>
                    <td><?php if($PHP_DNS){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;不支持采集和保存远程图片</font><?php }?></td>
                  </tr>
                </table>
<?php
   }
//$file = fopen($files, "w");   //以写入的方式打开config.php这个文件。

if($_POST['install']){  //获取用户提交的数据。  
$host=$_POST['host'];  
$user=$_POST['user'];  
$password=$_POST['password'];  
$dbname=$_POST['dbname'];  

if(!$conn=@mysql_connect($host,$user,$password)){  
       echo "连接数据库失败！请返回上一页检查连接参数 <a href='javascript:history.go(-1)' mce_href='javascript:history.go(-1)'><font color=#ff0000>返回修改</font></a>";  
       exit();  
}else{  
  mysql_query("set names gb2312");  //设置数据库的编码，注意要与前面一致。  
   if(!mysql_select_db($dbname,$conn)){   //如果数据库不存在，我们就进行创建。  
  
         if(!mysql_query($dbsql)){  
           echo "创建数据库失败，请确认是否有足够的权限！<a href='javascript:history.go(-1)' mce_href='javascript:history.go(-1)'><font color=#ff0000>返回修改</font></a>";  
           exit();  
          }  
   }

echo file_get_contents($files);

  exit;
	$file = fopen($files, "w");   //以写入的方式打开config.php这个文件。  
	fwrite($file,$config);  //将配置信息写入config.php文件。  
	fclose($file);  
	include_once(ABS_PATH."config.php");   //导入配置信息.  

//下面根据你实际的表的结构跟初始化表的数据来写，这些sql语句，我们在导出时可以找到。  
   //新建一个表test1  
   $sql_query[] = "CREATE TABLE `test1` (     
                 `id` int(4) NOT NULL auto_increment,  
                 `name` varchar(20) character set gb2312 NOT NULL,  
                 `major` varchar(40) character set gb2312 NOT NULL,  
                  PRIMARY KEY  (`id`)  
                  ) ENGINE=InnoDB  DEFAULT CHARSET=gb2312 AUTO_INCREMENT=1;";  
    //新建一个表test2  
    $sql_query[] = "CREATE TABLE `test2` (     
                 `id` int(4) NOT NULL auto_increment,  
                 `name` varchar(20) character set gb2312 NOT NULL,  
                 `major` varchar(40) character set gb2312 NOT NULL,  
                  PRIMARY KEY  (`id`)  
                  ) ENGINE=InnoDB  DEFAULT CHARSET=gb2312 AUTO_INCREMENT=1;";  
     //为test1表默认初始化一些数据。  
     $sql_query[]="INSERT INTO `test1` (`name`, `major`) VALUES('张三','电子商务')";  
     foreach($sql_query as $sql){  
            if(!mysql_query($sql)){      //依次执行以上的sql语句，就是创建表和初始化数据。  
            echo "创建表失败或者初始化数据失败";  
            exit();  
           }  
     }  
     mysql_close();  
     echo "安装成功";//可以做一个跳转到首页。  
     exit();  
}  
}  


?>  
<html>  
<head><title>php安装程序的基本原理</title></head>  
<body>  
<form action="install.php?" method="post">  
填写主机：<input type="text" name="host">本地主机为localhost<br />  
连接数据库的用户名：<input type="text" name="user"><br />  
连接数据库的密码：<input type="text" name="password"><br />  
要创建的数据库名：<input type="text" name="dbname"><br />  
<input type="submit" name="install" value="安装">  
</form>  
</body>  
</html>  