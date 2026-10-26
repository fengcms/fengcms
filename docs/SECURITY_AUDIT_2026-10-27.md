# FengCMS 代码安全审计记录（2026-10-27）

本文是维护期间的只读代码审计记录，不是部署授权，也不替代动态渗透测试。初次审计基线为提交 `0b43a1c`；复审基线更新为 `d65d5de`（`fix(security): 参数化后台栏目与模型查询`，2026-10-27）。维护者在审计期间并行提交了安全修复（`8940c0c`、`d65d5de`），因此文中的“当前状态”均以 `d65d5de` 为准；后续提交可能继续改变结论。凡标注“已修复”的条目均保留原始评分，便于回溯。

## 1. 审计范围与方法

- 范围：`app/`、`admin/app/`、`admin/api/`、`system/`（不含 `system/vendor/`）、`install/`、根目录入口文件、`tools/`、`tests/`、前台与后台模板中的输出点。
- 排除：`system/vendor/htmlpurifier/`（第三方源码）、`admin/public/wangeditor/`（第三方压缩代码）、`docker/` 与静态资源；`template/goods_search.html` 为维护者未跟踪文件，未纳入结论。
- 工具说明：宿主机没有 `ripgrep`，使用等价的 `grep -rnE`、`find` 与 Python 脚本完成函数扫描和数据流核对；`grep` 对以 `-` 开头的模式使用 `-e` 规避选项解析。
- 审计方式：从 `$_GET/$_POST/$_FILES/$_COOKIE/$_SERVER` 出发，追踪到 SQL、模板输出、文件、命令执行等 sink；只有参数完全不可控时才降级为“低危/理论风险”。
- 本次未修改任何应用代码，未执行漏洞利用，未连接或改动数据库，未运行 `./bin/dev test`（纯文档节点）。

## 2. 结论摘要

| 状态 | 漏洞 | 说明 |
| --- | --- | --- |
| 已修复 | FENG-001 | `hits()` 标签/作者计数 SQL 注入，`0b43a1c` 参数化并限制表名 |
| 已修复 | FENG-002 | 内容 `html` 任意写入型 XSS，`8940c0c` 按 `view_field` 白名单保存并排除 `html` |
| **仍存在** | FENG-003 | 标签名进入未编码的 `search()` 链接，全站页头存储型 XSS（编辑可写 `tags` 模块） |
| 已修复 | FENG-004 | 内容保存 mass assignment，`8940c0c` 改为字段白名单 + 状态枚举校验 |
| 已修复 | FENG-005 | 内容批量操作 `record[]` SQL 注入，`0183c59` 统一校验 |
| 已修复 | FENG-006 | 留言批量删除 `record[]` SQL 注入，`0183c59` 统一校验 |
| **仍存在** | FENG-007 | 栏目创建时 `enname` 未校验，服务端生成含载荷的 `html`，前台/后台 `{url(...)}` 形成存储型 XSS |
| **仍存在** | FENG-008 | 验证码无失败次数限制/失败不失效，可自动化绕过并批量留言 |
| 已修复 | FENG-009 | 报名批量删除 SQL 注入，`0183c59` 统一校验；且仅超级管理员可达 |
| 设计边界 | FENG-010 | 超级管理员可在线写模板 PHP，等价于设计内 RCE |
| 已修复 | FENG-011 | 栏目批量/批量新增 SQL 注入，`d65d5de` 统一 `cms_id_list()`、数组条件与 `(int)` |
| **仍存在（低危）** | FENG-012 | 外链栏目的 `html` 与友链 `url` 未限制协议，`javascript:`/`data:` 链接需访客点击后执行 |
| 低危/理论 | L-01～L-10 | 死代码、旧依赖、无超时请求、未定义变量等，见第 6 节 |

总体判断：相较旧版 FengCMS，当前代码已具备路由白名单、双角色读写白名单、强制 POST+CSRF、会话指纹、参数绑定、`model::identifier()`、`cms_path_within()`、上传 MIME/尺寸校验、HTML Purifier 与模板普通变量自动转义等防护。`8940c0c` 与 `d65d5de` 关闭了内容/栏目保存的 mass assignment 与栏目批量 SQL 注入。剩余可实际利用的问题集中在“编辑角色可控的栏目 `enname`/标签名”与“模板函数返回值不转义”两条链上；外链协议限制属于需交互的低危项。

