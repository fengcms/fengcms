<?php
require_once dirname(__DIR__).'/system/common/security.php';
cms_session_start();
header('Cache-Control: no-store');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>后台登录</title>
<link href="public/jquery/validform/css.css" rel="stylesheet" type="text/css" />
<script src="public/jquery/jquery.js"></script>
<script src="public/jquery/validform/validform.js"></script>

<style>
* { padding:0; margin:0;}
.body { padding:10% 0 0;}
.login { background:url(image/long_bg.png) repeat-x 0 0; height:443px;}
.login_box { background:url(image/login_box.jpg) no-repeat 0 0; width:527px; height:443px; margin:0 auto;}
.login_box dl { padding:160px 0 0 158px;width: auto;overflow: hidden;} 
.login_box dl dd { height:36px; width:180px; float:left;}
.login_box dl .input { border:none; background:none; width:150px; height:30px; font:bold 18px/30px Arial, Helvetica, sans-serif; display:block; float:left;}
.login_box dl .yzm { width:150px; font-size:12px;}
.login_box dl dt { float:right; width:160px;  height:120px;}
.login_box dl dt input { display:block;width:160px;  height:120px; float:right;border:none; background:none; text-indent:-99999em; cursor:pointer;}
#bodyie6 {_background: #000;}
#bodyie6 .login {_display:none;_background: none;}
#bodyie6 #le6 {line-height: 2;display: block;width: 600px;margin: 0 auto;color:#0f6;font-size: 14px;text-indent: 2em;}
#bodyie6 #le6 span {display:inline;color: #f60;}
#bodyie6 #le6 span a {display: inline;font-size: 14px;color: #f00;font-weight: bold;}

</style>
<script type="text/javascript">
$(function(){
	data=$(".form").Validform({
		tiptype:1,
		callback:function(data){
		if(data.status=="y"){
				location.href ="index.php?controller=home";
			}else if(data.status=="c"){
				alert('认证码不正确！');
			}else if(data.status=="n"){
				alert('用户名或密码不正确！');
			}
		}
	});

	$("#login").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
})
</script>
</head>

<body class="body" id="bodyie6">
<!--[if IE 6]><div id="le6">
	后台不支持 IE 6，请使用现代浏览器访问。<span>如需反馈问题，请访问 <a href="https://github.com/fengcms/fengcms/issues" target="_blank" rel="noopener noreferrer">GitHub Issues</a>。</span>
</div><![endif]-->
<form method="post" action="index.php?controller=admin" class="form">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
<div class="login">
	
	<div class="login_box">
    	<dl>
        	<dt><input type="button" id="login" value="登录" /></dt>
        	<dd><input type="text" name="user" id="user" class="input inputxt" datatype="*5-255" nullmsg="请输入用户名" errormsg="用户名至少5个字符！" /></dd>
            <dd><input type="password" name="password" id="password" class="input inputxt" datatype="*3-255" errormsg="密码至少5个字符！"/><em></em></dd>
            <dd><input type="text" name="admincode" id="admincode" class="input yzm inputxt" maxlength="128" datatype="*4-128" autocomplete="off" title="后台认证码" errormsg="请输入后台认证码！" /></dd>
        </dl>
    </div>
</div>
</form>

</body>
</html>
