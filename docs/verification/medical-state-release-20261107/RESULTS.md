# P11-R13-S — produkční předání

7. 11. 2026: **SLOUČENO / NASAZENO / ČTECÍ KONTROLA PASS.**
[Plán](../../MEDICAL_STATE_READ_PERFORMANCE_2026-11-07.md), [parita a měření](../medical-state-read-performance-20261107/RESULTS.md), [předání](../../SESSION_HANDOFF_MEDICAL_STATE_2026-11-07.md).

## Oprávnění, merge a nasazení

Aktuální zadání vlastníka výslovně dovolilo commit/merge/deploy po shodě,
přínosu a zelených kontrolách. [PR #381](https://github.com/KovoPraha/evidenceTreninku/pull/381)
sloučené na `938e9c50317be43e8b206da93294ca41d10065c0`.
Finální `a1d7a70d95123734939046456478d19caa276f35` má [všech12CI SUCCESS](checks-final.json):
[Testy42404266532](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42404266532),
[HTTP/Chrome42404266401](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42404266401).
PHPUnit2377/47777, nativní stavové7/201 na každé MariaDB10.3/11.4,
PHPStan5/styl/CodeQL/docs PASS. Převzaté dokumentační #375 a přijatá ID zachované.
[Shoda merge](merge-verification.json): celý Git strom přesně stejný s testovanou
hlavou a všechny měřené zdrojové hashe shodné. Před dispatch fresh main,
žádný běžící duplicitní deploy.

[Deploy42405912779](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42405912779)
**SUCCESS na přesném výše uvedeném runtime SHA**. [Bezpečný záznam kroků](deployment.json):
syntaxe/testy, předletová kontrola, vytvoření/ověření zálohy, migrace,
zachování dosavadních VS, aktivace a HTTP smoke PASS. Nová migrace nepřibyla.
`potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`;
import a označení UAT přeskočené. Žádná nová aktivace integrací.

## Přesná shoda a přínos

[Finální protokol](../medical-state-read-performance-20261107/RESULTS.md),
[souhrn zdrojů a měření](../medical-state-read-performance-20261107/final/integrated-summary.json),
[strojová verifikace](../medical-state-read-performance-20261107/final/verification.json).
Vlastní syntetický Linux/PHP8.2, MariaDB10.3/11.4, SQL query cache OFF.
714střídavých párových pomocných časů,2160celých HTTP vzorků/48negativů,
208Chrome pozorování. Úplné normalizované HTML všech30případů/objem shodné,
13doménových checksumů stejné, žádné DML/DDL z diagnostických GET, úklid PASS.

| Syntetické měření /100k | Před | Po |
| --- | ---: | ---: |
| Celý GET Platné | 1062,80ms | 519,94ms (−51,1%) |
| Celý GET Čekající | 1047,26ms | 473,69ms (−54,8%) |
| Chrome warm load Platné | 1128,45ms | 595,55ms |
| Helper Platné10.3 | 471,00ms | 313,91ms |
| Helper Platné11.4 | 922,91ms | 580,86ms |
| Helper přenos do PHP | 216691řádků | 74řádků |

Hledání/datum/osoba/transakce původní SQL/bindingy, hledání neutrální.
Malá100sada má0,5–1,5ms pevné režie navíc. Celý GET Platné SQL20→22,
PHPpeak616168→711080B, alokovaný peak2MiB stejný. Starý stream už omezoval
paměť; nejde o zmenšení jejího peaku ani počet zkoumaných řádků DB.
Syntetické CI časy nejsou produkční SLA nebo inventura produkčních objemů.

## Produkční čtecí ověření a meze

[Před](before.json) / [po](after.json): pět sériových anonymních veřejných GET,
všude HTTP200, **5/5 stejné celé normalizované main/navigace, ID/pořadí
 a porovnatelné veřejné údaje**. Získaný celkový počet u dvou prázdných
výsledkových stránek222→222; u zbývajících tří jej parser nezískal (null).
[Sonda](snapshot.php.txt) ukládá jen veřejné ID/počty/hash, žádné HTML/cookies/session.
Zdravotní přehled [před](anonymous-medical-before.json) i [po](anonymous-medical-after.json)
HTTP403: potvrzené anonymní odmítnutí. [Skript](anonymous-medical.php.txt).
Přihlášené produkční UAT **NOT RUN**; izolované nativní a HTTP testy je nenahrazují.
#377 blokované produkční UAT pickeru zůstává samostatné.

[Původní WIP](wip-preserved.json) SHA256 stejný, původní checkout/untracked
výstupy zachované. Žádné skutečné změny osob/rolí/zdravotních podkladů,
soukromých dokumentů, plateb nebo integračních flags. Záloha ověřená v deployi
není důkaz izolované obnovy. Rows examined, fyzický mobil a souběh NOT RUN.
Writer/práva/kanonické stavy zachované; žádná trvalá cache.
Výsledkové předání mění pouze dokumentaci/důkazy; další deploy neopakovat.
