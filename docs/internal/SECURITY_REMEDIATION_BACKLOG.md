# 安全整改与遗留代码清理清单

日期：2026-10-27。基线：`eba41ff`。本文是交给开发 AI 执行的整改任务书，不是生产部署授权，也不替代各站点的独立副本验收。

## 范围和优先级

本轮只处理以下三类问题：

1. **P0 安全**：编辑角色可触达的 SQL 拼接、保存字段越权、批量 ID 校验缺失。
2. **P1 稳定性**：未定义变量和 Deprecated、死代码，以及上传、备份、安装的负向验证。
3. **P2 后续**：性能测量、依赖盘点、存量站点升级手册。

不在本轮做：重写 MVC、引入 Composer/新框架、修改数据库结构、处理历史富文本、改路由或模板语法、重做后台 UI、升级 jQuery/wangEditor。历史富文本如需批量处理，由后续独立工具承担，不在 CMS 内增加审计或迁移入口。

每项任务必须先确认失败场景，再修复并补回归；没有验证证据不得标记完成。

## 强制工作规则

- 开始前阅读 `AGENTS.md`、`docs/SECURITY_UPGRADE.md`、`docs/HARDENING_ROADMAP_V1.36.md`、相关控制器/模型和 `tests/regression.php`。
- 只在本地 Docker 开发环境操作；不连接生产数据库、生产服务器或客户站点。禁止 `docker compose down -v` 和删除数据卷。
- 保留现有路由、`{$变量}`、`{$record['fy']}` 模板语法和现有 HTML 行为。
- 所有写操作保持 POST/CSRF/认证/角色校验；SQL 值必须参数绑定，动态表名/列名只能走 `model::identifier()`。
- 不使用 `@` 压制错误，不关闭 `error_reporting`，不删除测试，不用跳过测试的方式“修复”。
- 不新增内联样式；如确需样式，使用现有 CSS 文件。JavaScript 修改使用箭头函数；涉及依赖时使用 pnpm。
- 不把 `.env`、`config.local.php`、客户域名、服务器路径、账号或密钥写进仓库、日志或回复。
- 每项任务单独提交，提交标题使用 `type(scope): 中文描述`；未经要求不 push。
- 遇到需要改变业务语义、删除历史数据或无法确定表结构的场景，停止并交给维护者确认。

## P0：先修可触达的安全问题

### P0-1 批量记录 ID 统一校验并参数化删除

**问题**

`record[]` 从浏览器直接进入批量删除/修改路径，当前只校验了 `id`、`classid`、`module_id`，没有校验 `record`。可触达入口至少包括：

- `admin/app/controller/moduleController.php` 的 `batch()`、`recover()`、`thorough()`、`attrib()`、`attribc()`；
- `admin/app/controller/messageController.php` 的 `deletebatch()`；
- `admin/app/controller/signupController.php` 的 `deletebatch()`；
- `admin/app/model/messageModel.php` 和 `admin/app/model/signupModel.php` 的 `delete()/batch()`。

这些路径把数组元素拼进 `id="..."`，属于 SQL 拼接。编辑角色可以访问内容和留言，因此不能把它当作“只有超管能碰”的历史问题。

**修改要求**

1. 在 `system/common/security.php` 增加统一辅助函数，例如：
   `cms_id_list($value, string $label, int $max = 1000): array`
   - 必须是 list 数组，不能是关联数组或字符串；
   - 每项必须是 `^[1-9][0-9]{0,9}$` 的字符串；
   - 数量不超过上限；
   - 返回去重后的整数数组；
   - 失败时调用 `cms_fail(400, ...)`，错误信息用中文。
2. 在 `cms_route_guard()` 中只对确实消费 `record` 的接口做校验并回写规范化值：
   - `module` + `batch`
   - `message` + `deletebatch`
   - `signup` + `deletebatch`
   不要把 `record` 全局套到 `fieldmanage&operate=systemsave`，该接口已有自己的系统字段白名单和整批校验。
3. 控制器在调用模型前后都使用规范化 ID；模型内部把 `where('id="'.$v.'"')` 改为 `where(['id'=>$v])` 或 `where('id=?', [$v])`。
4. 单条 `deleteone`、`recoverone` 已经由 GET 数字参数校验覆盖，但仍建议改为绑定查询，避免以后绕过守卫。
5. 不改变现有批量语义：全部成功/部分失败/空选择的返回状态保持原样；不擅自加事务。

**验收**

- 新增回归：`record` 为字符串、嵌套数组、`1 OR 1=1`、`0`、超过上限时，三个入口均返回 400，数据库中目标行数量不变。
- 新增回归：合法 ID 批量删除/回收仍然成功。
- 现有 380 项回归全部通过；新增用例计入总数。
- `rg "where\('id=\"'\.\\$" admin/app` 在这些批量路径上不再命中。

