# FengCMS 全仓危险函数扫描与五批次数据流审计报告

> 后续复核：本报告保留原审阅结论；B2-01 对接口返回“模板源码”的表述、B3-01 对前台留言 CSRF 的判断与当前代码不符。逐项验证、修复及部署待办见[复核与修复记录](SECURITY_AUDIT_2026-10-27_FULL_SWEEP_REMEDIATION.md)。

- 审计日期：2026-10-27
- 审计基线：仓库当前工作区状态（第三轮审计基线 `3ee55ec` 之后）
- 审计方式：只读。未修改任何应用代码，未连接生产环境
- 执行者：AI 安全审计（MiMo，TraeCode 会话）
- 方法论：先全仓 ripgrep 扫描危险函数输出命中清单，再对每个命中追完整数据流（`$_GET/$_POST/$_FILES/$_COOKIE/$_SERVER` → 危险函数），仅参数完全不可控或已被既有防护拦截的才降级为低危/理论风险；不确定处标注"待验证"，不编造

## 交付物清单

1. 全仓危险函数命中清单（本文件第 1 部分，文件:行号，不展开）
2. 五批次数据流审计漏洞报告（第 2 部分，固定表格格式）
3. 已验证不可利用项及降级理由（第 3 部分）
4. 待验证边界清单（第 4 部分）
5. 结论与优先级建议（第 5 部分）

核心发现：**未发现高危或可直接利用的注入/RCE/认证绕过漏洞**；新增 7 条低危/理论级发现与 4 项待验证边界。

---

## 1. 全仓危险函数命中清单

扫描范围：`app/`、`admin/`、`system/`、`install/`、`tools/`、`tests/`、根目录入口；`system/vendor/htmlpurifier/` 为三方库，单列汇总。宿主机无 ripgrep CLI，使用 IDE 内置 ripgrep 完成。

### 1.1 SQL 拼接（`->query` / 原生拼接入口）

`->query(` 全仓 245 处，其中 `tests/regression.php` 134 处、`tests/driver-regression.php` 12 处、`tests/backup-import-regression.php` 10 处为测试代码。应用代码：

框架层（拼接汇聚点）：
- `system/core/model.php:86,95,105,121,141,152,162,172,182,196,206,344,354,384,388,392`（`buildselectsql()`/`excsql()`/DDL）
- `system/core/model.php:216`（`where()` 原始 SQL 字符串入参）、`:229`（`field()`）、`:264`（`sort()`）、`:311`（`buildselectsql()`）、`:343`（`excsql()`）
- `system/driver/db_mysqli.php:31,54`
- `system/core/page.php:378,382`
- `system/common/security.php:198,205`（`$table` 插值）
- `system/common/model_package.php:63,70,208,209,227`
- `system/common/backup.php:57,61,67,73,117,120,131,157,158,159,175,176`
- `system/common/backup_import.php:206,211,223,246,255,256,266,277,286,289,292,297,309,319`
- `system/common/install_schema.php:16,29,71`

前台 `app/`（经 `where()` 字符串拼接构造 SQL）：
- `app/model/classifyModel.php:24,28,29,34,77,96,52`
- `app/model/moduleModel.php:119,138,145,154,164,174,184,211,218,223,269,283,303,307,316,322,326,335,341,345,354,361,365,379,400`
- `app/model/singleModel.php:21`、`app/model/tagsModel.php:22,26`
- `app/controller/classifyController.php:31`

后台 `admin/`：
- `admin/app/model/baseModel.php:48,58`
- `admin/app/model/modulemanageModel.php:103,116`
- `admin/app/model/fieldmanageModel.php:183,237,294-358`（`fieldtype()` DDL 字段名拼接）
- `admin/app/model/classifyModel.php:23`、`fieldModel.php:32`、`signupModel.php:90`
- `admin/app/controller/classifyController.php:87`、`moduleController.php:195`（已用占位符）
- `admin/app/controller/fieldmanageController.php:97`（`sort(array_filter($_POST))` —— 用户输入进 `sort()`）
- `admin/app/controller/accountController.php:23,53,91,111,122,135,150,157`、`adminController.php:24,27,33,35,48,51`（均已参数化，第 5 批逐条复核）

