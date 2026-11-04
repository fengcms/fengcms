#!/usr/bin/env python3
"""Verify the locally bundled jQuery release against its recorded CDN digest."""
from hashlib import sha256
from pathlib import Path

root = Path(__file__).resolve().parents[1]
jquery_digest = "39a546ea9ad97f8bfaf5d3e0e8f8556adb415e470e59007ada9759dce472adaa"
for name in ("js/jquery.js", "js/jquery.min.js", "admin/public/jquery/jquery-4.0.0.min.js"):
    data = (root / name).read_bytes()
    assert data.startswith(b"/*! jQuery v4.0.0"), f"Wrong jQuery version: {name}"
    actual = sha256(data).hexdigest()
    assert actual == jquery_digest, f"jQuery digest mismatch: {name}"
    print(f"OK {name}")

# Locally built legacy conversion libraries are pinned by the build lockfile.
import json
legacy = root / 'admin/public/legacy-richtext'
provenance = json.loads((legacy / 'PROVENANCE.json').read_text())
assert sha256((legacy / 'index.js').read_bytes()).hexdigest() == provenance['bundle_sha256'], 'Legacy converter bundle digest mismatch'
print('OK admin/public/legacy-richtext/index.js')

# This client branch ships only the exact audited static subset of KindEditor.
kindeditor = root / 'admin/public/kindeditor-4.1.7'
metadata = json.loads((kindeditor / 'PROVENANCE.json').read_text())
assert metadata['version'] == '4.1.7 (2013-04-21)'
assert {str(p.relative_to(kindeditor)) for p in kindeditor.rglob('*') if p.is_file()} == set(metadata['files']) | {'PROVENANCE.json'}
for name, digest in metadata['files'].items():
    assert sha256((kindeditor / name).read_bytes()).hexdigest() == digest, f'KindEditor digest mismatch: {name}'
    assert Path(name).suffix not in ('.php', '.swf', '.asp', '.jsp')
print('OK KindEditor 4.1.7 static files and license')
