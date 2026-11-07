# P11 — omezené načítání katalogu, 7. 11. 2026

ID P11-CATALOG; vlastník zadání Marek, realizace Codex. Stav ROZPRACOVÁNO.
Oprávnění: sloučit #358 do main a pokračovat katalogem; bez nasazení, aktivace
nebo změn živých dat. Původní WIP zůstává ve své pracovní kopii.

## Cíl a důvod

[Analýza](verification/performance-analysis-20261107/RESULTS.md) prokázala tvorbu
celého katalogu před výběrem 12 karet. Zachovat přesný viditelný výsledek,
menu a počty, omezit načtení obrázků, JSON a prezentaci atributů na vybranou stránku.
Nezbytné lehké údaje pro prodejnost a řazení všech cen zůstávají globální.

Varianta SQL LIMIT před prodejností/cenami mění výsledek; dlouhodobá cache
oprávnění přidává invalidaci. Zvolena projekce pro jednu operaci a následná
dávka plných karet. Vyhledávání ponechá PHP Unicode/podřetězcovou sémantiku.
Detaily a checkout nadále používají své kanonické čtení. Transakce použije
původní úplnou cestu. Žádná migrace ani sdílená cache osobních dat.

## Postup a akceptace

1. Dokončit CI a merge #358, zaznamenat skutečný merge SHA odděleně od deploye.
2. Zmrazit původní řadič katalogu jako testovací referenci po #358.
3. Lehké kandidáty filtrovat a řadit přesnými dosavadními pravidly; plné karty
   sestavit dávkově pouze pro stránku. Zachovat i původní implicitní sort_order=0.
4. Striktní parita celých vybraných karet, typů, filtrů, stránkování a menu:
   anonym/člen, varianty, ceny, kategorie, Unicode hledání, sklad, import,
   programy a tarify, změny mezi voláními a transakční fallback.
5. Stejná syntetická data před/po, zahřátí a střídání pořadí měření; čas/p95,
   SQL, vrácené řádky, plné varianty/obrázky a přírůstek špičky paměti.
   SQLite místně; MariaDB 10.3/11.4 v CI. Žádné produkční dumpy ani připojení.
6. Přiměřené regresní testy, kvalita, dokumentační kontrola; PR a ověření CI.
   Katalog předat k review; nové nasazení ani merge katalogového PR nebyly zadány.

## Pracovní stav a návaznosti

Větev `codex/catalog-read-performance-20261107`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-catalog-performance-20261107`.
Základ před převzetím #358: main `9460ee76`; #358 sloučený `37d0ad68`, všech 11 kontrol PASS, bez deploye.
[Plán cen](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md) je v main.
Současná produkce a souběžné provozní práce jsou vedené pouze v CURRENT_STATE.

Výsledky budou v [protokolu](verification/catalog-read-performance-20261107/RESULTS.md),
převzetí v [session handoffu](SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md).
Další krok: ověřit striktní paritu a změřit omezenou projekci.

## Průběžný výsledek implementace

- Plán zapsaný před kódem `b44e7c53`; implementace `c76b60b0`, převzetí cen
  a skutečný merge #358 evidovaný v `7296909a`. Výchozí zmrazený řadič je
  `tests/Support/CatalogPageBaseline.php` z přesného main po merge `37d0ad68` (jen normalizace formátu).
- Nový reader načte lehké kandidáty a prodejnost, dávkové ceny pro řazení,
  přesné kategorie/menu a až poté úplné karty pro vybraných nejvýše 12 produktů.
  Hledání čte souhrny po 100 produktech; JSON a obrázky jen pro stránku.
- Cíleně 21 testů / 1357 assertions PASS (SQLite). Transakční porovnání ověřuje
  SQL, parametry a počty řádků; náhodné časy SQL nejsou součástí sémantické parity.
- Objemové párové měření a kvalita běží; MariaDB 10.3/11.4 zatím NOT RUN pro
  nový katalog. Plné HTTP/browser a produkce NOT RUN. Nové API bez výběru ID
  zachovává původní chování detailů a ostatních uživatelů úplného katalogu.
- Zůstává globální práce nad lehkými kandidáty a programovou kapacitou;
  změna neřeší bootstrap/navigaci ani nepředstavuje SQL stránkování všech dat.
