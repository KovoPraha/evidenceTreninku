# P11 — omezené načítání katalogu, 7. 11. 2026

ID P11-CATALOG; vlastník zadání Marek, realizace Codex. Stav HOTOVO: implementace, parita a měření; #364 K REVIEW.
[PR #364](https://github.com/KovoPraha/evidenceTreninku/pull/364),
[výsledky a metoda](verification/catalog-read-performance-20261107/RESULTS.md),
[session handoff](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md),
[rozhodnutí D2026-11-07-07](DECISION_LOG.md#d2026-11-07-07).

## Zadání, oprávnění a pracovní kopie

Vlastník zadal sloučit #358 do main a pokračovat katalogem podle
[analýzy](verification/performance-analysis-20261107/RESULTS.md), zachovat WIP,
ověřit shodu, změřit před/po a průběžně aktualizovat plán i handoff v repu.
Bez nasazení, aktivace nebo změn živých dat. Katalogový PR předat k review.

#358 sloučený `37d0ad687211aa912a90e8ad105842847956890d`, head `02ad8016`,
11 kontrol PASS; [cenový plán](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md).
Větev `codex/catalog-read-performance-20261107`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-catalog-performance-20261107`.
Původní checkout a WIP zůstávají nedotčené; otisk je v protokolu.
Převzaté main `75374564` zahrnuje produkční předání #362 bez opakování operací.
#363 přidává samostatné HTTP/browser měření
na `feb8fe8c`; zachované výsledky nejsou přeměření nového katalogu.

## Cíl, varianty a zvolený postup

Dříve vznikly obrázky, popisy, JSON a prezentace atributů celého katalogu
před výběrem 12 karet. Nový reader zachová globální lehké údaje pro prodejnost,
řazení skutečnou členskou cenou, kategorie a jejich počty; plné karty sestaví
jen pro vybranou stránku. Hledání čte popisy po 100 produktech v PHP se stejnou
Unicode/podřetězcovou sémantikou. Kategorie se čtou po 500 ID.

SQL LIMIT před prodejností/cenami by měnil výsledek, trvalá cache oprávnění
potřebuje invalidaci. Zvolena projekce pro jednu operaci a následná dávka
karet. První nativní měření prokázalo režii malého katalogu (~2 ms), proto
sonda nejvýše 65 ID aktivních produktů volí do 64 produktů původní cestu.
Sonda nevybírá obsah ani počty. Tento kompromis se měří v obou větvích.
Transakce vždy použije původní úplnou cestu bez sondy. Ostatní volání API
bez seznamu ID a checkout zůstávají kanonické. Žádná migrace ani sdílená cache.

## Etapy a ověřitelné přijetí

| Etapa | Stav a důkaz |
| --- | --- |
| Plán před implementací | `b44e7c53`; původní pracovní ID rozhodnutí 05 a události 13 po převzetí #363 přesunuta na 07/18; přijatá main ID zachovaná |
| Merge cen bez deploye | HOTOVO #358 `37d0ad68`; finální head 11 kontrol PASS |
| Reference a implementace | HOTOVO; zmrazený řadič z přesného `37d0ad68`, pouze obal/normalizace formátu; reader + omezené API, implicitní sort_order=0 zachovaný |
| Striktní shoda výsledků | PASS 31 testů / 2080 assertions; kompletní vybrané karty/typy, ceny, pořadí, menu, počty a filtry |
| Objemové párové měření | PASS nad runtime `623dcaa5`, integrovaným v `0252ba58`; 480 párů na MariaDB 10.3/11.4, všech 9 zdrojových otisků shodných |
| Regrese a předání | PASS cíleně 31/2080 a Linux 2350/45220, kvalita/dokumentace PASS; #364 k review, stav kompletních jobů u PR |
| Deploy / provozní přejímka katalogu | NOT RUN, není součástí autorizované práce |

Shoda zahrnuje anonym/člen/neověřený účet/odvolání, více variant, veřejné i
členské ceny, kategorické potomky, všechny druhy řazení, doslovné `%`/`_`,
Unicode hledání včetně přechodu názvu do popisu, prázdný výsledek a krajní
stránku. Také sklad 0/NULL, importy 157–159, tarify s oběma přepínači,
programovou prodejnost/podmínky/kapacitu, změny mezi čteními a transakční SQL.

Měření: 5 zahřátí a 30 střídavých párů na stejných datech a PDO, samostatná
instrumentace SQL/řádků/EXPLAIN. Ověřit medián/p95, PHP špičku a těžké řádky;
nevydávat čísla izolované operace za celé HTTP nebo produkční latenci.
Syntetická schémata, vlastní ověřený localhost kontejner; bez configu/dumpů.
Nad 64 produkty nejvýše 12 plných produktů; více variant na produkt je přípustných.

## Další krok a hranice

Reporty, otisky a vyhodnocení včetně malé režie jsou v protokolu.
5000 produktů: MariaDB 10.3 202,18 → 87,13 ms, 11.4 340,46 → 198,57 ms;
PHP špička 71,59 → 16,82 MiB. Počet malých SQL roste; obsah a cena zůstávají shodné.
Další krok: review #364; při pozdější integraci ověřit aktuální main a WIP.
Nasazení a HTTP/browser nové verze čekají na samostatné zadání.
Globální lehký průchod a programový kontext zůstávají O(katalog).
Navazující HTTP/browser této nové verze a další priority #363 zůstávají oddělené.

Před předáním převzatý také main `3b37e053` / #361 (mobilní karty přístupů).
Všech 9 měřených katalogových zdrojů je stále shodných s `0252ba58`.
Jeho samostatné provozní zadání se v tomto úkolu nevykonává. Poslední CI
převzetí sleduje PR; katalogová parita a měření zůstávají doložené.

## Pokračování a nové oprávnění vlastníka

Vlastník nyní výslovně zadal vhodné změny nasadit, určitě commitnout a pokračovat
implementací dalších optimalizací. Původní zákaz deploye je historický; pro
ověřený katalog je nyní schválen merge a standardní chráněný deploy bez importu.
Nasazení má smysl podle doložené parity a přínosu. Před ním převzít main #360,
dokončit celý CI včetně zrušené MariaDB 11.4, zachytit anonymní veřejný katalog
před/po a ověřit shodné karty/ceny/menu. Neměnit produkční data ani přepínače.
Aktuální produkční workflow už nasadilo `3b37e053` (42365946802) v jiném úkolu;
neopakovat jeho aktivace ani vizuální role UAT. Nyní nasadit pouze nový katalog.
Další etapa: P11-R06-LESSONS, dávkový výběr nabízených lekcí podle #363.
