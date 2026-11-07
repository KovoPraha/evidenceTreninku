# P11-R13-S — stavový filtr zdravotního přehledu

7. 11. 2026, Europe/Prague. **HOTOVO: technický řez, merge a schválený deploy.**
Vlastník vývoj podle aktuálního výslovného zadání Marka; celé K0.2 zůstává otevřené.
[Parita a měření](verification/medical-state-read-performance-20261107/RESULTS.md),
[provozní důkaz a meze](verification/medical-state-release-20261107/RESULTS.md),
[session handoff](SESSION_HANDOFF_MEDICAL_STATE_2026-11-07.md), [registr](WORK_PLAN_CURRENT.md).

## Zadání a původní plán

Navázat na [analýzu celého HTTP](verification/full-http-performance-20261107/RESULTS.md)
a [předchozí R13 řez](SESSION_HANDOFF_MEDICAL_READ_PERFORMANCE_2026-11-06.md):
ověřit přesnou shodu výsledků, změřit přínos před/po a po zelených kontrolách
commit/merge/standardní deploy. Historický zákaz deploye #352 není současný pokyn.
Plán před implementací uložený v553c6535, base6b1860d5. Původně scoped agregace,
přesný počet/stránka v DB, kanonické složité řetězce a fallback, nativní parita,
párový helper/HTTP/Chrome, archiv výsledků, teprve pak merge/deploy.
[Volba D14](DECISION_LOG.md#d2026-11-07-14), události E47–53.

Implementační větev `codex/medical-state-read-performance-20261107` / [PR #381](https://github.com/KovoPraha/evidenceTreninku/pull/381),
worktree `C:/Users/Marek/Documents/Codex/evidencePavel-medical-state-performance-20261107`.
Výsledkové předání `codex/medical-state-release-proof-20261107` ve stejném worktree.
Původní checkout/WIP i untracked výstupy zachované; `kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5` shodný před/po.

## Finální implementace a rozsah

Pouze čtecí stavový filtr bez doplňkových filtrů. SQL najde nejnovější přesný
verified a případně poslední záznam; nezamění jej za novější pending/revoked.
Case/trailing-space porovnání odpovídá PHP `===`. Jedna tenká agregace s
COUNT/ROW_NUMBER vrátí přesný počet a oříznutou stránku včetně příliš velkého čísla.
Nejvýše25 stavových projekcí a25 plných sledování; jména mimo pracovní okno.
Výstupní stav stále tvoří kanonická čistá funkce, žádné nové zdravotní rozhodnutí.

Budoucí verified s explicitním předchůdcem: nejvýše128sledování/4096metadat,
vyhodnocení původní funkcí nad celou jejich historií. Nad limitem původní stream;
sonda historie se před fallbackem uvolní. Hledání/datum/osoba a transakce používají
přesně původní SQL a bindingy. Seznam bez stavu, výběr osoby, detail, writer,
soukromé dokumenty, práva a integrační flags nezměněné. Bez DDL/trvalé cache.
SQL window funkce ověřené nativně na MariaDB10.3/11.4 i SQLite.

## Ověření, výsledek a provozní stav

Finální zdroj a1d7a70d:12CI SUCCESS, PHPUnit2377/47777, nativní cílené7/201
na každé DB, lokální18/356; úplné výsledky/filtry/počty/pořadí/stránky,
legacy řetězce/cykly/chybějící/cizí předchůdci, datumové hranice, čerstvé změny,
nebufferovaný PDO po úspěchu/výjimce a fallback limity PASS.
Pomocný profil21případů×17střídavých párů na každé DB, SQL query cache OFF,
3checksumy stejné. Celé HTTP10k/100k:2160časů,48negativů,208Chrome;
celé normalizované HTML a13doménových checksumů stejné, bez DML/DDL, úklid PASS.

100k celý GET Platné1062,80→519,94ms (−51,1%), Čekající1047,26→473,69ms (−54,8%).
Helper100k Platné10.3 471,00→313,91ms /11.4 922,91→580,86ms,
přenos216691→74řádků. Hledání neutrální původní cestou. Malá100sada má asi0,5–1,5ms
pevné režie navíc; celý GET SQL20→22, PHPpeak616168→711080B, alokovaný stále2MiB.
Jde o syntetický přínos rychlosti/přenosu, nikoli snížení peaku nebo produkční SLA.

#381 sloučené na938e9c50 má přesně stejný celý strom a měřené zdrojové hashe.
Deploy42405912779 SUCCESS: záloha/ověření, migrace, zachování VS, aktivace i smoke PASS;
import/UAT false. Veřejné čtení5/5 celé DOM/ID/pořadí/porovnatelné údaje shodné,
anonymní zdravotní GET403 před/po. Přihlášené produkční UAT NOT RUN.
Aktuální release vlastní [CURRENT](CURRENT_STATE.md). Další deploy neopakovat.

## Varianty a převzetí

Prototyp a7b1bee9 měl paritu, ale časovou regresi, zamítnut. V2/64137a5b odstranila
regresi, Platné neutrální. V3/c6f7576e doložila objemový přínos; nativní scoped hledání
mělo režii. V4/a1d7a70d scoped větev odstranila a zanechala původní doplňkové filtry.
Finální profil explicitně vypíná SQL result cache; extrémní starší neřízené10.3
poměry nejsou kauzální důkaz. [Historie a surové důkazy](verification/medical-state-read-performance-20261107/RESULTS.md).
Nativní test opravil předpoklad boolfalse vs int0; nyní ověřuje vypnutí a přesné
zachování skutečného typu PDO atributu po úspěchu/výjimce. Runtime tím nezměněný.

Převzaté #375/eafe835b a [100k studie](MEDICAL_PLANNER_SCALE_2026-11-07.md), její metadata,
důkazy a přijaté D13/E45/E46 zachované. Pracovní R13 ID nyní D14/E47–50;
původní commity zůstaly v historii. Rekurzivní SQL a persistentní stavy zamítnuté
kvůli složitosti/invalidaci; další prahová sonda pro malé sady nezavedená.

## Otevřené navazující úkoly

Přihlášené produkční UAT zdravotního přehledu, skutečné rows examined, inventura
produkčních indexů před další změnou, měřené scoped čtení, fyzický mobil/síť/CPU
souběh a ostatní K0.2 zůstávají otevřené. #377 blokované UAT pickeru, P05/P07/P06
ani heslo/T07 nejsou tímto řezem uzavřené. Výsledkové předání mění pouze dokumentaci.
