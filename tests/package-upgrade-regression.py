#!/usr/bin/env python3
import importlib.machinery
import importlib.util
from pathlib import Path
import tempfile
import zipfile

loader = importlib.machinery.SourceFileLoader('package_upgrade', str(Path(__file__).resolve().parent.parent/'bin/package-upgrade'))
module = importlib.util.module_from_spec(importlib.util.spec_from_loader(loader.name, loader))
loader.exec_module(module)
base = {'unchanged.txt':(b'keep',0o100644), 'delete.txt':(b'old',0o100644), 'mode.sh':(b'same',0o100644), '.htaccess':(b'old rules',0o100644), 'old-name.txt':(b'move',0o100644)}
target = {'unchanged.txt':base['unchanged.txt'], 'mode.sh':(b'same',0o100755), '.htaccess':(b'new rules',0o100644), 'new-name.txt':(b'move',0o100644), 'admin/new.js':(b'new',0o100644)}
with tempfile.TemporaryDirectory() as directory:
    path=Path(directory)/'patch.zip'
    manifest=module.build(base,target,path,'v1.37','v1.38','base','target')
    with zipfile.ZipFile(path) as archive:
        assert '服务器配置参考/.htaccess' in archive.namelist()
        assert '网站文件/.htaccess' not in archive.namelist()
        assert '网站文件/unchanged.txt' not in archive.namelist()
        assert 'delete.txt' in archive.read('需要删除的文件清单.md').decode()
    assert {row['path'] for row in manifest['files'] if row['action']=='delete'} == {'delete.txt','old-name.txt'}
    with zipfile.ZipFile(path,'a') as archive:
        archive.writestr('网站文件/extra.txt',b'unexpected')
    try:
        module.verify(base,target,path)
    except ValueError:
        pass
    else:
        raise AssertionError('Unexpected overlay payload must fail verification')
for path in ('../secret','/root','a/../b','a\\b','a//b'):
    try:
        module.safe_path(path)
    except ValueError:
        pass
    else:
        raise AssertionError(path)
print('增补包回归通过：新增、删除、重命名、权限变更、配置分离、叠加一致性及异常载荷拒绝。')

# Exercise git archive's real default attributes, not only explicit Unix modes.
actual = module.tree('HEAD')
assert actual['.gitignore'][1] == 0o100644
assert actual['bin/dev'][1] == 0o100755
with tempfile.TemporaryDirectory() as directory:
    module.build({}, actual, Path(directory)/'actual.zip', 'v0.0', 'v1.38', 'empty', 'HEAD')
print('真实 Git 发布树回归通过：普通文件默认权限、可执行脚本权限及完整叠加。')

# Special releases use an explicit suffix; arbitrary refs/paths remain invalid.
for tag in ('v1.37', 'v1.38.1', 'v1.37-kindeditor', 'v1.37.1-kindeditor'):
    assert module.valid_tag(tag), tag
for tag in ('v1.37-other', 'v1.37-kindeditor/../x', 'v1.37-kindeditor\n', '--help', 'HEAD', 'v1.37-kindeditor;id'):
    assert not module.valid_tag(tag), tag
with tempfile.TemporaryDirectory() as directory:
    patch=Path(directory)/'special.zip'
    manifest=module.build(base,target,patch,'v1.37','v1.37-kindeditor','base','target')
    assert manifest['to']=='v1.37-kindeditor'
    with zipfile.ZipFile(patch) as archive:
        guide=archive.read('升级说明.md').decode()
        server=archive.read('服务器配置说明.md').decode()
        assert '无新增数据库结构迁移' in guide
        assert 'KindEditor 富文本编辑' in guide
        assert '/admin/api/richtext.php' in server
        assert 'v1.37 → v1.37-kindeditor' in server
print('特别版增补包回归通过：限定标签后缀、迁移声明、安全清洗入口与对应编辑器指引。')

for base_tag in ('v1.37', 'v1.38'):
    with tempfile.TemporaryDirectory() as directory:
        patch=Path(directory)/'renamed-special.zip'
        module.build(base,target,patch,base_tag,'v1.38-kindeditor','base','target')
        with zipfile.ZipFile(patch) as archive:
            guide=archive.read('升级说明.md').decode()
            server=archive.read('服务器配置说明.md').decode()
            assert '无新增数据库结构迁移' in guide
            assert 'v1.38-kindeditor' in guide
            if base_tag=='v1.38':
                assert '本次无新增服务器入口规则' in server
                assert '在原 upload|files|thumbnail 后增加' not in server
            else:
                assert '在原 upload|files|thumbnail 后增加' in server
print('更名后两条升级路径回归通过：分别使用对应基准和服务器入口指引。')

# Run the actual release CLI against two immutable bases and verify the combined checksums.
import subprocess
import shutil
import hashlib
root=Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory() as directory:
    repo=Path(directory)
    (repo/'bin').mkdir()
    (repo/'admin').mkdir()
    shutil.copytree(root/'docs',repo/'docs')
    shutil.copy2(root/'项目说明.md',repo/'项目说明.md')
    shutil.copy2(root/'.gitattributes',repo/'.gitattributes')
    for name in ('package-release','package-upgrade'):
        shutil.copy2(root/'bin'/name,repo/'bin'/name)
    def git(*args):
        return subprocess.check_output(['git',*args],cwd=repo,text=True)
    git('init','-q')
    git('config','user.name','Release regression')
    git('config','user.email','release-regression@example.invalid')
    for tag in ('v1.37','v1.38','v1.38-kindeditor'):
        (repo/'admin/version.php').write_text(tag+'\n')
        (repo/'docs/releases'/f'{tag}-发布说明.md').write_text('# '+tag+'\n')
        git('add','.')
        git('commit','-qm','test(release): 创建隔离发布样本')
        git('tag',tag)
    subprocess.run(['./bin/package-release','v1.38-kindeditor','v1.37','v1.38'],cwd=repo,check=True,stdout=subprocess.DEVNULL)
    checks=(repo/'var/releases/SHA256SUMS.txt').read_text().splitlines()
    assert len(checks)==3
    expected={'fengcms-v1.38-kindeditor.zip','fengcms-v1.37-to-v1.38-kindeditor.zip','fengcms-v1.38-to-v1.38-kindeditor.zip'}
    assert {line.split()[1] for line in checks}==expected
    for line in checks:
        digest,name=line.split()
        assert hashlib.sha256((repo/'var/releases'/name).read_bytes()).hexdigest()==digest
print('多基准发布 CLI 回归通过：两份补丁均生成，汇总校验完整包含三个 ZIP。')
