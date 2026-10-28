# FengCMS 配置文件与配置加载

## 文件职责

- 本地 Docker 使用被 Git 忽略的 `.env`。Compose 将变量注入 PHP 容器；应用本身不解析 `.env` 文件。
- 共享主机安装器生成根目录私有 `config.php`。它只返回一个 PHP 数组，不放函数、条件判断、环境读取或常量定义。权限应为 `0600`，不能提交或由 Web 直接下载。
- `config.example.php` 是不含真实凭证的格式示例。
- `system/common/config_loader.php` 读取和验证环境变量/私有文件，并由应用入口定义运行所需常量。
- `var/runtime/site.json` 保存后台可修改的运行时设置。URL 模式优先使用这里的 `url_type`，不由管理员手工编辑私有密钥配置来切换。

## 来源优先级

每个部署变量优先取进程环境变量；没有相应环境变量时读取私有配置文件。`DB_PREFIX` 默认 `f_`。同时存在新的私有 `config.php` 和旧 `config.local.php` 时，启动与预检会失败，需备份并移除旧文件，避免两套值混用。

目前安装器生成的 `config.local.php` 仍受读取器兼容，存量站点无需因本次代码整理立即改名。新安装使用 `config.php`。如果旧发布文件覆盖部署后还残留原来的程序型 `config.php`，读取器会依据安装器专用标记识别并忽略它，不会执行该旧文件；在确认站点改由环境变量或 `config.local.php` 提供配置后，应清理残留程序文件。

## 手工部署格式

从 `config.example.php` 复制为 `config.php`，使用 PHP 数组语法填写数据库连接、表前缀、认证码和应用密钥。应用密钥至少 32 字节；请使用密码管理器或可信随机数生成器创建，不要使用示例占位符。设置文件属主为 PHP 运行用户，并将权限限制为仅该用户可读。不要把真实配置贴入问题报告或提交 Git。

## 升级与检查

1. 先备份代码、数据库、上传文件、`config.local.php`/`config.php` 和运行目录。
2. 在站点副本更新代码。存量 `config.local.php` 保持兼容；容器或主机环境变量仍逐项优先。
3. 运行 `php tools/config-preflight.php`，只检查配置完整性、数据库连接和数据库账号权限，不会输出凭证。
4. 若转换为新的私有 `config.php`，先写到临时文件、设置最小权限，再核对内容和连接；确认新文件可用后移除旧 `config.local.php`。两份同时存在时，系统会拒绝启动。
5. 使用真实 Web 服务器确认 `/config.php`、`/config.local.php`、`/config.example.php` 均不可下载，并检查首页、后台登录及 CLI 迁移工具。

不要手工编辑仓库的程序入口来保存站点凭证。历史版本中的程序型 `config.php` 不再是配置源；旧版 `define('DB_TYPE','mysql')` 等写法不能复制到新的数组配置中。
