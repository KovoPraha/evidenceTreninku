# Předání P11-ASSET — 10. 11. 2026

ROZPRACOVÁNO. [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md),
[důkazy](verification/bootstrap-assets-20261110/RESULTS.md).

RIX SQL/index varianty nebyly přijaty; původní reader je přesně ff182971.
Produkce nadále17110a7f, nový deploy NOT RUN. Draft425 nyní b8 obsahuje jen3odstraněné CDN linky; reader přesně baseline.
Neslučovat, dokud neprojde celá přejímka. Předchozí SQL b9 byl zamítnut.

Další analýzou doložený zásah: odstranit duplicitní Bootstrap CDN CSS z3stránek.
Lokální canonical232803bytes/SRI identické. WIP7a7afd75 zachovaný, vlastní
probe větev mimo htdocs. Přesné zdroje po odečtení tagů, HTTP/business/roles,
17paired Chrome cold/warm desktop390/public, CSS2→1, bez časové regrese.
Další krok implementace a nativní profil; žádné SQL/DB nebo provozní přepínače.

Aktuálně: whole parent HTML3stránky shodné oběDB; test role200 se opravuje
podle baseline. SharedUiShell a CodeQL test se zpřesňují; účetní PHPUnit
půlnoční UTC/CET fixture selhání se diagnostikuje odděleně. Chrome/gate/
merge/deploy stále nepřijaty. Viz aktuální odstavec plánu a RESULTS.


### Kořen příčiny role kontroly (šestý běh42721621744/731 FAIL)
Předchozí vysvětlení o inicializovaném kontextu bylo nepřesné. staffCurrentRoute
porovnává realpath(SCRIPT_FILENAME) s db.php __DIR__. Baseline root měl db.php
symlinkovaný do druhého rootu; pro zkopírované3rodiče tak workspace guard
vracel route=null. Žádná změna produkčního oprávnění: chyba izolace A/B harness.
Baseline nově dostane fyzickou, byte-shodnou kopii db.php z ff182971;
main db.php musí být zároveň identický. Oba kontexty coach/Správa a lead/Moje
opět musejí být odmítnuté se stejným statusem/Location, plus anonymní Moje/Správa.
Šestý ani předchozí asset běhy nejsou platnou kompletní přejímkou. Jejich
model/CSS/source/cleanup důkazy zůstávají, timing se musí zopakovat s opravou.
RIX pokusy s odděleným symlink baseline jsou rovněž pouze zamítnutá diagnóza,
nikoli důkaz ke sloučení. Čerstvý main PHPUnit42721710594 má stejné účetní
selhání během české půlnoci jako PR. Čeká ověření přesného logu a fixture času.
