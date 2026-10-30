# W10.4 — metadata účetního čtení

Základ W10.3 `16294fd69e3ce8d86da1bbbc005755a88a73f492` (PR #248).
Čistě syntetické údaje, žádné převzetí zdroje, platba nebo aktivace provideru.

## Změna a důkaz

[RED](metadata-red.txt): 20 čekajících zdrojů mělo 102 kontrol tabulek,
test vyžadoval nejvýše sedm. Čtecí projekce nyní má vlastní explicitní
`AccountingLedgerReadContext`, navázaný na přesný PDO. Uchovává jen bool
existence tabulky; neukládá řádky, autoritu, částky, aliasy nebo konflikty.
Kontext není statický ani sdílený mezi operacemi.

Veřejné `accountingLedgerSource` a `accountingLedgerSourceAllocations`
používané finančními zápisy nadále volají čerstvé čtení bez kontextu.
Zachovány původní signatury. Tři čtecí funkce jsou oddělené od write části;
nutné formátování zbytku ledgeru má shodný AST a literály SQL.

[Profil celého migrovaného schématu](financial-read-profile.json), FK zapnuté,
vlastní náhodná localhost DB uklizená v `finally`. Počty SQL/řádků zvlášť,
latence běžné PDO, 2 warmups / 10 vzorků. Porovnává celé výsledky s původními
třemi funkcemi zachovanými v `AccountingLedgerReadBaseline.php`.

| Zdrojových řádků / limit přehledu | SQL před → po | Metadata před → po | Medián ms před → po |
| --- | ---: | ---: | ---: |
| 1000 / 200 | 2204 → 1207 | 1002 → 5 | 403,39 → 317,93 |
| 10 000 / 200 | 2204 → 1207 | 1002 → 5 | 420,79 → 274,71 |

Stejných 800 načtených řádků a 74 969 B výsledku; dodatečná paměť
657 256 → 641 272 B. Zbývající finanční dotazy zůstávají čerstvé.
Nejde o měření maximálního limitu 10 000 vrácených zdrojů ani produkční SLA.

## Ověření

[Cílené testy](targeted.txt): 51 / 1201 PASS. Plná Windows sada
[PHPUnit](phpunit.txt): 1853 testů / 37670 assertions, tři POSIX skipy;
[PHPStan](phpstan.txt) PASS. Linux bez skipů
a MariaDB 10.3/11.4 ověří finální CI před přijetím.

Nové kontrakty pokrývají celé výsledky a pořadí, metadata rozpočet, změnu částky
v další operaci, novou tabulku/konflikt, návrat po chybě a odmítnutí jiného PDO.
Veřejný source API vidí novou konfliktní tabulku i vedle existujícího čtecího
kontextu: dokládá čerstvé kontroly pro write cestu. Současné účetní testy
dále ověřují aliasy, odvozené platby, skutečné vratky a finanční limity.

W10 tím uzavírá měřené ledger/historii a finanční projekce/XLSX v dohodnutém
rozsahu. Neznamená neomezeně velké PDF: Dompdf nadále renderuje celý dokument;
objemy kompletního scénáře a obnovu ověří W12/W15. Lidská metodika a ostré
účetní převzetí zůstávají samostatné. Následuje inventura a kontrakty W11.
