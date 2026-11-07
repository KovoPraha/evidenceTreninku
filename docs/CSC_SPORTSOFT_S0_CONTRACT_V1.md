# P15 / S0 — přesný čtecí kontrakt v1

Datum: 7. 11. 2026. Vlastník: Marek / vývoj KIS. Podpůrný kontrakt
[plánu P15](CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md), nikoli další roadmapa.
Stav: specifikace pro implementaci; skutečný úplný sběr a ingest NOT RUN.
[Stanice a předání](CSC_SPORTSOFT_S0_STATION.md), [důkazy](verification/csc-sportsoft-discovery-20261107/RESULTS.md).

## Pravidla přenosu

Čtyři kolekce: `csc_licenses`, `sportsoft_members`, `sportsoft_events`,
`sportsoft_registrations`. Poslední je vždy omezena právě jedním předem
povoleným `event_id`; ostatní jedním ověřeným oddílem/týmem.
Události jsou katalog zdroje, nezakládají závod ani účast v KIS.

V tabulkách níže jsou **všechna povolená pole řádku**. Klíče jsou povinné;
`?` dovoluje hodnotu `null`. Není-li buňka vyplněná, poslat `null`, nikoli
vymyšlenou hodnotu. Chybějící očekávaný sloupec je `layout_changed`, nikoli
prázdná buňka. Neznámé atributy odmítat (`additionalProperties=false`).
Obecný `payload`, HTML, tělo stránky a libovolná zdrojová URL jsou zakázané.
Identifikátory jsou řetězce, ne čísla. Text UTF-8/NFC, ořez vnějších mezer;
vnitřní obsah zachovat. Překročení limitu je chyba, ne tiché zkrácení.
Strojový formát definuje [JSON Schema v1](verification/csc-sportsoft-s0-20261107/contract.schema.json).
Schéma samo nekontroluje pravost scope, úplnost, row_count, lease ani shodu UCI;
tyto kontroly patří do budoucího serverového ingestu.

Legenda: **D** = doloženo přihlášeným DOM 7. 11.; **H** = doplněno z jeho
dochovaného lokálního snímku v S0; **N** = neověřeno, nyní výhradně `null`.
Snímky jsou soukromý pracovní podklad mimo Git. D/H neznamená novou živou
kontrolu ani důkaz všech stránek. Textové sloupce mohou být prázdné.

## ČSC — csc_licenses

Zdroj `/ClubDetail/Club/<club_id>`, tabulka závodníků/licencí.
Neotvírat editor, digitální kartu ani osobní přílohy.

| Pole řádku | Typ / limit | Přesný zdroj a význam | Důkaz |
| --- | --- | --- | --- |
| `license_id` | string 1–80 | Poslední segment odkazu `/Licenses/LicenseDetail/ClubDetail:<club_id>/<license_id>` v buňce Licence; oddíl musí souhlasit s connection | D |
| `uci_id_raw` | string? 80 | Buňka `Uci Id`, zachovat formátovací mezery; normalizuje server | D |
| `display_name` | string? 200 | Text odkazu licence, pouze jméno z přehledu; bez parsování na křestní/příjmení | H |
| `license_status_raw` | string? 100 | Přístupný popisek ikony v buňce Licence, ne její barva ani rok | H |
| `role_label` | string? 200 | Sloupec Role | D |
| `discipline_label` | string? 200 | Sloupec Disciplína | D |
| `category_label` | string? 200 | Sloupec Kategorie; bez odhadování CategoryId z názvu | D |
| `hosting_checked` | boolean? | Stav disabled checkboxu ve sloupci Hostování, pouze čtení checked/aria-checked; ne text celé buňky a nikdy kliknutí | H |
| `validity_year_raw` | string? 20 | Doslovný obsah Rok platnosti; ne datum expirace | D |

Z tohoto seznamu se nepřenáší datum narození, národnost ani interní RacerId:
nejsou doložené jako samostatné sloupce. ČSC osoba se propojí přes UCI,
licence přes `license_id`. Více licencí jedné osoby je platná vazba 1:N.
Chybějící ID licence nebo odkaz na jiný oddíl zastaví publikaci kolekce.

Server odvodí `license_status = valid|invalid|unknown`: pouze přesné popisky
`Platná licence` → valid, `Neplatná licence` → invalid, ostatní → unknown
a diagnostika. Popisky obou stavů jsou doložené v DOM. Z roku se platnost
neodvozuje. `validity_year` vznikne jen z přesně čtyř číslic, jinak null
a `invalid_year`; původní obsah zůstává součástí omezeného snímku.
Hostování je pozorovaný příznak, nikoli cíl/termín hostování ani povolení
nového hostování. Chybějící checkbox → null + diagnostika, ne false.

## Sportsoft — sportsoft_members

Zdroj `/private/team.aspx`. Přehled uvádí osoby s platnou licencí aktuální
sezony, proto se jeho počty nesmějí rovnat počtům všech licencí ČSC.

