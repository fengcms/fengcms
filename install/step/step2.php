<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>FengCms安装程序</title>
<meta name="description" content="">
<meta name="keywords" content="">
<meta name="generator" content="FengCms">
<meta name="author" content="FengCms">
<link href="images/css.css" rel="stylesheet">
</head>
<body>
<div id="stepbox">
	<div id="buzhou">
		<h1>FengCms安装程序</h1>
		<span>阅读协议</span>
	</div>
	<div id="stepinfo">
		<div id="steptable">
			<table cellpadding="0" cellspacing="0">
			  <tr>
				<th class="col1">检查项目</th>
				<th class="col2">当前环境</th>
				<th class="col3">FengCms建议</th>
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
				<td>mysql或mysqli必须开启其一</td>
				<td><?php if(extension_loaded('mysql')){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;</font><?php }?></td>
			  </tr>

			  <tr>
				<td>MYSQLI 扩展</td>
				<td><?php if(extension_loaded('mysqli')){ ?>√<?php }else{ ?>×<?php }?></td>
				<td>mysql或mysqli必须开启其一</td>
				<td><?php if(extension_loaded('mysqli')){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;</font><?php }?></td>
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
				<td>配置文件</td>
				<td><?php if($is_config){ ?>√<?php }else{ ?>×<?php }?></td>
				<td>必须设置正确</td>
				<td><?php if($is_config){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;检查config.php目录是否开启777权限</font><?php }?></td>
			  </tr>
			  
			  <tr>
				<td>上传目录</td>
				<td><?php if($is_upload){ ?>√<?php }else{ ?>×<?php }?></td>
				<td>必须设置正确</td>
				<td><?php if($is_upload){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;检查/upload/目录是否开启777权限</font><?php }?></td>
			  </tr>
			  
			  <tr>
				<td>模块导出</td>
				<td><?php if($is_module){ ?>√<?php }else{ ?>×<?php }?></td>
				<td>必须设置正确</td>
				<td><?php if($is_module){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;检查/upload/module目录是否开启777权限</font><?php }?></td>
			  </tr>
			  <tr>
				<td>前台缓存</td>
				<td><?php if($is_cache){ ?>√<?php }else{ ?>×<?php }?></td>
				<td>必须设置正确</td>
				<td><?php if($is_cache){ ?><span><img src="images/correct.gif" /></span><?php }else{ ?><font class="red"><img src="images/error.gif" />&nbsp;检查/app/cache目录是否开启777权限</font><?php }?></td>
			  </tr>
				
			</table>
		</div>
	</div>
	<div id="stepsub">
		<form id="install" method="get">
		<?php if(!(extension_loaded('mysql') || extension_loaded('mysqli'))){ ?>

		<span class="tishi">MYSQL或MYSQLI必须开启一个</span>

		<?php }elseif(phpversion() < '5.2.0'){ ?>

		<span class="tishi">PHP版本太低，推荐PHP5.2以上版本</span>

		<?php }elseif(!$is_config){ ?>

		<span class="tishi">配置文件不可写，将/config.php 文件权限设置为 777</span>

		<?php }elseif(!$is_upload ){ ?>

		<span class="tishi">上传目录不可写，将/upload/ 文件夹权限设置为 777</span>

		<?php }elseif(!$is_module){ ?>

		<span class="tishi">模型导出目录不可写，将/upload/module/ 文件夹权限设置为 777</span>

		<?php }elseif(!$is_cache){ ?>

		<span class="tishi">前台缓存目录不可写，将/app/cache/ 文件夹权限设置为 777</span>

		<?php }else{?>

		<input type="submit"  class="submit2"  value="下一步">

		<?php }?>

		<input type="hidden" name="step" value="3">

		</form>
	</div>
</div>
<div class="none"><script src="http://s17.cnzz.com/stat.php?id=5760804&web_id=5760804" language="JavaScript"></script>
</body>
</html>