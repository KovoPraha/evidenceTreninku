# W6.3 — paměť kapacitního výpočtu

Zahájeno 29. 10. 2026 z `8a44d82cc522f89983ea73e66b9d5aa6f5319262`,
větev `codex/refactoring-w63-capacity-20261029`, izolované worktree mimo htdocs.
Platí autonomní oprávnění vlastníka včetně merge a nasazení po ověření.

## Konkrétní řez a rozhodovací brána

Nejprve změřit kapacitní čtení na nové syntetické localhost databázi se všemi
migracemi, skutečnými indexy a zapnutými FK. Zaznamenat EXPLAIN, SQL/parametry,
vrácené řádky, paměť, časy a výsledný stav pro prázdný, malý, hustý a více-týmový
scénář. Oddělit profilování SQL od neinstrumentovaného měření času/paměti.

Kandidát: odstranit druhou materializaci rezervovaných položek v PHP `fetchAll`
a předávat čistému výpočtu jednorázový iterátor. Výpočet stále uchovává mapu
identit pro max(quantity); nejde o konstantní paměť celé operace ani změnu
bufferování PDO. Implementovat jen při doloženém přínosu na velkém scénáři.
SQL, parametry, pořadí zámků, deduplikace, expirace a návratový kontrakt zůstanou.
Žádná migrace, cache ani změna obchodních pravidel. Dávkování kapacit dalších
nabídek vyhodnotit zvlášť; tento řez neslibuje nižší počet SQL.

## Ověření a dokončení

- Nejdřív baseline a test jednorázového iterátoru; po změně cílené i plné testy.
- Přesná shoda stavů, SQL, parametrů a řádků s výchozím kódem; syntetický profil
  v CI MariaDB 10.3/11.4, včetně horní meze přírůstku paměti hustého scénáře.
- Zachovat checkout souběh, PHPStan bez nové baseline, styl, anonymní sweep,
  JS a dokumentační kontroly. Finální PR i přesný main musí projít.
- Čtecí produkční audit a HTTP smoke před/po chráněném deployi, záloha a
  migrační check. `uat_schvaleno=false`, `importovat_krouzky=false`.
- Aktualizovat plán a aktuální stav s odkazy na důkazy. Návrat je předchozí
  kód se stejným schématem, bez obnovy DB přes nové transakce.

Měření používá syntetickou distribuci dat, není produkční zátěžovou zkouškou.
Další oblasti K0.2 a W7–W15 zůstávají podle komplexního plánu otevřené.

Implementace a měření: [průběžný protokol](verification/refactoring-w63-20261029/RESULTS.md).