## 3. 危险函数命中清单（精简）

### 3.1 SQL

| 文件:行号 | 说明 | 数据流/结论 |
| --- | --- | --- |
| `system/core/model.php:86,95,105,121,141,152,346,356` | 统一查询入口 | 数组条件走 `?` 绑定；字符串条件由调用方负责 |
| `system/core/model.php:303-308` | `identifier()` | 列/表名白名单，非法标识符直接抛异常 |
| `system/driver/db_mysqli.php:31,54` | `mysqli::query` | 无参数时原样执行；`list_fields()` 表名另行校验 |
| `system/driver/db_mysql.php:88` | `mysql_query` | 旧驱动；`config.php:50` 固定 `DB_TYPE='mysqli'` |
| `admin/app/controller/adminController.php:24,27,33,35,48,51` | 登录与限流 | 全部 prepared |
| `admin/app/model/moduleModel.php:56,97,103,106,132-159` | 计数/保存/批量 | `:56` 参数化；`:97`/`:103` 字段白名单后数组更新/插入；`:132-159` 仍原样写入 `name/url`（FENG-003/FENG-012 写入链） |
| `admin/app/model/classifyModel.php:23,32,125-134,142-182,204-236` | 栏目查询/保存/批量 | 控制器传入数组条件；`save()` 已做字段白名单与 ID 校验；`batchsave()` 仍原样写入 `name/enname` |
| `admin/app/controller/classifyController.php:23,51,68,129-148,169-180,212-258` | 栏目查询/删除/清空/批量 | 全部 `(int)` 强转与数组条件；批量 ID 由 `cms_id_list()` 在路由层先行校验（FENG-011 已修复） |
| `admin/app/model/messageModel.php:33,44` | 留言回复/删除 | 已 `(int)` 强转 |
| `admin/app/model/signupModel.php:41,51,64` | 报名详情/删除 | 已 `(int)` 强转 |
| `admin/app/controller/moduleController.php:208-257` | 内容批量 | 已改为数组条件绑定 |
| `app/model/classifyModel.php:24,28,34,77,96` | 前台栏目 | 调用方为数字或数据库 ID |
| `app/model/moduleModel.php:208,213,273` | 搜索/标签 | LIKE 与 IN 均绑定；分页偏移由整数计算 |
| `app/model/moduleModel.php:297,316,335,355` | 整站聚合 | 表名来自 `module.project`，创建时受正则约束 |
| `system/common/backup.php`、`backup_import.php`、`model_package.php` | 备份/导入/模型包 | 标识符白名单 + 受控格式；导入不执行上传 SQL |
| `tools/*.php`、`tests/*.php` | 开发工具 | 仅开发/CLI 使用，不属于站点入口 |

### 3.2 RCE

| 文件:行号 | 说明 | 结论 |
| --- | --- | --- |
| `tools/check.php:9` | `exec(PHP_BINARY -l ...)` | 开发工具，参数 `escapeshellarg` |
| `system/core/page.php:52,348,353` | 带 `/ies` 的伪静态规则 | 死代码：`getstaticurl()` 使用不存在的 `$this->rewrite_rules`，且无调用点；L-01 |
| `system/core/template.php:201-233` | 模板编译器 `preg_replace` | 表达式来自模板文件，不是请求参数；模板写入权限见 FENG-010 |
| `app/`、`admin/app/`、`system/`（除 vendor） | `eval/assert/system/shell_exec/passthru/proc_open/create_function` | 未命中（`fieldmanageController::system()` 为方法名误报） |
| 全仓（除 vendor） | 反引号命令执行 | 未命中；反引号仅用于 SQL 标识符 |

### 3.3 反序列化

