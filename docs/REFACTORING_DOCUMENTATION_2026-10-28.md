# Dokumentační základ první vlny

Snapshot 28. 10. 2026, main `fa387e5302f54ca901f46233322bc6fb92a10eb9`.
Výsledný návrh: [První vlna refaktoringu](REFACTORING_WAVE1_2026-10-28.md).

## Úplnost a způsob čtení

Načten a inventarizován text všech **222 trackovaných Markdown souborů**
(2 357 465 bajtů), včetně historických plánů, předání, přejímek, auditů,
technických příruček, map datových toků a README fixtures. Celý korpus byl
tematicky prohledán pro výkon, refaktoring, duplicity, cache a provozní hranice.
U každého dokumentu je cesta, velikost, SHA-256 a nadpisy v
[úplném inventáři](verification/refactoring-wave1-20261028/documentation-inventory.json).
Inventář zachycuje stav před vznikem těchto nových dokumentů.

Podrobné čtení se soustředilo na níže uvedené autority, dotčené funkční
kontrakty a historické návrhy úklidu; zjištění byla porovnána se současným kódem
a testy. **Inventarizace ani fulltextové hledání neznamenají ruční revizi
každého řádku všech 222 dokumentů.** U dlouhých historických příruček a přejímek
byly použity relevantní části. Toto předání proto netvrdí, že byly nově
ověřeny všechny historické funkce nebo každý jejich důkaz.

Dále je oddělený [inventář 28 místních netrackovaných dokumentů](verification/refactoring-wave1-20261028/local-documentation-inventory.json)
z původního checkoutu. Jde o pracovní audity, etapy a UAT protokoly; byly
inventarizovány a relevantní audit/plán tematicky prověřeny, nejsou autoritou
nad novějším main. Jejich soubory nebyly přeneseny ani změněny. Cizí závislosti
v node_modules/vendor nejsou projektovou dokumentací.

[14 trackovaných příloh PDF/DOCX/XLSX/CSV](verification/refactoring-wave1-20261028/attachment-inventory.json)
je evidováno podle cest. Jejich obsah ani vizuální podoba nebyly v tomto kole
otevřeny a nejsou použity jako nový důkaz. Aktuální refaktoring vychází
z textových kontraktů, zdrojového kódu a vykonaných testů.

## Autority a dopad na návrh