| Pole řádku | Typ / limit | Přesný zdroj a význam | Důkaz |
| --- | --- | --- | --- |
| `member_id` | null v S0 | Stabilní ID řádku nebylo prokázáno; nevytvářet ho z čísla řádku | N |
| `uci_id_raw` | string? 80 | UCI ID | D |
| `display_name` | string? 200 | Jméno, bez odhadování pořadí částí jména | D |
| `birth_date_raw` | string? 40 | Datum narození; pouze kontrola rozporu, nikoli alternativní párovací klíč | D |
| `nationality_label` | string? 100 | Národnost; bez vymyšleného ISO kódu | D |
| `role_label` | string? 200 | Role | D |
| `source_label` | string? 50 | `source`, doložené `api` / `manual`; jiný text zachovat jako unknown zdroj | D |

`source=api` neprokazuje API dostupné KIS. Rok sezony není doložené pole
řádku; aktuální rok stanice se nesmí vydávat za sezonu licence.

## Sportsoft — sportsoft_events

Zdroj `/private/events.aspx`, pouze viditelné řádky katalogu přihlášeného
týmu. Aktuální průzkum pokrývá JANEV CUP, nikoli všechny závody Sportsoftu.

| Pole řádku | Typ / limit | Přesný zdroj a význam | Důkaz |
| --- | --- | --- | --- |
| `event_id` | string? 80 | `e` z již existujícího odkazu `order.aspx`; pokud bezpečný odkaz chybí, null | D |
| `event_date_raw` | string? 80 | Datum | H |
| `event_name` | string 1–300 | Název | H |
| `venue_label` | string? 300 | Místo | H |
| `registration_action_label` | string? 100 | Popisek existující akce: Prohlédnout / změnit přihlášku nebo Vytvořit přihlášku; jen čtení | D |

`registration_presence = existing|not_created|unknown` je serverová
interpretace přesného popisku, nikoli stav jednotlivého závodníka.
„Vytvořit přihlášku“ se nikdy neaktivuje. Chybějící event_id není důvod
zkoušet jeho číselné sousedy. Takový katalogový řádek nelze zařadit do
allowlistu sběru přihlášek, dokud ID nebude bezpečně doložené.
Nesbírat uzávěrky, startovné, disciplínu, kapacitu ani stav registrací,
které dnešní průzkum neprokázal.

## Sportsoft — sportsoft_registrations

Zdroj existující `/private/order.aspx?e=<event_id>&m=<opaque>`.
Parametr `m` je soukromý navigační údaj adapteru, ne prokázané ID člena,
týmu nebo objednávky; nepřenáší se do KIS ani do logů.

| Pole řádku | Typ / limit | Přesný zdroj a význam | Důkaz |
| --- | --- | --- | --- |
| `registration_id` | null v S0 | Stabilní ID jednotlivé přihlášky/řádku neprokázáno | N |
| `uci_id_raw` | string? 80 | UCI ID | D |
| `display_name` | string? 200 | Jméno | D |
| `birth_date_raw` | string? 40 | Datum narození | D |
| `category_label` | string? 200 | Kategorie | D |
| `paid_raw` | string? 100 | Text / přístupný popisek buňky Zaplaceno; nikoli checkbox výběru závodníka | D |

Serverové `paid = paid|unpaid|unknown`: v dochovaném DOM je doloženo
`ano` → paid. Negativní varianta zatím doložena není; `ne`, prázdná buňka
i neznámý popisek zůstávají unknown, dokud nebude mapa čtecím důkazem rozšířena.
Samotná přítomnost sloupce není boolean. Neověřená ikona → null/unknown.
Tvrzení webu „platná pouze pro zaplacené“ se nepřevádí na účast/platbu KIS.
Nesbírat částku, měnu, VS, účet ani úhrady z navazujícího platebního toku.

## Společná obálka, úplnost a klíče

Obálka v1 rozšiřuje příklad hlavního plánu. Implementace musí použít právě
tento kontrakt; příklad v plánu není druhé paralelní schéma.

| Pole | Typ / pravidlo |
| --- | --- |
| `schema_version` | integer přesně 1 |
| `run_id` | UUID vydané KIS |
| `connection_id` | Neprázdný opaque string max 80, vydaný KIS |
| `lease_generation` | integer >=1, souhlasí s aktivní lease |
| `collection` | Jedna ze čtyř kolekcí výše |
| `event_id` | string max80 pouze pro registrations, jinak null; souhlasí s jobem |
| `scope_id` | string max80, skutečně ověřený oddíl/tým; server porovná s connection |
| `adapter_version` | string max80, verze vydání |
| `observed_at` | RFC3339 UTC se Z; začátek pozorování stránky |
| `part_no` | integer >=1, pořadí transportního dílu |
| `page` | `index` >=1, `reported_total` integer>=0 nebo null, `row_offset` >=0, `row_count` >=0; offset v dané stránce, count odpovídá dílu |
| `rows` | Pole příslušného typu výše, max250 položek; celý HTTP payload max1MiB |

