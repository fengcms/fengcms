# 前台内容读取复查遗留项处理

依据[复查报告](FRONTEND_CONTENT_READ_RECHECK_2026-10-28.md)处理 FR03、FR04。FR01、FR02 在复查中已确认关闭，本次未改动。

## FR03：无状态索引的自定义模型

复查结论成立：导入的合法模型只有 `PRIMARY KEY(id)`，用 `WHERE status=1 ORDER BY id DESC LIMIT 500` 找候选时，LIMIT 不限制读取多少主键记录才能找到 500 个已发布行。

候选阶段现在移除状态过滤，改为单独沿主键倒序读取最多 500 个物理行 ID。随后再按状态、当前文章 ID、关键词执行有界匹配查询。因此即使模型没有 `(status,id)` 索引、已发布记录很稀疏，候选读取仍最多遍历 500 个主键记录。草稿也会占用这 500 行窗口；窗口之外的旧文章可能不再成为相关文章，详见[总修复记录](FRONTEND_CONTENT_READ_REMEDIATION_2026-10-28.md)。没有改模型包格式或给每个自定义内容表增加索引。

隔离回归使用只有主键的自定义模型表，其中最新 500 行只有少量已发布内容。MySQL 8.4.11 的 `EXPLAIN ANALYZE` 显示 `Covering index scan ... using PRIMARY (reverse)`，实际读取 500 行；回归断言这点并拒绝表扫描计划。

## FR04：栏目导航索引

复查结论成立：原栏目表没有能支持父栏目、导航标志和排序的索引；而 `classid` 是 `varchar`，使用数字比较可能妨碍索引匹配。

新安装 SQL 增加 `classify_nav_parent_sort (classid(16), nav, sort)`。栏目导航查询将经校验的父 ID 作为字符串比较，并使用 `EXISTS` 检查模型启用状态，使 MySQL 可以沿复合索引顺序扫描，在满足 limit 后停止。模型关联不再通过外连接触发结果排序。

旧站点通过 `php tools/migrate.php` 幂等增加该索引；本地开发数据库初始化也调用同一迁移。迁移前仍应备份并在副本验证；`ALTER TABLE` 在部分数据库版本/引擎可能短暂锁表。栏目表在隔离测试中加入 5,000 个导航项后，MySQL 8.4.11 的 `EXPLAIN ANALYZE` 显示 `classify_nav_parent_sort` 范围扫描，按索引顺序实际读取 10 行满足 limit，计划没有 Sort 节点。

## 验证边界

- 回归运行在本地带 `f_dev_meta` 标记的开发库及脚本创建的隔离表；未迁移生产数据库。
- 执行计划证明了当前 MySQL 8.4.11 与指定夹具上的访问路径；没有据此声称生产响应时间提升比例。其他 MySQL/MariaDB 版本可能选择不同计划，应在部署副本确认。