### P0-2 标签、作者等计数更新参数化

**问题**

`admin/app/model/moduleModel.php` 的 `hits()` 把文章提交的 `tags`、`author`、`inputer`、`origin` 拼进 `name="..."`。这是编辑保存文章时可触达的注入点，且原值是用户输入。

**修改要求**

- 将 `where('name="'.$v.'"')` 改为 `where(['name'=>$v])`。
- 逗号分隔后逐项 `trim()`，跳过空值；保留原有“每项 hits+1”的语义。
- 保留 `update('hits=hits+1')` 这种内部常量 SQL；不要把它改成用户输入。
- 如项目字段长度受限，可在模型层做长度校验，但不要改变已有正常标签的保存结果。

**验收**

- 新增回归：保存一篇带 `x" OR 1=1 -- ` 标签的文章，标签被原样写入 `f_tags`，其他标签的 `hits` 不变化，文章正常创建。
- 正常标签、作者、来源的计数行为不变。
- 现有文章保存、标签页和搜索回归通过。

### P0-3 清理编辑可达路径上的原生 WHERE 拼接

**范围**

优先处理请求输入进入 `where()` 的位置，不要求一次重写全部历史 SQL：

- `admin/app/controller/classifyController.php` 中多处 `where('id="'.$_GET['id'].'"')`、`where('classid="'...)`；
- `admin/app/controller/modulemanageController.php` 的 `update()`、`delete()`；
- `admin/app/model/classifyModel.php` 的 `save()`、`topid()`、`batchsave()`；
- `admin/app/model/fieldModel.php`、`admin/app/model/fieldmanageModel.php` 中由请求字段/模型名进入的查询；
- `admin/app/model/messageModel.php`、`admin/app/model/signupModel.php` 中仍拼接 ID 的位置。

**修改要求**

- 已验证为数字的值，改用 `where(['id'=>(int)$id])` 或占位符。
- `project`、`field` 等文本值，改用 `where(['project'=>$project,'name'=>$field])` 或 `where('project=? AND name=?', [$project, $field])`。
- 模型名/表名仍必须经过路由守卫和 `model::identifier()`；不要用 `intval` 代替结构校验。
- `update('status=-1')` 这类内部字面量可以保留，但能改成数组时优先改数组。
- `sqlpage($where, $params)` 已经支持参数绑定，调用方保留原分页语义，不要改变 URL 参数。

**验收**

- 编辑角色可访问的控制器/模型中，不再有直接拼接请求值的 `where(...)`。
- 栏目、文章、字段、留言、回收站相关回归通过。
- 不改变前台 URL、栏目层级和内容可见性。

### P0-4 收紧保存字段白名单，防止批量赋值

**问题**

`moduleModel::save()`、`messageModel::save()`、`signupModel::save()`、`classifyModel::save()` 仍把较大的 POST 数组直接交给 `insert/update`。虽然 `identifier()` 会拒绝非法列名，但合法列名中的 `html`、`hits`、`time` 等仍可能被客户端改写。

**修改要求**

- `moduleModel::save()`：按当前项目 `view_field` 的字段名加上系统字段白名单过滤 POST；`id` 只在更新时使用并先校验；`_csrf` 不进入 SQL；未知字段忽略而不是导致 500。
- `messageModel::save()`（后台回复）：只允许 `title`、`name`、`qq`、`tel`、`mail`、`content`、`reply`、`status`、`rtime` 及已校验的 `id`。
- `signupModel::save()`：先确认 `signup_base` 的实际列结构和行数；使用明确单行条件（没有稳定单行键时停止并询问维护者），只写允许字段。
- `classifyModel::save()`：保留现有编辑守卫，同时在模型层限制客户端不能改 `type/project/topid` 等归属字段。
- 保留现有返回结构 `{status, id}` 和成功/失败语义。

**验收**

- 新增回归：编辑提交未知字段、`html`、`hits`、`time`、`password` 等，不得写入数据库或导致 500；表单正常字段仍保存成功。
- 文章、栏目、留言回复、报名设置的现有回归通过。
- 被忽略字段在代码注释或文档中说明，不静默改变正常业务字段。

### P0-5 补齐编辑角色的注入与越权回归

**文件**：`tests/regression.php`。

**要求**

- 使用现有临时 `$tag`、临时编辑账号和 `finally` 清理模式，不写真实业务数据。
- 至少覆盖：
  - 编辑角色对 `module&project=article&operate=batch` 提交非法 `record`；
  - 编辑角色对 `message&operate=deletebatch` 提交非法 `record`；
  - 编辑保存文章时 `tags`/`author` 带引号和 SQL 片段；
  - 编辑保存文章时提交未授权字段；
  - 合法批量删除仍成功，且只删除目标行。
