# 旧模型导入码离线转换

旧版模型导入码解密后是可执行 SQL。新版后台只接收声明式 `.fengmodel.json`，不会执行旧码。仓库中的 `tools/convert-legacy-model.py` 可在本机用 Python 3 离线转换；它没有数据库连接。原站若还能使用新版代码导出模型，优先直接导出新格式。

1. 创建仓库已忽略的 `var/model-conversion/`，将**一个模型**的旧导入码单独保存为 UTF-8 文本，例如 `var/model-conversion/legacy-model.txt`。不要把它提交 Git。旧码使用旧程序内置的固定密钥，不能当作保密文件。
2. 从原模型设置中确认项目代号。旧导出器已把代号替换为 `#project`，工具无法从导入码自动找回，必须显式提供。
3. 在仓库目录运行：

   ```sh
   mkdir -p var/model-conversion
   python3 tools/convert-legacy-model.py \
     --input var/model-conversion/legacy-model.txt \
     --source-project goods \
     --output var/model-conversion/goods.fengmodel.json
   ```

4. 检查生成文件里的模块名称、物理列、字段类型、默认值和启用状态。输出权限为 `0600`；不要提交旧码或站点私有模型包。使用后台的导入预览，选择**新项目代号**后再确认。

转换工具只接受旧导出器生成的单个 `CREATE TABLE`、单个模型记录和字段记录；额外 SQL、触发器、自定义索引、未知字段类型及新格式无法表达的约束会被拒绝。拒绝后应从原站重新导出新格式，或人工整理模型定义，不能把旧 SQL 直接粘到数据库执行。

旧导出器会把记录中的空字符串转成 SQL `NULL`，也会丢失原项目代号。转换结果遵循**旧导入行为**，不能重建原记录中 `NULL` 与空字符串的区别；预览时须重点核对默认值。转换不会带走文章、上传文件或字段类型插件代码。目标站还需有相同的字段类型定义。先在隔离副本导入并测试表单，再迁移正式站点。
