# Třetí vlna — karty programů v katalogu

28. 10. 2026, [PR #213](https://github.com/KovoPraha/evidenceTreninku/pull/213).
Výchozí main `3de0bdcd61a1466d618c1938dfcd67de62faec0c`; aplikačně odpovídá
nasazené druhé vlně `331bd974`. [Plán](../../REFACTORING_WAVE3_2026-10-28.md).

## Změna

Katalog po vyhodnocení prodejnosti znovu načítal identitu každého produktu
a nabídky včetně kapacity všech programových variant. `shopPublicCatalogCards`
nově sdílí jeden `ClubProgramReadContext` s filtrem prodejnosti a používá již
načtené kategorie i původní zveřejněný název. Původní název je důležitý u
importovaných kurzů, jejichž zobrazovaný titulek je záměrně přepsaný.

Nejde o dlouhodobou cache. Další čtení vytváří nový kontext a znovu vyhodnotí
kapacitu i podmínky. Ceny účtu se nadále aplikují v katalogové stránce původním
postupem; checkout a zamykající rozhodnutí nebyly změněny. Bez nové migrace.

## Regrese a SQL

Původní postup vyčleněný z katalogové stránky selhal na novém testu pro
50 programů: 711 metadatových dotazů proti limitu 25. Po optimalizaci prošlo
5 nových testů / 27 assertions. Pokrývají shodu celého výstupu, více variant,
sdílenou kapacitu, obrazové URL, importovaný přejmenovaný kurz, výjimky zboží,
kategorie a nové čtení po změně kapacity, souhlasu nebo zveřejnění.
Stávající testy čtecího kontextu: 6/35 PASS včetně ignorování starého kontextu
při zamykání. Upravené wiring kontroly: 5/95 PASS.

[MariaDB 10.3](ci-mariadb-10.3.json) a [MariaDB 11.4](ci-mariadb-11.4.json):
izolované náhodné databáze, 5 zahřátí + 30 měření pro každý profil a velikost.
Všechny dvojice výstupních hashů stejné, včetně kontroly mezi iteracemi.
Počet SQL je totožný na obou verzích:

| Programů | Původní SQL | Nové SQL | Původní metadata | Nová metadata |
| ---: | ---: | ---: | ---: | ---: |
| 1 | 43 | 24 | 25 | 11 |
| 10 | 286 | 96 | 151 | 11 |
| 50 | 1 366 | 416 | 711 | 11 |

Medián pro 50 programů: MariaDB 10.3 245,46 → 39,76 ms,
MariaDB 11.4 436,15 → 111,65 ms. Syntetický CI benchmark, nikoli produkční latence.
Zůstává přibližně 8 dotazů na program pro kanonické čtení kapacity a podmínek;
tato vlna neprohlašuje celý katalog za konstantní počet SQL.
Reprodukce: `php tests/Support/CatalogCardsMariaDbSmoke.php`, pouze localhost,
port přes `EVIDENCE_MARIADB_SMOKE_PORT`; vlastní DB se odstraní ve `finally`.

## Formátování a úplná kontrola

Pravidla repozitáře vyžadují PSR-12 a maximálně 200 znaků na řádek v každém
změněném PHP souboru. Většinu rozsáhlého diffu `booking/eshop.php` tvoří proto
formátování. [Samotná zamýšlená extrakce](catalog-extraction.patch),
[zdroj AST kontroly](formatting-check-source.txt), [výsledek](formatting-check.json):
PHP AST totožný před/po formátování, všechny nebílé znaky HTML rovněž totožné.
Kontrola HTML ignoruje whitespace; skutečný výstup je ověřen HTTP a browserem.

Pět wiring testů nově toleruje whitespace a ověřuje cestu přes nový helper
do společné navigace/prodejného katalogu. Kontroly košíku, stránek, přístupnosti
a kanonické prodejnosti zůstávají zachované. Úplný Linux běh
[40760218746](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40760218746):
1 769 testů / 35 789 assertions PASS, POSIX 3/45, MariaDB 10.3/11.4 PASS.
Následná úprava dvou mezer v testu uzavřela požadavek style; lokálně 11 PHP
souborů / 0 chyb. Finální stav CI je zaznamenán v PR.

## HTTP a browser

[Zdroj HTTP porovnání](http-check-source.py), [šest scénářů](http-checks.json):
katalog, programová kategorie, hledání, řazení cen, druhá stránka a prázdný
výsledek. Shodný viditelný text, odkazy a obrázky. Ignorován pouze číselný
cache parametr `/assets/` po ověření stejných bajtů CSS/JS v obou worktrees;
timestamps souborů se při checkoutu liší. Doménové odkazy se nenormalizují.

Playwright ověřil [katalog 71 produktů](browser-catalog.txt),
[kategorii 20 programů](browser-programs.txt), [odeslání řazení](browser-sort.txt)
a [druhou stránku](browser-page2.txt). Zachovány termíny, kapacita 100,
cena 100 Kč a odkazy na detail; žádná objednávka ani registrace.
[Zkontrolovaný screenshot druhé stránky](browser-page2.png).
Jediná zaznamenaná console chyba byla chybějící localhost `favicon.ico` (404).

HTTP benchmark používá stejnou syntetickou databázi jako druhá vlna,
71 aktivních produktů / 20 programů; porty 18087 před a 18086 po změně,
5 zahřátí + 30 měření se střídáním pořadí. Naměřené hodnoty jsou v
[http-benchmark.json](http-benchmark.json). Měří celý HTTP přenos, ne browser paint.
Medián katalogu 5,003 → 0,770 s, p95 8,160 → 1,294 s.
Sdílená Windows stanice měla další úlohy a část přípravy se překrývala
s MariaDB benchmarkem; SQL počty a shoda výstupů jsou silnější důkaz než lokální čas.
Po ověření byly oba vlastní HTTP servery i izolovaná MariaDB na 33426 zastaveny
a browser zavřen. Browser pracovní soubory jsou mimo webroot v
`C:\Users\Marek\Documents\Codex\evidencePavel-wave3-local-artifacts-20261028`.
Původní checkout s rozpracovaným `kis_rosters_admin.php` nebyl změněn.

## Hranice

Tento protokol dokládá implementaci a testy. Přesné produkční vydání,
čtecí kontrola konfigurace a měření před/po patří do samostatného protokolu.
Integrace, importy, členské ceny ani práva nebyly aktivovány či změněny;
lidské a účetní UAT zůstává samostatně otevřené.

Následné [produkční vydání](../refactoring-wave3-release-20261028/RESULTS.md) dokládá nasazení PR #213 a čtecí kontrolu.
