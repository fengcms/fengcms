# wangEditor 迁移记录

2026-10-26，按已确认方案实施。保留前台模板变量语法和 HTML 数据库存储，不批量改写历史内容；重新编辑旧内容可能改变排版。

## 阶段一：依赖与适配层

- wangEditor 5.1.23（MIT），本地托管于 admin/public/wangeditor；PROVENANCE.json 记录来源和包 SHA-512，下载时与 npm registry 的 integrity 核对。
- admin/js/editor.js 统一管理多个编辑器、内容同步、上传状态和独立文件选择器。
- CSS 和 JavaScript 不依赖公共 CDN；node --check 语法检查通过。此阶段尚未切换页面。

## 阶段二：页面与 API 切换

已替换正文、自定义富文本字段和版权编辑器；图片、多图、附件和模板选择按钮使用独立选择器。上传与列表接口迁至 admin/api/upload.php、files.php，Nginx 不再执行旧编辑器 PHP 入口。浏览器实测中文正文直接保存成功，数据库值一致；81 项原有回归通过。
