# 共享虚拟主机首次安装

本向导用于**新网站的首次安装**。已有 FengCMS 网站不能用它覆盖升级；请先备份代码、数据库、上传文件和私有配置，再按[安全升级记录](SECURITY_UPGRADE.md)迁移。

此功能已在仓库源码实现，**尚未包含在 v1.35 发布包中**。对外安装请等待包含此功能的新版本发布。

已在隔离环境验证网页安装、首页和后台登录：PHP 8.4.27 + MySQL 8.4.11（Nginx 与 Apache），以及 PHP 8.3.36 + MariaDB 10.11.19（Apache）。后一个组合尚未完成整套 CMS 的业务回归；LiteSpeed 和第三方共享主机面板也尚未实测。

## 主机准备

1. 在主机面板启用 HTTPS，选择 PHP 8.3 或 8.4，并启用 `mysqli`、`mbstring`、`gd`、`fileinfo`、`json`。数据库用户需要对目标库建表、建视图、写入数据的权限。
2. 在面板中创建 MySQL 或 MariaDB 数据库及专用用户，记下主机名、库名、用户名和密码。安装器不会替你创建数据库。
3. 将正式发布包上传到网站根目录。Apache/LiteSpeed 需要允许 `.htaccess` 生效；Nginx 需要由主机管理员配置与 [Nginx 示例](../docker/nginx/default.conf)等效的访问限制。不能确认访问限制时，请先联系主机商配置。
4. 让 PHP 进程能创建根目录中的 `config.local.php`，并能写入 `upload/`、`app/cache/`、`admin/app/cache/`、`admin/app/dbbackup/`、`var/runtime/`。优先调整文件属主和最小必要权限，**不要使用 777**。

安装前，通过浏览器确认 `/install/install.sql`、`/install/authorize.example.php`、`/config.php`、`/system/common/install_schema.php` 不能下载；上传目录中的 PHP 文件也不能执行。Nginx 示例只供配置参考，本地 Docker 环境另有安装器禁用开关，不能直接当作共享主机配置。

## 一次性站长授权

通过主机文件管理器或 SFTP，复制 `install/authorize.example.php` 为 `install/authorize.php`。将其中的占位符换成自己生成的 **32–128 位随机密钥**，只使用英文字母、数字、`_` 和 `-`。不要使用示例值，也不要把密钥提交到 Git。文件内容格式为：

```php
<?php
return 'REPLACE_WITH_YOUR_OWN_RANDOM_INSTALL_SECRET';
```

这是一次性安装授权，不是后台密码。它必须保持在服务器端，安装成功后安装器会尝试删除；站长还应在文件管理器中确认其已删除。

## 浏览器安装

1. 打开 `https://你的域名/install/index.php`，输入一次性安装密钥。缺少密钥文件时，页面只提示准备步骤。
2. 确认环境检查通过，填写数据库连接、**未使用的表前缀**、管理员用户名和自选密码。管理员密码至少 12 字节，至多 72 字节。核对页列出目标库、表前缀以及将创建的表和视图；确认后才开始写入。所有字段通过 HTTPS 的 POST 提交，核对页不显示密码。
3. 保存完成页显示的后台认证码，与管理员密码分开保管。访问 `/admin/login.php` 登录，检查前台页面、后台、上传和模板功能。
4. 确认 `install/authorize.php` 已删除；再次访问安装入口应返回 404。妥善备份 `config.local.php`、数据库及上传文件；私有配置不能提交到 Git 或通过 Web 下载。

安装器先核对表前缀未被占用，不执行删除旧表的 SQL；它会生成随机应用密钥和后台认证码，使用 `password_hash()` 存储管理员密码。安装过程包含建表与建视图，数据库不能保证失败时自动回滚。如果页面提示“安装未完成”，**不要对同一前缀直接重试**：先查看数据库里本次已创建的对象，恢复备份或换一个全新数据库，再重新安装。

## 故障排查

| 现象 | 处理 |
| --- | --- |
| 安装入口 404 | 已安装站点或本地 Docker 环境会主动关闭安装器；新共享主机请确认上传了 `install/index.php`，并检查 Web 路由。 |
| 页面要求 HTTPS | 在主机面板启用证书，从 `https://` 地址访问；不要把数据库密码或安装密钥提交到 HTTP。 |
| 页面等待站长授权 | 检查 `install/authorize.php` 的位置、PHP 语法和随机密钥长度；示例占位符不可用。 |
| 目录不可写 | 调整 PHP 运行用户对指定目录及根目录的属主或最小必要权限，不要放开到 777。 |
| 数据库连接或前缀失败 | 核对面板提供的数据库主机、凭证、库名和建表/建视图权限；改用无现有对象的前缀。 |
| 完成后后台认证码不匹配 | 若通过容器或其他部署方式显式设置了 `ADMIN_CODE` 环境变量，它会优先于私有配置；检查部署环境。 |

本地容器开发继续使用 [`./bin/dev up`](LOCAL_DEVELOPMENT.md)。版本更新按 [GitHub Releases](https://github.com/fengcms/fengcms/releases) 手动进行，网页安装器不执行在线更新。
