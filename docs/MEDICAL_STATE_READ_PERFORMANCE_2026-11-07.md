# P11-R13-S — stavový filtr zdravotního přehledu

7. 11. 2026, Europe/Prague. Stav: ROZPRACOVÁNO, plán před implementací.
Vlastník: vývoj podle aktuálního zadání vlastníka aplikace. Registr P11-R13-S.
[Analýza celého HTTP](verification/full-http-performance-20261107/RESULTS.md),
[předchozí řez #352](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md),
[rozhodnutí D13](DECISION_LOG.md#d2026-11-07-13).

## Zadání, izolace a rozsah

Aktuální zadání: pokračovat optimalizací zdravotního přehledu, přesná parita,
měření před/po a po úspěchu commit/merge/standardní deploy. Historický zákaz
deploye v předání #352 není současný pokyn. Žádné nové produkční podklady,
osoby, role, soukromé soubory, zdravotní rozhodnutí ani aktivace integrací.
Větev `codex/medical-state-read-performance-20261107`, base `6b1860d5`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Původní checkout a WIP `kis_rosters_admin.php` zachovat; převzatý SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

Řez je čtecí filtr podle stavu: dnes streamuje všechna stavová metadata historie.
Seznam bez stavu, výběr osoby, detail, writer, soukromé stahování a práva zůstávají
kanonické. Výsledný počet, pořadí, stránky včetně ořezu a úplné stavy se nesmějí změnit.

## Navržený postup a důvod

1. Zmrazit přesnou současnou čtecí implementaci jako referenci.
2. SQL projekce aktuálního verified a nejnovějšího záznamu každého kandidáta,
   včetně přesné case-sensitive rovnosti stavu i na MariaDB. Nezaměnit nejnovější
   verified za nejnovější záznam; pending nesmí zneplatnit starší verified.
3. Běžné stavy filtrovat/count/paginovat v DB. Výstupní status stále vytváří
   kanonická čistá funkce; do PHP přenášet omezenou stránku stavových metadat.
   Budoucí verified s odkazem na předchůdce vyhodnotit původní funkcí nad úplnou
   historií v omezeném lokálním řezu. Po překročení limitu nebo v transakci původní
   streaming cesta. Žádná cache práv/dat mezi voláními ani nové schéma.
4. Rekurzivní SQL a persistentní přepočítané stavy odmítnuté kvůli složitosti,
   denním hranicím a invalidaci. N+1 po sledování ani návrat dávky200 nechceme;
   počet SQL omezený nezávisle na velikosti běžné historie.
5. SQLite a nativní MariaDB10.3/11.4 parita: všechny stavy, filtry, první/další/
   poslední/ořezaná stránka, žádný záznam, více verified, pending/revoked,
   budoucí řetězce včetně cyklu/chybějícího/cizího předchůdce, hranice dat,
   case/trailing-space data, čerstvá změna a transakční/fallback chování.
6. Párové helper měření na stejných syntetických datech s prohozeným pořadím,
   medián/p95, SQL a řádky vrácené PHP. Celý přihlášený GET a Chrome před/po,
   přesné normalizované HTML, samostatná diagnostika a kontrolní součty bez zápisů.
7. Povinné kontroly a aktuální docs; po přínosu a zelených kontrolách merge,
   standardní deploy s import/UAT false, čtecí produkční ověření, výsledkové předání.

## Akceptace a průběžný stav

Implementace lokálně hotová. Referenční36 původních kombinací i smíšená legacy data PASS;
18 cílených testů /344 assertions PASS, nový kontrakt zvlášť7/185 PASS, styl/lint PASS.
Nativní parita/měření a celé HTTP/Chrome připravené v CI. Merge/deploy/produkční smoke NOT RUN.
Přihlášené produkční UAT a rows examined jsou samostatné; neslibovat je z SQL countu.
Původní WIP hash ověřený shodný, nový worktree kompletně připravený z main.
Konkrétní projekce používá MAX aktuálního verified a posledního záznamu pouze nad
kandidáty danými filtrem. Nejvýše128 budoucích řetězců /4096 historických metadat;
při překročení původní stream. Filtr konkrétní osoby je původní cesta bez režie.
Další krok: PR, obě nativní MariaDB a celý HTTP/Chrome pár, archiv přínosu před merge.
Průběžné [předání](SESSION_HANDOFF_MEDICAL_STATE_2026-11-07.md).