模板内直接拼接（编译为 PHP 后执行）：
- `admin/template/module/index.html:102`（`where('classid="'.$_GET['classid'].'"&&project="'.$_GET['project'].'"')`）、`:107`

CLI/工具（非 Web 入口）：`tools/schema-migration.php`、`tools/migrate.php:21-57`、`tools/bootstrap.php`、`tools/config-preflight.php:27`

### 1.2 RCE

- `tools/check.php:9`（`exec(escapeshellarg(PHP_BINARY)...)`，参数已转义，CLI 专用）
- `system/vendor/htmlpurifier/.../VarParser/Native.php:30`（`eval`）、`ConfigSchema/InterchangeBuilder.php:182`（`eval`）—— 三方库内部
- 应用代码：`system`/`shell_exec`/`passthru`/`proc_open`/`popen`/反引号执行/`preg_replace /e`/`assert()` 调用 **0 命中**（`fieldmanageController.php:34` 的 `public function system()` 为函数定义，非危险调用）
- `system/core/template.php:202`（`{include}` 编译为 `include Template::Tpl(...)`，动态 include 生成点，见 1.4）

### 1.3 反序列化

- 仅三方库：htmlpurifier `DefinitionCache/Serializer.php:73`、`ConfigSchema.php:72`、`EntityLookup.php:26`（读取本地固定文件）
- 应用代码 `unserialize(` **0 命中**，无对象注入入口

### 1.4 文件操作 / LFI

- `move_uploaded_file`：`admin/api/upload.php:59`、`admin/app/controller/dbmanageController.php:69`
- `file_get_contents`：`admin/api/thumbnail.php:12`、`admin/app/controller/tplmanageController.php:45`、`modulemanageController.php:127`、`config.php:59`、`system/common/backup_import.php:116`、`system/common/install_schema.php:53`、`system/core/template.php:195`、`admin/app/model/baseModel.php:33`、`install/index.php:191`
- 动态 include/require：`system/common/function.php:44,50,74,251`、`system/app.php:158`（`require_once $controller_file`）、`config.php:16`、`install/index.php:104,224`、`system/core/template.php:202`
- `file_put_contents`：`system/common/function.php:256`、`system/core/template.php:147`、`admin/app/model/tplmanageModel.php:62`、`baseModel.php:73`、`system/common/backup.php:167`

### 1.5 其他

- `extract()`：`system/core/controller.php:33`（`extract($data, EXTR_SKIP)`，仅 `getcontent()` 内调用，受方法白名单限制）
- `parse_str()`：仅 `tests/regression.php:708`
- `$_REQUEST`：应用代码 **0 命中**
- `md5()`：`system/common/security.php:126,130`（旧口令三重 md5 兼容，登录后迁移）、`system/common/function.php:465,467,490`（`encrypt()` 签名）、`system/core/controller.php:19`（缓存文件名，输出为 hex）
- CSRF：`system/common/security.php:17-33,44,76,406`（`cms_csrf_check`）、`system/core/template.php:199,232,235`（自动注入 hidden field / meta tag）、`install/index.php:117-129`

---

## 2. 五批次漏洞报告（固定格式）

### 第 1 批：后台内容模块（module / classify / fieldmanage / modulemanage / recover）

