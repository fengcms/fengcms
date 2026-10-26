<?php if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } ?>
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
		<div id="read">
			<p>FengCMS 自有代码与文档采用 MIT License，允许商业使用、修改和再发布；分发时须保留版权及许可声明。第三方组件遵循各自许可证。</p>
			<pre><?php echo htmlspecialchars((string)file_get_contents(dirname(__DIR__, 2).'/LICENSE'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
		</div>
	</div>
	<div id="stepsub">
		<form id="install" method="get">
		<input type="submit" class="submit1" value="开始安装">
		<input type="hidden" name="step" value="2">
		</form>
	</div>
</div>
<div class="none"><script src="http://s17.cnzz.com/stat.php?id=5760804&web_id=5760804" language="JavaScript"></script>
</body>
</html>