# FengCMS 与第三方组件许可范围

FengCMS 自有代码与文档采用根目录 [MIT License](../LICENSE)。MIT 允许商业使用、修改、再发布和出售副本；分发软件或其重要部分时须保留版权与许可声明。站点内容及站点管理员自填的版权信息不因本仓库的软件许可而改变。

仓库随附的第三方代码不因根目录 `LICENSE` 改为 MIT 而改变原授权。请保留各组件自带的许可证、版权标头和来源信息：

| 组件 | 仓库位置 | 仓库中可核对的许可信息 |
| --- | --- | --- |
| wangEditor 5 | `admin/public/wangeditor/` | `LICENSE`、`package.json` 标注 MIT；来源摘要随组件保存。 |
| HTML Purifier | `system/vendor/htmlpurifier/` | `LICENSE`、`composer.json` 标注 LGPL-2.1-or-later。 |
| Validform | `js/validform/`、`admin/public/jquery/validform/` | 主脚本标头声明 MIT；插件可能有各自声明。 |
| jQuery 与 HTML5 Shiv | `js/`、`admin/public/jquery/` | 保留原脚本标头；HTML5 Shiv 标注 MIT/GPL2。 |
| SWFUpload | 两套 `validform/plugin/swfupload/` | 原脚本标头声明 MIT。 |

旧插件如 `js/jquery.marquee.js` 和 `validform/plugin/jqtransform/` 在仓库内只有作者信息，缺少可核验的完整授权文本。不能仅凭 FengCMS 的 MIT `LICENSE` 将它们宣称为 MIT；对外分发前应核对来源及授权，必要时替换组件。历史归档中的旧 FengCMS 授权协议仅用于说明旧版本，现行授权以根目录 `LICENSE` 为准。