| 漏洞ID | 文件:行号 | 漏洞类型(OWASP分类) | 攻击路径(数据流) | 影响 | CVSS估算 | 修复代码 |
|---|---|---|---|---|---|---|
| B1-01 | admin/app/model/fieldmanageModel.php:161-167（入口 fieldmanageController.php:74） | 批量赋值 Mass Assignment（A04:2021 Insecure Design） | `$_POST`（除 `id`/`project`/`_csrf` 外任意键）→ `fieldmanageController::save` → `fieldmanageModel::save` 更新分支 → `update($array)`：键经 `identifier()` 标识符白名单、值参数化 | 无 SQLi、无提权（fieldmanage 仅 super_admin 可访问，security.php:333/365-380）。但可在不执行 ALTER 的情况下改写 `module_field` 任意列（`name`/`type`/`module_id`/`sort`），造成字段元数据与物理列错配，影响前台内容保存白名单（`view_field`）行为 | 4.0（PR:H） | 更新分支按表单实际字段做白名单：`$array = array_intersect_key($array, array_fill_keys(['aliases','type','length','must','enable','search','sort'], true));` |
| B1-02 | admin/app/model/fieldmanageModel.php:49 → system/core/model.php:205-206；admin/app/controller/modulemanageController.php:72 → model.php:161-163 | SQL 注入（DDL 拼接，A03:2021）—— 数据源为 DB 而非请求 | `DROP COLUMN`/`DROP TABLE` 标识符直接拼接 `$arr['name']`、`$arr['project']`；两值来自 `f_module_field.name`、`f_module.project`。写入路径已正则校验（fieldmanageModel.php:172-174、modulemanageModel.php:78），存量老库数据未核验 | 若历史库中存在含反引号/空格的字段名或模型名，删除字段/模型时可逃逸反引号注入 DDL。当前 schema 下不可触发 | 4.4（待验证：依赖存量数据）（AV:N/AC:H/PR:H/UI:N） | `dropalter`/`droptable` 内复用校验：`if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$fiend)){ throw new InvalidArgumentException(...); }` |
| B1-03 | admin/app/controller/fieldmanageController.php:25,37,47,56 + system/core/model.php:56-57 | 拒绝服务/错误日志膨胀（A05:2021 Security Misconfiguration） | `$_GET['project']` 通过 guard 正则（`^[a-zA-Z][a-zA-Z0-9_]*$`，security.php:310-313）但不校验 module 表存在性 → `identifier()` 通过 → 查询不存在的表 → 异常 → `systemerror()` 写私有日志 | 已登录 super_admin 可重复触发 500 并撑大错误日志；错误详情不外泄 | 2.9（PR:L） | 读动作复用 security.php:383-393 的 module 存在性校验，或控制器入口 `if(!D('module')->where(['project'=>$_GET['project']])->getone()){cms_fail(404,...);}` |

### 第 2 批：上传与文件模块

| 漏洞ID | 文件:行号 | 漏洞类型(OWASP分类) | 攻击路径(数据流) | 影响 | CVSS估算 | 修复代码 |
|---|---|---|---|---|---|---|
| B2-01 | admin/api/files.php:20,26 | 信息暴露（A01:2021，边界说明项） | 已登录 editor → `dir=''` → 列出 `/template/*.html` 模板源码 | editor 本就可读前台页面，模板源不含密钥，泄露面有限 | 2.5 | 如需收紧，`dir=''` 仅允许 `$_SESSION['manage_role']==='super_admin'` |

上传/路径主链路均闭环（详见第 3 部分）：upload.php 五重校验、thumbnail/files/down 的 `cms_path_within` 路径限制、导入的 nonce+sha256+自动备份、tplmanage 的文件名白名单与 `cms_template_write_enabled()` 开关。

### 第 3 批：前台模块与搜索

| 漏洞ID | 文件:行号 | 漏洞类型(OWASP分类) | 攻击路径(数据流) | 影响 | CVSS估算 | 修复代码 |
|---|---|---|---|---|---|---|
| B3-01 | app/controller/messageController.php:25-28 + app/model/messageModel.php:5-22 | CSRF 缺失（A01:2021）—— 有替代防护，低危 | 访客页面嵌入表单 → POST `controller=message&operate=save`（前台白名单允许，security.php:353；`cms_csrf_check` 仅对 `$admin` 生效，security.php:76） | 仅能投递 `status=0`（服务端锁定，需后台审核后显示）的留言垃圾；字段 6 类白名单+长度上限+参数化 insert+验证码 | 3.7 | 在 `cms_route_guard` 中对前台 `message/save` 同样要求 `_csrf`（`js/csrf.js` 已在前台模板注入 meta token，template.php:235，改动极小） |

### 第 4 批：路由、动态 include 与模板编译

| 漏洞ID | 文件:行号 | 漏洞类型(OWASP分类) | 攻击路径(数据流) | 影响 | CVSS估算 | 修复代码 |
|---|---|---|---|---|---|---|
| B4-01 | system/core/template.php:212-230；admin/app/model/tplmanageModel.php:48-73 | 任意代码执行（A03:2021 / 设计信任边界） | super_admin → tplmanage save → 模板写入 → `{if 条件}`、`{函数(参数)}`、`{raw}` 编译为原生 PHP → 缓存文件被 include | 等价于 super_admin 已有权限，非提权：editor 不在 tplmanage 白名单（security.php:337/351），`cms_template_write_enabled()` 可关闭 | 4.9（PR:H；若把 tplmanage 下放给 editor 则升为 RCE） | 无需改码；在 `docs/SECURITY_UPGRADE.md` 明示"模板编辑=代码执行"，并保持 editor 永不获得 tplmanage；可选在 `parse()` 中对 `{if}` 参数禁用 `system\|exec\|...` 与反引号 |

