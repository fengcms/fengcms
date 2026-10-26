#!/usr/bin/env python3
"""Summarize PHP diagnostics from docker compose logs without printing input values."""
import collections
import re
import sys

sites = collections.Counter()
for line in sys.stdin:
    match = re.search(r"PHP (Warning|Deprecated|Notice|Fatal error):.* in (/var/www/html/[^ ]+) on line (\d+)", line)
    if match:
        sites[(match.group(1), match.group(2).removeprefix('/var/www/html/'), match.group(3))] += 1
print(f"PHP 诊断总数: {sum(sites.values())}; 位置数: {len(sites)}")
for (kind, path, line), count in sites.most_common(25):
    print(f"{count:5} {kind:12} {path}:{line}")
