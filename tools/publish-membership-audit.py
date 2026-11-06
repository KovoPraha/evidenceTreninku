"""Render reviewed documentation paths from a server-captured membership audit."""
import datetime as dt
import hashlib
import json
import os
import re
from pathlib import Path
import sys

UTC = dt.timezone.utc
DOCS = ["docs/HANDOFF_CURRENT.md", "docs/SESSION_HANDOFF_MEMBERSHIP_SCHEDULER_2026-11-06.md",
        "docs/WORK_PLAN_CURRENT.md", "docs/MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md",
        "docs/MEMBERSHIP_SCHEDULER_2026-11-06.md"]
OUTPUTS = {"preflight": "docs/verification/membership-morning-server-20261106",
           "publish": "docs/verification/membership-scheduler-20261107"}
ALLOWED = set(DOCS + ["docs/CURRENT_STATE.md", "docs/CHANGELOG-PROVOZ.md"] +
              [p + "/" + n for p in OUTPUTS.values() for n in ["RESULTS.md", "audit.json"]])


def timestamp(value):
    date = dt.datetime.fromisoformat(value.replace("Z", "+00:00"))
    if date.utcoffset() != dt.timedelta(0):
        raise ValueError("UTC evidence required")
    return date


def replace_block(text, mode, block):
    start = "<!-- membership-morning-" + mode + ":start -->"
    end = "<!-- membership-morning-" + mode + ":end -->"
    chunk = start + "\n" + block + "\n" + end
    if start in text:
        a = text.index(start)
        b = text.index(end, a) + len(end)
        return text[:a] + chunk + text[b:]
    return text.rstrip() + "\n\n" + chunk + "\n"