- 失败用例必须在修复前可复现，修复后通过；不要只测返回码而不同时核对数据库行数。
- 测试产生的账号、文章、留言、标签、临时模型沿用现有清理逻辑。

**验收**

- 新增用例先失败、后通过，并在回复中给出失败/通过证据。
- `./bin/dev test` 全部通过，日志中没有新增业务 PHP Warning。

## P1：稳定性与可维护性

### P1-1 清理未定义变量和 Deprecated

**已知位置（以基线为准）**

- `admin/app/controller/messageController.php`：`index()` 的 `$where` 未初始化。
- `admin/app/controller/signupController.php`：`index()` 的 `$where` 未初始化。
- `admin/app/model/classifyModel.php`：`procoption()` 的 `$arr`/`$option`；`save()` 的 `$array['id']`、`$re`；`batchsave()` 的 `$classify[...]` 可选键。
- `admin/app/model/moduleModel.php`：`save()` 的 `$array['id']`、`$array['info']`、`tags/author/inputer/origin`；`hits()` 的 `$re`。
- `admin/app/controller/moduleController.php`：`batch()` 的 `record`/`method`/`parameter`；`attrib_*` 可选键。
- `system/core/model.php`：`buildselectsql()` 中未定义的 `$options`，以及 `field()` 数组分支里错误的 `$this->prefixname`。
- 后台模板中未传入的 `$info` 等变量：先用 `./bin/dev logs php` 汇总确认，再在控制器补齐数据或模板中安全判空，不要用 `@` 压制。
- `system/core/email.php`：`ereg_replace()` 已在 PHP 7 移除；构造器把必填参数放在可选参数后，也有 PHP 8.4 Deprecated。

**要求**

- 用 `??`、默认值或显式初始化修复；可选数组键先 `isset`。
- 不改变错误显示策略：访客仍看不到内部错误，开发/测试要能记录 Warning。
- 模板修复不能破坏 `{$变量}` 语法和默认转义。
- `email.php` 确认无引用后，优先改为 PHP 8.4 兼容；如决定移除，必须单独说明影响和回退方式。

**验收**

- `./bin/dev check` 通过。
- 跑完回归后，用现有诊断汇总工具检查业务代码不再产生 `Undefined variable`、`Undefined array key`、`Deprecated`；测试框架自身的 CLI/session 提示单独说明，不混入业务问题。
- 至少补一个后台留言列表/报名列表/栏目表单的 HTTP 回归，防止初始化变量后行为变化。

### P1-2 删除或修复死代码

**候选**

- `system/core/page.php`：`getstaticurl()` 没有调用，引用不存在的 `$this->rewrite_rules`；`$rewriteRules` 还使用了现代 PHP 不可用的 `/ies` 修饰符。
- `system/app.php`：`__call()` 未被实际调用。
- `system/core/model.php`：`buildselectsql()` 的分页分支使用未定义 `$options`；`field()` 数组分支的 `$this->prefixname` 访问错误。
- `system/core/email.php`：未被当前入口加载，但仍含 PHP 7 已移除函数。
- `admin/app/model/recoverModel.php` 等未被路由使用的保存方法。

**要求**

- 删除前先 `rg` 全仓库引用，并核对 `cms_route_guard()` 的读写白名单；没有调用证据的代码可删除，可能在定制站点使用的公共 API 只做兼容修复，不直接删。
- 删除死代码和修 Warning 尽量分成不同提交，便于回退。
- 不删除“看起来旧但仍有模板/定制站点调用”的路由或模型方法，除非有明确证据。

**验收**

- 删除项有引用检查结果；保留项有原因说明。
- 回归全部通过，路由列表和模板输出无变化。

### P1-3 上传、备份、安装的负向验证

本轮原则上不改这些已加固的实现，只补验证；发现真实问题再单独修复。

**要求**

- 上传：未登录、缺 CSRF、双扩展名、伪造 MIME、SVG/HTML、超大文件、异常图片尺寸、路径穿越均拒绝；合法图片仍成功。
- 备份：非管理员、缺 CSRF、伪造备份 ID、路径穿越、直接访问备份文件均拒绝；合法下载可用。
- 数据库导入：非管理员、缺 CSRF、格式版本错误、注入列名、旧 SQL 包、超限包均拒绝；合法预览和确认流程可用。
- 安装器：确认开发环境 HTTP 入口关闭；安装锁、一次性密钥、未占用前缀校验仍在；测试使用空库/隔离库，不碰现有开发库。
- Apache/Nginx 的上传目录脚本禁止规则保持一致；未实测的服务器组合明确写入报告限制。

**验收**

- 新增或确认对应回归；不修改生产配置。
- 报告列出已测服务器/环境与未测范围，不把本地 Nginx 结果说成所有共享主机都安全。