| 文件:行号 | 说明 | 结论 |
| --- | --- | --- |
| `system/vendor/htmlpurifier/...` | `unserialize` | 第三方库内部配置缓存；本项目已关闭 `Cache.DefinitionImpl` |
| `system/core/database.php:66`、`db_mysql.php:229`、`db_mysqli.php:91` | `__destruct` | 仅关闭数据库连接，无可控对象注入点 |
| 应用代码 | `unserialize` | 未命中 |

### 3.4 文件

| 文件:行号 | 说明 | 结论 |
| --- | --- | --- |
| `admin/api/upload.php:55` | `move_uploaded_file` | 扩展名分组、`finfo` MIME、图片尺寸、随机命名；安全 |
| `admin/app/controller/dbmanageController.php:69` | `move_uploaded_file` | `is_uploaded_file`、大小、私有备份目录；安全 |
| `admin/api/thumbnail.php:12` | `file_get_contents` + `imagecreatefromstring` | 路径 `cms_path_within`、扩展名/MIME/尺寸；安全 |
| `admin/app/controller/tplmanageController.php:45`、`tplmanageModel.php:58` | 模板读写 | 固定根目录 + `cms_path_within` + 禁止符号链接；写入能力见 FENG-010 |
| `admin/app/controller/modulemanageController.php:126` | 读取上传 JSON | 交 `model_package` 严格校验；安全 |
| `admin/app/controller/safeController.php:30` | `file_get_contents` | 固定 `www.so.com` 主机；无超时（L-03） |
| `system/app.php:158` | 动态 `require_once` | 控制器名经 `^[a-z][a-z0-9]*$` 与读写白名单 |
| `system/core/template.php:195` | 读取模板 | `createfile()` 限定模板目录与 `.html` |

### 3.5 其他

| 文件:行号 | 说明 | 结论 |
| --- | --- | --- |
| `system/core/controller.php:33` | `extract($data, EXTR_SKIP)` | `$tpl/$path/$data` 已先赋值，不会被覆盖；L-09 |
| 应用代码 | `parse_str`、`$_REQUEST` | 未命中 |
| `system/common/security.php:126,130` | 旧 `md5(md5(md5()))` 校验 | 仅用于旧密码首次登录迁移，登录后 `password_needs_rehash` 升级 |
| `system/core/controller.php:19` | `md5(host+uri)` 缓存文件名 | 仅缓存键，不用于口令 |
| 表单/写接口 | CSRF | 模板自动注入 `_csrf`；`cms_post_required()` 强制 POST + token；登录页也带 token |

## 4. 详细漏洞报告

> 说明：CVSS 为基于当前代码的静态估算，未做动态验证；已修复项保留原始评分用于回溯。

