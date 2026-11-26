#!/usr/bin/env python3
"""Remove only the modern mariadb-dump client directive for legacy restore."""

import shutil
import sys


def main() -> int:
    first_line = sys.stdin.buffer.readline().rstrip(b"\r\n")
    if first_line != b"/*M!999999\\- enable the sandbox mode */":
        print("Unexpected hosting dump header; refusing to alter SQL stream.", file=sys.stderr)
        return 2
    try:
        shutil.copyfileobj(sys.stdin.buffer, sys.stdout.buffer)
    except BrokenPipeError:
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
