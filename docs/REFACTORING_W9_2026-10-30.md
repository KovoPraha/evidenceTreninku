# W9 — obchod a sklad

Plán před implementací 30. 10. 2026, základ `46ffede1c368d33c25c524f967be02f2040cb1a9`.
W8.5 doplňuje sportovní CI samostatně; W8.4 čeká na produkční vydání.

## W9.1 — čtení skladu

`eshop_inventory_admin.php` načte všechny goods varianty, filtruje pomocí
`mb_stripos` a v každém řádku volá `shopInventoryBalance` (kontrola tabulky
a SELECT). Změřit celý výpis 100/1000/10000 variant, filtr i prázdný stav,
rozdíl catalog_manager/finance_manager a zachování vybrané varianty.

Připravit oddělenou čtecí projekci s omezenými dávkami zůstatků. Zachovat
filtr přesně včetně Unicode/diakritiky a literálních %, _, !; SQL kolace
nesmí nepozorovaně rozšířit výsledky. Případné streamování nesmí ztratit
výběr a pořadí. Porovnat celé HTML/view model a paměť; neakceptovat
neomezenou druhou kopii všech zůstatků. Žádná cache pro skladové writery.

## W9.2 — košík a checkout

Změřit čtení košíku 1/10/50 položek a průběh checkoutu odděleně. Izolovat
jen prokazatelně čistý výpočet/projekci, pokud přinese menší složitost nebo
opakování. Zachovat serverové ceny, minor units, členské podmínky, kupony,
kapacitu, fingerprint, CSRF a opakované autorizace uvnitř transakce.
Košík může při prvním přístupu vzniknout: neoznačovat tento krok za čisté
čtení. Nepřesouvat zamčené kontroly jen kvůli redukci SQL.

## Ověření a návaznost

Zmrazený baseline, RED proti konkrétnímu rozpočtu, behaviorální kontrakty,
celý katalog migrací a nativní MariaDB 10.3/11.4. Ověřit idempotenci,
souběh objednávky/výdeje/vratky, oprávnění cen a rollback. U dosavadních
race skriptů doložit, zda skutečně běží v CI, nebo jsou lokální.

Případnou UI změnu nejprve podložit měřením a konkrétním návrhem; nyní se
nemění obchodní flow ani poskytovatelé plateb. Žádné skutečné objednávky,
platby či odesílání. Po zelené regresi a přesném main sloučit/nasadit
autonomně; pokračovat W10–W15. Zbývající cross-offer kapacitu K0.2
posoudit při košíku a zapsat přijetí/odmítnutí s důkazy.
