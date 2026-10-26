# FengCMS

FengCMS 是一套基于 PHP 和 MySQL 的开源内容管理系统，面向文章、产品等展示型网站。它以自定义内容模型、字段和模板为核心，让站点可以按实际内容组织后台和前台页面。

**当前正式版：[`v1.35`](https://github.com/fengcms/fengcms/releases/tag/v1.35)。** 版本与更新说明统一在 [GitHub Releases](https://github.com/fengcms/fengcms/releases) 发布；后台首页可以查询最新正式版。系统不提供在线更新，站点升级需由维护者备份、验证后手动完成。

## 主要功能

- **自定义模型与字段**：按业务建立内容模型，配置系统字段和自定义字段，用同一套后台管理不同类型的内容。
- **栏目与内容管理**：管理栏目、文章等内容，支持排序、属性设置和回收站。
- **模板与内容分离**：通过模板、公共片段和 CSS 控制页面展示；后台可以管理模板文件。现有模板变量写法保持兼容。
- **内容编辑**：后台集成 wangEditor 5，支持富文本、图片和附件资源选择，也支持插入网络图片。
- **前台功能**：提供栏目、内容、搜索、标签和留言等页面与接口，支持现有动态和伪静态 URL 形式。

## v1.35 安全更新

这一版本为旧系统建立了新的安全维护基础，主要包括：

- 适配 PHP 8.4，并提供基于 Docker 的 PHP、MySQL、Nginx 本地开发环境。
- 加固管理员登录、会话和密码存储；后台写操作检查 POST 与 CSRF，限制可访问的控制器方法。
- 对数据库查询、路由参数和文件路径增加校验，限制未发布内容和内部文件的访问。
- 上传时校验文件类型、实际 MIME、尺寸和大小，并在 Web 服务器层禁止上传目录执行脚本。
- 普通模板变量默认进行 HTML 转义；新提交的富文本在服务端按白名单清洗。旧文章内容不会自动重写。
- 将旧富文本编辑器替换为 wangEditor 5，修复文章编辑、模板管理、自定义模型字段、回收站、留言及搜索等已发现的问题。

修复范围和验证记录见[安全升级记录](docs/SECURITY_UPGRADE.md)及[编辑器迁移记录](docs/EDITOR_MIGRATION.md)。这些更新不等于完成了全部安全审计，也不代表所有旧站点都能直接覆盖升级。

## 本地运行

本地开发只需 Docker Compose；macOS 可配合 Colima 使用，无需在宿主机安装 PHP、MySQL 或 Apache。

```sh
./bin/dev up       # 构建、初始化并启动
./bin/dev status   # 查看容器状态
./bin/dev check    # 检查 PHP 语法和扩展
./bin/dev test     # 仅在带开发标记的数据库运行回归测试
./bin/dev down     # 停止容器，保留数据卷
```

启动后访问前台 <http://127.0.0.1:18080/>、后台 <http://127.0.0.1:18080/admin/login.php>。首次启动生成的本地密码和后台认证码保存在未纳入 Git 的 `.env` 中。详细步骤和备份说明见[本地开发文档](docs/LOCAL_DEVELOPMENT.md)。

本地已验证 PHP 8.4、MySQL 8.4 和 Nginx 组合。现有网站可能使用不同的运行环境、数据库结构和定制模板；迁移前应备份代码、数据库、上传文件及配置，在站点副本中完成兼容性和业务验证。发布包的制作与校验方法见[GitHub 发布流程](docs/RELEASING.md)。

## 授权与维护

FengCMS 自有代码和文档采用 [MIT License](LICENSE)。仓库内第三方组件保留各自的许可证，详见[第三方许可说明](docs/THIRD_PARTY_LICENSES.md)。

项目由维护者主导，借助 AI 辅助开发、测试和持续维护。问题反馈使用 [GitHub Issues](https://github.com/fengcms/fengcms/issues)；维护约定和提交规范见 [AGENTS.md](AGENTS.md)。更多资料见[文档目录](docs/README.md)。
