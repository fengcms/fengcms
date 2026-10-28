# GitHub 发布流程

FengCMS 的版本信息和更新说明统一发布到 [GitHub Releases](https://github.com/fengcms/fengcms/releases)。后台只查询最新正式版本，不下载或安装更新。旧官网和论坛不参与发布。

1. 完成代码审查、`./bin/dev check`、`./bin/dev test` 与适用的 JavaScript 检查。确保 `admin/version.php` 是本次标签名，例如 `v1.35`。在 `docs/releases/` 写好发布说明，说明兼容范围、升级边界和已知限制。
2. 按 `type(scope): 中文描述` 提交并确认 Git 工作区的已跟踪文件没有未提交改动。创建并推送与版本文件一致的注释标签，例如 `git tag -a v1.35 -m 'FengCMS v1.35'`、`git push origin v1.35`。
3. 运行 `./bin/package-release v1.35`，得到忽略目录 `var/releases/` 中的 ZIP 包与 SHA-256 校验文件。打包脚本会确认内部资料未进入压缩包，并检查中文用户指南存在。可在该目录运行 `shasum -a 256 -c fengcms-v1.35.sha256` 检验。
4. 用 GitHub Release 发布同一标签，上传这两个文件并使用对应的 `docs/releases/v1.35-发布说明.md` 作为说明。正式版不能标记为预发布，否则后台不会将其作为最新正式版显示。
5. 检查 Release 页面、附件、校验值，以及后台首页的最新版本提示。发布后不移动或复用标签；后续修复发布新版本。

发布包是该标签的源码快照；根目录 `.gitattributes` 会从归档排除 `docs/internal/` 和 `AGENTS.md`。包内包含中文用户文档，不包含未跟踪文件、`.env`、上传、数据库或运行时数据。旧站点可能有自定义模板和不同的 PHP、数据库环境；应先在副本中验证并备份代码、数据库、上传与配置，再按站点实际情况手动升级。直接覆盖运行中的定制站点不在本发布流程内。
