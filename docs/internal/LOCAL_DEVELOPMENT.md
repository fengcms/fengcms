# 本地容器开发

首次克隆后运行 `./bin/setup-git-hooks`（开发工具需要 Python 3），启用仓库内提交校验。所有提交标题使用 `type(scope): 中文描述`，例如 `fix(editor): 修复图片插入失败`。完整维护约定见根目录 [AGENTS.md](../../AGENTS.md)。编辑器适配变更还应运行 `node tests/editor-regression.js`。

后台保存、批量操作或结果页跳转变更还应运行 `node tests/navigation-regression.js`。它执行实际页面回调，覆盖正常、缺失和异常 ID 的成功/失败结果；PHP 端排序与结果页检查包含在 `./bin/dev test` 中。

本方案使用本机已有的 Docker / Colima；PHP、MySQL、Nginx 均运行在容器中，没有安装裸机服务。当前是安全升级开发环境，不是直接上线配置。

本地容器固定禁用网页安装入口，初始化仍通过 `./bin/dev up` 完成。共享虚拟主机首次安装请使用[网页安装说明](INSTALLATION.md)。

FengCMS 自有代码与文档采用根目录 [MIT License](../../LICENSE)；随附组件仍适用 [第三方许可说明](THIRD_PARTY_LICENSES.md)。旧版安装示例页脚包含与 MIT 冲突的文字，数据库迁移只替换内容完全一致的旧默认值，不改动站点管理员自定义的版权信息。

```sh
./bin/dev up       # 构建、初始化并启动
./bin/dev status
./bin/dev check    # PHP 语法和扩展
./bin/dev test     # 仅限开发数据库，会创建并清理测试数据
./bin/dev backup   # 数据库一致性备份
./bin/dev down     # 停止，保留数据卷
```

本地默认使用 PHP 8.4；验证 PHP 8.5 时，可用 `PHP_VERSION=8.5 ./bin/dev up` 重建 PHP 镜像，然后运行 `PHP_VERSION=8.5 ./bin/dev check` 和 `PHP_VERSION=8.5 ./bin/dev test`。两种 PHP 版本共用本地开发数据库卷，不要把此流程指向生产数据库。

前台 http://127.0.0.1:18080/ ，后台 http://127.0.0.1:18080/admin/login.php 。默认用户名 `admin`；首次随机生成的密码、后台认证码分别保存在根目录 `.env` 的 `FENGCMS_ADMIN_PASSWORD`、`ADMIN_CODE`，不要提交或公开此文件。修改 `.env` 不会自动修改已初始化的数据库密码或管理员密码。

2026-10-26 实测 PHP 8.4.27、MySQL 8.4.11、Nginx 1.30.6。镜像使用版本系列标签，后续拉取可能变化；发布前应记录并固定经过验证的镜像摘要。应用程序和配置只读挂载；前台 `template/`、`css/` 单独可写绑定到本地目录，以支持后台模板管理。上传、缓存、备份和运行配置使用独立数据卷。只有 Nginx 发布到 127.0.0.1，数据库和 FPM 不发布端口。PHP/数据库所在内部网络限制出网，邮件、远程图片和远程检测功能需另行配置验证。

后台模板/CSS 在线编辑会直接修改本地 `template/`、`css/` 文件，可通过 Git 查看和提交。首次运行 `./bin/dev` 会把当前非 root 用户 UID 记录到忽略的 `.env` 的 `LOCAL_UID`，构建时使 FPM 的 www-data 用户使用相同 UID，无需放宽目录权限。新镜像首次启动时仅迁移命名数据卷的属主；不更改源码或模板目录的权限。更换电脑或仓库属主后需更新 LOCAL_UID 并运行 `./bin/dev up` 重建。基础设置的 URL 模式保存到独立运行目录 site.json，不再修改 PHP 配置源码。数据库初始化只允许空库或带开发标记的数据库，禁止用 setup 导入生产数据；test 同样只允许开发标记数据库。

`down` 保留数据；不要对有价值的环境执行 `down -v`，它会删除数据卷。数据库备份位于忽略提交的 `var/backups/`，使用全表读锁以覆盖 MyISAM，备份时会短暂阻塞写入。该命令仅备份数据库；完整恢复还需要上传卷、运行配置卷、源码版本和私密配置。不要只保留 SQL。

如遇拉取网络问题，可使用本机代理：

```sh
export https_proxy=http://127.0.0.1:7897
export http_proxy=http://127.0.0.1:7897
export all_proxy=socks5://127.0.0.1:7897
```

Docker daemon 拉取镜像不一定继承 shell 代理，必要时在 Colima/Docker 中单独配置代理，不要把代理或凭证固化进镜像。

## 本地提交历史整理

2026-10-26 经维护者授权，将当前 `master` 原有 16 个提交说明改为中文 Conventional Commits 标题。逐个保持文件树、作者、提交者与时间不变；父提交引用和提交哈希随历史改写而变化。原提交备份为本机已忽略的 `var/git-backups/before-chinese-history.bundle`，映射表为同目录 `chinese-history-map.json`，需要恢复时可在另一目录克隆该 bundle 检查。备份不纳入发布仓库。

发布 v1.35 时，先将旧远端保存为本机已忽略的 `var/git-backups/before-v1.35-remote.bundle`，并核对远端原三条提交与新历史前三条提交的文件树、作者和时间逐一一致。随后使用带租约的推送同步新历史并创建发布标签。原提交哈希会改变，旧克隆需要重新同步仓库。
