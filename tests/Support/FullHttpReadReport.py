"""Archive safe synthetic measurement outputs; never archive config, credentials, cookies or HTML."""
import argparse
import collections
import csv
import gzip
import hashlib
import json
import math
import statistics
import subprocess
from pathlib import Path
from urllib.parse import urlsplit

parser = argparse.ArgumentParser()
parser.add_argument("input", type=Path)
parser.add_argument("output", type=Path)
parser.add_argument("--playwright-cli-version", required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=True)


def read(name):
    return json.loads((args.input / name).read_text(encoding="utf-8-sig"))


def archive(name, compressed=False):
    value = read(name)
    raw = (json.dumps(value, ensure_ascii=False, indent=2) + "\n").encode()
    target = args.output / (name + ".gz" if compressed else name)
    target.write_bytes(gzip.compress(raw, mtime=0) if compressed else raw)
    return value


def median(values):
    return statistics.median(values)


def p95(values):
    return sorted(values)[math.ceil(len(values) * 0.95) - 1]


timings = archive("http-timings.json", True)
diagnostics = archive("http-diagnostics.json", True)
dom = archive("http-dom-verification.json")
revocation = archive("http-revocation.json")
cleanup = archive("cleanup.json")
final_core = archive("final-core-check.json")
archive("matrix.json")
archive("logins.json")
archive("browser-cpu-diagnostic.json")
archive("browser-observer-check.json")
assert timings["complete"] and diagnostics["complete"] and dom["complete"]
assert diagnostics["core_unchanged"] and final_core["core_unchanged_after_browser_and_revocation"]
assert revocation["complete"] and cleanup["schema_removed"] and cleanup["http_stopped"] and cleanup["mariadb_stopped"]
assert not any(c["write_commands"] for c in diagnostics["cases"])
positive = {c["id"]: c for c in timings["cases"] if c["status"] == 200}
assert len(timings["cases"]) == 65 and len(positive) == 31
assert all(len(c["samples"]) == 30 for c in positive.values())
diagnostic_by_id = {c["id"]: c for c in diagnostics["cases"]}
browser = {}
for role in ["coach", "sports_lead", "registrar", "other_coach"]:
    value = archive("browser-" + role + ".json", True)
    assert value["complete"]
    for case in value["cases"]:
        assert case["id"] not in browser and case["id"] in positive
        assert len(case["samples"]) == 13
        assert all(s["status"] == 200 and s["navigation_count"] == 1 for s in case["samples"])
        browser[case["id"]] = case
assert set(browser) == set(positive)

summary = {
    "complete": True,
    "http_cases": len(timings["cases"]), "http_positive": len(positive),
    "http_negative": len(timings["cases"]) - len(positive), "http_timing_samples": 930,
    "browser_cases": len(browser), "browser_samples": 403, "browser_cold_per_case": 3,
    "browser_warm_per_case": 10, "core_unchanged": True, "diagnostic_get_writes": 0,
    "p95_method": "nearest rank; browser warm n=10 is diagnostic, not an SLA",
    "cases": [], "browser_console_errors": {}, "browser_failed_requests": [], "resource_origins": []
}
errors = collections.Counter()
origins = set()
for case_id, case in positive.items():
    b = browser[case_id]
    cold = [s for s in b["samples"] if s["cache"] == "cold"]
    warm = [s for s in b["samples"] if s["cache"] == "warm"]
    assert len(cold) == 3 and len(warm) == 10
    for s in b["samples"]:
        errors.update(s["console_errors"])
        summary["browser_failed_requests"].extend(s["failed_requests"])
        for resource in s["resources"]:
            u = urlsplit(resource["url"])
            origins.add(u.scheme + "://" + u.netloc)
    fcp = [p["ms"] for s in warm for p in s["paint"] if p["name"] == "first-contentful-paint"]
    d = diagnostic_by_id[case_id]
    row = {
        "id": case_id, "role": case["role"], "path": case["path"],
        "http_ttfb_median_ms": median([s["ttfb_ms"] for s in case["samples"]]),
        "http_total_median_ms": median([s["total_ms"] for s in case["samples"]]),
        "http_total_p95_ms": p95([s["total_ms"] for s in case["samples"]]),
        "html_bytes": case["samples"][0]["bytes"], "sql": d["sql_count"], "metadata_sql": d["metadata_sql_count"],
        "php_peak_bytes_instrumented": d["php"]["peak_php_bytes"],
        "browser_cold_load_median_ms": median([s["load_ms"] for s in cold]),
        "browser_warm_ttfb_median_ms": median([s["ttfb_ms"] for s in warm]),
        "browser_warm_response_end_median_ms": median([s["response_end_ms"] for s in warm]),
        "browser_warm_dcl_median_ms": median([s["dom_content_loaded_ms"] for s in warm]),
        "browser_warm_load_median_ms": median([s["load_ms"] for s in warm]),
        "browser_warm_load_p95_ms": p95([s["load_ms"] for s in warm]),
        "browser_warm_fcp_median_ms": median(fcp) if fcp else None,
        "cold_transfer_total_median_bytes": median([s["transfer_bytes"] + sum(r["transfer_bytes"] for r in s["resources"]) for s in cold]),
        "warm_transfer_total_median_bytes": median([s["transfer_bytes"] + sum(r["transfer_bytes"] for r in s["resources"]) for s in warm]),
    }
    summary["cases"].append(row)