### 第 5 批：认证、CSRF 与口令存储

| 漏洞ID | 文件:行号 | 漏洞类型(OWASP分类) | 攻击路径(数据流) | 影响 | CVSS估算 | 修复代码 |
|---|---|---|---|---|---|---|
| B5-01 | system/common/function.php:464-498 | 弱加密/硬编码密钥（A02:2021）—— 死代码 | `encrypt($str,'E','123456789')`：默认密钥硬编码、md5 截 8 字节作校验、RC4 式 XOR | 当前全仓无任何调用点（仅定义 1 处），纯理论；若未来被用于 cookie/凭据可伪造 | 2.0（实际不可达） | 删除该函数，或改用 `sodium_crypto_secretbox` + 环境变量密钥 |
| B5-02 | system/common/security.php:123-140（兼容分支）+ adminController.php:46-49 | 弱哈希存量（A02:2021）—— 已知边界 | 旧三重 md5 口令登录 → `cms_password_verify` 兼容 → `password_needs_rehash` 立即升级 bcrypt 并回写 | 未登录过的存量账号仍为 md5 三重，需先拖库才可离线爆破；新设/重置账号全为 bcrypt | 3.7（依赖"已获取数据库"前提） | 运维侧：对从未登录的存量账号用 `account/reset` 强制重置 |

---

## 3. 已验证不可利用项及降级理由（数据流闭环摘要）

### 后台内容模块（第 1 批）

| 命中 | 不可利用依据 |
|---|---|
| fieldmanageController.php:25,37,47,56 `$_GET['project']` 拼 where | guard 正则限定 `[a-zA-Z][a-zA-Z0-9_]*`，无法携带引号/空格/注释符；仅 super_admin 可达 |
| fieldmanageController.php:106-108 `$_GET['id']` 拼 where | guard 强制 `^[0-9]{1,10}$`（security.php:307-309） |
| fieldmanageController.php:97 `sort(array_filter($_POST))` | guard 对 id 数组逐项校验（security.php:316-323）+ `cms_sort_pairs` 数字校验（:424-438）+ `update` 数组形式参数化（model.php:138） |
| moduleController.php:161-177 `$this->{$method}(...)` 动态方法 | 方法名白名单 `['recover','thorough','attrib','attribc']`（:163）；`record` 经 `cms_id_list`（security.php:401）；参数白名单（:165-168）；where 全部数组参数化 |
| moduleController.php:195 `IN ('.$placeholders.')` | POST classarray 经正则 `^[1-9][0-9]{0,9}(,...)$`（:188-191）→ intval + 全部占位符绑定 |
| 模板 admin/template/module/index.html:102-108 | guard 已分别限定数字/标识符正则，引号无法闭合 |
| meta refresh 反射点（fieldmanage:85,87,110,112；module:137-201；recover:55-72） | project 正则不含 `"`/`<`，无法破属性；动作在 `$write` 白名单内，任何请求（含 GET）被 security.php:395-397 强制 POST+CSRF |
| classifyController 全链路 | where 全部数组形式或 `project=?` 绑定；id 经 `(int)`；sort 经 `cms_sort_pairs`；intro 经 `cms_clean_html`；enname 正则 |
| moduleController::save → moduleModel::save（moduleModel.php:66-127） | 列白名单（物理列 ∩ view_field，排除 id/hits/html/time/password）+ 值类型校验 + `cms_clean_record` 服务端 Purifier + 数组参数化 |
| modulemanageModel::save DDL（:100-103） | project 正则 `{0,29}` + `identifier()` + 值参数化 |
| recoverController 全链路 | search 字段经 view_field 白名单 + `identifier()` + LIKE 绑定；`recordIds` 逐项数字校验；方法白名单 |
| M()/D() 动态加载（function.php:64-106） | `M()` 仅被静态字符串调用（全仓 `M($_…` 0 命中）；`D()` 不含 include，表名经 `identifier()` → 无 LFI |