| 漏洞ID | 文件:行号 | 漏洞类型(OWASP分类) | 攻击路径(数据流) | 影响 | CVSS估算 | 修复代码 |
| --- | --- | --- | --- | --- | --- | --- |
| FENG-001 | `admin/app/model/moduleModel.php:50-59`；调用 `:100-102`；入口 `admin/app/controller/moduleController.php:264-266` | SQL 注入（A03:2021） | 编辑登录 → `POST /admin/index.php?controller=module&operate=save&project=article` → `tags/author/inputer/origin` 未在 `cms_route_guard` 校验 → `hits($table,$name)` → `where('name="'.$v.'"')` | 编辑可盲注数据库，读取管理员哈希/改数据 | 8.8（已修复） | `0b43a1c`：表名限定 `tags/author/inputer/origin`，`where(['name'=>$value])` 绑定，空值跳过 |
| FENG-002 | `admin/app/model/moduleModel.php:66-117`（原 `:87`、`:101`）；输出 `template/index.html:41,61,76,101,126,159,191,212,230,248`、`template/article.html:61...`、`template/article_content.html:56,78,99,117,135`、`admin/template/system/list/title.html:1`；函数 `system/common/function.php:365-382` | 存储型 XSS（A03:2021） | 编辑 → 内容保存 POST 额外字段 `html=http://x/"><script>…</script>` → 原 `update($array)` 接受任意合法列名 → 前台 `{url($v['html'])}` 编译为 `echo url(...)` 不转义 | 公开页面执行脚本；超级管理员查看内容列表时也可触发，可借同源会话执行后台操作 | 6.2（已修复） | `8940c0c`：按 `view_field` + 物理列构造白名单，排除 `id/hits/html/time/password`，`status` 只允许 `0/1`；`html` 仍由服务端生成。模板层未转义的 `{url(...)}` 仍是 FENG-007 的根因，建议后续统一改为转义输出 |
| FENG-003 | `system/common/function.php:404-410`；输出 `template/scrap/header.html:48`、`template/index.html:267`、`template/tags.html:33`、`template/article.html:286`、`template/article_content.html:154`、`template/article_class.html:176` 等；写入 `admin/app/model/moduleModel.php:132-159`（`:137`、`:154`）+ `admin/app/controller/moduleController.php:264-276` | 存储型 XSS（A03:2021） | 编辑 → `module&operate=batchsave&project=tags`（编辑白名单允许；`tags` 为安装 SQL 内置 `type=0,status=1` 模型）→ 逐行写入标签名 `"><script>…</script>` → 全站页头 `{search($v['name'])}` 输出 `/?controller=search&tags=` + 原串 | 每个公开页面执行脚本，影响全部访客与后台预览 | 6.2（仅 `URL_TYPE=0`；`URL_TYPE=1` 走 `urlencode`） | `search()` 改为 `'/?controller=search&tags='.rawurlencode($string)`；`moduleModel::batchsave()` 对 `tags` 名称限制字符集/长度；模板侧对 `search()` 返回值转义 |
| FENG-004 | `admin/app/model/moduleModel.php:66-117`；`system/core/model.php:303-308`；`system/common/security.php:276-289` | Mass Assignment（A04:2021） | 编辑 → 内容保存 POST 任意合法列名（如 `html/status/time/hits/template`）→ 原 `update($array)` 只校验标识符、不校验字段语义 | 数据完整性破坏，并使能 FENG-002；可篡改 `html`、发布时间、状态、模板等 | 4.3（已修复） | `8940c0c`：以 `view_field` + 物理列构造允许写入集合；`id/hits/html/time/password` 排除；`status` 枚举校验；`time/hits` 由服务端控制 |
| FENG-005 | `admin/app/controller/moduleController.php:161-176`，原 sink `:208-257`；校验 `system/common/security.php:359-363` | SQL 注入（A03:2021） | 编辑 → `POST module&operate=batch` → `record[]=1" OR SLEEP(5)--` → 旧代码 `where('id="'.$v.'"')` | 编辑可盲注/批量篡改或删除内容 | 8.8（已修复） | `0183c59`：`cms_id_list()` 校验列表、逐项数字并转 `int`；批量方法改数组条件绑定 |
| FENG-006 | `admin/app/controller/messageController.php:58-60`；`admin/app/model/messageModel.php:31-59` | SQL 注入（A03:2021） | 编辑 → `POST message&operate=deletebatch` → `record[]` 原样传入 `delete()` → 旧 `where('id="'.$id.'"')` | 编辑可盲注并越权删除留言 | 8.8（已修复） | `0183c59`：同一 `cms_id_list()`；`delete()/reply()` 使用 `(int)` 与数组条件 |
| FENG-007 | `admin/app/model/classifyModel.php:142-182`（`:154` 允许创建字段、`:157` 白名单、`:169` 生成 `html`）、`:204-236`（`:211` `enname`、`:229-231` 生成 `html`）；入口 `admin/app/controller/classifyController.php:304-312`；guard `system/common/security.php:222-245`；输出 `template/scrap/header.html:97,144`、`admin/template/classify/index.html:61`；函数 `system/common/function.php:365-382` | 存储型 XSS（A03:2021） | 编辑 → `POST classify&operate=save`（`genre=one`，`type=module`，`project` 为已授权内容模型）或 `genre=batch` → `enname` 未校验，服务端拼出 `/project_<payload>_<id>.html` → 前台页头 `{url($v['html'])}` 编译为不转义 `echo url(...)`；`URL_TYPE=0` 时载荷原样进入 `classify=` 参数，`URL_TYPE=1` 时原样进入 `href` | 全站导航/栏目页执行脚本；后台栏目列表同样触发；影响公开访客与管理员会话 | 7.3 | `classifyModel::save()`/`batchsave()` 对 `enname` 校验 `^[a-z0-9-]{1,50}$` 并在写入前统一生成 `html`；模板层改为 `cms_escape(url(...))`；`url()` 不应把数据库字符串直接拼入 HTML 属性 |
| FENG-008 | `app/controller/homeController.php:18-24`；`app/model/messageModel.php:5-23`；`vccore.php:1-9`；`system/core/validatecode.php:14-16,28-33` | 认证机制缺陷/自动化滥用（A07:2021） | 访客获取验证码图片 → `POST home&operate=vccore`（只读 action，无 CSRF、无失败计数）反复猜 4 位码 → 成功后带同一会话的 `_csrf` 提交 `message&operate=save`；失败不 `unset` 验证码 | 批量垃圾留言/骚扰；验证码形同虚设 | 5.3 | 失败也 `unset($_SESSION['authnum'])` 或失败 3 次即失效；按会话/IP 限流；`hash_equals` 比较；验证码改用 `random_int` |
| FENG-009 | `admin/app/controller/signupController.php:66-68`；`admin/app/model/signupModel.php:70-79` | SQL 注入（A03:2021） | 超级管理员 → `signup&operate=deletebatch` → `record[]` 原样传入 `delete()` | 仅超级管理员可达；可盲注，但该角色已有数据库管理能力 | 4.9（已修复） | `0183c59`：`cms_id_list()`；`delete()` 使用 `(int)` 与数组条件 |
| FENG-010 | `admin/app/controller/tplmanageController.php:69-71`；`admin/app/model/tplmanageModel.php:45-68`；`system/core/template.php:190-226`；`system/app.php:151-162` | 设计内代码执行（A03/A08） | 超级管理员 → `tplmanage&operate=save` 写入含 `<?php … ?>` 的 `.html` → 模板编译后 `include` 执行 | 超级管理员可在服务器执行任意 PHP（与直接改代码等价） | 7.2（设计边界） | 若需降低误操作/账号失窃影响：保存时拒绝 `<?php`、`<?=` 与 `{php}`；模板编辑仅限部署流程或加二次认证与审计日志；保持仅超级管理员可达 |
| FENG-011 | `admin/app/controller/classifyController.php:195-259`（`:216`）；`admin/app/model/classifyModel.php:142-196`（`:184-196`）；`system/common/security.php:364-366` | SQL 注入（A03:2021） | 超级管理员 → `classify&operate=batch` 传 `classify[]=1" OR SLEEP(5)--`，或 `save&genre=batch` 传 `classid` 注入 → 原 `findone()`/`topid()` 字符串拼接 | 需超级管理员；技术可注入，但该角色已有备份/模板/模型管理能力，实际提权价值低 | 7.2（已修复） | `d65d5de`：路由层对 `classify` 批量统一 `cms_id_list(max 500)`；控制器/模型全部改数组条件与 `(int)`；`topid()` 只接受整数；`save()` 增加 ID 与字段白名单 |
| FENG-012 | `admin/app/model/classifyModel.php:142-182`（`:152`、`:155` 允许 `html`）；`admin/app/model/moduleModel.php:132-159`（`:140`、`:150` 写入 `url`）；输出 `template/scrap/header.html:97,144`、`template/index.html:290`、`admin/template/classify/index.html:61,66,70` | 不安全 URL 协议（A03:2021） | 编辑 → 创建/修改 `type=url` 栏目并提交 `html=javascript:alert(document.cookie)`，或 `batchsave&project=friend` 提交 `url=javascript:…` → 模板输出 `href="{$v['html']}"` / `href="{$v['url']}"`（属性已转义，协议未限制） | 访客点击导航或友链后在同源执行脚本；需交互，影响低于 FENG-003/007 | 4.6 | 写入前用 `cms_safe_url()` 校验（仅允许 http/https/mailto/tel）；模板侧对 `javascript:`/`data:` 等协议拒绝渲染 |

