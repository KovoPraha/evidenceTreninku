# Čtvrtá vlna — dávkové podmínky programů

28. 10. 2026, [PR #215](https://github.com/KovoPraha/evidenceTreninku/pull/215).
Výchozí main `fdd6e8f03d912b6375cd436eccc567c31560eb70`;
[plán a hranice](../../REFACTORING_WAVE4_2026-10-28.md).

## Změna a zachované chování

Veřejné čtení přednačte podmínky pro prodejné nabídky a jejich programy do
existujícího kontextu jedné operace. Dvě dávky nahrazují čtyři dotazy na program.
Každá dávka má nejvýše 80 cílů / 160 SELECT větví / 800 parametrů. Poddotazy
vybírají MAX(id) s původními vázanými predikáty; rozhoduje skutečná kolace SQL.
Následný JOIN vrací úplný původní řádek, včetně původních datových typů.

Přednost nabídky před programem, poslední aktivní verze a blokování prázdných
či vzorových textů zůstávají zachované. Chybějící dokument se cachuje pouze
v rámci této operace. Další operace a zamykající čtení vidí aktuální stav.
Kanonické funkce v `club_program.php` a `club_program_terms.php`, výpočet kapacity,
checkout, členské ceny a oprávnění se nemění. Bez migrace či nové závislosti.

## Důkazy

Nový test před opravou selhal: 200 dotazů na podmínky proti limitu 2.
Po změně 7 testů / 41 assertions PASS. Stávající kompatibilita programových
čtení 6/35 a katalogových karet 5/27 PASS. Testy zahrnují více aktivních verzí,
částečné překrytí, prázdné/vzorové/stažené dokumenty, chybějící registry,
81 programů přes hranici dávky, zboží bez programů a čerstvé čtení pod zámkem.

[Před](before.json) / [po](after.json): všechny 12 úplných výstupních hashů
shodné (navigace, katalog, karty, detail; 1/10/50 programů).
Reprodukce z kořene worktree: `php docs/verification/refactoring-wave4-20261028/profile-source.txt <absolutní-kořen-verze>`.
Jednotlivé časy v tomto profilu jsou diagnostické, ne statistický benchmark.

| Programů | SQL karet před | SQL karet po | Dotazy na podmínky před | Po |
| ---: | ---: | ---: | ---: | ---: |
| 1 | 24 | 22 | 4 | 2 |
| 10 | 96 | 58 | 40 | 2 |
| 50 | 416 | 218 | 200 | 2 |

[Lokální MariaDB 10.4](mariadb-local.json) potvrzuje stejné úplné řádky,
kolace s velikostí písmen, diakritikou a koncovými mezerami, čerstvé čtení pod
zámkem a pro 81 programů čtyři dávky / nejvýše 800 parametrů. Používá existující
index `idx_terms_scope_current`; EXPLAIN MAX lookup hlásí „Select tables optimized away“.
Reprodukce: `php tests/Support/ProgramTermsBatchMariaDbSmoke.php`, pouze localhost,
port přes `EVIDENCE_MARIADB_SMOKE_PORT`; náhodná syntetická DB se odstraní ve finally.
Stejný smoke je součástí CI matice MariaDB 10.3/11.4.

[HTTP shoda](http-checks.json): šest profilů, shodný viditelný text, odkazy
a obrázky. Ignorují se pouze číselné cache verze assetů po kontrole shodných
bajtů v obou worktrees. Žádná změna šablony, CSS ani JS.
[HTTP měření](http-benchmark.json): stejná syntetická DB, 71 aktivních produktů,
20 programů, střídavé pořadí, 5 zahřátí + 15 měření. Medián katalogu
247,41 → 209,95 ms (−15,1 %). Čas celé HTTP odpovědi včetně lokální sítě;
sdílený Windows host, nikoli zátěžový test ani produkční příslib.
[Reprodukční skript](http-check-source.py).

Zůstávají přibližně čtyři SQL dotazy na program pro nabídku a kapacitu;
nejde o konstantní počet dotazů celého katalogu. Členské a lidské/účetní UAT
nenahrazuje syntetické ani anonymní ověření. Importy a integrace se neaktivují.

## Kompletní CI

Commit `9f87e548e83a5172817508e16349adec54da4470`:
[Testy 40770521904](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40770521904)
PASS, 1 776 testů / 35 830 assertions, POSIX 3/45, anonymní sweep a JS kontroly.
[MariaDB 10.3](ci-mariadb-10.3.json) a [MariaDB 11.4](ci-mariadb-11.4.json)
potvrzují shodné kanonické řádky, kolace, zámky a rozpočet 22/218/344 SQL
pro 1/50/81 programů. PHPStan level 5, style a kontrola po naplnění cache
[40770521901](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40770521901) PASS;
[aktuální dokumentace 40770522149](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40770522149) PASS.
Finální dokumentační commit musí před sloučením projít stejným CI.
