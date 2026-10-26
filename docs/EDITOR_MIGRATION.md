# wangEditor 迁移记录

2026-10-26，按已确认方案实施。保留前台模板变量语法和 HTML 数据库存储，不批量改写历史内容；重新编辑旧内容可能改变排版。

## 阶段一：依赖与适配层

- wangEditor 5.1.23（MIT），本地托管于 admin/public/wangeditor；PROVENANCE.json 记录来源和包 SHA-512，下载时与 npm registry 的 integrity 核对。
- admin/js/editor.js 统一管理多个编辑器、内容同步、上传状态和独立文件选择器。
- CSS 和 JavaScript 不依赖公共 CDN；node --check 语法检查通过。此阶段尚未切换页面。
