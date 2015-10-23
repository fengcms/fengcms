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
			<script src="images/jquery.min.js"></script>
			<script type="text/javascript">
			$(function(){
				$("#submit").click(function(){
					if($("#host").val()==""){
						alert("请填写主机信息！");
						return false;
					}else if($("#user").val()==""){
						alert("请填写数据库链用户名！");
						return false;
					}else if($("#dbname").val()==""){
						alert("请填写数据库名称！");
						return false;
					}else if($("#prefix").val()==""){
						alert("请填写数据表前缀！");
						return false;
					}
				});
			});
			</script>
			<form id="install" method="get" class=".form">
			<table cellpadding="0" cellspacing="0" class="tablemysql">
			  <tr>
				<th class="w120">数据库主机：</th>
				<td><input type="text" class="input" name="host" id="host" value="localhost"></td>
				
			  </tr>
			  <tr>
				<th>数据库用户名：</th>
				<td><input type="text" class="input" name="user" id="user" value=""></td>
				
			  </tr>
			  <tr>
				<th>数据库密码：</th>
				<td><input type="text" class="input" name="password" id="password" value=""></td>
				
			  </tr>
			  <tr>
				<th>数据库名称</th>
				<td><input type="text" class="input" name="dbname" id="dbname" value=""></td>
				
			  </tr>
			  <tr>
				<th>数据表前缀</th>
				<td><input type="text" class="input" name="prefix" id="prefix" value="f_"></td>
				
			  </tr>
			  <tr>
				<th>是否开启伪静态</th>
				<td><label><input type="radio" name="url_type" value="1" checked="checked" /> 开启伪静态 </label> <label><input type="radio" name="url_type" value="0" /> 关闭伪静态</label></td>
				
			  </tr>
			</table>
		</div>	
	</div>
	<div id="stepsub">
		
		<input type="submit" class="submit2" value="下一步" id="submit">
		<input type="hidden" name="step" value="4">
		</form>
	</div>
</div>
<div class="none"><script src="http://s17.cnzz.com/stat.php?id=5760804&web_id=5760804" language="JavaScript"></script>
</body>
</html>