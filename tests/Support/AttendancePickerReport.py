"""Archive a completed owned fixture, never credentials, cookies, config or raw HTML."""
import argparse
import collections
import gzip
import hashlib
import json
import math
import shutil
import statistics
import subprocess
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument("input", type=Path)
parser.add_argument("output", type=Path)
args = parser.parse_args()
root = Path(__file__).resolve().parents[2]
assert args.output.resolve().is_relative_to(root / "docs" / "verification")
assert args.output.name == "attendance-person-picker-20261107"
args.output.mkdir(parents=True, exist_ok=True)
read = lambda name: json.loads((args.input / name).read_text(encoding="utf-8-sig"))
before, after = read("http-before.json"), read("http-after.json")
assert before["complete"] and after["complete"] and after["form_state_parity"]
assert before["core_checksums_before"] == after["core_checksums_after"]
cleanup = read("cleanup.json")
assert all(cleanup[k] for k in ["schema_removed", "web_user_removed", "owned_config_removed", "http_stopped", "mariadb_stopped", "browser_closed"])
assert all(v != 0 for v in cleanup["socket_connect_results"].values())
diags = {v: read("diagnostics-" + v + ".json") for v in ["before", "after"]}
for d in diags.values():
    assert d["complete"] and d["core_unchanged"] and all(not c["write_commands"] for c in d["cases"])
    assert d["core_checksums_before"] == before["core_checksums_before"]
browser = {v: {role: read(f"browser-{role}-{v}.json") for role in ["coach", "sports_lead", "other_coach"]} for v in ["before", "after"]}
for roles in browser.values():
    assert all(d["complete"] and len(d["cases"][0]["samples"]) == 13 for d in roles.values())
    assert all(s["status"] == 200 and s["navigation_count"] == 1 for d in roles.values() for s in d["cases"][0]["samples"])

def stats(values):
    values = sorted(values)
    return {"samples": len(values), "median_ms": statistics.median(values), "p95_ms": values[math.ceil(.95 * len(values)) - 1]}

summary = {"complete": True, "same_measured_fixture": True, "http_cases_per_variant": len(before["cases"]),
           "http_positive_per_variant": 9, "http_samples_per_variant": 270, "browser_samples_per_variant": 39,
           "p95_method": "nearest rank ceil(0.95*n)", "cases": []}
for left, right in zip(before["cases"], after["cases"]):
    assert (left["id"], left["status"]) == (right["id"], right["status"])
    if left["status"] != 200:
        continue
    item = {"id": left["id"], "role": left["role"], "path": left["path"]}
    for label, case in [("before", left), ("after", right)]:
        diag = next(c for c in diags[label]["cases"] if c["id"] == case["id"])
        item[label] = {"http": stats(s["total_ms"] for s in case["samples"]),
                       "ttfb": stats(s["ttfb_ms"] for s in case["samples"]), "html_bytes": case["samples"][0]["bytes"],
                       "participant_options": case["form"]["participant_options"], "excuse_options": case["form"]["excuse_options"],
                       "sql_count": diag["sql_count"], "php": diag["php"]}
        if case["id"] in ["closure-coach-training", "closure-lead-training", "closure-other-own"]:
            d = browser[label][case["role"]]
            assert d["viewport"] == {"width": 1036, "height": 703} and d["device_pixel_ratio"] == 1.25
            item[label]["browser"] = {cache: stats(s["load_ms"] for s in d["cases"][0]["samples"] if s["cache"] == cache) for cache in ["cold", "warm"]}
    summary["cases"].append(item)
summary["console"] = {}
for label, roles in browser.items():
    errors = collections.Counter(e for d in roles.values() for s in d["cases"][0]["samples"] for e in s["console_errors"])
    failed = [r for d in roles.values() for s in d["cases"][0]["samples"] for r in s["failed_requests"]]
    summary["console"][label] = {"sample_errors": dict(errors), "failed_requests": failed}

def dump(name, data):
    (args.output / name).write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

dump("summary.json", summary)
runtime = read("runtime.json")
sources = read("measured-after-sources.json")
assert all(hashlib.sha256((root / n).read_bytes()).hexdigest() == h for n, h in sources.items())
base_sources = {}
for name in sources:
    if name == "includes/training_closure_people.php":
        continue
    raw = subprocess.check_output(["git", "show", runtime["application_sha"] + ":" + name])
    base_sources[name] = hashlib.sha256(raw.replace(b"\r\n", b"\n")).hexdigest()
original_route = (args.input / "training_closure_admin.before.php.txt").read_bytes().replace(b"\r\n", b"\n")
assert hashlib.sha256(original_route).hexdigest() == base_sources["training_closure_admin.php"]
environment = {k: runtime[k] for k in ["application_sha", "port", "http_port", "runtime", "datadir", "fixture_tables", "migrations", "synthetic_only"]}
environment.update(php=before["php"], server=before["server"], playwright_cli="0.1.23",
                   before_sources_sha256_lf=base_sources,
                   chrome=browser["before"]["coach"]["browser_version"],
                   native_viewport=browser["before"]["coach"]["viewport"], dpr=1.25,
                   after_sources_sha256_lf={n: hashlib.sha256((root / n).read_bytes().replace(b"\r\n", b"\n")).hexdigest() for n in sources},
                   business_fixture={"plans": 728, "weeks": 52, "participants": 200, "revisions": 52,
                                     "medical_watches": 10000, "medical_records": 30000, "selectable_people": 10221})
dump("environment.json", environment)
for name in ["http-before", "http-after", "diagnostics-before", "diagnostics-after"] + [f"browser-{role}-{v}" for v in browser for role in browser[v]]:
    raw = (args.input / (name + ".json")).read_bytes()
    (args.output / (name + ".json.gz")).write_bytes(gzip.compress(raw, mtime=0))
for name in ["dom-before.json", "dom-after.json", "http-functional-verification.json", "browser-functional.json",
             "browser-excuse-write.json", "post-uat-state.json", "http-revocation.json", "full-fixture-person-coverage.json",
             "mariadb-picker-parity.json", "cleanup.json", "matrix.json", "targeted-tests.xml"]:
    shutil.copyfile(args.input / name, args.output / name)
for source, target in [("phpstan.log", "phpstan.txt"), ("final-style.log", "style.txt")]:
    shutil.copyfile(args.input / source, args.output / target)
captures = root / "output" / "playwright" / "attendance-picker-20261107"
for name in ["picker-js-desktop.png", "picker-nojs-desktop.png", "picker-js-mobile.png", "picker-nojs-mobile.png",
             "picker-form-desktop.png", "picker-form-mobile.png"]:
    shutil.copyfile(captures / name, args.output / name)
for name in ["functional-template.js", "excuse-template.js"]:
    shutil.copyfile(captures / name, args.output / (name + ".txt"))
for role in browser["before"]:
    for variant in ["before", "after"]:
        name = role + "-" + variant + "-measure.js"
        shutil.copyfile(captures / name, args.output / (name + ".txt"))
manifest = {p.name: hashlib.sha256(p.read_bytes().replace(b"\r\n", b"\n") if p.suffix in [".json", ".xml", ".txt"] else p.read_bytes()).hexdigest()
            for p in sorted(args.output.iterdir()) if p.is_file() and p.suffix != ".md" and p.name != "artifact-sha256.json"}
dump("artifact-sha256.json", manifest)
print("Archived 540 HTTP timings, 78 browser observations, parity/UAT/cleanup, source and artifact hashes.")
