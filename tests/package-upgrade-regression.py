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
