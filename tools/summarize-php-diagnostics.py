#!/usr/bin/env python3
"""Summarize PHP diagnostics from docker compose logs without printing input values."""
import collections
import argparse
import re
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--fail-on-diagnostics', action='store_true', help='return failure when any PHP diagnostic appears')
args = parser.parse_args()
sites = collections.Counter()
diagnostic = re.compile(r"PHP (Warning|Deprecated|Notice|Fatal error|Parse error):")
location = re.compile(r"\bin (/var/www/html/.+?)(?::(\d+)| on line (\d+))(?:\s|$)")
thrown = re.compile(r"\bthrown in (/var/www/html/.+?) on line (\d+)(?:\s|$)")
pending_fatal = None


def record(kind, path=None, line=None):
    sites[(kind, path.removeprefix('/var/www/html/') if path else '<unknown>', line or '?')] += 1


for entry in sys.stdin:
    match = diagnostic.search(entry)
    if match:
        if pending_fatal is not None and not pending_fatal['counted']:
            record(pending_fatal['kind'])
        kind = match.group(1)
        place = location.search(entry)
        if place:
            record(kind, place.group(1), place.group(2) or place.group(3))
        if kind == 'Fatal error':
            pending_fatal = {'kind': kind, 'counted': bool(place)}
        else:
            pending_fatal = None
            if not place:
                record(kind)
        continue
    if pending_fatal is not None:
        place = thrown.search(entry)
        if place:
            if not pending_fatal['counted']:
                record(pending_fatal['kind'], place.group(1), place.group(2))
            pending_fatal = None
if pending_fatal is not None and not pending_fatal['counted']:
    record(pending_fatal['kind'])
print(f"PHP 诊断总数: {sum(sites.values())}; 位置数: {len(sites)}")
for (kind, path, line), count in sites.most_common(25):
    print(f"{count:5} {kind:12} {path}:{line}")
if args.fail_on_diagnostics and sites:
    sys.exit(1)
