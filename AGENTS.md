# FengCMS AI 维护约定

本文件适用于整个仓库。与维护者用中文交流，执行前先检查工作区和相关文档；保留维护者已有改动。每个完成并验证的关键节点分别提交 Git，完成后说明变更、验证和限制。

## 项目与目录

这是从旧 PHP/MySQL CMS 迁移到 PHP 8.4 的项目，当前兼容 PHP 8.3、8.4、8.5；保留原路由、HTML 内容存储和模板调用方式。当前本地默认环境是 Docker / Colima 中的 PHP 8.4、MySQL 8.4、Nginx，PHP 8.5 回归也需保持通过；存量站点的运行环境和定制代码可能不同，不能直接覆盖部署。

- `app/`：前台控制器、模型；`admin/app/`：后台控制器、模型。
- `system/`：框架、数据库驱动、模板编译、安全函数；`system/common/security.php` 负责认证、CSRF、路径等公共防护；`richtext.php` 负责服务端富文本清洗。
- `template/`、`admin/template/`：前台及后台模板。原有 `{$变量}`、`{$record['fy']}` 语法必须保留。
- `admin/js/editor.js`、`admin/css/editor.css`：wangEditor 适配与样式；`resource-picker.js`：资源选择器；`admin/api/`：上传、列表、缩略图接口。
- 后台只有 `super_admin` 与 `editor` 两种固定角色；编辑可管理“内容”“相关”，不能访问“系统”“模块”和“系统账号管理”。超级管理员可创建两种账号，但不可删除或停用超级管理员账号；编辑账号可删除。路由、模型 `project`、独立 API 都要在服务端校验；权限与迁移见 `docs/internal/ADMIN_ROLES.md`。
- `admin/js/legacy-richtext.js`：主动历史正文优化、原文快照、预览和撤销；`admin/api/richtext.php` 只进行认证和 CSRF 保护下的清洗，不读写文章。转换库本地托管在 `admin/public/legacy-richtext/`，构建源码和锁文件在 `tools/vendor/`。禁止恢复默认自动重排旧正文；维护说明见 `docs/internal/历史富文本优化说明.md`。
- `admin/public/wangeditor/`：wangEditor 5.1.23（MIT）；`system/vendor/htmlpurifier/`：HTML Purifier 4.19.1（LGPL）。这些是部署所需源码，应纳入 Git。
- `docker/`、`compose.yaml`、`bin/dev`：本地容器；`tools/`：初始化、迁移、检查；`tests/regression.php`：HTTP/数据库回归。
- `install/index.php`、`system/common/install_schema.php`：共享主机的一次性网页安装与开发初始化共用数据导入；用户安装步骤见 `docs/安装指南.md`。本地 Docker 显式关闭网页入口。
- `config.php`：部署生成并被 Git 忽略的私有设置数组，只返回配置数据；示例为 `config.example.php`。`system/common/config_loader.php` 负责读取、验证和初始化常量；本地 Docker 从 Compose 环境变量读取，旧站点 `config.local.php` 保持兼容。维护细节见 `docs/internal/CONFIGURATION.md`。
- `README.md`：GitHub 用户个人主页介绍；`.github/README.md`：GitHub 仓库项目介绍；发布压缩包入口为根目录 `项目说明.md`。`.gitattributes` 将个人简介、仓库元数据和维护文档排除于发布归档。
- `docs/`：面向用户的中文安装、使用和升级说明；`docs/internal/`：开发、安全审阅、修复和历史记录，在 Git 中保留但排除于发布归档；原 Windows 文档位于 `docs/internal/legacy/`。新文档使用 UTF-8。
- 根目录 `LICENSE` 是 FengCMS 自有代码与文档的 MIT 许可；第三方组件许可证与版权声明独立保留，用户说明见 `docs/第三方组件许可.md`。历史文档中的旧授权条款已失效，不得复制回现行界面或安装流程。

按工作范围阅读 `docs/internal/LOCAL_DEVELOPMENT.md`、`docs/internal/SECURITY_UPGRADE.md`、`docs/internal/EDITOR_MIGRATION.md`、`docs/internal/RESOURCE_PICKER_UI_PLAN.md`、`docs/internal/PERFORMANCE_OPTIMIZATION_2026-10-27.md`、`docs/internal/SEARCH_REQUEST_CONTRACT.md`。安全记录中的早期测试数量和旧组件状态属于阶段记录，以后续记录和实际代码为准。

## 开发与验证

1. 先运行 `git status --short`，检查相关实现及调用链，再做最小必要修改。
2. 使用 `./bin/dev up|status|check|test|backup|down` 管理本地环境。不在宿主机安装 PHP、MySQL、Apache 等服务。JavaScript 可用 `node --check 文件` 检查；后台结果页回调改动还要运行 `node tests/navigation-regression.js`。
3. PHP 修改在容器内运行 `./bin/dev check`；认证、存储、上传、清洗、路由等变更运行 `./bin/dev test` 并补充能捕获问题的回归。纯文档或样式变更按影响范围验证，避免无意义重复测试。
4. 后台界面修改应在真实浏览器验证，覆盖实际 iframe 容器、编辑器全屏、选择与取消回填；按需验证窄屏。截图保存到已忽略的 `var/verification/`，报告中如实说明已测和未测范围。
5. 回归测试仅允许带 `f_dev_meta` 标记的开发数据库；初始化工具仅用于空库或开发库。临时账号、文章、文件使用独立标记，完成后只清理自己创建的数据。
6. 应用程序及配置在容器中只读挂载；前台 `template/`、`css/` 为后台在线编辑单独可写绑定，本地修改须用 Git 检查。FPM 的 LOCAL_UID 与本机仓库属主一致；不要通过 chmod 777 解决权限。上传、缓存、备份、运行配置位于独立数据卷。不能因为宿主机看不到上传文件就认定不存在。禁止随意执行 `docker compose down -v` 或删除数据卷。
7. 结束前运行适用检查和 `git diff --check`，检查暂存区，按节点提交并报告提交哈希。

