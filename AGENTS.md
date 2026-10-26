# FengCMS AI 维护约定

本文件适用于整个仓库。与维护者用中文交流，执行前先检查工作区和相关文档；保留维护者已有改动。每个完成并验证的关键节点分别提交 Git，完成后说明变更、验证和限制。

## 项目与目录

这是从旧 PHP/MySQL CMS 迁移到 PHP 8.4 的项目，保留原路由、HTML 内容存储和模板调用方式。当前本地环境是 Docker / Colima 中的 PHP 8.4、MySQL 8.4、Nginx；存量站点的运行环境和定制代码可能不同，不能直接覆盖部署。

- `app/`：前台控制器、模型；`admin/app/`：后台控制器、模型。
- `system/`：框架、数据库驱动、模板编译、安全函数；`system/common/security.php` 负责认证、CSRF、路径等公共防护；`richtext.php` 负责服务端富文本清洗。
- `template/`、`admin/template/`：前台及后台模板。原有 `{$变量}`、`{$record['fy']}` 语法必须保留。
- `admin/js/editor.js`、`admin/css/editor.css`：wangEditor 适配与样式；`resource-picker.js`：资源选择器；`admin/api/`：上传、列表、缩略图接口。
- `admin/public/wangeditor/`：wangEditor 5.1.23（MIT）；`system/vendor/htmlpurifier/`：HTML Purifier 4.19.1（LGPL）。这些是部署所需源码，应纳入 Git。
- `docker/`、`compose.yaml`、`bin/dev`：本地容器；`tools/`：初始化、迁移、检查；`tests/regression.php`：HTTP/数据库回归。
- `docs/`：维护记录；`docs/legacy/`：原 Windows 文档的 UTF-8 归档。新文档使用 UTF-8。

按工作范围阅读 `docs/LOCAL_DEVELOPMENT.md`、`docs/SECURITY_UPGRADE.md`、`docs/EDITOR_MIGRATION.md`、`docs/RESOURCE_PICKER_UI_PLAN.md`。安全记录中的早期测试数量和旧组件状态属于阶段记录，以后续记录和实际代码为准。

## 开发与验证

1. 先运行 `git status --short`，检查相关实现及调用链，再做最小必要修改。
2. 使用 `./bin/dev up|status|check|test|backup|down` 管理本地环境。不在宿主机安装 PHP、MySQL、Apache 等服务。JavaScript 可用 `node --check 文件` 检查。
3. PHP 修改在容器内运行 `./bin/dev check`；认证、存储、上传、清洗、路由等变更运行 `./bin/dev test` 并补充能捕获问题的回归。纯文档或样式变更按影响范围验证，避免无意义重复测试。
4. 后台界面修改应在真实浏览器验证，覆盖实际 iframe 容器、编辑器全屏、选择与取消回填；按需验证窄屏。截图保存到已忽略的 `var/verification/`，报告中如实说明已测和未测范围。
5. 回归测试仅允许带 `f_dev_meta` 标记的开发数据库；初始化工具仅用于空库或开发库。临时账号、文章、文件使用独立标记，完成后只清理自己创建的数据。
6. 应用程序及配置在容器中只读挂载；前台 `template/`、`css/` 为后台在线编辑单独可写绑定，本地修改须用 Git 检查。FPM 的 LOCAL_UID 与本机仓库属主一致；不要通过 chmod 777 解决权限。上传、缓存、备份、运行配置位于独立数据卷。不能因为宿主机看不到上传文件就认定不存在。禁止随意执行 `docker compose down -v` 或删除数据卷。
7. 结束前运行适用检查和 `git diff --check`，检查暂存区，按节点提交并报告提交哈希。

## 安全与兼容边界

- 管理接口必须保留认证、写操作 POST/CSRF、会话保护、参数类型校验、目录范围限制。SQL 值使用参数绑定，动态标识符必须白名单校验。
- 上传保留大小、扩展名、实际 MIME、图片尺寸校验及随机命名。不得以扫描二进制内的 PHP 字符串替代校验，也不得移除 Web 服务器对上传目录脚本执行的禁止规则。
- 模板普通值默认转义；由程序层返回 `CmsHtml`/`cms_html()` 的可信 HTML 保留原模板语法。`cms_html()` 是信任声明，不是清洗器；不按变量名自动放行，不把模板批量改成 `{raw ...}`。
- 管理员提交的富文本仍需由服务端 HTML Purifier 白名单清洗，包括正文、自定义富文本、栏目介绍、版权。客户端校验仅用于体验，不替代服务端防护。
- 网络图片使用浏览器直接引用 HTTP/HTTPS 地址。若未来增加服务器下载远程图片，需要单独设计 SSRF、重定向、大小和超时防护。
- 编辑器/资源选择器依赖本地托管。保留第三方许可证和来源摘要，升级依赖核验版本、来源和许可；不要手改压缩版 vendor 代码处理业务适配。
- 错误详情写入私有日志，不向访客泄露 SQL、路径、堆栈、密码或密钥。
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
- 每个关键节点提交，避免混入无关改动。维护 `.gitignore`：`.env`/密钥、上传、缓存、备份、日志、运行时、截图、下载缓存及本地 Git 历史备份不提交；`.env.example` 保留安全占位。
- 不输出 `.env` 或生产凭证。提交前检查 `git diff --cached`，不要盲目提交测试数据。
- 未经要求不 push，不重写或强制推送共享历史。维护者已授权的历史改写先生成忽略目录中的 Git bundle，并核对每个提交文件树、作者和时间保持不变。远端跟踪引用反映远端已知状态，不为美化日志伪造更新。

网络失败时可临时使用维护者提供的本机代理：`https_proxy=http://127.0.0.1:7897 http_proxy=http://127.0.0.1:7897 all_proxy=socks5://127.0.0.1:7897`。不要将代理或凭证固化到部署配置。
