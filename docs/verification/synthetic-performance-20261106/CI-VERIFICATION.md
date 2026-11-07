# Nezávislé syntetické ověření v CI — 6. 11. 2026

Stejný měřič úspěšně dokončil celou matici na Linuxu s MariaDB 10.3.39
i 11.4.13, PHP 8.2.35. [Testovací běh 42265527246](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42265527246)
pro hlavu PR `1c7fb25292b085440d1bac15272c5df06dc73281` skončil SUCCESS;
všech 11 souvisejících kontrol PR bylo zelených. Následující změna archivuje
jen tyto důkazy a aktualizuje dokumentaci; aktuální kontrolu hlavy určuje
[PR #349](https://github.com/KovoPraha/evidenceTreninku/pull/349).

Každé prostředí: **21 případů / 630 časových vzorků / 21 SQL měření**, čtyři
negativní autorizační případy odmítnuté, shodné datové i normalizované HTML
otisky měřicích režimů a odstraněné vlastní schéma. Počty SQL a načtených řádků
všech 21 případů jsou přesně stejné jako místně. Časy mezi různými stroji
nevyjadřují zrychlení aplikace.

| Prostředí | Úplné shrnutí | Úklid | R13 / 10k / vše: SQL, řádky | Medián / p95 ms | Přírůstek paměti MiB |
| --- | --- | --- | --- | ---: | ---: |
| MariaDB 10.3 | [JSON](ci-10.3/summary.json) | [Odstraněno](ci-10.3/cleanup.json) | 6 / 50 224 | 186.12 / 197.11 | 43.94 |
| MariaDB 11.4 | [JSON](ci-11.4/summary.json) | [Odstraněno](ci-11.4/cleanup.json) | 6 / 50 224 | 134.59 / 150.06 | 43.94 |

Skutečný checkout je GitHubem vytvořený testovací merge
`7f639840eb1703a93e70b2db475534eafcf935f6`, nikoli hlava větve ani nasazené
vydání. JSON zaznamenává tento SHA, hash měřiče a normalizované SHA256 všech
tří stránek a docházkové šablony. Tyto zdrojové otisky byly porovnány se
současnou pracovní kopií; žádný rozdíl. Místní výchozí aplikační strom zůstává
`1d0e974798cb199344cbfb595f4805509582a788`; měřené zdroje se neměnily.

Plné jednotlivé vzorky, dotazy a EXPLAIN obsahují artifacty běhu (retence 14 dnů):

- `synthetic-read-pages-mariadb-10.3`, ID `13527212180`, archiv SHA256
  `c24eb964fe784d67baaeefb6912db8dcf84588cb56d43c5e2ed9864e4460ebc6`.
- `synthetic-read-pages-mariadb-11.4`, ID `13527421655`, archiv SHA256
  `d0013a296cda23ba4786e9169f946d2116e7de9b58986c9a1ace91f14e5191af`.

Trvalé souhrny jsou výše v repozitáři; plné místní vzorky jsou také verzované.
Toto je pouze syntetická čtecí kontrola v CI. Produkční deploy ani změnu
produkčních dat, konfigurace či přepínačů tento běh neprovedl.