### 上传与文件（第 2 批）

| 命中 | 不可利用依据 |
|---|---|
| upload.php:36-59 `move_uploaded_file` | `is_uploaded_file` + 10MB → 文件名危险字符与双扩展正则（php/phtml/phar/svg/html/js）→ 扩展×目录白名单 → finfo 实测 MIME → 图片再过 `getimagesize` → 随机 32 位 hex 命名；`$dir` 必须命中 `$groups` 键，无路径拼接注入 |
| thumbnail.php:6-12 `file_get_contents($path)` | `cms_path_within(upload/image)`（realpath 前缀 + `..`/NUL/反斜杠拦截，security.php:440-457）→ 扩展+MIME+尺寸白名单 |
| files.php:26 `cms_path_within($base, $path)` | 同上 + 目录白名单 + 排序/分页参数白名单 + 无 SQL |
| dbmanageController:52-131 导入上传 | 仅 `.sql` 正则 + 8MB + `is_uploaded_file` → 私有目录 0600 随机名 → 会话 nonce `hash_equals` + 10 分钟过期 + sha256 复核 + 导入前自动备份 |
| tplmanageModel pathset/save/deletefile | 文件名 Unicode 白名单 + 禁 `..` + 符号链接拦截 + `cms_path_within` + `cms_template_write_enabled()`；仅 super_admin |
| modulemanageController preview:118-136 | `file_get_contents($file['tmp_name'])` 前有 `is_uploaded_file`+1MB；导入为 nonce+600 秒预览模式 |
| downController:4-21 | base64（strict）→ 强制 `/upload/` 前缀 → `cms_path_within` → 20 类扩展白名单；备份文件在私有数据卷，不在该根下 |

### 前台与搜索（第 3 批）

| 命中 | 不可利用依据 |
|---|---|
| searchController | tags≤80 字符、project 正则+`search=1` 存在性校验、page 数字、POST→303 转 GET、会话 60 次/分钟限流 |
| moduleModel::search:201-227 | `$needle` 经 `?` 绑定；`$table` 走 `identifier()`；`$offset` 为 int 运算；project 仅作内存比较 |
| classifyController 构造器:17-34 | slug/classid/project 三重正则 → 数组参数化查库，未命中 404 |
| `whereclass()` → classifyModel::children:43-48 | `(int)` 后仅用 DB 自身行生成 `classid = "N"` 片段 |
| `related()`/`atags()` | LIKE 全部 `?` 绑定，`IN` 占位符批量绑定 |
| `content()` | `lib_replace_end_tag`+guard 正则 project、`intval(id)`、数组 where、富文本经 `cms_html`（入库时已 Purifier） |
| 跨角色存储 XSS 输出面 | 前台模板 0 处 `{raw}`，全部走 `cms_template_output` 转义；admin `{raw}` 仅 tplmanage 文件名（rawurlencode + 默认转义）→ 当前不可达（与第二轮 R-01 整改结论一致） |
| `classifyModel::classify()/classside()`、`model::alter()` | 全仓无调用点，死代码，仅登记 |

### 路由与模板编译（第 4 批）

| 命中 | 不可利用依据 |
|---|---|
| app.php:151-161 控制器 require | `$class_name` 来自 `^[a-z][a-z0-9]*$` + read/write 白名单双闸（:96-120,332-353） |
| function.php:251 `include $path.$name.'.php'`（cache） | 唯一调用方 `controller.php:60-68` 的 `$this->cfile`（控制器/action 派生，均已白名单）→ 无穿越 |
| `{include}` → `Template::Tpl()` | `tpl_path` 恒前缀模板目录 + 禁 `..` + 必须 `.html` + realpath 前缀校验（template.php:145-158,440-457）→ DB/请求来源的 `channel_template`/`template` 值无法 LFI |
| 模板变量输出 | 普通变量 → `cms_template_output` 转义；`href="{...}"` → `cms_safe_url`+`cms_escape`（:207-210） |
| controller.php:33 `extract($_POST)` | 仅 `getcontent()` 内调用，`method` 已被 read 白名单限制 → 不可达外部输入 |

### 认证与 CSRF（第 5 批）

