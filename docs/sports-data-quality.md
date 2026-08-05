# Kvalita sportovních dat – M3.5a až M3.5e

Stav k 5. 8. 2026: read-only inventura, verzovaný kontrakt v1, jeho použití při
novém zápisu, read-only příprava importu (M3.5d) i deterministický rozpoznávací
kontrakt historické volnotextové tabulky `mereni` (M3.5e) jsou implementované a
ověřené na localhostu. Produkce se nezměnila.

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

## Kontrakt M3.5b–c: `sports-measurement-v1`

Nový kontrakt používají všechny čtyři formuláře a handlery pro vytvoření i editaci
tréninku a závodu. Nemění ani automaticky nepřevádí žádný historický řádek:

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

## Kontrakt M3.5e: rozpoznávání historické tabulky `mereni`

M3.5a i M3.5d záměrně vynechaly historickou volnotextovou tabulku `mereni`
(vzdálenost i čas jako volný text, 7 řádků na localhostu), dokud nebude
definován formát. M3.5e nedefinuje formát pro převod — definuje pouze
**deterministický rozpoznávací kontrakt**, který každou hodnotu klasifikuje
jako rozpoznatelnou nebo nejednoznačnou. Kontrakt nic nepřevádí, nic
neodhaduje a neukládá žádnou normalizovanou hodnotu; slouží jen k tomu, aby
administrátor viděl, které řádky bude muset později rozhodnout ručně.

Uznané vzory (přesná shoda, jednotka výhradně malými písmeny):

- vzdálenost: `<číslo> km` nebo `<číslo> m`,
- čas: striktní `MM:SS(.mmm)` / `HH:MM:SS(.mmm)` (stejný parser jako
  `sports-measurement-v1`, viz `sportsMeasurementDurationMilliseconds()`) nebo
  `<číslo> min`.

`<číslo>` je nezáporné číslo s nejvýše třemi desetinnými místy; desetinný
oddělovač je tečka nebo čárka (stejná číselná gramatika jako u
`sports-measurement-v1`). Mezi číslem a jednotkou smí být nejvýše jedna
mezera — hodnota tedy může, ale nemusí mít mezeru („200m“ i „200 m“ jsou
rozpoznatelné; „200  m“ se dvěma mezerami už ne).

Cokoli jiné je nejednoznačné, mimo jiné:

- rozsahy („10-15 km“),
- přibližné zápisy s „cca“ nebo podobnou předponou,
- více hodnot v jednom poli („10 km, 5 km“),
- prázdná hodnota — chybějící vzdálenost nebo čas je vždy nejednoznačný, není
  to automatické „nic k řešení“,
- jiná jednotka, jiný počet desetinných míst nebo jiné velikosti písmen
  jednotky (`KM`, `Km`), než kontrakt uznává.

Řádek tabulky `mereni` je jako celek rozpoznatelný, jen když jsou rozpoznatelná
obě pole zároveň (vzdálenost i čas). Klasifikace nikdy nevrací `sportovec_id`
ani jméno; vazba na trénink se uvádí pouze jako `trénink <datum>`.

Kontrakt je čistě přípravný krok pro `sports_import_review_admin.php`. Ruční
rozhodnutí o každém nejednoznačném řádku a případný samostatně schválený
formát převodu zůstávají navazujícím krokem; M3.5e sám o sobě žádný řádek
tabulky `mereni` nemění.

## Pravidlo pro ostrá data

- KIS a e-shop se později převezmou jednorázovým kontrolovaným importem.
- Tréninky se převezmou ze stávající produkční Evidence; jejich objem je malý.
- Historické texty se při importu nepřevádějí odhadem. Pokud jednotka nebo formát
  není jednoznačný, zůstane normalizovaná hodnota prázdná a originál čitelný.
- M3.5c připojuje formuláře `formular.php`, `edit_trenink.php`,
  `formular_zavod.php` a `edit_zavod_form.php` ke společnému parseru
  `includes/sports_measurement_input.php`. Nový zápis ukládá původní čitelná pole
  i normalizované hodnoty v1; neplatná jednotka, čas nebo RPE se odmítnou ještě
  před databázovou transakcí.
- Zobrazení starších řádků bez uložené jednotky zachovává dosavadní význam `km`.
  Při editaci však musí obsluha jednotku výslovně potvrdit, takže se nový zápis
  nevytváří odhadem.
- Stejný soubor obsahuje fail-closed mapper budoucího importu závodních výsledků.
  Samotný jednorázový import KIS/e-shopu ani produkčních tréninků se v M3.5c
  nespouští a produkční data zůstávají beze změny.