## P2：后续但不阻塞本轮

### P2-1 性能测量后再优化

`classifyModel` 的逐层查询、站点地图、文章标签和搜索 `LIKE '%...%'` 仍可能放大 SQL 次数。先在真实数据副本采集 `EXPLAIN`、SQL 次数、峰值内存和响应时间，再决定是否批量读取/加索引/全文检索；不要在没有测量数据时大改查询。

### P2-2 存量站点升级手册

新增或完善 `docs/UPGRADE_CHECKLIST.md`，只写通用流程，不写客户域名、IP、路径或账号：

- 私有台账盘点站点 PHP 版本、数据库引擎、定制代码/模板、上传规模；
- 完整备份代码、数据库、上传和私有配置，并实测恢复；
- 在副本执行 `tools/migrate.php`，验证登录、栏目、内容、搜索、留言、附件、模板和备份；
- 安排停写窗口、切换入口、准备回滚快照；
- 回滚必须同时考虑代码、数据库和上传文件，不能只回滚 PHP 文件。

### P2-3 依赖与生命周期盘点

盘点 PHP 支持版本、jQuery 4/compat 使用点、wangEditor、HTML Purifier 和旧插件；核对来源、版本、许可证和本地托管方式。不要在本轮未经测试直接替换压缩版 vendor 代码。

## 统一验收命令

```bash
./bin/dev check
./bin/dev test
node tests/navigation-regression.js   # 后台结果页/回调有改动时
git diff --check
```

后台界面有改动时，还需在真实浏览器验证 iframe、编辑器全屏、选择与取消回填，并按需验证窄屏；截图放 `var/verification/`。涉及数据库的改动只在带 `f_dev_meta` 标记的开发库验证，测试数据使用独立标记并在 `finally` 清理。

## 建议提交顺序

1. `fix(security): 校验批量记录并参数化删除`
2. `fix(security): 参数化标签和作者计数更新`
3. `fix(admin): 收紧内容与留言保存字段白名单`
4. `test(security): 增加编辑角色注入与越权回归`
5. `fix(core): 清理未定义变量和废弃代码`
6. `docs(upgrade): 增加存量站点升级与回退清单`

每个提交只包含对应任务，不混入 `template/goods_search.html` 等维护者未跟踪文件；提交前检查 `git diff --cached`。

## 完成定义

- 每个 P0/P1 任务都有“修复前可复现、修复后通过”的回归证据。
- 现有测试全部通过，新增测试覆盖非法和合法两条路径。
- 不改变既有前台 URL、模板语法、内容可见性和角色边界。
- 变更说明中写明实际运行过的命令、未覆盖的环境和仍需人工确认的事项。
- 没有输出或提交任何密钥、客户信息、生产数据或验证截图。

## 本轮执行记录（2026-10-27）

- P0-1 至 P0-5：批量 ID、标签与作者计数、栏目及模型查询、保存字段边界均已修复并补合法/非法两侧回归。相关提交：`0183c59`、`0b43a1c`、`8940c0c`、`d65d5de`。回归测试检查数据库结果，不只核对 HTTP 状态。
- P1-1/P1-2：未定义值、PHP 8 邮件兼容、旧分页与未调用保存方法已处理；涉及的旧公共方法保留兼容修复。相关提交：`63e34ff`、`3fb45aa`、`047def1`。
- P1-3：新增上传、备份、导入负向回归；双扩展名问题经失败用例复现后修复，见 `eb0bc39`。开发容器中 `/install/index.php` 返回 404；安装器的一次性授权、CSRF、安装锁及隔离空库安装已有[安装说明](INSTALLATION.md)中的历史验证记录，本轮未重新运行独立空库安装。Apache、LiteSpeed、第三方共享主机配置仍需逐站验证。
- 报名设置表写入收紧后，使用独立临时表验证正常单行、有歧义多行、缺少主键三种情况，见 `9c68398`；存量站点先按[升级清单](UPGRADE_CHECKLIST.md)检查实际结构。
- P2：完成[性能测量方案](PERFORMANCE_MEASUREMENT_PLAN.md)、[依赖生命周期盘点](DEPENDENCY_LIFECYCLE.md)和[升级清单](UPGRADE_CHECKLIST.md)。真实性能测量须等待各站点隔离数据副本，不以开发库代替。
- 最终本地验证：`./bin/dev check` 为 329 个 PHP 文件、0 错误；`./bin/dev test` 为驱动 9 项、HTTP/数据库回归 419 项及隔离导入通过；`node tests/navigation-regression.js` 为 288 个组合通过；`python3 tools/verify-dependencies.py` 为 3 个 jQuery 副本通过。测试窗口内 PHP 容器诊断汇总为 0。
