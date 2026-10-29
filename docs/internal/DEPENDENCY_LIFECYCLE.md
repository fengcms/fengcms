# 运行环境与前端依赖盘点

盘点日期：2026-10-27。这里记录仓库实际携带的版本与维护动作；各站点仍须按[升级清单](UPGRADE_CHECKLIST.md)核对自己的运行环境。更新第三方组件前先在隔离副本验证，不直接替换压缩文件。

| 组件 | 当前版本、位置与用途 | 来源、许可与核验 | 后续动作 |
| --- | --- | --- | --- |
| PHP | 安装器允许 8.3、8.4、8.5；默认本地容器为 8.4.27，隔离验证容器为 8.5.12。 | [PHP 官方支持周期](https://www.php.net/supported-versions.php)：8.3 安全支持至 2027-12-31；8.4 常规支持至 2026-12-31、安全支持至 2028-12-31；8.5 常规支持至 2027-12-31、安全支持至 2029-12-31。 | CI 对 PHP 8.4、8.5 执行完整回归；本轮在 PHP 8.5.12 跑过完整业务回归及隔离网页安装和后台登录，PHP 诊断为 0。首轮发现的 `imagedestroy()` 弃用调用已移除。 |
| jQuery | 本地 4.0.0：`js/jquery.js`、`js/jquery.min.js`、`admin/public/jquery/jquery-4.0.0.min.js`，用于前后台模板。 | [jQuery 官方下载页](https://jquery.com/download/)；三个副本与官方发布文件的 SHA-256 核对方式见 `tools/verify-dependencies.py`。版权和许可证标头随文件保留。 | 运行核验脚本；检查定制模板的旧 API。`js/jquery-compat.js` 仅补旧插件需要的 `trim` 与浏览器标记，不能视为完整的 jQuery 旧版兼容层。 |
| wangEditor | 5.1.23，`admin/public/wangeditor/` 本地托管；业务适配在 `admin/js/editor.js`。 | [项目源码](https://github.com/wangeditor-team/wangEditor)、目录内 `package.json`、`PROVENANCE.json`、`LICENSE`，MIT。 | 检查上游安全公告与发布记录；升级时单独验证图片上传、网络图片、全屏、表单回填。服务端仍须清洗提交内容。 |
| HTML Purifier | 4.19.1，`system/vendor/htmlpurifier/`；服务端富文本清洗。 | [上游发布页](https://github.com/ezyang/htmlpurifier/releases)、目录内 `VERSION`、`composer.json`、`LICENSE`，LGPL-2.1-or-later。 | 留意安全及兼容更新；改版本时验证清洗白名单和现有正文保存回归。 |
| Validform 与日期插件 | `js/validform/` 和 `admin/public/jquery/validform/` 本地副本，后台登录及表单使用。 | 仓库主脚本标头声明 MIT；子插件依各自声明，详见[第三方许可清单](THIRD_PARTY_LICENSES.md)。 | 升级 jQuery 时连同表单与日期选择交互实测；未经确认不要将所有子插件一概标为 MIT。 |
| marquee、jqtransform 等旧插件 | `js/jquery.marquee.js`、`*/validform/plugin/jqtransform/`；前台模板仍加载 marquee。 | 仓库内缺少可核验的完整授权文本。 | 对外分发前查明来源与授权，无法确认时以功能等价且许可清楚的组件替换，并验证受影响模板。 |

## 发布与复查

1. 每次发布前运行 `python3 tools/verify-dependencies.py`，保存结果；它校验三个 jQuery 副本和历史富文本转换包的记录摘要，不代表所有依赖都已完成完整性验证。
2. 对照官方支持周期、上游安全公告、许可证、仓库内版本与来源摘要记录变动。发现新版本不自动升级；先评估影响，再在 Docker 与独立站点副本测试。
3. 前端依赖升级覆盖登录、文章编辑器、上传、日期控件、搜索和自定义模板；PHP 升级覆盖安装、后台角色、内容保存及数据库导入/备份。
4. 发布包保留第三方原有许可和版权声明。根目录 MIT 许可仅覆盖 FengCMS 自有部分；未确认授权的旧插件需要单独处理。

已有隔离网页安装组合及未覆盖的服务器见[安装说明](INSTALLATION.md)；本文件没有宣称所有共享主机或定制站点已经验收。
