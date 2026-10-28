# Web 服务器伪静态与访问限制

当前仓库维护三套面向当前 FengCMS 的 Web 服务器配置：

| Web 服务器 | 配置文件 | 当前用途 |
| --- | --- | --- |
| Apache | [`.htaccess`](../../.htaccess) | 共享主机伪静态与基础访问限制 |
| Nginx | [`docker/nginx/default.conf`](../../docker/nginx/default.conf) | 本地容器示例；生产 Nginx 应由管理员按本站根目录和 PHP-FPM 地址调整 |
| IIS | [`web.config`](../../web.config) | IIS URL Rewrite 路由、私有路径拦截与 PHP 入口限制 |

根目录 `nginx.conf` 是旧示例，不是本地 Docker 当前加载的配置。根目录 `httpd.ini` 面向旧版 ISAPI_Rewrite 扩展，保留给仍依赖该第三方模块的历史站点；它不是 IIS 原生配置，也没有跟进当前完整栏目路由和访问限制。新部署及迁移到 IIS 的站点应使用 `web.config`，并安装 IIS URL Rewrite 模块。PHP 需要由主机管理员配置为 FastCGI 处理程序。

## IIS 部署要求

1. 安装并启用 IIS URL Rewrite；确认主机上的 PHP 版本满足 [安装要求](INSTALLATION.md)，并由 IIS FastCGI 正确处理 PHP。
2. 将发布包放在 IIS 站点根目录，使用仓库中的 `web.config`。IIS 应用池身份只获得 CMS 需要的目录写权限；不要为简化部署开放整个站点目录。
3. 确认 IIS URL Rewrite 没有被上级站点配置覆盖，且 `web.config` 的 `system.webServer/rewrite` 配置可用。出现 HTTP 500.19 时，优先检查 URL Rewrite 模块及 IIS 配置节锁定状态。
4. 通过浏览器确认首页、栏目列表及分页、文章、标签搜索、留言和注册等路由可用，并确认原有查询参数页面仍能进入前端控制器。
5. 验证 `/system/`、`/app/`、`/template/`、`/install/install.sql`、`/config.php`、`/docs/`、私有配置文件及 `/upload/` 下的 PHP/HTML/SVG/JS 文件不能被访问或执行；确认 `/admin/api/upload.php`、`files.php` 和 `thumbnail.php` 仍能按后台认证与 CSRF 规则工作。
6. 新安装时按[安装说明](INSTALLATION.md)完成后，确认 `/install/authorize.php` 已删除。站点已安装后，`/install/` 下只有 `index.php` 会被允许进入 PHP 处理；安装器自身仍会拒绝已安装站点。

IIS 规则负责入口路由和 Web 层访问限制，不替代 PHP 应用里的认证、CSRF、路径校验、上传检查或文件系统权限。该配置尚未在 Windows/IIS 实机运行验证，投入生产前应先在与目标 IIS 版本和 PHP FastCGI 配置相同的副本中完成上述检查。

## 维护约定

路由更新时同步检查三种当前配置中的路径规则和访问限制。`route` 参数承载完整栏目路由，不能再拆成旧的 `project`、`classify`、`classid` 参数，否则包含下划线的历史栏目路径可能解析错误。配置变更应在对应 Web 服务器上验证；静态 XML 可解析不代表 IIS URL Rewrite 表达式已在目标主机通过。
