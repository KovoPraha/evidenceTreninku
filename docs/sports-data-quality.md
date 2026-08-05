# Kvalita sportovních dat – M3.5a

Stav k 5. 8. 2026: read-only inventura je implementovaná a ověřená na localhostu.
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

## Následující rozhodnutí M3.5b

Nejdříve je potřeba schválit verzovaný měřicí kontrakt: jednotku vzdálenosti,
normalizovaný čas, stupnici RPE, výsledkové stavy závodu a pravidla migrace
nejednoznačných historických hodnot. Staré hodnoty se nesmí automaticky převádět
odhadem. Zátěžové testy zůstávají mimo progresové výpočty, dokud nebude schválena
jejich zvláštní ochrana.
