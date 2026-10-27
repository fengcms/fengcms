#!/usr/bin/env python3
"""Verify that CI rejects new PHP diagnostics without echoing their values."""
import argparse
import subprocess
import sys
from pathlib import Path

script = Path(__file__).resolve().parents[1] / 'tools/summarize-php-diagnostics.py'
arguments = argparse.ArgumentParser(description=__doc__)
arguments.add_argument('--live-php', action='store_true', help='also check a real PHP Fatal from the development container')
args = arguments.parse_args()


def summarize(log: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(script), '--fail-on-diagnostics'],
        input=log, text=True, capture_output=True, check=False,
    )


clean = summarize('php-1 | request completed\n')
assert clean.returncode == 0 and 'PHP 诊断总数: 0' in clean.stdout

cases = {
    'Warning': 'PHP Warning: secret input value in /var/www/html/system/core/model.php on line 12\n',
    'Deprecated': 'PHP Deprecated: secret input value in /var/www/html/app/model/webModel.php on line 20\n',
    'Notice': 'PHP Notice: secret input value in /var/www/html/app/model/webModel.php on line 21\n',
    'Parse error': 'PHP Parse error: secret input value in /var/www/html/app/model/webModel.php on line 22\n',
    'Fatal error': (
        'PHP Fatal error: Uncaught RuntimeException: secret input value in /var/www/html/system/core/model.php:44\n'
        'Stack trace:\n#0 {main}\n  thrown in /var/www/html/system/core/model.php on line 44\n'
    ),
    'Fatal with later location': (
        'PHP Fatal error: Uncaught RuntimeException: secret input value\n'
        'Stack trace:\n#0 {main}\n  thrown in /var/www/html/system/core/model.php on line 45\n'
    ),
}
for label, source in cases.items():
    result = summarize('php-1 | '+source.replace('\n', '\nphp-1 | '))
    assert result.returncode == 1, label
    assert 'PHP 诊断总数: 1' in result.stdout, label
    assert 'secret input value' not in result.stdout, label
    assert '.php:' in result.stdout, label
print('PASS Warning, Deprecated, Notice, Fatal and Parse logs fail without exposing values')

if args.live_php:
    php = (
        'define("TPL_INCLUDE",true);'
        'define("DRIVER_PATH","/var/www/html/system/driver/");'
        'define("DB_TYPE","mysqli");'
        'require "/var/www/html/system/core/database.php";'
        'require "/var/www/html/system/core/model.php";'
        'new model("bad-name");'
    )
    live = subprocess.run(
        ['docker', 'compose', 'exec', '-T', 'php', 'php', '-r', php],
        text=True, capture_output=True, check=False,
    )
    assert live.returncode != 0 and 'PHP Fatal error:' in live.stderr
    result = summarize(live.stderr)
    assert result.returncode == 1 and 'PHP 诊断总数: 1' in result.stdout
    assert 'system/core/model.php:' in result.stdout
    assert 'Invalid SQL identifier' not in result.stdout
    print('PASS actual PHP Fatal fails the diagnostic gate')
