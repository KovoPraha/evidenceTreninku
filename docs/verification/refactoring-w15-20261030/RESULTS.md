# W15 — závěrečná technická přejímka

ParentJourneyAcceptanceTest: 4 testy / 159 assertions PASS. První případ
nově pokračuje bez výměny DB, rodiny, osoby nebo platby do uzavřené
90minutové docházky, ledgeru 10 000 haléřů a skutečného exportního ZIP.
Druhé převzetí stejné platby vrací stejný pohyb, export obsahuje jediný
příjem. Cizí rodina nevidí osobu, odebraná rodinná vazba je účinná okamžitě,
odebraná finanční role zakáže download existujícího archivu. Hash archivu
zůstane shodný. Čtyři frontové zprávy před tím prošly jen lokálním callbackem.

Cílená sada rodina/program/import/fronty/sklad/docházka/export: 71 testů /
1511 assertions PASS. Nová přejímka je součástí standardního PHPUnit.
Celý místní PHPUnit po propojení: 1860 testů / 37775 assertions, tři
Windows POSIX skipy, žádná chyba. W14 přidává další tři samostatné kontrakty.
Všechny soubory vznikají v náhodném soukromém adresáři a jsou odstraněny
ve finally; žádný skutečný účet, objednávka, platba ani zpráva nevzniká.

Současně CI opakuje skutečné MariaDB souběhy W7–W11, aktuální rozpočty čtení,
úplný XLSX, parser a W12 obnovu 290 tabulek/triggerů/FK/soukromého archivu.
Samotný SQLite případ neprokazuje zamykání MariaDB. Windows má tři POSIX
skipped testy; závěrečná Linux brána je musí provést bez skipů.

Finální přehled oblastí, důvodů nepřijatých kandidátů a hranic přejímky:
[technické uzavření](../../REFACTORING_CLOSURE_2026-10-30.md).
Technické dokončení a lidská/účetní přejímka jsou různé stavy.

Konečný spojený Linux CI: 1864 testů / 37843 assertions, bez skipů,
obě MariaDB a všechny objemové profily PASS. Implementace je sloučena a nasazena.
[Původní pokus](../refactoring-final-release-20261030/RESULTS.md) zastavil DB disk full;
[následná obnova kapacity a úspěšný release](../db-temp-recovery-20261030/RESULTS.md)
prokázaly novou zálohu, skutečnou obnovu, HTTP 26/26, ledger 142 a zachování VS/flags.
