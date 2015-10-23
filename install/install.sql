DROP TABLE IF EXISTS `#prefix_article`;
CREATE TABLE `#prefix_article` (
  `id` int(11) NOT NULL auto_increment,
  `hits` int(11) default '0',
  `date` date default NULL,
  `template` varchar(255) default NULL,
  `origin` varchar(255) default NULL,
  `inputer` varchar(255) default NULL,
  `author` varchar(255) default NULL,
  `tags` varchar(255) default NULL,
  `attrib_j` int(1) default '0',
  `attrib_g` int(1) default '0',
  `attrib_t` int(1) default '0',
  `attrib_r` int(1) default '0',
  `attrib_d` int(1) default '0',
  `attrib_h` int(1) default '0',
  `classid` varchar(255) default NULL,
  `title` varchar(255) default NULL COMMENT '标题',
  `info` text COMMENT '导读',
  `content` longtext COMMENT '内容',
  `img` text COMMENT '图片',
  `status` int(1) default '1',
  `time` int(11) default NULL,
  `html` varchar(255) default NULL COMMENT 'HTML',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='文章系统';
DROP TABLE IF EXISTS `#prefix_author`;
CREATE TABLE `#prefix_author` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(50) default NULL COMMENT '名称',
  `url` varchar(255) default NULL COMMENT '地址',
  `hits` int(11) default '1' COMMENT '使用热度',
  `time` int(11) default NULL COMMENT '添加日期',
  `status` int(1) default '1' COMMENT '状态（-1回收站、0未发布、1发布）',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='作者';
DROP TABLE IF EXISTS `#prefix_base`;
CREATE TABLE `#prefix_base` (
  `name` varchar(255) default NULL COMMENT '网站名称',
  `title` varchar(255) default NULL COMMENT '网站标题',
  `domran` varchar(255) default NULL COMMENT '网站域名',
  `copyright` text COMMENT '网站版权',
  `key` varchar(255) default NULL COMMENT '网站关键词',
  `description` varchar(255) default NULL COMMENT '网站描述',
  `template` varchar(255) default NULL COMMENT '模板',
  `time` varchar(15) default NULL COMMENT '修改时间'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='网站基本';
insert into `#prefix_base` VALUES ('FengCms','FengCms 打造最简单的网站内容管理系统','www.fengcms.com','Copyright 20013-2015 Powered by FengCms.COM，地方网络工作室 All Rights Reserved.<br />地方网络工作室 版权所有 未经许可严禁复制或建立镜像 <br />版本号：FengCms Beta 1.0','测试,文章系统,文章','我的站描述','/index.html','1383976418');
DROP TABLE IF EXISTS `#prefix_classify`;
CREATE TABLE `#prefix_classify` (
  `id` int(11) NOT NULL auto_increment,
  `topid` int(11) default '0' COMMENT '顶层栏目ID',
  `classid` varchar(255) default '0' COMMENT '栏目ID',
  `type` varchar(20) default '' COMMENT '栏目类型',
  `nav` int(1) default '1' COMMENT '导航是否显示',
  `project` varchar(30) default '' COMMENT '项目',
  `sort` int(11) default '0' COMMENT '排序',
  `name` varchar(50) default NULL COMMENT '栏目名称',
  `enname` varchar(50) default NULL COMMENT '英文名称',
  `intro` varchar(255) default NULL COMMENT '栏目介绍',
  `img` text COMMENT '栏目图片',
  `key` varchar(255) default NULL COMMENT '栏目关键词',
  `description` text COMMENT '栏目描述',
  `channel_template` varchar(255) default NULL COMMENT '频道模板',
  `classify_template` varchar(255) default NULL COMMENT '栏目模板',
  `content_template` varchar(255) default NULL COMMENT '文章内容模板',
  `url_type` int(1) default '0' COMMENT '链接方式（0当前，1弹出）',
  `time` int(11) default NULL COMMENT '操作时间',
  `html` varchar(255) default NULL COMMENT '栏目url',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='栏目系统';
DROP TABLE IF EXISTS `#prefix_complier`;
CREATE TABLE `#prefix_complier` (
  `id` int(11) NOT NULL auto_increment,
  `type` int(1) default '1' COMMENT '类型（1、作者|2、编辑|3、来源）',
  `name` varchar(50) default NULL COMMENT '名称',
  `url` varchar(255) default NULL COMMENT '地址',
  `hits` int(11) default '1' COMMENT '使用热度',
  `time` int(11) default NULL COMMENT '添加日期',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='编辑';
DROP TABLE IF EXISTS `#prefix_friend`;
CREATE TABLE `#prefix_friend` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL COMMENT '名称',
  `url` varchar(255) default NULL,
  `status` int(1) default '1',
  `time` int(11) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='友情链接';
DROP TABLE IF EXISTS `#prefix_inputer`;
CREATE TABLE `#prefix_inputer` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(50) default NULL COMMENT '名称',
  `url` varchar(255) default NULL COMMENT '地址',
  `hits` int(11) default '1' COMMENT '使用热度',
  `time` int(11) default NULL COMMENT '添加日期',
  `status` int(1) default '1' COMMENT '状态（-1回收站、0未发布、1发布）',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='编辑';
DROP TABLE IF EXISTS `#prefix_manage`;
CREATE TABLE `#prefix_manage` (
  `id` int(11) NOT NULL auto_increment,
  `admin` varchar(255) default NULL COMMENT '用户名',
  `password` varchar(32) default NULL COMMENT '密码',
  `datetime` datetime default NULL COMMENT '登录时间',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='管理员';
insert into `#prefix_manage` VALUES (1,'admin','c56d0e9a7ccec67b4ea131655038d604','2013-09-27 15:57:35');
DROP TABLE IF EXISTS `#prefix_module`;
CREATE TABLE `#prefix_module` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(50) default NULL COMMENT '名称',
  `project` varchar(30) default NULL COMMENT '数据表名',
  `type` int(2) default '1' COMMENT '类型（0系统、1字定义）',
  `item` varchar(50) default NULL COMMENT '项目',
  `unit` varchar(10) default NULL COMMENT '单位',
  `description` varchar(255) default NULL COMMENT '描述',
  `recover` int(1) default '0' COMMENT '回收站（0未启用，1启用）',
  `search` int(1) default '0' COMMENT '搜索（0未启用，1已启用）',
  `status` int(1) default '1' COMMENT '状态（-1未开启，0已开启，1系统）',
  `time` int(11) default NULL COMMENT '时间',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='模型';
insert into `#prefix_module` VALUES (1,'单页系统','single',0,'单页','页','-',0,0,1,1383190188),(2,'关键词管理','tags',0,'关键词','条','系统自带关键词',0,0,1,1382423598),(3,'作者管理','author',0,'作者','条','系统自带作者',0,0,1,1382423598),(4,'编辑管理','inputer',0,'编辑','条','系统自带编辑',0,0,1,1382423598),(5,'来源管理','origin',0,'来源','条','系统自带来源',0,0,1,1382423598),(6,'友情链接','friend',0,'链接','条','-',0,0,1,1383891991),(7,'文章系统','article',1,'文章','篇','-',1,1,1,1384154903);
DROP TABLE IF EXISTS `#prefix_module_field`;
CREATE TABLE `#prefix_module_field` (
  `id` int(11) NOT NULL auto_increment,
  `module_id` int(11) default NULL COMMENT '模块ID',
  `sort` int(11) default '0' COMMENT '排序',
  `name` varchar(50) default NULL COMMENT '名称',
  `aliases` varchar(50) default NULL COMMENT '别名',
  `type` int(11) default NULL COMMENT '类型',
  `defaults` varchar(255) default NULL COMMENT '默认',
  `must` int(1) default '0' COMMENT '是否为必填',
  `length` varchar(50) default '0-255' COMMENT '限制长度',
  `enable` int(1) default '0' COMMENT '后台是否启用',
  `search` int(1) default '0' COMMENT '是否启用检索（0不启用，1启用）',
  `nullmsg` varchar(100) default NULL COMMENT '为空提示',
  `errormsg` varchar(100) default NULL COMMENT '格式错误提示',
  `time` int(11) default NULL COMMENT '操作时间',
  `status` int(1) default '1' COMMENT '是否启用',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=54 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='字段';
insert into `#prefix_module_field` VALUES (1,1,1,'title','标题',1,NULL,1,'0-255',1,1,NULL,NULL,1383190405,1),(2,1,2,'classid','栏目',2,NULL,0,'0-255',1,0,NULL,NULL,1383190405,1),(3,1,3,'tags','关键词',4,NULL,0,'0-255',0,0,NULL,NULL,1383190405,1),(4,1,4,'content','内容',9,NULL,1,'0-255',0,0,NULL,NULL,1383190405,1),(5,1,5,'template','模板',11,NULL,0,'0-255',0,0,NULL,NULL,1383190405,1),(6,1,6,'date','日期',12,NULL,0,'0-255',0,0,NULL,NULL,1383190405,1),(7,1,7,'status','状态',15,'1',0,'1-1',1,0,NULL,NULL,1383190405,1),(8,1,8,'html','地址',16,NULL,0,'0-255',0,0,NULL,NULL,1383190405,1),(9,2,1,'name','名称',17,NULL,1,'2-50',1,0,NULL,NULL,NULL,1),(10,2,2,'hits','热度',14,'1',0,'0-255',0,0,NULL,NULL,NULL,1),(11,2,3,'time','时间',13,NULL,0,'0-255',0,0,NULL,NULL,NULL,1),(12,2,4,'status','状态',15,'1',0,'1-1',0,0,NULL,NULL,NULL,1),(13,3,1,'name','名称',17,NULL,1,'2-30',1,0,NULL,NULL,NULL,1),(14,3,2,'url','链接',24,'http://',0,NULL,1,0,NULL,NULL,NULL,1),(15,3,3,'hits','热度',14,NULL,0,'0-255',0,0,NULL,NULL,NULL,1),(16,3,4,'time','时间',13,NULL,0,'0-255',0,0,NULL,NULL,NULL,1),(17,3,5,'status','状态',15,'1',0,'1-1',0,0,NULL,NULL,NULL,1),(18,4,1,'name','名称',17,NULL,1,'2-30',1,0,NULL,NULL,NULL,1),(19,4,2,'url','链接',24,'http://',0,NULL,1,0,NULL,NULL,NULL,1),(20,4,3,'hits','热度',14,NULL,0,'0-255',0,0,NULL,NULL,NULL,1),(21,4,4,'time','时间',13,NULL,0,'0-255',0,0,NULL,NULL,NULL,1),(22,4,5,'status','状态',15,'1',0,'1-1',0,0,NULL,NULL,NULL,1),(23,5,1,'name','名称',17,NULL,1,'2-30',1,0,NULL,NULL,NULL,1),(24,5,2,'url','链接',24,'http://',0,NULL,1,0,NULL,NULL,NULL,1),(25,5,3,'hits','热度',14,NULL,0,'0-255',0,0,NULL,NULL,NULL,1),(26,5,4,'time','时间',13,NULL,0,'0-255',0,0,NULL,NULL,NULL,1),(27,5,5,'status','状态',15,'1',0,'1-1',0,0,NULL,NULL,NULL,1),(28,6,1,'name','名称',17,NULL,1,'1-255',1,0,NULL,NULL,NULL,1),(29,6,2,'url','地址',24,'http://',1,'1-255',1,0,NULL,NULL,NULL,1),(30,6,3,'time','时间',13,'1',0,'1-1',1,0,NULL,NULL,NULL,1),(31,6,4,'status','状态',15,1,1,'1-255',0,0,NULL,NULL,NULL,1),(32,7,1,'title','标题',1,NULL,1,'1-255',1,1,NULL,NULL,1384589297,1),(33,7,9,'info','导读',8,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(34,7,10,'content','内容',9,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(35,7,11,'img','图片',10,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(36,7,12,'time','时间',13,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(37,7,13,'status','状态',15,'1',0,'1-1',1,0,NULL,NULL,1384589297,1),(38,7,14,'html','地址',16,NULL,0,'6-50',0,0,NULL,NULL,1384589297,1),(39,7,2,'classid','栏目',2,NULL,0,'0-255',1,0,NULL,NULL,1384589297,1),(40,7,3,'attrib','属性',3,NULL,0,'0-255',1,0,NULL,NULL,1384589297,1),(41,7,4,'tags','关键词',4,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(42,7,5,'author','作者',5,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(43,7,6,'inputer','编辑',6,NULL,0,'0-255',1,0,NULL,NULL,1384589297,0),(44,7,7,'origin','来源',7,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(45,7,15,'template','模板',11,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(46,7,16,'date','日期',12,NULL,0,'0-255',1,0,NULL,NULL,1384589297,1),(53,7,8,'hits','热度',14,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1),(54,1,9,'time','时间',13,NULL,0,'0-255',0,0,NULL,NULL,NULL,1),(55,1,4,'img','图片',10,NULL,0,'0-255',0,0,NULL,NULL,1384589297,1);
DROP TABLE IF EXISTS `#prefix_module_field_type`;
CREATE TABLE `#prefix_module_field_type` (
  `id` int(11) NOT NULL auto_increment,
  `f` int(1) default '1' COMMENT '分类',
  `name` varchar(50) default NULL COMMENT '名称',
  `class` varchar(50) default NULL COMMENT '类型',
  `verification` varchar(10) default NULL COMMENT '验证方法',
  `quote` varchar(255) default NULL COMMENT '引用',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='字段类型';
insert into `#prefix_module_field_type` VALUES (1,0,'标题','title','*','title.html'),(2,0,'栏目','classid',NULL,'classify.html'),(3,0,'属性','attrib',NULL,'attrib.html'),(4,0,'关键词','tags','*','tags.html'),(5,0,'作者','author','s','author.html'),(6,0,'编辑','inputer','s','inputer.html'),(7,0,'来源','origin','s','origin.html'),(8,0,'导读','info','*','info.html'),(9,0,'内容','content','*','content.html'),(10,0,'图片','img','*','img.html'),(11,0,'模板','template','*','template.html'),(12,0,'日期','date',NULL,'date.html'),(13,0,'时间','time','time','time.html'),(14,0,'热度','hits','*','hits.html'),(15,0,'状态','hidden','*','status.html'),(16,0,'html','hidden','*','hidden.html'),(17,1,'万能文本框','text','*','text_w.html'),(18,1,'数字文本框','text','n','text_n.html'),(19,1,'字符串文本框','text','s','text_s.html'),(20,1,'中文文本框','text','z','text_z.html'),(21,1,'邮政编码文本框','text','p','text_p.html'),(22,1,'手机文本框','text','m','text_m.html'),(23,1,'email文本框','text','e','text_e.html'),(24,1,'url文本框','text','url','text_url.html'),(25,1,'文本域','textarea','*','textarea.html'),(26,1,'编辑器','htmledit','*','htmledit.html'),(27,1,'隐藏域','hidden','*','hidden.html'),(28,1,'图片','pic','*','pic.html'),(29,1,'批量图片','batchpic','*','batchpic.html'),(30,1,'上传文件','upfile','*','upfile.html');
DROP TABLE IF EXISTS `#prefix_origin`;
CREATE TABLE `#prefix_origin` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(50) default NULL COMMENT '名称',
  `url` varchar(255) default NULL COMMENT '地址',
  `hits` int(11) default '1' COMMENT '使用热度',
  `time` int(11) default NULL COMMENT '添加日期',
  `status` int(1) default '1' COMMENT '状态（-1回收站、0未发布、1发布）',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='作者';
DROP TABLE IF EXISTS `#prefix_single`;
CREATE TABLE `#prefix_single` (
  `id` int(11) NOT NULL auto_increment,
  `title` varchar(255) default NULL,
  `content` text,
  `template` varchar(255) default NULL,
  `date` date default NULL,
  `tags` varchar(255) default NULL,
  `classid` varchar(255) default NULL,
  `img` text COMMENT '图片',
  `status` int(1) default '1',
  `time` int(1) default '11',
  `html` varchar(255) default NULL COMMENT 'HTML',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='单页系统';
DROP TABLE IF EXISTS `#prefix_tags`;
CREATE TABLE `#prefix_tags` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(50) default NULL COMMENT '名称',
  `hits` int(11) default '1' COMMENT '使用热度',
  `time` int(11) default NULL COMMENT '添加日期',
  `status` int(1) default '1' COMMENT '状态（-1回收站、0未发布、1发布）',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='关键词';

DROP TABLE IF EXISTS `#prefix_message`;
CREATE TABLE `#prefix_message` (
  `id` int(11) NOT NULL auto_increment,
  `title` varchar(255) default NULL COMMENT '标题',
  `name` varchar(50) default NULL COMMENT '名称',
  `qq` varchar(20) default NULL COMMENT 'QQ',
  `tel` varchar(20) default NULL COMMENT '电话',
  `mail` varchar(50) default NULL COMMENT '邮箱',
  `content` text COMMENT '留言',
  `reply` text COMMENT '回复',
  `time` int(11) default NULL COMMENT '时间',
  `rtime` int(11) default NULL COMMENT '回复时间',
  `status` int(1) default '0' COMMENT '状态',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COMMENT='留言';

CREATE VIEW `#prefix_view_field` AS select #prefix_module_field.*,#prefix_module.project as project,#prefix_module_field_type.f as type_f,#prefix_module_field_type.name as type_name,#prefix_module_field_type.class as type_class,#prefix_module_field_type.verification as type_verification,#prefix_module_field_type.quote as type_quote from #prefix_module_field,#prefix_module,#prefix_module_field_type where #prefix_module_field.type=#prefix_module_field_type.id and #prefix_module_field.module_id=#prefix_module.id;