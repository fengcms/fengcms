# 文档目录

- [本地容器开发](LOCAL_DEVELOPMENT.md)：环境启动、账号配置、检查与备份。
- [共享虚拟主机首次安装](INSTALLATION.md)：HTTPS、站长授权、浏览器安装与故障排查。
- [GitHub 发布流程](RELEASING.md)：版本标签、发布包、校验和与后台版本查询。
- [安装程序检查](INSTALLER_AUDIT.md)：旧网页向导的历史风险及本次改造记录。
- [共享主机网页安装方案](WEB_INSTALLER_PLAN.md)：首次安装流程、安全边界与验收标准。
- [安全升级记录](SECURITY_UPGRADE.md)：修复、验证、线上盘点和迁移限制。
- [v1.36 代码审阅报告](CODE_AUDIT_V1.36.md)：安全、数据完整性、性能与维护性风险及优先级。
- [性能与代码维护性审阅报告](PERFORMANCE_CODE_REVIEW_2026-10-27.md)：当前性能问题、代码组织改进、验证证据与开发 AI 实施验收要求。
- [性能优化复审回复报告](PERFORMANCE_OPTIMIZATION_REVIEW_2026-10-27.md)：优化提交的逐项验收、事务与缓存复现问题、CI 诊断缺口及下一轮交付要求。
- [R1 至 R4 修复复验报告](PERFORMANCE_R1_R4_RECHECK_2026-10-27.md)：最新修复的源码核对、原失败用例独立重放、真实导入并发验证与四项关闭结论。
- [搜索、分页与内容页兼容性审阅](SEARCH_CONTENT_COMPATIBILITY_REVIEW_2026-10-27.md)：链接与路由往返、搜索页数边界、模型名大小写、缺失栏目处理及六项确认问题的验收要求。
- [SC01 至 SC06 修复复审报告](SEARCH_CONTENT_COMPATIBILITY_RECHECK_2026-10-27.md)：六项原问题的逐项关闭证据、Nginx 与 Apache 实测，以及原有未提交配置引起的搜索词转义回归。
- [后台内容管理数据一致性与异常恢复审阅](CONTENT_WRITE_CONSISTENCY_REVIEW_2026-10-27.md)：新增编辑、状态流转、批量及并发请求的八项实测问题、异常残留和开发验收要求。
- [CM01 至 CM08 修复复审报告](CONTENT_WRITE_CONSISTENCY_RECHECK_2026-10-27.md)：六项关闭证据、批量锁超时与栏目删除竞态的两项未闭合问题，以及真实浏览器和多引擎故障复验。
- [CM03 与 CM07 剩余问题再复审](CONTENT_WRITE_CM03_CM07_RECHECK_2026-10-27.md)：原最小复现已修复；累积锁等待的 HTTP 超时及跨模型子树删除仍需处理，附独立实测和验收要求。
- [CM03 与 CM07 关闭复验报告](CONTENT_WRITE_CM03_CM07_CLOSURE_2026-10-27.md)：重放原 14 条累计等待及跨模型 SQL 行锁场景，确认两项关闭，记录重试、多级删除清空和验证边界。
- [模型与自定义字段结构一致性审阅](MODEL_SCHEMA_REVIEW_2026-10-27.md)：六项确认问题，覆盖 DDL 与配置分步失败、批量列冲突、模型删除残留及导入和内容保存并发，附双引擎实测与修复验收要求。
- [MS01 至 MS06 修复复审报告](MODEL_SCHEMA_RECHECK_2026-10-27.md)：五项关闭，MS06 仍有删除中途失败后排队保存产生缺表 500 的边界；附双引擎 HTTP 重放、真实 iframe 和升级验证。
- [搜索与内容页兼容性修复结果](SEARCH_CONTENT_COMPATIBILITY_FIX_2026-10-27.md)：六项问题的处理、Nginx 与 Apache 实测及兼容边界。
- [性能审阅落实记录](PERFORMANCE_OPTIMIZATION_2026-10-27.md)：开发方的实现说明、本地验证结果及搜索优化的测量边界。
- [性能测量与优化判定](PERFORMANCE_MEASUREMENT_PLAN.md)：性能基线、执行计划、复测指标和优化合入门槛。
- [v1.36 后续加固实施方案](HARDENING_ROADMAP_V1.36.md)：后台备份与模型导入保留方案，以及已采纳风险的分阶段实施计划。
- [历史文档归档](legacy/README.md)：原 `_doc` 资料，文本已统一为 UTF-8，附模板白皮书原件与纯文本版。
- [第三方许可说明](THIRD_PARTY_LICENSES.md)：FengCMS MIT 许可与随附组件原许可的范围。

- [富文本编辑器安全评估](EDITOR_SECURITY_ASSESSMENT.md)：现状、风险边界和替换建议。

- [wangEditor 迁移记录](EDITOR_MIGRATION.md)：组件版本、接口、清洗规则、验证与回退。

- [资源选择器调整记录](RESOURCE_PICKER_UI_PLAN.md)：图片、多图、附件、模板选择与上传队列的实现和验收。