| Oblast / podklady | Závěr pro první vlnu |
| --- | --- |
| [CLAUDE](../CLAUDE.md), [CURRENT_STATE](CURRENT_STATE.md), [HANDOFF_CURRENT](HANDOFF_CURRENT.md), [rozcestník](README.md) | Jeden modulární monolit; práce z main ve vlastním worktree mimo webroot. Implementace, technické ověření a provozní převzetí jsou různé stavy. |
| [PHP_QUALITY](PHP_QUALITY.md), [F1–F3](DOZOR_F1_F3_2026-10-26.md), [úklid dozoru](DOZOR_UKLID_2026-10-25.md) | PHPStan a styl jsou již zavedeny. Nenavrhovat je znovu; baseline snižovat cíleně, zachovat negativní kontrakty. Introspekci a wiring testy měnit po dílech. |
| [Architektonický návrh](plan-eshop-tymova-evidence/03-cilova-architektura.md), [technický základ](technicka-dokumentace.md) | Historická architektura vysvětluje původ; wallet, SSO a runtime DDL nejsou automatický aktuální plán. Dělení musí respektovat existující doménové služby. |
| [Pracovní pozice](ADMIN-PRACOVNI-POZICE.md), [trenérské rozsahy](COACH_SCOPE_R09.md), [revokace](auth-revocation-rate-limit.md) | Aktivní pozice a aktuální rozsah; nekachovat oprávnění napříč požadavky ani obejít kontrolu uvnitř transakce. |
| [Identita a stavy osoby](PERSON_FOUNDATION_R07_R08.md) | Účet, osoba, vztah, členství a platba se neslučují. Rodičovská vazba končí okamžikem, soupiska má jiné datové hranice. |
| [Publikace katalogu](shop-catalog-publication.md), [checkout](shop-checkout-k4.md), [životní cyklus programu](shop-program-payment-verification.md) | Detail a navigace jsou čtení. Neudělují právo na nákup; cena, příjemce, kapacita a podmínky se znovu ověřují při zápisu. Starší popis se koriguje novější skladovou dokumentací. |
| [Sklady R18–R24](INVENTORY_R18_R24_IMPLEMENTATION.md), [skladové opravy](verification/stock-followup-20261024/RESULTS.md) | Fyzické zboží s neověřeným NULL není automaticky neomezené. Zachovat existující `shopCheckoutVariantIsSaleable` kontrakt včetně dostupnosti skladového schématu. |
| [Peněžní ledger](ACCOUNTING_LEDGER_R13_R16.md), [B1](FINANCE_B1_IMPLEMENTATION.md) | Nezasahovat do částek, historických snapshotů, auditních transakcí, alokací a zamčené evidence. |
| [Sdílené UI](shared-ui-foundation.md), současné HTML a wiring testy | Zachovat veřejnou navigaci, URL, escaping a běžné pracovní cesty. Refaktoring nemá znamenat redesign. |
| [Migrační pravidla](../migrations/README.md), současné CI workflow | Neměnit historické migrace. SQL ověřit na MariaDB 10.3 i 11.4, SQLite nestačí jako produkční důkaz. |
| [Rozhodnutí D01–D20](CLUB_COMPLETION_DECISIONS.md), [poslední vydání](verification/dozor-release-20261026/RESULTS.md) | Výkonová úprava neuzavírá lidskou ani účetní přejímku a nezapíná import nebo integraci. |
| [Datové nálezy 19. 10.](../outputs/data-flow-audit-2026-10-19/NALEZY_A_RIZIKA.md), následné tematické a přejímací dokumenty | Starší nález není automaticky otevřený bug. V první vlně znovu neimplementovat již dokončené opravy rodičovské cesty a outboxu. |

## Zjištěné rozdíly v dokumentaci

1. Původní lokální checkout obsahuje starší dlouhé CURRENT_STATE/HANDOFF.
   Na main jsou již zkráceny a provozní historie přesunuta do CHANGELOG-PROVOZ.
   První vlna nesmí znovu řešit již dokončený dokumentační úklid.
2. `shop-checkout-k4.md` stále popisuje některé původní předpoklady (např. NULL
   zásobu a starou administrativní cestu potvrzení peněz). Novější skladové,
   rolové a účetní kontrakty a současný kód mají přednost. Při W1-2 doplnit
   cílenou poznámku k platnému skladovému pravidlu, ne přepisovat historické důkazy.
3. Poslední doložený produkční commit v CURRENT_STATE je `fc0d3779`; základ
   přípravy `fa387e53` je novější dokumentační commit. Zelené testy nasazeného
   commitu nejsou zelené testy každého pozdějšího commitu. Aktuální CI základ
   má neprovedenou větev MariaDB 11.4 po chybě inicializace kontejneru.
4. Názvy Wiring testů nejsou spolehlivá klasifikace: některé již skutečně
   vykreslují HTML. Nahrazovat jen konkrétní křehké zdrojové aserce, nikoli celé
   soubory podle názvu.
5. Rozcestník odkazoval na chybějící `pravidla-shody-osob.md`. V této přípravě
   je odkaz nahrazen aktuálním `PERSON_FOUNDATION_R07_R08.md`, který popisuje
   kanonickou identitu a kontrakt `person-match-v1`.

Pro pokračování je závazný konkrétní rozsah [W1-1 až W1-3](REFACTORING_WAVE1_2026-10-28.md),
aktuální main, dotčené testy a nové měření; historické počty a tvrzení se nepřebírají bez kontroly.
