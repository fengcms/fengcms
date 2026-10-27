#!/usr/bin/env python3
"""Verify that CI rejects new PHP diagnostics without echoing their values."""
import subprocess
import sys
from pathlib import Path

script = Path(__file__).resolve().parents[1] / 'tools/summarize-php-diagnostics.py'


def summarize(log: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(script), '--fail-on-diagnostics'],
        input=log, text=True, capture_output=True, check=False,
    )


clean = summarize('php-1 | request completed\n')
assert clean.returncode == 0 and 'PHP 诊断总数: 0' in clean.stdout

diagnostic = summarize(
    'php-1 | PHP Warning: secret input value in /var/www/html/system/core/model.php on line 12\n'
)
assert diagnostic.returncode == 1
assert 'Warning' in diagnostic.stdout and 'system/core/model.php:12' in diagnostic.stdout
assert 'secret input value' not in diagnostic.stdout
print('PASS PHP diagnostics fail CI without exposing input values')
