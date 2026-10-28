# 富文本编辑器安全与替换评估

> 此报告为替换前的评估。后续已按用户选择迁移至 wangEditor 5，当前实现见 [迁移记录](EDITOR_MIGRATION.md)。

评估日期：2026-10-26。范围：本地仓库静态审查、已有回归覆盖与官方公开资料核对；未对线上进行漏洞利用测试。本次仅新增评估文档，不替换编辑器、不改变数据。

## 结论

建议将 KindEditor 替换列为本轮上线前的重要工作，同时补齐服务端富文本清洗。现有本地加固适合作为迁移过渡，不宜作为长期维护方案。不能仅凭版本认定生产站已被攻击，也不能将本地上传修复视为线上已修复。

本地 `admin/public/htmledit/kindeditor.js` 标注 4.1.7（2013-04-21）。[官方仓库](https://github.com/kindsoft/kindeditor) 已声明长期未维护、未来不再升级，并已归档。仅升级到同项目 4.1.12 无法解决持续维护问题。

## 证据和风险判断

| 项目 | 本地证据 | 判断 |
| --- | --- | --- |
| 编辑器维护 | 4.1.7，官方停止升级 | 高优先级维护风险，需迁移 |
| 上传与文件管理 | PHP 入口已重写，要求登录；上传校验 CSRF、类型、MIME、大小；随机文件名；路径限制 | 已缓解旧入口风险，不是所有编辑器漏洞都已消除 |
| 文件执行 | docker/nginx/default.conf 禁止上传脚本执行、屏蔽示例和非允许 PHP 入口 | 保护依赖部署配置，线上 Apache 必须另行核对 |
| 富文本存储型 XSS | admin/app/model/moduleModel.php 保存正文未做专用 HTML 清洗；前台 cms_html 保留正文 HTML | 高优先级应用层风险；需有后台写入权限或其他内容污染途径，未证实匿名访客可直接写正文 |
| 浏览器过滤 | kindeditor.js 的 filterMode 默认开启 | 客户端可被绕过，不能作为服务器信任边界 |
| 文件管理插件 | plugins/filemanager/filemanager.js 把 filename 拼入部分 HTML 属性 | 条件性注入风险：取决于目录文件名能否被不可信来源控制；随机上传名有缓解，历史文件/模板文件仍需核对。仅静态发现，未完成浏览器利用验证 |
| 多图上传 | batchpic/batchimg 使用 multiimage，旧包包含 SWF 资源 | 需要现代浏览器兼容性替换，不能假设换正文编辑器就涵盖此功能 |

HttpOnly Cookie 能限制脚本直接读取 Cookie，但不能阻止 XSS 在已登录会话中执行操作。CSRF 令牌也不能替代 XSS 防护。CmsHtml 是程序层信任标记，不是清洗器；目前正文沿用可信管理员内容假设，必须显式记录这个边界。

## 公开漏洞的适用边界

- [CVE-2018-18950 / NVD](https://nvd.nist.gov/vuln/detail/CVE-2018-18950)：公开记录描述 KindEditor 至 4.1.11 的 PHP 上传处理路径遍历风险。本地版本落入版本范围，但相应 PHP 端点已重写，不能把原版漏洞直接套到当前端点。
- [CVE-2021-30086 / CVE 官方记录](https://www.cve.org/CVERecord?id=CVE-2021-30086)：记录涉及 4.1.12 中文版本 XSS。用于说明升级至 4.1.12 不等于完成安全治理；记录并未据此证实当前 4.1.7 存在完全相同利用链。
- 当前未逐一复现所有公开漏洞，也未给出“无漏洞”认证。生产存在自定义上传文件且仍运行旧平台，需要独立核对。

## 方案比较

| 方案 | 适合本 CMS 的程度 | 代价与限制 |
| --- | --- | --- |
| 保留 KindEditor 并持续打补丁 | 可短期过渡 | 官方停止维护，后续安全责任转由项目承担；不建议长期使用 |
| 自托管 TinyMCE 受支持版本 | 优先做兼容性验证 | 与传统 textarea/HTML 工作流接近，仍需上传、文件库、表单同步适配及旧内容往返测试；许可需核对 |
| 自托管 CKEditor 5 | 备选 | 可输出 HTML、支持源码编辑，但模型/插件配置及旧 HTML 保留需要更多验证；许可需核对 |

优先验证 TinyMCE 是基于本项目传统 HTML 内容和后台架构的工程判断，不是“它没有漏洞”的保证。最终选择还应检查功能和授权是否符合当前项目。TinyMCE 和 CKEditor 5 均提供 GPL 路径及商业授权，不应将“可免费下载”理解为任意分发无条件免费；此处不作项目许可证兼容性结论。

参考：[TinyMCE 许可](https://www.tiny.cloud/docs/tinymce/latest/license-key/)、[TinyMCE 支持策略](https://www.tiny.cloud/docs/tinymce/latest/support/)、[CKEditor 5 许可](https://ckeditor.com/docs/ckeditor5/latest/getting-started/licensing/license-key-and-activation.html)、[CKEditor 5 源码编辑](https://ckeditor.com/docs/ckeditor5/latest/features/source-editing/source-editing.html)。

## 推荐实施顺序与验收

1. 盘点实际使用：正文、自定义富文本字段、图片、附件、多图、版权、模板选择器。入口不只 admin/js/common.js，还包括 admin/template/system/form/ 和栏目配置模板。
2. 增加服务端 HTML 白名单清洗：控制标签、属性、URL 协议、CSS 和嵌入内容，拒绝事件属性和脚本；同时验证标题等普通字段仍转义。使用维护中的解析器/清洗库，不用正则扫描 HTML。先对旧内容副本比较差异，避免误删表格、图片和排版；不直接批量覆盖历史内容。
3. 建立编辑器适配层，保留表单字段名、数据库 HTML 存储和前台模板变量语法。后台组件代码需改造，但无需要求使用者重写前台模板。
4. 新编辑器自托管，接入已有鉴权和 CSRF 上传服务；按新编辑器响应协议适配，不直接复制第三方示例上传 PHP。模板选择器独立保留或重做；多图改用现代上传接口。
5. 样本测试：旧文章打开后无修改保存、中文、表格、链接、图片尺寸、相对 URL、复制粘贴、多个编辑器实例、表单提交同步、文件选择和多图。安全检查覆盖恶意 HTML、危险 URL、未登录上传、CSRF 和上传目录不可执行。
6. 记录并固定经过验证的编辑器版本，移除旧脚本/插件/示例文件；按关键节点提交 Git，再到预发布验证。保留数据备份和回退入口，内容转换不可逆时不能只回退 JS。

[TinyMCE 官方安全指南](https://www.tiny.cloud/docs/tinymce/latest/security/) 同样要求考虑服务端过滤；换用现代编辑器不能取代上述服务端工作。当前已有 81 项回归检查并未覆盖完整的富文本恶意 HTML 和浏览器 DOM XSS 场景，需要新增专项测试。
