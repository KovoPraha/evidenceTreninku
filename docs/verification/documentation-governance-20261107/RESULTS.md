# Ověření souborové dokumentace — 7. 11. 2026

[Zadání, rozsah a akceptace DOC01](../../DOCUMENTATION_GOVERNANCE_2026-11-07.md).
Výchozí revize `feb8fe8c`, vlastní větev `codex/repo-documentation-policy-20261107`.
Bez změny aplikačního kódu, produkce, databáze či provozní konfigurace.

## Výsledek místního ověření

| Kontrola | Výsledek |
| --- | --- |
| `php tools/check-current-docs.php` | PASS; CURRENT 80 řádků, hlavní handoff 70, session rozcestník 15; přes 1 400 místních odkazů, 107 původních archivních bloků a tři nové snapshoty bez porušení |
| `php -l tools/check-current-docs.php` | PASS |
| PSR-12 formát kontrolního skriptu | Upraven pomocí projektového PHP CS Fixer; konečnou kontrolu provádí PR CI |
| Existující `CurrentDocumentationContractTest` | PASS, 2 testy / 14 assertions; samostatný běh se sdílenými instalovanými závislostmi, bez přístupu k DB |
| `git diff --check` | PASS |
| `python tests/Support/test_membership_audit_publication.py` | PASS, všech pět existujících regresí |
| Negativní zkoušky dokumentační kontroly | PASS; přetečení handoffu, chybějící povinný odkaz a změna obsahu archivu správně odmítnuty; soubory po zkoušce obnoveny |
| Serverový publikátor nad kopií skutečných nových dokumentů | PASS; syntetický výsledek nahradil jediný vyhrazený blok, zachoval DOC01, handoff po zápisu 69 řádků a CURRENT 80 |

[Strojový souhrn](checks.json), [manifest původních snapshotů](archive-manifest.json).
SHA-256 porovnává původní obsah při normalizaci konců řádků na LF, bez obálky archivu.
Původní soubory jsou z doložené base, včetně celých 3 697 řádků session historie;
nevytvářela se z nich redukovaná historická parafráze. Opraven i přímý odkaz na
historickou sekci MariaDB, aby přesun nepřerušil její dohledání.

## Důkazní hranice a další krok

Zápis publikátoru byl pouze syntetický v dočasné kopii. Není výsledkem skutečného
ranního auditu, novým povolením ani produkční operací. Stav draftu #347 a
otevřeného #355 je časový snímek; před integrací jej znovu ověřit.
Původní WIP v `C:/xampp/htdocs/evidencePavel` se neupravoval; bezpečný otisk
`kis_rosters_admin.php` při předání je v souhrnu, nejde o commit tohoto souboru.
GitHub předání [PR #357](https://github.com/KovoPraha/evidenceTreninku/pull/357),
první commit `620512ee`; konečný head a stav povinných kontrol/sloučení určuje PR.
