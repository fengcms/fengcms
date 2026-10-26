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
