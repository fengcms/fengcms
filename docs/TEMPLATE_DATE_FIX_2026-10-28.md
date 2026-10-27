# 无日期内容渲染修复记录

日期：2026-10-28。修复 GitHub 验证中 `PHP 诊断总数: 2; 位置数: 1` 的失败：编译后的前台首页模板执行 `strtotime(null)`，产生 `PHP Deprecated` 并让诊断门槛退出 1，同时让 `date('m/d', false)` 把没有日期的内容显示成 `01/01`。

## 原因

`f_article.date` 允许为 `NULL`，后台或迁移数据可以创建没有日期的内容。前台模板用 `{date('m/d',strtotime($v['date']))}` 直接输出日期，编译后原样执行：PHP 8.1 起对内部函数传 `null` 触发弃用告警，`strtotime()` 返回 `false` 后 `date()` 回落到 1970-01-01。`./bin/dev test` 不汇总 FPM 日志，且本地日志有历史条目，所以只有全新数据库的 CI 暴露了问题。

## 处理

- `system/common/function.php` 新增模板辅助函数 `cms_date($value, $format='m/d')`：非字符串与非整数、空串、空白串、以 `0000-00-00` 开头的值、无法解析的值都返回空串，合法日期按指定格式输出。
- `template/index.html`、`template/article.html`、`template/article_class.html`、`template/article_content.html` 共 22 处 `{date(...,strtotime($x['date']))}` 改为 `{cms_date($x['date'])}` 或 `{cms_date($x['date'],'Y年m月d日')}`，原有 `{...}` 模板语法与调用方式不变。
- 回归：`tests/regression.php` 插入一条 `date IS NULL` 的发布内容，验证首页日期单元格为空且页面不再出现 `01/01`；`tests/link-helper-regression.php` 覆盖空值、空白、`0000-00-00`、非法值、数组以及合法日期的两种格式。

## 验证范围

- `./bin/dev check`：346 个 PHP 文件无语法错误。
- `./bin/dev test`：598 项 HTTP/数据库回归全部通过，含新增的无日期渲染检查与日期辅助函数检查；日志中 `PHP Deprecated` 计数在整套测试前后未增加（仍为修复前本地复现遗留的 1136 条）。
- 手工复现：插入 2 条 `date IS NULL`、`status=1` 的标记内容后访问首页，日期单元格渲染为 `<span></span>`，FPM 日志未新增 `strtotime` 弃用记录；标记内容已删除，库内 `date IS NULL` 记录归零。

## 限制

- 本仓库自带的前台模板已改用 `cms_date`；站长通过后台在线编辑保存的自定义模板若仍写 `{date('m/d',strtotime($v['date']))}`，在无日期内容上仍会产生同样的弃用告警和 `01/01` 显示。旧模板文档中的该写法属于历史归档，未改动。
- 本地未跟踪的 `template/goods*.html` 演示模板做了同样的本地替换，便于本地环境零诊断；这些文件未纳入 Git。
- CI 的诊断汇总读取整份容器日志，本次未改动该门槛，也未在本地清空历史日志；判断依据为前后计数差。
