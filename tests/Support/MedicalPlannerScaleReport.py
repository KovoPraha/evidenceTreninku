"""Archive only the explicit non-secret evidence whitelist for the owned scale study."""
from __future__ import annotations

import gzip
import hashlib
import json
import math
import statistics
import subprocess
import sys
from pathlib import Path


LEVELS = ("baseline", "medical50k", "medical100k", "planner260", "planner520", "roster200", "roster1000")


def read(path: Path):
    return json.loads(path.read_text(encoding="utf-8-sig"))


def write(path: Path, data):
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8", newline="\n")


def main():
    source, target = map(lambda value: Path(value).resolve(), sys.argv[1:3])
    root = Path(__file__).resolve().parents[2]
    assert target == root / "docs/verification/medical-planner-scale-20261107"
    target.mkdir(parents=True, exist_ok=True)
    environment = read(source / "environment.json")
    assert environment["production_changed"] is False
    assert environment["browser_performance_measured"] is False
    for name, expected in environment["sources_sha256_lf"].items():
        data = (root / name).read_bytes().replace(b"\r\n", b"\n")
        frozen = subprocess.check_output(["git", "show", environment["measured_application_sha"] + ":" + name], cwd=root)
        assert data == frozen and hashlib.sha256(data).hexdigest() == expected, name
    cleanup = read(source / "cleanup.json")
    for key in ("schema_removed", "web_user_removed", "owned_config_removed", "credentials_and_cookie_jars_removed",
                "database_stopped", "http_stopped", "ports_closed", "original_wip_unchanged"):
        assert cleanup[key] is True, key
    assert read(source / "web-select-only.json")["only_schema_select_granted"] is True
    secret_scan = read(source / "secret-scan.json")
    assert secret_scan["complete"] and secret_scan["matches"] == []
    summary = dict(complete=True, method="same frozen application; independently scaled medical/history/membership dimensions",
                   warmups=5, samples_per_positive=30, p95="nearest rank ceil(.95*n)", production_changed=False,
                   browser_performance_measured=False, positive_cases=0, negative_cases=0, observations=0, levels={})
    archive = {}

    def preserve(name, data):
        content = json.dumps(data, ensure_ascii=False, indent=2).encode("utf-8") + b"\n"
        destination = target / (name + ".json.gz")
        destination.write_bytes(gzip.compress(content, mtime=0))
        archive[destination.name] = hashlib.sha256(destination.read_bytes()).hexdigest()

    for level in LEVELS:
        reports = {name: read(source / level / (name + ".json"))
                   for name in ("seed", "http", "diagnostics", "verification", "projections")}
        assert all(data["complete"] for data in reports.values()), level
        assert all(reports[name]["checksums_unchanged"] for name in ("http", "diagnostics", "verification", "projections")), level
        assert reports["verification"]["read_only"] and reports["projections"]["read_only"]
        diagnostics = {case["id"]: case for case in reports["diagnostics"]["cases"]}
        verification = {case["id"]: case for case in reports["verification"]["cases"]}
        projections = {case["id"]: case for case in reports["projections"]["cases"]}
        assert len(diagnostics) == len(reports["http"]["cases"])
        rows = []
        for case in reports["http"]["cases"]:
            diagnostic = diagnostics[case["id"]]
            assert diagnostic["html_parity"] and diagnostic["write_commands"] == []
            assert all(sample["status"] == case["status"] for sample in case["samples"])
            if case["status"] != 200:
                assert len(case["samples"]) == 1
                summary["negative_cases"] += 1
                continue
            assert len(case["samples"]) == 30
            assert verification[case["id"]]["full_domain_parity"] and verification[case["id"]]["dom_parity"]
            assert diagnostic["php"]["db_bootstrap_included"] and diagnostic["php"]["navigation_included"]
            assert not diagnostic["php"]["fatal"] and diagnostic["logged_statement_count"] > 0
            times = sorted(sample["total_ms"] for sample in case["samples"])
            assert len({sample["bytes"] for sample in case["samples"]}) == 1
            helper = projections[case["id"]]
            rows.append(dict(id=case["id"], role=case["role"], median_ms=statistics.median(times),
                             p95_ms=times[math.ceil(.95 * len(times)) - 1], min_ms=min(times), max_ms=max(times),
                             html_bytes=case["samples"][0]["bytes"], full_http_sql=diagnostic["sql_count"],
                             actual_rows_examined=diagnostic["rows_examined"], server_rows_sent=diagnostic["rows_sent"],
                             php_peak_bytes=diagnostic["php"]["peak_php_bytes"],
                             projection_scope=helper["scope"], projection_fetched_rows=helper["fetched_rows"],
                             projection_sql=helper["sql_count"]))
            summary["positive_cases"] += 1
            summary["observations"] += len(times)
        assert len(rows) == len(verification) == len(projections)
        summary["levels"][level] = dict(dimensions=reports["seed"]["dimensions"], counts=reports["seed"]["counts"],
                                        checksum_tables=len(reports["seed"]["checksums"]), cases=rows)
        for name, data in reports.items():
            preserve(level + "-" + name, data)
    assert (summary["positive_cases"], summary["negative_cases"], summary["observations"]) == (72, 28, 2160)
    incomplete = read(source / "incomplete-run.json")
    assert incomplete["complete"] is False and incomplete["excluded_from_final_timings"] is True
    checkpoints = read(source / "restart-checkpoints.json")
    assert len(checkpoints["checkpoints"]) >= 3
    assert all(c["complete"] and c["domain_unchanged"] for c in checkpoints["checkpoints"])
    assert all(c["checksums"] == checkpoints["checkpoints"][0]["checksums"] for c in checkpoints["checkpoints"])
    for name in ("environment", "cleanup", "secret-scan", "logins", "logins-initial", "web-select-only", "schema-metadata", "restart-checkpoints", "checks"):
        data = read(source / (name + ".json"))
        write(target / (name + ".json"), data)
        archive[name + ".json"] = hashlib.sha256((target / (name + ".json")).read_bytes()).hexdigest()
    preserve("incomplete-run", incomplete)
    write(target / "summary.json", summary)
    archive["summary.json"] = hashlib.sha256((target / "summary.json").read_bytes()).hexdigest()
    for name in ("MedicalPlannerScaleProfile.php", "MedicalPlannerScaleRuntime.ps1", "MedicalPlannerScaleRun.ps1", "MedicalPlannerScaleReport.py"):
        destination = target / (name + ".txt")
        destination.write_bytes((root / "tests/Support" / name).read_bytes().replace(b"\r\n", b"\n"))
        archive[destination.name] = hashlib.sha256(destination.read_bytes()).hexdigest()
    tests = target / "targeted-tests.xml"
    tests.write_bytes((source / "targeted-tests.xml").read_bytes().replace(b"\r\n", b"\n"))
    archive[tests.name] = hashlib.sha256(tests.read_bytes()).hexdigest()
    write(target / "artifact-sha256.json", archive)
    def case(level, name):
        return next(c for c in summary["levels"][level]["cases"] if c["id"] == name)

    medical_valid = [case(level, "medical-valid") for level in ("baseline", "medical50k", "medical100k")]
    medical_all = [case(level, "medical-all") for level in ("baseline", "medical50k", "medical100k")]
    planner_root = [case(level, "planner-lead-root") for level in ("baseline", "planner260", "planner520")]
    examined = " / ".join(str(c["actual_rows_examined"]) for c in medical_valid)
    planner_rows = " / ".join(str(c["projection_fetched_rows"]) for c in planner_root)
    planner_memory = " / ".join(f'{c["php_peak_bytes"]/1048576:.2f}' for c in planner_root)
    lines = ["# Zdravotní přehled a plánovač — větší syntetická data, 7. 11. 2026", "",
             "Místní HTTP/SQL měření HOTOVO; aplikace ani produkce se touto studií neměnily.",
             "[Plán](../../MEDICAL_PLANNER_SCALE_2026-11-07.md), [předání](../../SESSION_HANDOFF_MEDICAL_PLANNER_SCALE_2026-11-07.md).", "",
             "## Praktický výsledek", "",
             f'- Zdravotní filtr **Platné**: server při 10k / 50k / 100k sledování prozkoumal **{examined} řádků**, '
             'pokaždé při stejných 20 SQL. Přesný počet vyžaduje průchod celou kandidátní historií.',
             f'- Při 100k trvalo Platné orientačně **{medical_valid[-1]["median_ms"]/1000:.2f} s**, '
             f'PHP peak zůstal {medical_valid[-1]["php_peak_bytes"]/1048576:.2f} MiB a HTML {medical_valid[-1]["html_bytes"]:,} B. '
             f'Běžný seznam měl {medical_all[-1]["median_ms"]/1000:.2f} s a {medical_all[-1]["actual_rows_examined"]:,} Rows_examined. '
             'Malá stránka a omezený počet načtených řádků neznamenají omezenou práci databáze.',
             f'- Plánovač vedoucího s filtrem kořene při 52 / 260 / 520 týdnech: '
             f'historický helper načetl **{planner_rows} řádků**, PHP peak celého requestu **{planner_memory} MiB**. '
             'Jde o verzi před souběžnou opravou #374; časy v úplné tabulce nejsou důkazem zrychlení při větším objemu.',
             '- Výsledky filtrů, konkrétní položky, počty odpovědí a přístupové hranice souhlasí. '
             'Tato studie nemění aplikaci ani produkci. Další priority vede předání.', "",
             "## Rozsah a metoda", "",
             "Zmrazená aplikace 69f49033, PHP 8.2.12 / MariaDB 10.4.32 na nevyhrazeném Windows PC.",
             "Nový vlastní datadir/porty, 350 tabulek / 161 migrací, žádný produkční config nebo dump.",
             "Jde o minimální legacy testovací schéma doplněné celým migračním katalogem, nikoli kopii produkčního schématu.",
             "[Definice a indexy devíti tabulek](schema-metadata.json) zachycují podmínky studie; vedle PK začíná index plánů veřejností, další legacy indexy produkce se nekopírují.",
             "Dvanáct rolí standardně přihlášených formulářem; po restartu vlastního serveru znovu všech 12. Web DB účet při měření SELECT only.",
             "72 kladných případů × pět zahřátí + 30 časů = **2160 HTTP vzorků**, 28 záporných kontrol.",
             "Celý prostý HTTP request včetně bootstrapu, aktuálního oprávnění a navigace; prostředky prohlížeče se nenačítají.",
             "P95 je 29. z 30 seřazených hodnot. Všechny vzorky finálních úplných běhů zachované; žádné odlehlé hodnoty vyřazené.",
             "První neúplná 100k fáze skončila chybou sdílené dočasné složky. Po ověření 21 součtů dostal vlastní server vlastní tmpdir a celých 100k se zopakovalo.",
             "[Neúplný běh](incomplete-run.json.gz) je oddělený od 2160 vzorků; [kontrolní body](restart-checkpoints.json) dokládají nezměněná data.",
             "Původní 10k/50k a zopakované 100k mají odlišnou cestu tmpdir na stejném disku; časové poměry nejsou kontrolovaný experiment.",
             "Studie měří růst objemů stejné aplikace. Nejde o pár starého/nového kódu ani o produkční SLA.", "",
             "Oddělená diagnostika ověřuje úplné HTML (jen CRLF/LF a CSP nonce normalizované), PHP peak a SQL.",
             "Skutečné rows examined pocházejí z **mysql.slow_log.rows_examined** vlastního serveru; nejsou to odhady EXPLAIN.",
             "Jde o serverový čítač, nikoli počet všech fyzických čtení úložiště. Slow log může navíc evidovat Quit s nulovými řádky.",
             "Rows sent jsou serverové výstupní řádky včetně metadat. Samostatné CLI projekce měří skutečně fetched rows",
             "jen daného helperu (seznam + picker nebo historický souhrn), ne celé HTTP stránky. Tyto počty nezaměňovat.", "",
             "## Nezávislé objemy", "",
             "| Sada | Sledování / záznamy | Týdny / plány | Členství / hrany / soupisky celkem |", "| --- | ---: | ---: | ---: |"]
    for level in LEVELS:
        d = summary["levels"][level]
        c = d["counts"]
        lines.append(f'| {level} | {c["medical_clearance_watches"]} / {c["medical_clearance_records"]} | {d["dimensions"][1]} / {c["planovane_treninky"]} | {c["club_roster_members"]} / {c["club_team_edges"]} / {c["club_teams"]} |')
    lines += ["", "Zdravotní růst zachovává 52 týdnů a 20 členství; historie zachovává 10k sledování a 20 členství.",
              "Růst členství zachovává 52 týdnů a 10k sledování, současně rozšiřuje příslušnou hierarchii.",
              "Poslední dvě sady proto neoddělují samotný vliv počtu členů od počtu soupisek.",
              "Všechny plány míří na kořen 1, rozšířené podřízené soupisky mají vlastní ID 10000–10049.",
              "Historie platí od roku 2010. V aktuálním týdnu je 14 plánů, odpovědi 50 % ano / 25 % ne / 25 % bez odpovědi; cizí odpověď se nepočítá.",
              "Zdravotní fixture má tři záznamy/sledování, 90 % Platné a 10 % Odvoláno, ostatní stavový filtr má přesně nulu.", "",
              "## Všechny měřené případy", "",
              "| Sada / případ | HTTP medián / p95 ms | HTML B | SQL celé HTTP | Rows examined / sent | PHP peak MiB | Fetched rows helperu |",
              "| --- | ---: | ---: | ---: | ---: | ---: | ---: |"]
    for level in LEVELS:
        for c in summary["levels"][level]["cases"]:
            lines.append(f'| {level} / {c["id"]} | {c["median_ms"]:.1f} / {c["p95_ms"]:.1f} | {c["html_bytes"]} | {c["full_http_sql"]} | {c["actual_rows_examined"]} / {c["server_rows_sent"]} | {c["php_peak_bytes"]/1048576:.2f} | {c["projection_fetched_rows"]} |')
    lines += ["", "## Ověření a meze", "",
              "72 úplných doménových/DOM porovnání PASS, 72 projekcí READ ONLY; všechna diagnostická HTML stejná jako prosté HTTP.",
              "Zdravotní výsledek včetně všech stavových metadat se rovná zmrazenému původnímu algoritmu; stránka má maximálně 25 položek a výběr osob 25 + případný kontext.",
              "Historický souhrn se rovná původnímu algoritmu, RSVP kanonickému readeru i nezávislým známým počtům seedu; ověřeny konkrétní ID karet a zdravotních řádků.",
              "V každé sadě všech 21 doménových kontrolních součtů před/po časování, projekcích, paritě a diagnostice shodných; diagnostika 0 DML/DDL.",
              "Časy jednotlivých objemů jsou sekvenční běhy na sdílené pracovní stanici; běžící činnosti Windows nejsou řízené. Časový poměr není kauzální odhad ani SLA.",
              "Data neobsahují dlouhé komplikované řetězce náhrad ani proměnlivou historickou topologii; starší malé regrese je pokrývají samostatně.",
              "Rozšíření počtu členství současně rozšiřuje hierarchii; další faktoriální matice může tyto vlivy oddělit.",
              "Nový prohlížečový výkon, fyzický mobil, řízená síť/CPU a souběžní uživatelé **NOT RUN**. Databáze 10.3/11.4 tímto místním během nejsou přeměřené.",
              "Úklid, porty a původní WIP PASS, viz [cleanup](cleanup.json). Bez deploye/aktivací/transportu.", "",
              "[Souhrn](summary.json), [prostředí a otisky](environment.json), [manifest](artifact-sha256.json).",
              "Každá ze sedmi sad má všech pět komprimovaných JSON podkladů: seed/http/diagnostics/verification/projections.",
              "[Reprodukce](REPRODUCE.md), [kalibrace](CALIBRATION.md)."]
    (target / "RESULTS.md").write_text("\n".join(lines) + "\n", encoding="utf-8", newline="\n")
    print(json.dumps({key: summary[key] for key in ("positive_cases", "negative_cases", "observations")}))
    print(f"Archived {len(archive)} hashed non-Markdown artifacts.")


if __name__ == "__main__":
    main()
