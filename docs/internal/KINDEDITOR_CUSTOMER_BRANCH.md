# KindEditor 客户兼容分支维护

基线：`master` 的 `85b48c9`。分支：`codex/customer-kindeditor-4.1.7`。不修改 master，不执行部署或 push。

## 代码边界

- `admin/public/kindeditor-4.1.7/`：来自官方历史提交 `3403a67f61896c4aa4dd11ab40bbde7ba7e77ed6`（Build 4.1.7）的静态子集，逐文件摘要见 `PROVENANCE.json`。与本仓库历史主脚本统一换行后相同；纳入文件保留上游原始字节和 LGPL 许可证。禁止业务适配直接修改 vendor。
- `admin/js/editor.js`：KindEditor 生命周期、表单同步、选区恢复、全屏 iframe、资源入口、原文快照、安全源码预览与撤销。原有 textarea 名称、ID 和 `FengEditor.sync/upload` 入口保留。
- `admin/js/resource-upload.js`：从原适配器提取的公共上传运输层，保留 CSRF、取消、超时、进度及重试请求标识。
- `admin/js/resource-picker.js`：新增无绑定输入框时的 `initial` 参数，供正文插图/附件使用；已有图片字段仍按原契约回填。
- `admin/js/richtext-compat.js`：惰性 DOM 解析、图片与图片链接核对、URL 校验、认证/CSRF 下的服务端清洗请求。不运行 Markdown 转换。
- `system/common/richtext.php`：在 HTML Purifier 白名单内保留旧式字体、段落间距、缩进、行距及表格属性；CSS URL、定位、事件、脚本等继续禁止。版权缓存命名空间已更新，避免复用旧策略结果。
- wangEditor 和历史 Markdown 转换静态资产保留以便后续与 master 对照，但本分支编辑页不加载它们。资源功能不依赖 wangEditor 运行时。

默认覆盖已有 `data-cms-editor` 字段：正文、自定义 `htmledit.html` 和版权。栏目介绍实际是 `varchar(255)` 普通文本，不在本次替换范围内，不扩表、不改模板语法或前台渲染路径。

## 保存和安全

1. 先保存原始 textarea 快照，再经现有 `/admin/api/richtext.php` 安全清洗后载入 KindEditor。原文仅以 textarea 的 value 展示，不直接写入活动 DOM。
2. 将编辑器首次规范化的 HTML 作为比较基准。现有记录的未改字段在表单序列化时 disabled，后端按既有“仅更新提交字段”的契约保留原始字节；新建和改动的字段正常提交并清洗。没有客户端“可信/跳过清洗”标志。
3. 编辑状态通过 `form.elements` 的关联控件识别，兼容旧模板中不在 form 子树内的 `_settings_version`。内容写入令牌、站点设置版本和权限校验仍生效。
4. 初始化、粘贴清洗或上传期间禁止保存。已有正文载入失败或含未转存的 Base64 图片时，可省略该字段保存其他设置；新建时失败字段会阻止保存。
5. 源码编辑只能经清洗、图片核对、sandbox 预览后应用。粘贴 HTML 也先清洗；拖放 HTML/文件不绕过上传入口。未放行 `data:`，不添加旧的上传服务端、SWF 或远程图片代理。
6. 原生图片/链接菜单使用业务插件，表格插件保持上游代码；插件重复加载时避免重复注册菜单。全屏扩展父页面 iframe，Escape 和页面退出恢复布局。

## 回归入口

```sh
python3 tools/verify-dependencies.py
node tests/editor-regression.js
node tests/resource-picker-regression.js
node tests/upload-adapter-regression.js
node tests/navigation-regression.js
./bin/dev check
./bin/dev test
```

`bin/dev test` 已包含新适配器、资源入口和 `tests/richtext-legacy-regression.php`。`tests/fixtures/kindeditor-legacy.html` 是合成中文历史排版样本，覆盖字体、行距、缩进、合并单元格、图文混排、列表、预格式文本和链接图片。HTTP 回归增加只改标题时逐字节保留、修改时安全清洗、自定义字段省略及版权省略检查。

可选真实浏览器回归：在忽略目录安装 Playwright，不改变部署依赖；指定本机 Chromium/Chrome，或使用 Playwright 管理的 Chromium。

```sh
npm install --prefix var/verification/browser --no-audit --no-fund playwright
PLAYWRIGHT_MODULE="$PWD/var/verification/browser/node_modules/playwright" \
CHROME_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' \
node tests/kindeditor-browser-regression.cjs
```

浏览器脚本只允许通过带 `f_dev_meta` 标记的 Docker 开发库生成独立临时账号和文章，结束时清理本次生成的数据与上传文件。默认地址为本机开发端口；`FENGCMS_BROWSER_ORIGIN` 仅用于对应开发容器地址，不能指向生产。多字段检查只拦截测试浏览器的表单响应插入第二个标准富文本控件，不改表结构。截图写入忽略的 `var/verification/`。

## 验证记录与限制

PHP 8.4 的全套回归及新增 HTTP 659 项已通过。最终双版本、浏览器和诊断结果在完成后补记。

目前样本为合成回归资料，未收到本客户的代表性正文，不能据此宣称所有历史文章完全兼容。已变更正文可能由浏览器或清洗器规范化空白、属性顺序和标签；未变更正文按原字节保留。未测试旧 IE，也不为其放宽安全策略。回退本分支的代码不会恢复已经保存修改的文章；正式升级与回退都应使用一致的代码、数据库、上传及运行配置备份。
