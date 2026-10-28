# 后台权限 AUTH01 修复复审报告

日期：2026-10-28。复审基线：`eaeee02 fix(auth): 限制编辑角色栏目读取范围`。复审开始时工作区干净。本轮只读检查代码和新增回归，并在本地开发环境重跑测试；未修改程序代码。

## 结论

AUTH01 可以关闭。原三条读取路径均已按编辑角色范围拒绝或过滤受限栏目，超级管理员原有可见结果保持不变。新增 HTTP/数据库回归实际覆盖原失败场景并通过。

| 原路径 | 修复后的行为 | 复验 |
| --- | --- | --- |
| `classify&operate=index&id=<parent>` | 编辑先校验父栏目权限，再过滤不可见子栏目；不可访问父栏目返回 403 | 通过 |
| `classify&operate=sorts&classid=<parent>` | 同样校验父栏目并过滤排序列表 | 通过 |
| `classify&operate=classify` 缺少 `p` | 编辑结果过滤为可管理栏目；显式指定不可访问模型返回 403 | 通过 |
| 超级管理员的上述读取 | 不应用编辑过滤，保持原完整结果 | 通过 |

## 代码复核

- `cms_editor_category_allowed()` 统一判定编辑可见栏目：启用的自定义内容模型栏目、单页栏目，以及所属模型启用或属于公共范围的外链栏目（`system/common/security.php:225-248`）。
- `cms_editor_category_read_target()` 校验父级参数、记录存在和角色可见性（同文件 `cms_editor_category_read_target` 定义处）。`cms_editor_classify_guard()` 在控制器派发前为 `index`、`sorts` 调用该检查（同文件 `cms_editor_classify_guard` 定义处；入口见 `system/app.php:101-119`）。
- 列表、排序列表和选择树在渲染前使用 `cms_editor_visible_category_rows()` 过滤行；该函数对非编辑角色原样返回（`admin/app/controller/classifyController.php:23-26,51-56,77-94`）。显式未授权模型仍由前置 guard 拒绝。
- 原有 `save`、`delete`、`emptys`、`batch`、`order`、`update` 树级写入授权检查未被移除；本次变更没有放宽编辑角色写权限。

## 原失败场景重放

新增回归创建启用模型栏目、系统模型父子栏目，并用编辑和超级管理员 HTTP 会话访问原漏洞路径。测试断言：编辑根列表与排序列表不含系统模型栏目；在允许栏目下的列表与排序隐藏不可管理子栏目；将受限栏目作为查询父级时返回 403；省略模型参数的选择树不泄露受限栏目，显式请求受限模型被拒绝；超级管理员仍可看到受限父子栏目。相关断言在 `tests/regression.php` 中，复验全部通过。

## 验证与限制

- `./bin/dev test`：通过；主 HTTP/数据库回归 636 项通过，包含新增 AUTH01 场景；隔离站点设置、模板缓存、数据库导入/恢复及模型结构回归通过。
- `./bin/dev check`：PHP 8.4.27，355 个 PHP 文件、0 语法错误。
- `git diff --check`：通过。
- 验证限于带 `f_dev_meta` 标记的本地开发数据库及当前 PHP 8.4 / MySQL 8.4 环境；未验证生产定制分类数据、浏览器界面或其他数据库版本。
