# W7.5 — omezené načítání adresáře registráře

Základ `1e273c205ac4f25c686780e46bac2a38da4eef86`, 30. 10. 2026.
Původní stránka načetla všechny filtrované osoby s agregáty a teprve v PHP
vybrala stránku. Nová čtecí projekce počítá statistiky a načítá pouze stránku
pomocí LIMIT/OFFSET. Explicitní volba `pp=0` nadále vrací všechny osoby.
Autorizace a POST writery zůstávají ve stránce; osobní data se necachují.

## Kontrakty

[RED](red-budget.txt): původní čtení načetlo 103 řádků pro stránku 50 osob
z adresáře 100 osob. Po implementaci je limit velikost stránky + 3 statistické
řádky. [Profil](directory-profile.json) porovnává 50 scénářů se zmrazeným
původním readerem: prázdná data, 100/1000/10000 osob, všechny sorty a směry,
filtry členství/KIS, NULL, wildcard i bezpečné parametrizované hledání,
smíšené kolace a diakritika, poslední/neplatná stránka a limity 50/100/500/0.

Záměrné zpřesnění: za původní řazení se přidává ID ve stejném směru.
Původní pořadí shodných hodnot nebylo definované; srovnávací reader pro tyto
shody používá stejný explicitní ID klíč. Ostatní data, statistiky a filtry
se porovnávají přesnou shodou PHP polí včetně typů a pořadí klíčů.
Původní hledání opakovalo pojmenovaný placeholder a vyžadovalo emulované PDO;
nové poziční parametry prošly nativními prepared statements.

| Osob v adresáři | Načtené řádky při stránce 50, před → po | SQL před → po |
| --- | --- | --- |
| 0 | 3 → 3 | 4 → 4 |
| 100 | 103 → 53 | 4 → 4 |
| 1000 | 1003 → 53 | 4 → 4 |
| 10000 | 10003 → 53 | 4 → 4 |

Při textovém hledání přibývá proti původnímu výpočtu nad celým PHP polem
jeden agregační dotaz (3 → 4 SQL), ale načtené osoby jsou omezené stránkou.
Statistika trénujících zůstává bez textového hledání globální, s hledáním
odpovídá filtrovaným osobám. Nejde o nový transakční snapshot celé stránky.

## Paměť a čas

Celý migrační katalog nad minimálním legacy základem; index/FK tréninků
a index skupin odpovídají `database/local-demo-schema.sql`. První časový běh
s neúplným legacy indexem byl zastaven a vyřazen. Finální profil obsahuje
EXPLAIN, FK zůstaly zapnuté. Jde o syntetický lokální profil, nikoli produkci.

Plain PDO, jeden zahřívací průchod a pět střídavých vzorků, instrumentace mimo čas.
Stránka 100 osob, mediány posledního lokálního běhu:

| Osob | Čas před → po | Přírůstek peak paměti před → po |
| --- | --- | --- |
| 0 | 0,46 → 0,92 ms | 51 632 → 86 880 B |
| 100 | 2,26 → 2,77 ms | 420 416 → 436 408 B |
| 1000 | 16,90 → 4,87 ms | 3 660 544 → 436 456 B |
| 10000 | 207,23 → 24,26 ms | 36 162 816 → 436 456 B |

Malé seznamy mají malý pevný režijní přírůstek; pro 10 000 osob se materializace
zmenšila o 98,8 %. Deterministická brána pro 1000+ osob: nejvýše 2 MiB
a nejvýše 50 % původního přírůstku paměti. Čas není tvrdá CI brána.
Explicitní zobrazení všech osob zůstává úměrné celému seznamu.

## Stránka, kvalita a vydání

[AST a doslovné HTML](formatting-ast.txt) po skládání konstantních echo řetězců
shodné před/po nutném formátování; porovnání se týká již upravené čtecí části.
[Skript](formatting-ast-check.txt) lze spustit přes PHP proti
[snapshotu](page-before-format.txt) a stránce z tohoto vydání.
[Editor, UI a přístupnost](ui-contracts.txt): 30 testů / 4691 assertions.
Kontrola SRI rozpoznává doslovné konkatenace i víceřádkové tagy bez vykonání
kódu; negativní testy dál odmítají chybějící integrity nebo crossorigin.

[Plná lokální sada](phpunit-windows.txt): 1825 testů / 36168 assertions,
3 POSIX skips. [PHPStan](phpstan.txt) bez chyb; odstraněno 27 legacy výjimek
(PDO guard a přesný návratový tvar), žádné nové výjimky. Styl a dokumentace PASS.
CI zahrnuje profil na MariaDB 10.3/11.4 a všechny dosavadní kontrakty.
Nasazení a výsledky CI budou doplněny po ověření přesného commitu.

R03, cílené `accountPersonCanManage`, detail R04 a další vlny zůstávají
samostatné navazující práce; W7 jako celek zde není uzavřena. UAT a skutečné
provozní převody nejsou tímto syntetickým měřením schváleny.
