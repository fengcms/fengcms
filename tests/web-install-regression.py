"""Run only against a fresh, isolated Docker copy with a test authorize.php."""
from http.cookiejar import CookieJar
from pathlib import Path
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import HTTPCookieProcessor, Request, build_opener
import json
import os
import re
import secrets
import sys

base = sys.argv[1].rstrip('/') if len(sys.argv) > 1 else 'http://127.0.0.1:18082'
root = Path(__file__).resolve().parents[1]
env = {}
for line in (root / '.env').read_text().splitlines():
    line = line.strip()
    if not line or line.startswith('#'):
        continue
    key, value = line.split('=', 1)
    env[key] = value
token = (root / 'var/test-install-token').read_text()
admin_password = secrets.token_urlsafe(24)
opener = build_opener(HTTPCookieProcessor(CookieJar()))


def request(path, data=None):
    body = None if data is None else urlencode(data).encode()
    try:
        response = opener.open(Request(base + path, data=body), timeout=10)
    except HTTPError as error:
        response = error
    return response.status, response.read().decode('utf-8', 'replace')


def csrf(page):
    match = re.search(r'name="_csrf" value="([a-f0-9]+)"', page)
    assert match, 'CSRF token missing'
    return match.group(1)


status, page = request('/install/index.php')
assert status == 200 and 'name="install_key"' in page
temporary_probe = root / '.config.local.web-probe.tmp'
temporary_probe.write_text('private-install-probe')
try:
    assert request('/.config.local.web-probe.tmp')[0] in (403, 404)
finally:
    temporary_probe.unlink()
assert request('/install/authorize.example.php')[0] in (403, 404)
assert request('/install/index.php', {'action': 'unlock', 'install_key': token})[0] == 403
status, page = request('/install/index.php', {'action': 'unlock', '_csrf': csrf(page), 'install_key': 'incorrect'})
assert status == 200 and '密钥不正确' in page
status, page = request('/install/index.php', {'action': 'unlock', '_csrf': csrf(page), 'install_key': token})
assert status == 200 and 'name="db_host"' in page
assert '环境检查未通过' not in page

values = {
    'action': 'review', '_csrf': csrf(page), 'db_host': 'db', 'db_user': 'fengcms',
    'db_pass': env['DB_PASSWORD'], 'db_name': 'fengcms', 'db_prefix': 'f_',
    'admin_user': 'siteadmin', 'admin_pass': admin_password,
    'admin_confirm': admin_password,
}
bad = dict(values, admin_confirm='mismatch')
assert '输入不符合要求' in request('/install/index.php', bad)[1]
assert '请先填写信息并核对目标' in request('/install/index.php', {'action': 'install', '_csrf': csrf(page)})[1]
status, page = request('/install/index.php', values)
assert status == 200 and '确认安装目标' in page and 'f_manage' in page and 'f_view_field' in page
assert 'fengcms' in page and admin_password not in page and env['DB_PASSWORD'] not in page
assert not (root / 'config.local.php').exists()
status, page = request('/install/index.php', {'action': 'install', '_csrf': csrf(page)})
assert status == 200 and '安装完成' in page, 'web install failed'
match = re.search(r'后台认证码：<strong>([0-9]{4})</strong>', page)
assert match, 'admin code missing from success page'
assert (root / 'config.php').is_file()
assert (root / 'config.php').stat().st_mode & 0o077 == 0
assert 'FengCMS private configuration data v1' in (root / 'config.php').read_text()
assert not (root / 'install/authorize.php').exists()
assert request('/install/index.php')[0] == 404
for path in ('/config.php', '/config.local.php', '/install/install.sql', '/system/common/install_schema.php'):
    assert request(path)[0] in (403, 404), path
assert request('/')[0] == 200

status, page = request('/admin/login.php')
assert status == 200
code = env['ADMIN_CODE'] if os.environ.get('FENGCMS_TEST_CONTAINER_ENV_CODE') == '1' else match.group(1)
status, body = request('/admin/index.php?controller=admin', {
    '_csrf': csrf(page), 'user': 'siteadmin', 'password': admin_password, 'admincode': code
})
assert status == 200 and json.loads(body)['status'] == 'y'
assert request('/admin/index.php?controller=home&operate=main')[0] == 200
print('网页安装回归通过：授权、CSRF、环境检查、强密码、私有配置、安装锁、站点与后台登录。')