## 5. 已审模块与结论

| 模块 | 关键文件 | 结论 |
| --- | --- | --- |
| 登录/认证 | `admin/login.php`、`admin/app/controller/adminController.php`、`system/common/security.php` | prepared、登录限流（IP 60/15min、账号 5/15min）、`session_regenerate_id`、指纹绑定、1h 活动/12h 会话、`REMOTE_ADDR` 限流；无 SQLi |
| 角色与账号 | `accountController.php`、`manageController.php`、`manageModel.php` | 仅超级管理员管理账号；改密需当前密码；编辑账号可删/停用；不可删/停用超级管理员；prepared |
| 内容模块 | `admin/app/controller/moduleController.php`、`admin/app/model/moduleModel.php` | `8940c0c` 后字段白名单/状态枚举已生效（FENG-002/004 已修复）；`batchsave` 仍原样写入 `name/url`，是 FENG-003/FENG-012 的写入链 |
| 栏目模块 | `admin/app/controller/classifyController.php`、`admin/app/model/classifyModel.php` | `d65d5de` 后批量/删除/排序全部数组条件与 `(int)`（FENG-011 已修复）；`save()` 已白名单化，但创建分支的 `enname` 仍进入 `html`（FENG-007），`type=url` 的 `html` 协议未限制（FENG-012） |
| 留言模块 | `admin/app/controller/messageController.php`、`admin/app/model/messageModel.php`、`app/controller/messageController.php` | 批量删除已修复；前台字段白名单/长度/服务端状态控制；验证码缺陷见 FENG-008 |
| 报名/回收站 | `signupController.php`、`signupModel.php`、`recoverController.php` | 详情/删除 `(int)`；回收站批量仅 `recovers/thorough` 且逐项数字校验 |
| 上传/文件 | `admin/api/upload.php`、`files.php`、`thumbnail.php`、`app/controller/downController.php` | 扩展名/MIME/尺寸/随机名/路径范围/`nosniff`；上传目录禁止脚本需以部署配置为准 |
| 备份/导入 | `admin/app/controller/dbmanageController.php`、`system/common/backup.php`、`backup_import.php` | 受控 SQL 生成、哈希/锁/预览 nonce、上传 SQL 只解析不执行、结构核对与临时表交换 |
| 模板/缓存 | `system/core/template.php`、`admin/app/controller/tplmanageController.php`、`cacheController.php` | 模板路径限定 `.html` 与模板根；普通变量自动转义；函数返回值不转义（FENG-002/003/007）；模板写入为设计边界 |
| 模型/字段管理 | `modulemanageController.php`、`fieldmanageController.php`、`system/common/model_package.php` | 表名/列名/类型白名单，DDL 走 `identifier()`；仅超级管理员 |
| 安装器/配置 | `install/index.php`、`config.php`、`system/common/install_schema.php` | 一次性密钥、HTTPS、CSRF、安装锁、前缀占用检查、私有配置 0600；拒绝弱默认值 |
| 基础设置/安全检测 | `baseController.php`、`baseModel.php`、`safeController.php` | 版权富文本清洗、`url_type` 白名单与原子写；安全检测请求固定主机但无超时（L-03） |
| 数据库驱动 | `db_mysqli.php`、`db_mysql.php` | 当前固定 mysqli；prepared + 严格错误模式；旧 `mysql` 驱动仅在显式切换时加载 |
| 第三方 | HTML Purifier 4.19.1、wangEditor 5.1.23 | 本地托管；本审计未逐行审计第三方源码 |

