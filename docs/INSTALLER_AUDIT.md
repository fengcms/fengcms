# 安装程序检查（2026-10-26）

## 结论

当前仓库有两套不同年代的安装路径：

- `install/index.php`、`install/data.php`、`install/install.php` 和 `install/step/` 是旧网页安装向导。入口在当前 Nginx 配置中返回 404；主入口和部分脚本另有非 CLI 拒绝逻辑。**它不能用于安装 PHP 8.4 版本，也不能通过删除访问限制来恢复使用。**
- `./bin/dev up` 调用 `tools/bootstrap.php`，只用于隔离的 Docker 开发环境。它要求开发模式标记，空库初始化后设置随机生成的管理员密码；对已有开发库重复运行时保留管理员密码，遇到没有开发标记的非空库会拒绝初始化。它不是现成的生产安装器。

本次在独立的 `fengcms-installer-audit` Docker 项目和独立数据卷中验证：空库初始化成功，重复执行 setup 返回“Existing development database retained”，首页 HTTP 200，旧安装入口 HTTP 404；9 项数据库驱动检查和 280 项业务回归通过。测试项目及其数据卷已清理，原开发项目和数据库保留。本次没有启用旧向导，也没有使用客户站点数据。

## 旧网页向导的主要问题

| 严重程度 | 证据与影响 |
| --- | --- |
| 高：若重新开放 | `install/step/step3.php` 使用 GET 表单提交数据库密码；即使使用 HTTPS，密码仍可能留在浏览器历史、访问日志等 URL 记录中。步骤 4 无身份认证或 CSRF 保护。 |
| 高：若重新开放 | `install/index.php` 步骤 4 调用已移除的 `mysql_connect()`，而环境检查又提示 `mysqli` 可用。当前 PHP 8.4 中 `mysql_connect()` 不存在，流程会中断。 |
| 严重：若重新开放且授予写权限 | `install/data.php` 将 GET 参数直接替换进 `config.php` 的 PHP 源码并再次加载，没有正确编码或校验。恶意输入存在 PHP 代码注入风险；数据库名、表前缀也未经严格验证。当前只读源码挂载阻止了此写入路径。 |
| 严重：若重新开放 | `install/install.sql` 有多处 `DROP TABLE IF EXISTS`；旧向导不要求数据库为空，且 `upload/INSTALL` 存在时只输出提示而不终止。重新进入步骤 4 可能覆盖已有站点数据。 |
| 高：手工导入原始 SQL 时 | `install/install.sql` 自带已知的 `admin` 密码哈希，对应 `123456`；`config.php` 还保留旧默认认证码、数据库密码和应用密钥。开发环境的 setup 会覆盖管理员密码并从 `.env` 注入配置，但直接导入原始 SQL 或在缺少环境变量时运行存在风险。 |
| 中 | 旧导入器按分号拆 SQL、忽略单条执行失败，仍可能显示安装成功；步骤 5 可独立写入 `upload/INSTALL` 标记。向导还加载外部 HTTP 统计脚本，并建议将文件或目录设为 `777`。 |

这些是**旧向导被重新开放或手工使用原始 SQL 时的风险**；当前 Nginx 对 `/install/` 返回 404，PHP 入口也拒绝 Web 请求，不能据此声称当前运行站点已可被这些路径直接利用。其他 Web 服务器必须保留同等限制。旧版网页向导文件仍在仓库中供历史分析；其 SQL 样本是开发 bootstrap 的输入，不应当作为独立生产安装包执行。

## 后续处理建议

1. 保持旧网页向导禁用，不建议修改 Nginx 或 PHP 入口让它重新可访问。
2. 如果需要全新生产安装，应另做一次性 CLI 安装器：只接受空库，校验数据库标识符与权限，通过环境变量或私有配置读取凭证，生成强随机管理员密码或由操作者安全输入，使用 `password_hash()`，检查每条导入语句，失败时明确停止并要求恢复备份。MyISAM 导入不能靠事务回滚。
3. 生产配置必须明确提供数据库凭证、后台认证码和应用密钥；旧默认值与 SQL 样本账号不应当视为安全初始值。安装后验证后台登录、上传、路由、备份，并保持 Web 服务器对配置、安装目录和上传脚本的限制。
4. 在新的安装方式完成前，文档和错误页不应引导用户访问 `/install/index.php`。存量站点升级继续遵循[安全升级记录](SECURITY_UPGRADE.md)中的备份、迁移和验收顺序。

参考：[PHP 手册关于旧 MySQL 扩展的移除](https://www.php.net/manual/en/function.mysql-connect.php)、[OWASP 关于 URL 查询参数泄露敏感信息](https://community.owasp.org/vulnerabilities/Information_exposure_through_query_strings_in_url)、[OWASP 默认凭证测试指南](https://wstg.owasp.org/latest/4-Web_Application_Security_Testing/04-Authentication/02-Default_Credentials/)。