| 检查项 | 结论 |
|---|---|
| 登录（adminController） | 参数长度校验 → IP 60 次 + 用户 5 次（HMAC bucket，15 分钟窗）→ 占位符查询 → dummy bcrypt 哈希消除用户枚举时序 → 认证码 `password_verify`/`hash_equals` 双轨 → 统一失败响应 → 成功才清计数 + `session_regenerate_id(true)` + `auth_fingerprint` |
| CSRF 覆盖 | admin 全部写动作强制 POST + `hash_equals`（security.php:76-90），token 60 秒轮换；登录页与全部后台模板自动注入 hidden `_csrf`/meta tag（template.php:198-199,231-236）。"表单无 CSRF token"检查项通过（唯一缺口 B3-01） |
| 账号管理 | 全 `prepare/bind_param`；super_admin 不可删/停用、改其口令须操作者密码核验、编辑账号可删（与 `docs/ADMIN_ROLES.md` 一致） |
| safeController::safe SSRF | 固定 `https://www.so.com` + 协议白 HTTPS + 禁重定向 + 1s/3s 超时 + 64KB 响应上限 |
| `$_REQUEST` | 全仓 0 命中 |

---

## 4. 待验证边界（不编造）

1. **V-1 存量数据**：`f_module_field.name`、`f_module.project`、`classify.id/classid` 是否全部匹配标识符/数字正则（决定 B1-02 及前台 `wz/title/classname` 拼接的实际定级）——需只读查询生产/镜像库，本次未执行。
2. **V-2 留言回显**：`admin/template/message/reply.html` 对 `content`/`reply` 的输出是否走默认转义（前台已确认 0 处 `{raw}`；后台 reply 模板未逐行核对）。
3. **V-3 生产上传目录**：`docker/nginx/default.conf` 已禁 upload 脚本执行，生产 Nginx/Apache 等效规则未核（运维项，对应 AGENTS 安全边界）。
4. **V-4 伪静态模式**：`url_type=1` 时 project/classid 来自路径解析而非 `$_GET`；生产 rewrite 规则（第一轮 L-14 伪静态注入）属现场复核项。
5. **V-5 死代码登记**：`classifyModel::classify()/classside()`、`model::alter()`、`function::encrypt()` 无调用方，建议列入清理清单而非漏洞修复。

---

## 5. 结论与优先级建议

五个批次全部完成，全程只读、零代码修改。**未发现高危或可直接利用的注入/RCE/认证绕过**：路由层 `cms_route_guard`（参数格式 + 读写白名单 + 写操作 POST/CSRF）与模型层参数化构成双闸，第一轮 17 项整改（FENG-003、FENG-007、FENG-008 等）在当前基线确认有效。

本轮新增发现共 7 条，全部为低危/理论级：

| 优先级 | 条目 | 建议 |
|---|---|---|
| P2 | B3-01 前台留言无 CSRF | `cms_route_guard` 对 `message/save` 补 `_csrf` 校验（改动小、有现成 meta token） |
| P2 | B1-01 fieldmanage 更新分支批量赋值 | 更新分支补列白名单 `array_intersect_key` |
| P3 | B1-02 DDL 依赖存量数据 | 先执行 V-1 只读核验，再决定是否给 `dropalter`/`droptable` 补正则 |
| P3 | B1-03 project 无存在性校验 | 读动作复用 module 存在性校验 |
| P3 | B5-01 `encrypt()` 死代码 | 删除或替换为 sodium |
| P4 | B2-01 editor 可列模板源 | 按需收紧 `dir=''` |
| P4 | B4-01 模板编辑=代码执行 | 文档明示信任边界，保持 editor 永不获得 tplmanage |
| P4 | B5-02 存量 md5 口令 | 运维对未登录存量账号执行 `account/reset` |

待验证边界 V-1～V-4 需现场或数据库只读复核后再闭环。修复执行时遵循项目既有约定：逐项修复、每完成一项执行一次 git commit，并为每项配套回归用例。

---

相关文档：`docs/SECURITY_AUDIT_2026-10-27.md`（前三轮审计）、`docs/SECURITY_REMEDIATION_BACKLOG.md`（修复清单）、`docs/SECURITY_UPGRADE.md`（安全升级记录）、`docs/ADMIN_ROLES.md`（角色权限）、`docs/DATABASE_IMPORT.md`（导入演练）。
