# Kvalita sportovních dat – M3.5a a M3.5b

Stav k 5. 8. 2026: read-only inventura i verzovaný kontrakt v1 jsou implementované
a ověřené na localhostu.
Produkce se nezměnila.

## Účel

Administrátorská stránka `sports_data_quality_admin.php` ukazuje pouze agregované
počty a technická zjištění. Nezobrazuje jména, ID sportovců, poznámky, výsledné
texty ani naměřené hodnoty. Nemá formulář a nic nezapisuje.

Přehled záměrně nehodnotí zdraví ani výkonnost sportovce. Jeho úkolem je zjistit,
jestli jsou data před budoucím porovnáváním dostatečně jednoznačná.

## Zdroje

1. Tréninky a docházka: `treninky`, `trenink_sportovec`, `sportovci`.
2. Strukturovaná měření: `mereni_zaznamy`, `trenink_mereni`, `zavod_mereni`.
3. Starší textová měření: `mereni`.
4. Výsledky závodů: `zavody`, `zavod_sportovec`.
5. Zátěžové testy: `zatezove_testy`, `zatezove_testy_soubory`.

Chybějící tabulka je hlášena jako nedostupný zdroj, nikoli jako nula.

## Živý localhost snapshot

- 456 tréninků a 422 vazeb docházky,
- 239 sportovců alespoň s jednou evidovanou docházkou,
- 0 strukturovaných měření,
- 7 starších textových měření,
- 2 závodní účasti bez rozlišeného výsledkového stavu,
- 6 zátěžových testů, z toho 4 s vyplněnou výškou i hmotností,
- všech pět zdrojů je dostupných.

Počty jsou pouze snapshot testovacích localhost dat a mohou se měnit.

## Zjištěné návrhové dluhy

- 455 historických tréninků nemá kategorii,
- délka tréninku používá hodiny jen jako konvenci UI, nikoli jako verzovaný
  databázový kontrakt,
- strukturovaný čas a RPE jsou textové a vzdálenost nemá samostatně uloženou
  jednotku; formuláře dnes připouštějí km i m,
- starší vzdálenost a čas jsou volný text,
- závodní účast nerozlišuje dosud nedoplněný výsledek, DNS a DNF,
- zátěžové testy před další analytikou potřebují schválený účel, přístupy,
  retenční dobu a pravidlo výmazu.

## Kontrakt M3.5b: `sports-measurement-v1`

Nový kontrakt je připraven pro budoucí formuláře a jednorázové importy. Nemění
ani automaticky nepřevádí žádný historický řádek:

- vzdálenost vždy vyžaduje výslovnou jednotku `m` nebo `km` a ukládá se také
  normalizovaně v metrech,
- čas přijímá jen `MM:SS(.mmm)` nebo `HH:MM:SS(.mmm)` a ukládá se v milisekundách,
- RPE je číslo od 1,0 do 10,0 s nejvýše jedním desetinným místem,
- stav závodu je právě jeden z `entered`, `finished`, `dns`, `dnf`, `dsq`,
- zátěžové testy nejsou součástí kontraktu ani progresových výpočtů.

Migrace `20260805050000_sports_measurement_contract` přidává pouze nullable
sloupce. V `mereni_zaznamy` jsou `contract_version`, `distance_unit`,
`distance_meters`, `duration_ms` a `rpe_value`; v `zavod_sportovec` jsou
`result_contract_version`, `result_status` a `result_time_ms`. Původní sloupce
zůstávají kvůli čitelnosti historie beze změny.

Validace je v `includes/sports_measurement_contract.php`. Nejednoznačný neprázdný
vstup se odmítne; prázdná volitelná hodnota zůstane `NULL`. Read-only přehled
ukazuje pokrytí kontraktem v1, ale nikdy nevypisuje hodnoty nebo osoby.

## Pravidlo pro ostrá data

- KIS a e-shop se později převezmou jednorázovým kontrolovaným importem.
- Tréninky se převezmou ze stávající produkční Evidence; jejich objem je malý.
- Historické texty se při importu nepřevádějí odhadem. Pokud jednotka nebo formát
  není jednoznačný, zůstane normalizovaná hodnota prázdná a originál čitelný.
- M3.5b zatím nepřepojuje staré formuláře na nový zápis. To je samostatný řez
  M3.5c, který použije tento společný validátor v UI a importérech.