### 5.1 本轮复审补充（基线 `d65d5de`）

| 范围 | 复核要点 | 结论 |
| --- | --- | --- |
| 安装器 | `install/index.php`：HTTPS 强制、一次性 `authorize.php` 密钥、CSRF、5 次失败锁 15 分钟、安装锁、前缀占用检查、临时配置 `0600` + `rename` 发布、安装失败不自动删表 | 未发现新的越权/注入；`install.sql` 不含默认可登录口令，管理员由 `cms_install_admin()` 以 `password_hash` 覆盖 |
| 安装 SQL 导入 | `system/common/install_schema.php`：前缀正则、`DROP` 拒绝、`multi_query` 前占用检查、数据库级 `GET_LOCK` | 前缀只允许 `[A-Za-z][A-Za-z0-9_]{0,29}`；未发现注入 |
| 备份导入 | `system/common/backup_import.php`：固定 4 行文件头、表/列名 `^[A-Za-z_][A-Za-z0-9_]{0,63}$`、只解析 `INSERT`/字面量、拒绝未知表达式、8 MB/1 万行/100 表/120 秒上限、导入锁、临时表整体交换、导入前自动备份 | 上传 SQL 不被执行；未发现可利用的 SQL/路径问题 |
| 模型包 | `system/common/model_package.php`：JSON 精确键集合、列名/类型白名单、必需系统字段、字段类型与目标站一致性核对、`GET_LOCK`、失败回滚建表 | 未发现 DDL 注入；仅超级管理员可达 |
| 备份管理 | `admin/app/controller/dbmanageController.php`：`backup_id` 正则、上传 `is_uploaded_file`、预览 nonce + 10 分钟过期 + SHA-256 复核、写操作 POST + CSRF | 未发现越权读取/删除任意文件 |
| 缩略图 API | `admin/api/thumbnail.php`：登录校验、`cms_path_within(upload/image)`、扩展名 + `getimagesize` + MIME + 像素上限 | 未发现任意文件读取；GD 解码限制明确 |
| 上传/列表 API | `admin/api/upload.php`、`files.php`：登录 + POST + CSRF、扩展名分组、`finfo` MIME、图片尺寸、随机命名；目录白名单、`cms_path_within`、跳过符号链接、文件数上限 | 未发现任意文件写入/读取；模板选择器仅列 `.html` |
| 认证/账号 | `admin/app/controller/accountController.php`、`manageController.php`、`manageModel.php`：仅超级管理员管理账号、prepared、角色固定、重置超级管理员需当前密码、改密需旧密码、`auth_version` 失效会话 | 未发现越权或注入 |
| 前台搜索/留言 | `app/controller/searchController.php`、`app/model/messageModel.php`：tags 长度/模型存在性/页码校验、POST 303、会话 60 次/分钟限流；留言字段白名单/长度、状态与时间服务端控制 | 搜索无注入；验证码缺陷沿用 FENG-008 |
| 路由/控制器加载 | `system/app.php:100-120,151-162`：controller/action `^[a-z][a-z0-9]*$` + 读写白名单；自动加载只拼接已验证的类名 | 未发现路径穿越或任意类加载 |
| Nginx | `docker/nginx/default.conf`：仅开放指定 PHP 入口、`/install/index.php` 单独放行、`system/app/template/tools/tests` 等目录 404、上传目录脚本扩展 404、配置/SQL/日志 404 | 与代码层防护一致；生产 Apache 需按同等规则核查 |
| 模板路径 | 内容/栏目 `template` 字段可被编辑写入，但 `Template::Tpl()` 前置模板根目录，`createfile()` 再经 `cms_path_within()` 且只允许 `.html` | 不能借此读取任意文件或执行 PHP；可在修复 FENG-007 时顺带把模板字段限制为现有模板白名单 |