summary["browser_console_errors"] = dict(errors)
summary["resource_origins"] = sorted(origins)
(args.output / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
with (args.output / "cases.csv").open("w", newline="", encoding="utf-8") as f:
    writer = csv.DictWriter(f, fieldnames=list(summary["cases"][0]), lineterminator="\n")
    writer.writeheader()
    writer.writerows(summary["cases"])

runtime = read("runtime.json")
env = {k: runtime[k] for k in ["port", "http_port", "runtime", "schema", "fixture_tables", "migrations", "synthetic_only", "external_sends_disabled"]}
first_browser = read("browser-coach.json")
env.update({"application_sha": runtime["application_sha"], "php": timings["php"],
            "server": timings["server"], "chrome": first_browser["browser_version"], "playwright_cli": args.playwright_cli_version,
            "node": subprocess.check_output(["node", "--version"]).decode().strip(),
            "native_window_css_pixels": first_browser.get("viewport") or runtime["browser_window_css_pixels"],
            "device_pixel_ratio": first_browser.get("device_pixel_ratio") or runtime["device_pixel_ratio"],
            "php_server": "single process CLI development server, Windows; no gzip/TLS/load simulation",
            "cache_note": "cold/warm applies only to browser HTTP resource cache; no server/database cold reset",
            "bootstrap_and_navigation": "unmodified application entry points, db.php, auth_session and hlavicka.php",
            "source_files_sha256": {}})
for name in ["planovac.php", "training_closure_admin.php", "medical_clearance_admin.php", "db.php", "hlavicka.php",
             "includes/auth_session.php", "includes/staff_workspaces.php", "includes/ui_shell.php", "assets/app-ui.js",
             "includes/coach_training_access.php", "includes/training_planner_read.php", "includes/training_planner_rsvp_read.php",
             "includes/medical_clearance_read.php"]:
    raw = Path(name).read_bytes().replace(b"\r\n", b"\n")
    assert raw == subprocess.check_output(["git", "show", runtime["application_sha"] + ":" + name])
    env["source_files_sha256"][name] = hashlib.sha256(raw).hexdigest()
(args.output / "environment.json").write_text(json.dumps(env, indent=2) + "\n", encoding="utf-8")
console_lines = set()
for p in Path(".playwright-cli").glob("console-*.log"):
    console_lines.update(p.read_text(encoding="utf-8-sig").splitlines())
console = collections.Counter()
for line in console_lines:
    if "[ERROR]" in line:
        console[line.split(" @ ")[-1]] += 1
(args.output / "browser-console-summary.json").write_text(json.dumps({"unique_error_locations": dict(console),
    "sample_errors": dict(errors), "failed_requests": summary["browser_failed_requests"],
    "note": "CLI console includes setup/navigation outside samples; sample errors remain in raw browser reports."}, indent=2) + "\n", encoding="utf-8")

for name in ["closure-desktop.png", "medical-desktop.png"]:
    source = Path("output/playwright/full-http-performance-20261107") / name
    (args.output / name).write_bytes(source.read_bytes())
for role in ["coach", "sports_lead", "registrar", "other_coach"]:
    source = Path("output/playwright/full-http-performance-20261107") / (role + "-measure.js")
    (args.output / ("browser-" + role + "-source.js.txt")).write_bytes(source.read_bytes())
manifest = {p.name: hashlib.sha256(p.read_bytes().replace(b"\r\n", b"\n") if p.suffix in [".json", ".xml", ".csv", ".txt"] else p.read_bytes()).hexdigest() for p in sorted(args.output.iterdir())
            if p.is_file() and p.name not in ["RESULTS.md", "CALIBRATION.md", "artifact-sha256.json"]}
(args.output / "artifact-sha256.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
print("Archived 65 HTTP cases / 930 timings, 31 browser cases / 403 observations, diagnostics and cleanup.")
