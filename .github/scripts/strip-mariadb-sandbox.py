#!/usr/bin/env python3
"""Remove only the modern mariadb-dump client directive for legacy restore."""

import hashlib
import shutil
import sys


def main() -> int:
    first_line = sys.stdin.buffer.readline().rstrip(b"\r\n")
    if first_line != b"/*M!999999\\- enable the sandbox mode */":
        print("Unexpected hosting dump header; refusing to alter SQL stream.", file=sys.stderr)
        # The directive itself is public format metadata. Never print an
        # arbitrary first SQL line from a production database export.
        if b"sandbox mode" in first_line and first_line.startswith(b"/*M!") and len(first_line) <= 128:
            print(f"Sandbox directive bytes: {first_line!r}", file=sys.stderr)
        else:
            print(f"First line length={len(first_line)} sha256={hashlib.sha256(first_line).hexdigest()}", file=sys.stderr)
        return 2
    try:
        shutil.copyfileobj(sys.stdin.buffer, sys.stdout.buffer)
    except BrokenPipeError:
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