## 6. 低危/理论风险与死代码

| ID | 文件:行号 | 问题 | 建议 |
| --- | --- | --- | --- |
| L-01 | `system/core/page.php:52,341-367` | `/ies` 规则（PHP 8 已移除 `/e`），且 `getstaticurl()` 引用不存在的 `$this->rewrite_rules`，无调用点 | 删除死方法或改为 `preg_replace_callback` |
| L-02 | `system/core/email.php:51,166,203,250-258` | 使用 PHP 7 已移除的 `ereg/ereg_replace`；当前无调用点 | 删除或改用 `preg_*`，不要恢复旧邮件功能时直接启用 |
| L-03 | `admin/app/controller/safeController.php:30` | 无超时、无响应大小限制的 `file_get_contents`，主机固定 | 增加超时/大小限制，失败降级为静态提示 |
| L-04 | `system/common/function.php:507-523` | `getIP()` 优先信任 `X-Forwarded-For`；当前无调用点 | 若将来用于限流，改用可信代理白名单后的 `REMOTE_ADDR` |
| L-05 | `app/error/error.php:19-22`、`admin/app/error/error.php:19-22` | 未转义 `$Message/$File/$Line`，且当前无引用 | 删除死模板，或统一走 `systemerror()` 的私有日志 |
| L-06 | `system/driver/db_mysql.php:88` | 旧 `mysql_query` 驱动，函数在 PHP 8 不存在 | 保持 `DB_TYPE='mysqli'`，后续删除旧驱动 |
| L-07 | `admin/app/model/recoverModel.php` | `save()` 直接 `update($_POST)` mass assignment，当前未发现调用点 | 删除死代码，避免被重新接线 |
| L-08 | `admin/app/controller/messageController.php:25`、`signupController.php:25` | `$where` 未定义即传入 `sqlpage()`，产生警告 | 初始化为空串 |
| L-09 | `system/core/controller.php:33` | `extract(..., EXTR_SKIP)`；模板变量来自控制器/数据库 | 保持 `EXTR_SKIP`；新增模板变量时避免与局部变量同名 |
| L-10 | `system/core/page.php:307-313` | `wap` 分页把 `$_GET` 原值拼入 `postfield`，默认 `pagetype='web'` 不可达 | 若启用 wap 分页，对键值统一转义 |
| L-11 | `app/controller/homeController.php:18-24` | `$_POST['param']` 未校验字符串类型；数组输入在 PHP 8 触发 `TypeError` 并返回 500 | 先 `is_string()` 校验，失败时统一返回“验证码不正确” |

