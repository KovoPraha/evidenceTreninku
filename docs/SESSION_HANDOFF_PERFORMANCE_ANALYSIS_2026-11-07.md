# Předání — analýza rychlosti 7. 11. 2026

Zadání vlastníka: analýza rychlosti aplikace a míst vhodných k refaktoringu.
Výsledky, metoda, naměřené hodnoty, zdroje i navržená přejímka jsou v jediném
[protokolu analýzy](verification/performance-analysis-20261107/RESULTS.md).
Další práci eviduje [P11 v registru](WORK_PLAN_CURRENT.md), širší návaznost
[komplexní plán](COMPREHENSIVE_REFACTORING_PLAN_2026-10-29.md).

## Pracovní kopie a rozsah

Větev `codex/performance-analysis-20261107`, izolovaná kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-performance-analysis-20261107`.
Výchozí čerstvý main `89edfdbe` již obsahuje R05 #353 a R13 #352.
Původní `C:/xampp/htdocs/evidencePavel` se nepřepínala; její cizí WIP
`kis_rosters_admin.php` má ověřený SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

Hotová analýza vybraných cest a nová diagnostika: 9 SQLite případů / 180
časových vzorků + oddělené SQL počty a shodný výstup instrumentace; 25
veřejných sériových GET / HTTP 200 a 3 HEAD. Žádné tajemství, osobní data,
config nebo session cookies se do důkazů neukládaly.
12 stávajících testů / 123 assertions PASS; JUnit je přiložený v protokolu.

## Doporučená návaznost

1. Dávkové ceny katalogu: společná způsobilost a cenová pravidla pro účet/datum,
   čistý výpočet ceny varianty; zachovat čerstvé ověření v checkoutu.
2. Zvlášť lehký kandidátní katalog a plné karty jen pro výslednou stránku;
   zachovat prodejnost, přesné počty a řazení skutečnou cenou.
3. Před prací na R06 prověřit již existující
   `C:/Users/Marek/Documents/Codex/evidencePavel-attendance-performance-20261107`;
   tato analýza tam nic neměnila. Stav rozpracovanosti není důkaz merge.
4. Stránkovat tréninkové seznamy a detaily měření; potom datové filtry/hledání.
5. Přihlášené celé HTTP/bootstrap/nav, souběh stejné session a browser metriky
   jsou samostatné dosud neměřené hranice. Před novým PR ověřit čerstvý main.

Další implementace není součástí hotové analýzy. Žádný deploy, aktivace,
provozní zápis, platba nebo pošta. Živý release a flags nadále pouze
[CURRENT_STATE](CURRENT_STATE.md); HTTP vzorek neověřuje shodu release s main.
Původní odložené P05/P07/P06, heslo/T07 a serverový ranní audit jiné relace
se touto analýzou nemění. Nové služby ani databázové schéma se nezakládaly.
