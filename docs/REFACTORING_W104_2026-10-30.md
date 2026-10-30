# W10.4 — metadata při přehledu čekajících účetních zdrojů

Nález W10.3 na celém migrovaném schématu: výchozích 200 čekajících zdrojů
provádí 2204 SQL, přestože velikost zdrojů vzrostla z 1000 na 10 000.
Medián lokálně 472–491 ms, data/flags a omezení 200 jsou stabilní.
Ledger 100 000 pohybů naopak používá 2 SQL / 2 výsledné řádky a 46 ms;
jeho SQL a indexy zůstanou v tomto řezu zachovány. Osobní historie 5000
záznamů má 12 SQL a 9,6 MB, odpovídá celému požadovanému historickému výpisu.

Prověřit opakované kontroly tabulek aliasů a přídělů. Přidat pouze explicitní
kontext životnosti jednoho čtecího přehledu, navázaný na přesné PDO.
`accountingLedgerSource` a čtení přídělů jej mohou volitelně přijmout;
všechny finanční write cesty zůstanou bez kontextu a s čerstvými kontrolami.
Nekešovat autoritu, částky, aliasy, konflikty, data ani výsledky finančních dotazů.

Před změnou uložit původní orchestraci přehledu pro porovnání. Regrese:
shodné řádky/typy/pořadí/flags/konflikty/limity, nová tabulka a změněný zdroj
viditelné v další operaci, jiné PDO oddělené, výjimka nesmí přenést stav.
Měřit skutečné SQL, řádky, čas a paměť na obou CI MariaDB. Žádné přebírání
zdrojů, skutečné platby, nové migrace nebo aktivace providerů.

Gate jako W10.3, rollback návratem kódu. Po dokončení posoudit W10 jako celek
a pokračovat W11–W15; metodika a ostré účetní převzetí zůstávají samostatné.
