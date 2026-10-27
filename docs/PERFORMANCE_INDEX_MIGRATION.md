# 文章发布状态索引迁移

新安装的默认文章表包含 `idx_status_id (status, id)`。现有站点可在核对执行计划后单独增加该索引；表前缀不一定是 `f_`，下列 SQL 须按实际前缀替换。

## 适用条件

- 文章列表、推荐和搜索使用 `status=1` 过滤，且发布记录只占文章表的一部分。
- `SHOW INDEX FROM f_article;` 中尚无以 `(status, id)` 为前导列的等效索引。
- 已有可恢复的数据库备份，并为索引构建预留磁盘空间和维护窗口。MyISAM 表的 `ALTER TABLE` 会阻塞写入；具体执行时间取决于表大小与主机性能。

## 执行与核验

```sql
ALTER TABLE `f_article` ADD INDEX `idx_status_id` (`status`, `id`);
SHOW INDEX FROM `f_article` WHERE Key_name = 'idx_status_id';
```

比较升级前后的首页、栏目、内容及搜索页结果，并用 `EXPLAIN` 检查 `status=1` 查询是否使用该索引。若原站点已有等效索引，跳过新增操作，避免重复索引。

需要回退且确认该索引由本次迁移新增时，执行：

```sql
ALTER TABLE `f_article` DROP INDEX `idx_status_id`;
```

索引仅改变查询路径，不改变模板语法、字段或文章数据。针对自定义模型的索引应分别结合记录量、发布比例和执行计划决定，不批量套用。
