# Předání P11-R13-S — zdravotní stavový filtr

7. 11. 2026: **HOTOVO, #381 sloučené a schválené nasazení SUCCESS.**
[Plán](MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [parita/přínos](verification/medical-state-read-performance-20261107/RESULTS.md), [provozní důkaz](verification/medical-state-release-20261107/RESULTS.md).
Runtime938e9c50, deploy42405912779; release/flags vlastní [CURRENT](CURRENT_STATE.md).
Finální a1d7a70d12CI PASS, celý merge strom a zdrojové hashe přesně shodné.
PHPUnit2377/47777; nativní obě DB7/201; lokální18/356. Celé HTML/13checksumů
stejné,2160HTTP/48negativ/208Chrome PASS, doménové DML/DDL0, úklid PASS.
100k celý GET Platné1062,80→519,94ms, Čekající1047,26→473,69ms.
Helper100k Platné10.3 471,00→313,91ms /11.4 922,91→580,86ms;216691→74řádků.

Finální projekce jen pro stav bez doplňkových filtrů; hledání/datum/osoba/transakce
přesně původní SQL a bindingy. Budoucí explicitní řetězce kanonické; nad limity stream.
Malá100sada má0,5–1,5ms režie; SQL20→22 a PHPpeak616168→711080B, alokovaný2MiB.
SQL result cache při finálním měření OFF; staré neřízené10.3 poměry neuvádět jako přínos.
Prototypy, důvody volby D14 a surové důkazy jsou v plánu/protokolu.

Převzaté #375 a přijaté D13/E45/E46 zachované; vlastní D14/E47–53. Původní WIP
`kis_rosters_admin.php` SHA256 `7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` znovu shodný.
Veřejné před/po5/5 DOM/ID/pořadí a srovnatelné údaje stejné, zdravotní anonymní403.
Přihlášené produkční UAT NOT RUN; #377 picker UAT BLOKOVANÉ zůstává samostatné.
Žádné skutečné zdravotní/osobní/rolové změny nebo nové integrační flags.
Výsledkové dokumentační předání: `codex/medical-state-release-proof-20261107`, stejný
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Další deploy neopakovat. Dále přihlášené UAT/rows examined/indexy/scoped čtení,
fyzický mobil/souběh a ostatní K0.2; P05/P07/P06/heslo/T07 tím nejsou uzavřené.