## 7. 未测与待验证边界

- 未做动态渗透与真实浏览器验证；FENG-003/007/012 的实际触发依赖具体模板组合、`URL_TYPE` 与编辑账号权限，建议在隔离开发库用独立标记账号复现后再修复验证。FENG-002/004/011 已按代码复核确认修复，但未做回归执行。
- 未验证存量站点的定制模板与旧插件；若前台模板自行调用 `{url(...)}`、`{search(...)}` 或其他未转义函数，风险面可能更大。
- 未审计 `system/vendor/htmlpurifier/` 与 `admin/public/wangeditor/` 第三方源码；升级时仍需核验来源、版本与许可证。
- 未验证部署层：Nginx 对上传目录脚本执行的禁止、`install/` 入口收敛、HTTPS/HSTS、备份目录权限需在现场核查。
- FENG-008 的验证码字符集为 56 字符、长度 4；是否可被实际爆破取决于站点请求速率与是否有反向代理限流，本文未做压力测试。
- FENG-010 的“设计内 RCE”是否可接受取决于运维模型；若超级管理员账号可被社工/失窃，建议增加模板保存二次确认与内容扫描。

## 8. 建议修复顺序

1. **优先**：FENG-007、FENG-003（存储型 XSS，影响公开访客与后台会话；两者均涉及“函数返回值不转义”或“未编码链接”）。
2. **其次**：FENG-008（验证码失败失效与限流）、FENG-012（外链/友链协议白名单，使用现成的 `cms_safe_url()`）。
3. **加固**：模板层统一转义 `{url(...)}`/`{search(...)}` 返回值；FENG-010 的模板内容限制与审计日志；L-01～L-10 的死代码清理。
4. 修复后补充回归：编辑角色构造 `enname/tags/classify[]` 的负向用例、`javascript:` 链接拒绝用例、前台输出断言、验证码失败次数断言；仅在带 `f_dev_meta` 的开发库运行。

## 9. 变更记录

- 2026-10-27：创建本文档，基线 `0b43a1c`；记录 FENG-001～FENG-011 与 L-01～L-10；确认 FENG-001/005/006/009 已由 `0b43a1c`、`0183c59` 修复。
- 2026-10-27：复审基线更新为 `d65d5de`。复核 `8940c0c` 后确认 FENG-002/004 已修复，`d65d5de` 后确认 FENG-011 已修复；FENG-007 收敛为“创建时 `enname` 未校验”，新增 FENG-012（外链/友链协议未限制）；补充安装器、备份导入、模型包、缩略图 API 与 Nginx 配置复核结论（5.1 节）。
- 本次仅新增/更新文档，未修改应用代码、模板、数据库或部署配置。