Zvolená velikost stránky může být větší než díl; `row_offset` zabrání záměně
stránky a transportního dílu. Kompletní run musí pokrýt všechny souvislé
offsety/stránky právě jednou. Závěrečný manifest uvádí počet stránek/dílů,
řádků a hash každého dílu; součet musí souhlasit s reported_total, je-li
dostupný. Bez total je nutné doložit konec stránkování a žádný aktivní filtr.
Pouhá první stránka nebo uložená HTML část DataTables není kompletní kolekce.

Publikace je atomická po kontrole manifestu, počtů a scope; neexistující
přihláška je samostatný výsledek jobu `not_created`, nikoli upload prázdné
existující přihlášky. Nula/pokles>20 % potřebují posouzení podle plánu P15.
Po timeoutu/expiraci zůstává poslední úplný snímek, se zřetelným stářím.

Server vytvoří run-local ID každého řádku z `(run_id, part_no, row_index)`;
to není stabilní identita. Licence: stabilní `(provider, scope, license_id)`.
Člen bez member_id: složený `(scope, normalized_uci, role_label)`; přihláška
bez registration_id: `(scope, event_id, normalized_uci, category_label)`.
Složení serializovat jako pole JSON, ne spojováním neescapovaných textů.
Chybějící UCI/role/kategorie nebo duplicitní složený klíč → pouze run-local
záznam a review, žádné sloučení/ztráta řádku. Změna role/kategorie vytváří
novou externí kombinaci; není důkaz nového člověka ani storna.

## Serverová normalizace a párování

- `uci_id`: odstranit pouze ASCII space a NBSP z `uci_id_raw`, pak přesně
  `[0-9]{11}`. Null/prázdné → missing; jiný formát → invalid. Tabulátory,
  nové řádky, interpunkci a písmena neopravovat. Úvodní nuly zachovat.
- `birth_date`: podporované formáty `D.M.YYYY`, `D. M. YYYY`, `YYYY-MM-DD`,
  s kontrolou skutečného kalendářního data. Jinak null + `invalid_birth_date`;
  nepřevádět časovým pásmem a neodhadovat den/měsíc.
- UCI se porovnává výhradně s aktuálním `sportovci.uciid`; jméno a datum
  narození slouží jen k odhalení rozporu. Chybějící hodnota není rozpor.
- Jedna unikátní KIS osoba → matched; žádná → unmatched; více →
  duplicate_kis_uci. Rozporné jméno/datum/existující vazba → identity_conflict.
  Konkrétní pravidla tolerance jmen a změny příjmení musí S1 pokrýt fixture.
- Výpočty UCI/stavů/klíčů vytváří server; worker neposílá `sportovec_id`,
  rozhodnutí o shodě ani příkaz k úpravě karty. Při změně UCI vazbu přehodnotit.
- Uložit jen nové integrační snímky/vazby/review; neprovádět canonical import.

## Vyloučená pole a otevřená ověření

V1 nepřenáší rodné číslo, pohlaví, adresy, e-maily, telefony, nouzový kontakt,
fotografie, smlouvy, souhlasy, SCM, UCI role z editoru, doklady, výsledky,
digitální karty/jejich URL, cookies, storageState, ViewState, CSRF tokeny,
hesla, celé síťové odpovědi ani libovolné poznámky. Názvy/ID číselníků ČSC
se neodvozují z editačních formulářů; CategoryId zůstává mimo v1.

Před pilotem doložit: identifikátor týmu a ověření scope Sportsoftu, všechny
stránky obou adresářů, bezpečné ID vybraných závodů, platební popisky a stabilitu
složených klíčů. Nedostupný údaj není doplněn z jiného zdroje odhadem.
Vzorové řádky a negativní případy jsou pouze syntetické v
[fixtures](verification/csc-sportsoft-s0-20261107/fixtures.json).
[HTML podklad](verification/csc-sportsoft-s0-20261107/source-fixtures.html) je
záměrně malý a statický; nenahrazuje přejímku skutečných selektorů.

## Čtecí akce a zbývající ověření selektorů

Povolená implementační cesta je otevření pevné stránky vlastního oddílu/týmu,
čtení odpovídající tabulky a její běžné stránkování. ČSC status/jméno/hostování
se čtou z konkrétní buňky; Sportsoft členové/přihlášky podle kontrolovaných
záhlaví. Katalogová akce „Prohlédnout / změnit přihlášku“ smí vést jen na
již existující přihlášku povoleného eventu, se znovu ověřeným scope.
Nesledovat doklady o úhradě a neaktivovat novou přihlášku, licence, editor,
žádost, export ani platbu. Žádné změny checkboxů nebo formulářových hodnot.
Konkrétní stránkovací/postback selektory musí S2 potvrdit na přihlášeném DOM;
S0 nevydává dynamické reference `f4e...` ze starého snímku za stabilní selektory.
