"""Preserve the current planner follow-up without altering the frozen primary archive."""
import gzip
import hashlib
import io
import json
import math
import statistics
import subprocess
import sys
import tarfile
from pathlib import Path

from MedicalPlannerScaleReport import read, write


def main():
    root = Path(__file__).resolve().parents[2]
    source = Path(sys.argv[1]).resolve()
    target = root / "docs/verification/medical-planner-scale-20261107/current-planner"
    target.mkdir(exist_ok=True)
    environment = read(source / "environment.json")
    frozen = tarfile.open(fileobj=io.BytesIO(subprocess.check_output(
        ["git", "archive", environment["application_pin"], *environment["sources_sha256_lf"]], cwd=root)))
    for file, digest in environment["sources_sha256_lf"].items():
        data = (root / file).read_bytes().replace(b"\r\n", b"\n")
        assert data == frozen.extractfile(file).read().replace(b"\r\n", b"\n")
        assert hashlib.sha256(data).hexdigest() == digest
    cleanup = read(source / "cleanup.json")
    assert all(cleanup.get(k) is True for k in ("schema_removed", "web_user_removed", "owned_config_removed",
                                               "credentials_and_cookie_jars_removed", "database_stopped", "http_stopped",
                                               "ports_closed", "original_wip_unchanged"))
    assert read(source / "secret-scan.json")["matches"] == []
    before = read(target.parent / "summary.json")
    manifest = {}
    summary = dict(complete=True, application_pin=environment["application_pin"], positive_cases=0,
                   negative_cases=0, observations=0, controlled_timing_pair=False, levels={})
    for level in ("planner520", "roster1000"):
        reports = {name: read(source / level / (name + ".json")) for name in ("seed", "http", "diagnostics", "verification", "projections")}
        assert all(d["complete"] for d in reports.values())
        assert all(reports[n]["checksums_unchanged"] for n in ("http", "diagnostics", "verification", "projections"))
        assert reports["projections"]["read_only"] and reports["verification"]["read_only"]
        old_verification = json.loads(gzip.decompress((target.parent / (level + "-verification.json.gz")).read_bytes()))
        old_domain = {c["id"]: c["domain"] for c in old_verification["cases"]}
        assert all(c["full_domain_parity"] and c["dom_parity"] and c["domain"] == old_domain[c["id"]]
                   for c in reports["verification"]["cases"])
        diagnostics = {c["id"]: c for c in reports["diagnostics"]["cases"]}
        projections = {c["id"]: c for c in reports["projections"]["cases"]}
        old_cases = {c["id"]: c for c in before["levels"][level]["cases"]}
        cases = []
        for c in reports["http"]["cases"]:
            d = diagnostics[c["id"]]
            assert d["html_parity"] and d["write_commands"] == []
            assert all(s["status"] == c["status"] for s in c["samples"])
            if c["status"] != 200:
                assert len(c["samples"]) == 1
                summary["negative_cases"] += 1
                continue
            assert len(c["samples"]) == 30 and not d["php"]["fatal"]
            assert d["php"]["db_bootstrap_included"] and d["php"]["navigation_included"]
            times = sorted(s["total_ms"] for s in c["samples"])
            cases.append(dict(id=c["id"], median_ms=statistics.median(times), p95_ms=times[math.ceil(.95 * len(times)) - 1],
                              full_http_sql=d["sql_count"], actual_rows_examined=d["rows_examined"], server_rows_sent=d["rows_sent"],
                              php_peak_bytes=d["php"]["peak_php_bytes"], projection_fetched_rows=projections[c["id"]]["fetched_rows"],
                              previous_projection_fetched_rows=old_cases[c["id"]]["projection_fetched_rows"],
                              previous_php_peak_bytes=old_cases[c["id"]]["php_peak_bytes"]))
            summary["positive_cases"] += 1
            summary["observations"] += 30
        summary["levels"][level] = dict(counts=reports["seed"]["counts"], cases=cases, domain_equals_primary=True)
        for name, data in reports.items():
            file = target / (level + "-" + name + ".json.gz")
            file.write_bytes(gzip.compress(json.dumps(data, ensure_ascii=False, indent=2).encode("utf-8"), mtime=0))
            manifest[file.name] = hashlib.sha256(file.read_bytes()).hexdigest()
    assert (summary["positive_cases"], summary["negative_cases"], summary["observations"]) == (18, 8, 540)
    for name in ("environment", "cleanup", "secret-scan", "checks", "logins", "web-select-only"):
        write(target / (name + ".json"), read(source / (name + ".json")))
        manifest[name + ".json"] = hashlib.sha256((target / (name + ".json")).read_bytes()).hexdigest()
    write(target / "summary.json", summary)
    manifest["summary.json"] = hashlib.sha256((target / "summary.json").read_bytes()).hexdigest()
    for name in ("MedicalPlannerCurrentRun.ps1", "MedicalPlannerCurrentReport.py", "MedicalPlannerScaleProfile.php",
                 "MedicalPlannerScaleRuntime.ps1", "MedicalPlannerScaleReport.py"):
        file = target / (name + ".txt")
        file.write_bytes((root / "tests/Support" / name).read_bytes().replace(b"\r\n", b"\n"))
        manifest[file.name] = hashlib.sha256(file.read_bytes()).hexdigest()
    file = target / "targeted-tests.xml"
    file.write_bytes((source / "targeted-tests.xml").read_bytes().replace(b"\r\n", b"\n"))
    manifest[file.name] = hashlib.sha256(file.read_bytes()).hexdigest()
    write(target / "artifact-sha256.json", manifest)
    print(json.dumps(dict(cases=18, observations=540, negative_cases=8, hashed_artifacts=len(manifest))))


if __name__ == "__main__":
    main()
