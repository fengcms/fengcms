<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>System error</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
<style type="text/css">
*{margin:0; padding:0;}
body{font:12px Verdana; text-align:center;}
a{text-decoration:none;color:#174B73;}
a:hover{ text-decoration:none;color:#FF6600;}
#notice{margin:0 auto; text-align:left; margin:10px; padding:10px; background:#e6e6e6;}
#notice p{padding:10px; line-height:20px;}
#notice p span{}
#notice h1{font-size:20px; padding:10px;}
</style>
</head>
<body>
<div id="notice">
	<h1>Message	：<?php  echo $Message?></h1>
	<p>File：<?php echo $File; ?></p>
	<p>Time：<?php echo date('Y-m-d h:i:s') ?></p>
	<p>Lines：<?php echo $Line; ?></p>
</div>
</body>
</html>