def generate(root, mode, data, flags, run):
    if mode not in OUTPUTS or not str(run).isdigit() or data.get("ok") is not True:
        raise ValueError("Invalid publication operation")
    checked = timestamp(data["checked_at_utc"])
    repo_ok = flags == {"schedule": "false", "apply": "true", "mail": "true"}
    if mode == "preflight":
        beat = data.get("heartbeat") or {}
        membership = data.get("membership") or {}
        age = int(checked.timestamp()) - beat.get("last_tick_at", 0)
        if not (data.get("installed_expected_source") is True and beat.get("phase") == "waiting"
                and beat.get("trigger") == "hosting_cron" and 0 <= age <= 180
                and data.get("report") is None and membership.get("recent_hosting_tick") is True
                and membership.get("automation_enabled") is True and membership.get("mail_enabled") is True and repo_ok):
            raise ValueError("Natural waiting observer/flags not proven")
        report = {"version": 1, "mode": mode, "verdict": "PASS", "checked_at_utc": data["checked_at_utc"],
                  "observer_tick_utc": dt.datetime.fromtimestamp(beat["last_tick_at"], UTC).isoformat(),
                  "phase": "waiting", "morning_result": "NOT RUN", "repository_flags": flags}
        title = "Serverová příprava ranního auditu — PASS"
        detail = "Přirozený čekající tick pozorovatele: " + report["observer_tick_utc"] + "."
        link_label = "Serverový preflight"
    else:
        report = data.get("report")
        if not isinstance(report, dict) or report.get("version") != 1 or report.get("target_day") != "2026-11-07":
            raise ValueError("Immutable target-day capture missing")
        if report.get("trigger") != "hosting_cron" or report.get("verdict") not in {"PASS", "LATE", "FAIL", "BLOCKED"}:
            raise ValueError("Invalid server capture")
        captured = timestamp(report["captured_at_utc"])
        if captured < dt.datetime(2026, 11, 7, 5, 30, tzinfo=UTC) or captured > checked:
            raise ValueError("Premature or future capture")
        if report["verdict"] == "PASS":
            checks = report.get("checks", {})
            if len(checks) != 13 or any(v is not True for v in checks.values()):
                raise ValueError("PASS without all server checks")
            actual = timestamp(report["actual_start_utc"])
            expected = dt.datetime(2026, 11, 7, 5, 23, tzinfo=UTC)
            delay = (actual - expected).total_seconds()
            if not 0 <= delay <= 60 or report.get("start_delay_seconds") != delay:
                raise ValueError("PASS with inconsistent start time")
        report = dict(report, repository_flags=flags, collected_at_utc=data["checked_at_utc"])
        if not repo_ok:
            report["verdict"] = "FAIL"
        title = "Ranní audit členství 7. 11. 2026 — " + report["verdict"]
        actual = report.get("actual_start_utc") or "nedoloženo"
        if actual != "nedoloženo":
            actual_cet = timestamp(actual).astimezone(dt.timezone(dt.timedelta(hours=1))).isoformat()
        else:
            actual_cet = actual
        delay = report.get("start_delay_seconds")
        delay_text = str(delay) if isinstance(delay, int) else "nedoloženo"
        failures = ", ".join(k for k, value in report.get("checks", {}).items() if value is not True) or "žádné"
        detail = ("Zachyceno hostingem: " + report["captured_at_utc"] + ".\n"
                  "Doložený start v ledgeru UTC: " + actual + "; CET: " + actual_cet + "; odchylka " + delay_text + " s.\n"
                  "Očekávaný start 05:23 UTC (06:23 CET). Úspěch UTC: " + (report.get("last_success_utc") or "nedoloženo") + ".\n"
                  "Nesplněné kontroly: " + failures + ".\n"
                  "Případné retry/omezení prvního pokusu: " + str(report.get("first_attempt_limitation")) + ".")
        link_label = "Ranní serverový výsledek " + report["verdict"]
    report["publication_run"] = int(run)
    report["deferred"] = ["P05/P07 actual membership/payment", "P06 export"]
    out = OUTPUTS[mode]
    note = ("# " + title + "\n\n" + detail + "\n\n"
            "[Bezpečný strojový důkaz](audit.json). [Serverový workflow](https://github.com/KovoPraha/evidenceTreninku/actions/runs/" + str(run) + ").\n\n"
            "Kontrola pouze četla stav a ledger; žádný apply/send, změna tarifu, platba ani import.\n"
            "Skutečné P05/P07 a export P06 zůstávají odložené.\n")
    block = ("## " + link_label + "\n\n[" + link_label + "](" + out.removeprefix("docs/") + "/RESULTS.md).\n"
             "Kontrola i přenos běží na serverech, bez zapnutého počítače vlastníka.\n"
             "Skutečné P05/P07 a export P06 zůstávají odložené.\n")
    updates = {out + "/RESULTS.md": note, out + "/audit.json": json.dumps(report, ensure_ascii=False, indent=2) + "\n"}
    for name in DOCS:
        updates[name] = replace_block((root/name).read_text(encoding="utf-8"), mode, block)
    if mode == "publish":
        name = "docs/MEMBERSHIP_SCHEDULER_MORNING_AUDIT_2026-11-07.md"
        updates[name] = re.sub(r"^Stav:.*$", "Stav: **DOKONČENO — " + report["verdict"] + "**.", updates[name], count=1, flags=re.M)
    if mode == "publish":
        state = (root/"docs/CURRENT_STATE.md").read_text(encoding="utf-8")
        old = state.rstrip("\n")
        source = "docs/CURRENT_STATE.md@before-membership-morning-audit-20261107"
        history = (root/"docs/CHANGELOG-PROVOZ.md").read_text(encoding="utf-8")
        if source not in history:
            meta = {"source": source, "part": 0, "sha256": hashlib.sha256(old.encode()).hexdigest()}
            history += "\n\n<!-- archive " + json.dumps(meta) + " -->\n" + old + "\n<!-- /archive -->\n"
        line = "Ranní serverový audit 7. 11.: [" + report["verdict"] + "](" + out.removeprefix("docs/") + "/RESULTS.md)."
        phrase = "příští ranní běh zatím nelze doložit."
        if phrase not in state:
            raise ValueError("Current-state morning marker changed; preserve for review")
        local = checked.astimezone(dt.timezone(dt.timedelta(hours=1)))
        updated = re.sub(r"Aktualizováno \d+\. \d+\. \d{4}",
                         f"Aktualizováno {local.day}. {local.month}. {local.year}", state, count=1)
        updates["docs/CURRENT_STATE.md"] = updated.replace(phrase, line, 1)
        updates["docs/CHANGELOG-PROVOZ.md"] = history
    for name, content in updates.items():
        if name not in ALLOWED:
            raise ValueError("Unexpected output path")
        path = root/name
        if any(p.is_symlink() for p in [path, *path.parents]):
            raise ValueError("Symlink publication path")
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8", newline="\n")
    return report["verdict"]


if __name__ == "__main__":
    if len(sys.argv) == 3 and sys.argv[1] == "--check-paths":
        paths = Path(sys.argv[2]).read_text().splitlines()
        if not paths or any(p not in ALLOWED for p in paths):
            raise SystemExit("Unreviewed files on publication branch")
    else:
        mode, source, run = sys.argv[1:]
        flags = {k: os.environ[k.upper() + "_FLAG"] for k in ["schedule", "apply", "mail"]}
        verdict = generate(Path.cwd(), mode, json.loads(Path(source).read_text()), flags, run)
        print("Published documentation verdict: " + verdict)
        with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as f:
            f.write("verdict=" + verdict + "\n")