## 安全与兼容边界

- 管理接口必须保留认证、写操作 POST/CSRF、会话保护、参数类型校验、目录范围限制。SQL 值使用参数绑定，动态标识符必须白名单校验。
- 后台异步保存的结果跳转统一使用 `FengAdmin.executionURL`。单条记录只有拿到有效 ID 才附加 `id`；批量操作和设置保存不附加。结果页可在没有 ID 时显示状态，但不得查询空 ID 或产生 `id=` 链接。若保存失败时需要保留用户正在编辑的表单，页面可以明确留在表单并显示错误，但必须由回归检查覆盖；数字参数的数组只允许明确的批量接口并逐项验证。
- 数据库导入只接受本系统生成的受限备份格式。保持预览、会话 nonce、POST/CSRF、当前结构核对、导入前自动备份及临时表整体交换；不得恢复执行上传 SQL 的旧实现。维护细节见 `docs/internal/DATABASE_IMPORT.md`，用户操作说明见 `docs/数据库备份与恢复.md`。
- 上传保留大小、扩展名、实际 MIME、图片尺寸校验及随机命名。不得以扫描二进制内的 PHP 字符串替代校验，也不得移除 Web 服务器对上传目录脚本执行的禁止规则。
- 模板普通值默认转义；由程序层返回 `CmsHtml`/`cms_html()` 的可信 HTML 保留原模板语法。`cms_html()` 是信任声明，不是清洗器；不按变量名自动放行，不把模板批量改成 `{raw ...}`。
- 管理员提交的富文本仍需由服务端 HTML Purifier 白名单清洗，包括正文、自定义富文本、栏目介绍、版权。客户端校验仅用于体验，不替代服务端防护。
- 网络图片使用浏览器直接引用 HTTP/HTTPS 地址。若未来增加服务器下载远程图片，需要单独设计 SSRF、重定向、大小和超时防护。
- 编辑器/资源选择器依赖本地托管。保留第三方许可证和来源摘要，升级依赖核验版本、来源和许可；不要手改压缩版 vendor 代码处理业务适配。
- 错误详情写入私有日志，不向访客泄露 SQL、路径、堆栈、密码或密钥。
- 网页安装只允许新站点使用。保留站长预置的一次性密钥、HTTPS、CSRF、安装锁、未占用表前缀检查及私有配置文件保护；安装 SQL 不可加入覆盖旧表或默认可登录口令。Apache/Nginx 只开放 `install/index.php`，不可直连配置、SQL、临时配置及上传目录脚本。更新安装器时必须在隔离空库实测，不能使用现有站点数据库。
- 私有 `config.php` 必须是受限权限的纯返回数组，不能提交到 Git；运行逻辑放在配置加载器中。环境变量优先于单一私有文件；新的 `config.php` 与旧 `config.local.php` 同时存在时必须先消除冲突。安装器的配置改动需在隔离空库实际安装和登录验证。
- 未经明确部署授权，不修改生产代码、数据库、配置或重启服务；只读检查授权不等于部署授权。不要在仓库文档中记录客户域名、服务器路径、账号或可识别的运行环境盘点。
- 旧密码登录后会迁移为新哈希，旧程序不识别。发布与回退须考虑代码、数据库、上传和运行配置的一致性；MyISAM 一致性备份不能仅依赖 `--single-transaction`。

## Git 与提交规范（强制）

所有新提交标题必须严格使用 `type(scope): 中文描述`，不省略 scope。示例：

```text
feat(editor): 支持插入网络图片
fix(upload): 修复合法图片误判
chore(git): 配置中文提交规范校验
```

- type 仅允许 `feat`、`fix`、`docs`、`style`、`refactor`、`perf`、`test`、`build`、`ci`、`chore`、`revert`。
- scope 使用小写英文字母开头，可含数字和连字符，如 `editor`、`security`、`template`、`dev-env`。
- 冒号后一个半角空格，描述包含中文且简洁准确；正文也使用中文，可保留技术名称。破坏性变更在正文以 `BREAKING CHANGE:` 说明。
- 合并和回退提交也必须使用上述标题；合并时自行指定规范消息，回退默认消息应编辑后再提交。不要使用 `--no-verify` 绕过检查。
- 首次克隆运行 `./bin/setup-git-hooks`，启用仓库内 `.githooks/commit-msg`（需要 Python 3，只用于开发工具）。此配置不随 clone 自动生效；新环境必须执行。钩子属于本地防错，不能替代托管平台的分支规则或 CI。
- 每个关键节点提交，避免混入无关改动。维护 `.gitignore`：`.env`/密钥、私有 `config.php`、上传、缓存、备份、日志、运行时、截图、下载缓存及本地 Git 历史备份不提交；`.env.example` 与 `config.example.php` 保留安全占位。
- 不输出 `.env` 或生产凭证。提交前检查 `git diff --cached`，不要盲目提交测试数据。
- 未经要求不 push，不重写或强制推送共享历史。维护者已授权的历史改写先生成忽略目录中的 Git bundle，并核对每个提交文件树、作者和时间保持不变。远端跟踪引用反映远端已知状态，不为美化日志伪造更新。

网络失败时可临时使用维护者提供的本机代理：`https_proxy=http://127.0.0.1:7897 http_proxy=http://127.0.0.1:7897 all_proxy=socks5://127.0.0.1:7897`。不要将代理或凭证固化到部署配置。
