# 后台权限边界与对象级授权审阅

日期：2026-10-28。审阅基线：`9f0d7d3 docs(frontend): 记录内容读取问题关闭复验`。审阅时工作区干净。范围为后台路由角色白名单、模型/栏目范围、独立 API 与写操作 POST/CSRF；未修改程序代码。

## 结论

确认 1 项编辑角色的对象级读取越权（P2，CVSS 估算 4.3）。编辑可以通过栏目读取路由枚举其无权管理的栏目元数据。当前审阅未发现对应写操作绕过：编辑角色的栏目编辑、删除、清空、批量和排序在服务端另有树归属校验。该读越权尚未在回归测试中覆盖，完整 HTTP/数据库回归通过不代表该路径已验证。

## 授权边界概览

| 范围 | 超级管理员 | 编辑 | 复核证据 |
| --- | --- | --- | --- |
| 后台路由/动作 | 受中央路由白名单约束；除登录外需有效会话 | 仅允许内容、相关及个人密码相关动作；其他系统动作拒绝 | `system/common/security.php:309-409`；`system/app.php:101-119` |
| 内容模型 | 可访问已注册且未处于结构操作锁定的模型 | 仅可访问启用模型；回收站另要求内容模型且回收功能启用 | `system/common/security.php:389-398` |
| 栏目写入 | 可管理栏目 | 保存、删除、清空、批量和排序检查目标栏目树及模型归属；编辑既有栏目不能改模型/类型/顶层关系 | `system/common/security.php:225-306` |
| 独立资源 API | 有效后台会话；上传要求 POST/CSRF | 同样可使用媒体选择、缩略图与上传，符合角色说明 | `admin/api/files.php:2-5`、`thumbnail.php:2-5`、`upload.php:2-7` |
| 写请求 | 后台 POST 统一走 CSRF 校验 | 同左 | `system/common/security.php:408-419`；上传直接调用 `cms_post_required()` |

## 发现

### AUTH01：编辑可以读取无权管理的栏目元数据

**严重度：**P2（受限后台元数据越权读取）；CVSS 估算 4.3（AV:N/AC:L/PR:L/UI:N/S:U/C:L/I:N/A:N）。

**攻击路径：**攻击者先取得有效 `editor` 会话，然后直接请求以下允许的 GET 路由：

1. `GET /admin/index.php?controller=classify&operate=index&id=<任意栏目ID>`。编辑角色的 `classify.index` 在读白名单中；`cms_editor_classify_guard()` 没有处理 `index` 动作。控制器直接按 `classid=<id>` 返回全部直接子栏目（`system/common/security.php:372-387`；`admin/app/controller/classifyController.php:23-25`）。
2. `GET /admin/index.php?controller=classify&operate=sorts&classid=<任意父栏目ID>`。`sorts` 也在编辑读白名单中，控制器直接查询该父 ID 下所有栏目（`admin/app/controller/classifyController.php:51-53`），同样没有对象归属校验。
3. `GET /admin/index.php?controller=classify&operate=classify`。该动作的归属检查只在 `p` 存在且非空时运行；省略 `p` 后，控制器按 `type="module"` 取出所有模型栏目并生成选择树（`system/common/security.php:259-266`；`admin/app/controller/classifyController.php:77-89`）。

这些路径没有写操作，不会据此直接修改受限栏目；但响应可包含编辑角色无权管理的停用/受限模型栏目名称、ID、URL/层级及模型标识，便于枚举后台配置。能否看到某些值取决于现有栏目数据；越权路径本身由路由与查询代码确认，具体敏感数据量待站点数据验证。

**现有防护及差异：**`update`、`delete`、`emptys`、`batch`、`order` 和 `save` 会调用栏目树授权检查；但列表/排序读取及无 `p` 的选择辅助没有同等范围过滤。因此这是读取范围不一致，不是已确认的栏目写入权限绕过。

**建议修复：**编辑角色的栏目列表、排序列表和栏目选择查询都应按允许的栏目/模型集合做服务端过滤。对带父级 ID 的读取先校验父栏目属于编辑可管理的树；对 `classify` 辅助路由，编辑请求缺少 `p` 时应按启用且有权管理的模型集合限制结果，或拒绝缺省范围。添加回归：创建一个启用模型栏目和一个编辑不可管理模型栏目，分别用编辑会话请求根列表、受限父级的 `index`/`sorts` 及缺省/指定 `p` 的 `classify`，断言响应不泄露受限栏目；同时确认超级管理员原结果不变。

## 验证记录

- `./bin/dev test`：通过；主 HTTP/数据库回归报告 631 项通过。既有角色回归覆盖编辑访问系统路由被拒绝、回收站模型限制、管理员表模型拒绝、媒体/模板文件名选择、栏目模型篡改拒绝等（`tests/regression.php:308-344,490-503`）。本轮发现的三个栏目读取边界不在这些断言中。
- 审阅时核对了中央路由白名单与后台公开控制器方法；未通过直接调用 `batchdelete`、`record` 等辅助方法绕过白名单的路径。
- 本轮未额外创建运行时测试数据或账号；AUTH01 的确认基于可达路由、缺失对象检查和直接查询的数据流，具体返回内容取决于站点栏目数据。
- 未执行浏览器端测试；CSRF 与写请求限制通过服务端代码和既有 HTTP 回归验证。
