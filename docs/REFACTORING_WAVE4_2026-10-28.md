# Čtvrtá vlna — dávkové čtení zveřejněných podmínek programů

28. 10. 2026, výchozí main `fdd6e8f03d912b6375cd436eccc567c31560eb70`,
aplikačně shodný s nasazeným `09510ac6`. Všechny čtyři CI main PASS.
Navazuje na [třetí vlnu](REFACTORING_WAVE3_2026-10-28.md).

## Rozsah a plán ověření

Po třetí vlně má 50 programů 416 SQL dotazů. Zbývá kanonické čtení nabídky,
fyzických soupisek, obsazenosti a podmínek. Nejprve změřit podíl jednotlivých
dotazů. Kandidátem jsou opakované dotazy na dva dokumenty nabídky a programu;
výpočet sdílené kapacity a zamykající checkout neměnit.

1. Zachytit původní profil a selhávající rozpočet počtu dotazů na podmínky.
2. Načíst poslední aktivní dokumenty pro známé nabídky/programy v omezených
   dávkách uvnitř jediného čtecího kontextu. Zachovat SQL porovnání scope klíčů,
   nejvyšší aktivní ID, prioritu nabídky před programem a blokování vzorového textu.
3. Kanonické čtení mimo kontext a všechny zamykající cesty zachovat. Každá další
   veřejná operace dostane nový kontext; žádná persistentní cache ani migrace.
4. Ověřit shodu s kanonickými funkcemi, více aktivních verzí, částečné překrytí,
   stažené/vzorové/chybějící dokumenty, chybějící registry, různé kolace,
   hranice dávky, nové čtení po publikaci a ignorování kontextu pod zámkem.
5. SQLite a MariaDB 10.3/11.4: SQL, úplné výstupy a kontrakty. HTTP porovnání
   na stejné syntetické DB; plné CI, PHPStan, style a bezpečnostní sweep.
6. Po úspěšném ověření PR, merge a chráněný deploy; čtecí kontrola a stejné
   HTTP cesty před/po. Aktualizovat CURRENT_STATE a zachovat celý starý přehled.

Vlastník výslovně schválil autonomní implementaci, sloučení a nasazení po ověření.
Bez aktivace importů/integrací a bez změny cen, oprávnění nebo skutečných dat.
Plné lidské/účetní UAT zůstává otevřené; deploy UAT=false, importovat_krouzky=false.

## Implementace a ověření

[PR #215](https://github.com/KovoPraha/evidenceTreninku/pull/215) implementuje
dávkové čtení v kontextu jedné veřejné operace. Pro 50 programů SQL 416 → 218,
podmínky 200 → 2, všech 12 porovnávaných výstupů shodných. Kompletní CI
1 776 testů / 35 830 assertions, MariaDB 10.3/11.4, PHPStan a style PASS.
[Výsledky, reprodukce a limity](verification/refactoring-wave4-20261028/RESULTS.md).
Technické vydání `e40df7d3` proběhlo 28. 10. 2026;
[produkční protokol](verification/refactoring-wave4-release-20261028/RESULTS.md).
