#!/usr/bin/env python3
"""Build an exact, fail-closed production storage cleanup plan."""

import argparse
import hashlib
import json
import pathlib
import re
import shlex
import sys

RELEASE_ROOT = "data/.kis-deploy/releases"
BACKUP_ROOT = "data/.kis-backups"
RELEASE_RE = re.compile(r"data/\.kis-deploy/releases/([0-9a-f]{40})-([0-9]+)")
BACKUP_RE = re.compile(
    r"data/\.kis-backups/(?:files|bar-url-[0-9]+|bar-transfer-(?:images-)?[0-9]+"
    r"(?:\.tar\.gz)?|training-transfer-[0-9]+|evidence_[0-9A-Za-z_-]+"
    r"\.(?:sql\.gz|manifest\.json|sha256)|trainer-merge-[0-9]{8}-[0-9]{6}-"
    r"[0-9A-Za-z]+-before\.json|bar-stock-[0-9]+|config|keys|private-storage)"
)
FILE_BACKUP_RE = re.compile(r"data/\.kis-backups/files/kis-files-[0-9]+\.zip")
BACKUP_RETAIN = {
    f"{BACKUP_ROOT}/config",
    f"{BACKUP_ROOT}/keys",
    f"{BACKUP_ROOT}/private-storage",
}


def inventory(filename):
    result = {}
    for line in pathlib.Path(filename).read_text(encoding="utf-8").splitlines():
        parts = line.split("\t")
        if len(parts) != 2 or not parts[0].isdigit():
            raise ValueError(f"Invalid inventory row: {line!r}")
        size, path = parts
        if path in result or int(size) <= 0:
            raise ValueError(f"Duplicate or empty inventory entry: {path}")
        result[path] = int(size)
    if not result:
        raise ValueError("Empty inventory")
    return result


def canonical(data):
    return json.dumps(data, sort_keys=True, separators=(",", ":"), ensure_ascii=True).encode()


def plan(args):
    manifest_bytes = pathlib.Path(args.manifest).read_bytes()
    manifest = json.loads(manifest_bytes)
    if manifest.get("schema") != "server-storage-release-manifest-v1":
        raise ValueError("Unexpected release manifest schema")
    items = manifest["items"]
    if len(items) != 280 or len({item["path"] for item in items}) != 280:
        raise ValueError("Release manifest is not the complete 280-item inventory")
    expected = {}
    for item in items:
        match = RELEASE_RE.fullmatch(item["path"])
        if not match or item["sha"] != match[1] or item["run_id"] != int(match[2]):
            raise ValueError(f"Invalid release identity: {item['path']}")
        expected[item["path"]] = item["kib"]

    releases = inventory(args.releases)
    if releases != expected:
        raise ValueError("Live release names or sizes differ from the reviewed manifest")
    deployed = json.loads(pathlib.Path(args.deployment).read_text(encoding="utf-8"))
    current = f"{RELEASE_ROOT}/{deployed['sha']}-{deployed['run_id']}"
    if current not in releases:
        raise ValueError("Active deployment has no matching release directory")
    if int(deployed["run_id"]) != max(item["run_id"] for item in items):
        raise ValueError("Active deployment is not the newest release in the inventory")

    backups = inventory(args.backups)
    if any(not BACKUP_RE.fullmatch(path) for path in backups):
        raise ValueError("Backup inventory contains an unclassified top-level path")
    if not BACKUP_RETAIN.issubset(backups) or f"{BACKUP_ROOT}/files" not in backups:
        raise ValueError("Expected retained backup support paths or file archives are absent")
    files = inventory(args.files)
    if any(not FILE_BACKUP_RE.fullmatch(path) for path in files):
        raise ValueError("File-backup directory contains an unexpected item")
    if len(files) != 5:
        raise ValueError("File-backup archive count changed; inspect and refresh the plan")

    release_delete = [
        {"path": path, "kib": size}
        for path, size in sorted(releases.items())
        if path != current
    ]
    backup_delete = [
        {"path": path, "kib": size}
        for path, size in sorted(backups.items())
        if path not in BACKUP_RETAIN
    ]
    result = {
        "schema": "server-storage-cleanup-plan-v1",
        "source_manifest_sha256": hashlib.sha256(manifest_bytes).hexdigest(),
        "active_release": current,
        "release_delete": release_delete,
        "backup_delete": backup_delete,
        "backup_retain": sorted(BACKUP_RETAIN),
        "file_backup_count": len(files),
        "release_delete_kib": sum(item["kib"] for item in release_delete),
        "backup_delete_kib": sum(item["kib"] for item in backup_delete),
    }
    if len(release_delete) != 279 or len(backup_delete) < 70:
        raise ValueError("Cleanup target count differs from the reviewed scope")
    result["digest"] = hashlib.sha256(canonical(result)).hexdigest()
    return result


def shell_script(result):
    targets = result["release_delete"] + result["backup_delete"]
    lines = [
        "#!/bin/sh",
        "set -eu",
        "test \"$(pwd)\" = /",
        "for root in data data/.kis-deploy data/.kis-deploy/releases data/.kis-backups; do",
        "  test -d \"$root\" && test ! -L \"$root\"",
        "done",
        f"test -d {shlex.quote(result['active_release'])}",
    ]
    for item in targets:
        path = item["path"]
        regex = RELEASE_RE if path.startswith(RELEASE_ROOT + "/") else BACKUP_RE
        if not regex.fullmatch(path) or path in BACKUP_RETAIN:
            raise ValueError(f"Unsafe cleanup target: {path}")
        quoted = shlex.quote(path)
        lines += [
            f"test -e {quoted} && test ! -L {quoted}",
            f"set -- $(du -sk -- {quoted})",
            f"test \"$1\" = {item['kib']}",
        ]
    lines.append("echo 'Preflight PASS; removing exact reviewed targets.'")
    for item in targets:
        lines.append(f"rm -rf -- {shlex.quote(item['path'])}")
    for item in targets:
        lines.append(f"test ! -e {shlex.quote(item['path'])}")
    lines += [
        f"test -d {shlex.quote(result['active_release'])}",
        "echo 'Cleanup PASS; active release retained.'",
    ]
    return "\n".join(lines) + "\n"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--releases", required=True)
    parser.add_argument("--backups", required=True)
    parser.add_argument("--files", required=True)
    parser.add_argument("--deployment", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--shell-output")
    args = parser.parse_args()
    result = plan(args)
    pathlib.Path(args.output).write_text(
        json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    if args.shell_output:
        pathlib.Path(args.shell_output).write_text(shell_script(result), encoding="utf-8")
    print(
        json.dumps(
            {
                "digest": result["digest"],
                "active_release": result["active_release"],
                "release_count": len(result["release_delete"]),
                "release_delete_kib": result["release_delete_kib"],
                "backup_count": len(result["backup_delete"]),
                "backup_delete_kib": result["backup_delete_kib"],
            }
        )
    )


if __name__ == "__main__":
    try:
        main()
    except (ValueError, KeyError, OSError, json.JSONDecodeError) as error:
        print(f"Storage cleanup preflight failed: {error}", file=sys.stderr)
        sys.exit(1)
